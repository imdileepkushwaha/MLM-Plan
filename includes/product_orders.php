<?php
/**
 * Member product purchase (Shopping Wallet).
 */

require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/utility.php';

function product_orders_ensure_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    products_ensure_columns($pdo);
    wallet_ensure_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(40) NOT NULL,
            member_id INT NOT NULL,
            wallet_type ENUM('shopping') NOT NULL DEFAULT 'shopping',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_bv DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'paid',
            shipping_name VARCHAR(150) NULL,
            shipping_phone VARCHAR(30) NULL,
            shipping_address TEXT NULL,
            note TEXT NULL,
            ledger_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_po_invoice (invoice_no),
            KEY idx_po_member (member_id),
            KEY idx_po_status (status),
            KEY idx_po_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(150) NOT NULL,
            sku VARCHAR(60) NULL,
            qty INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            unit_bv DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_bv DECIMAL(12,2) NOT NULL DEFAULT 0,
            KEY idx_poi_order (order_id),
            KEY idx_poi_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $done = true;
}

function product_orders_cart_get(): array
{
    if (!isset($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
        $_SESSION['shop_cart'] = [];
    }
    $out = [];
    foreach ($_SESSION['shop_cart'] as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid > 0 && $qty > 0) {
            $out[$pid] = $qty;
        }
    }
    $_SESSION['shop_cart'] = $out;
    return $out;
}

function product_orders_cart_set(array $cart): void
{
    $clean = [];
    foreach ($cart as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid > 0 && $qty > 0) {
            $clean[$pid] = min(999, $qty);
        }
    }
    $_SESSION['shop_cart'] = $clean;
}

function product_orders_cart_clear(): void
{
    $_SESSION['shop_cart'] = [];
}

function product_orders_cart_add(int $productId, int $qty = 1): void
{
    if ($productId < 1 || $qty < 1) {
        return;
    }
    $cart = product_orders_cart_get();
    $cart[$productId] = min(999, ($cart[$productId] ?? 0) + $qty);
    product_orders_cart_set($cart);
}

function product_orders_cart_update(int $productId, int $qty): void
{
    $cart = product_orders_cart_get();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = min(999, $qty);
    }
    product_orders_cart_set($cart);
}

function product_orders_cart_count(): int
{
    return array_sum(product_orders_cart_get());
}

/** Active catalog products for purchase. */
function product_orders_catalog(PDO $pdo, string $q = ''): array
{
    product_orders_ensure_tables($pdo);
    $params = [];
    $where = "p.status = 'active'";
    if ($q !== '') {
        $where .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like];
    }

    $sql = "
        SELECT p.id, p.name, p.sku, p.price, p.bv, p.stock_qty, p.thumbnail, p.description,
               c.name AS category_name, sc.name AS subcategory_name
        FROM products p
        LEFT JOIN product_categories c ON c.id = p.category_id
        LEFT JOIN product_subcategories sc ON sc.id = p.subcategory_id
        LEFT JOIN subcategory_settings ss ON ss.subcategory_id = p.subcategory_id
        WHERE {$where}
          AND (p.subcategory_id IS NULL OR ss.allow_purchase IS NULL OR ss.allow_purchase = 1)
        ORDER BY p.name ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        // subcategory_settings may not exist
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sku, p.price, p.bv, p.stock_qty, p.thumbnail, p.description,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM products p
            LEFT JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_subcategories sc ON sc.id = p.subcategory_id
            WHERE {$where}
            ORDER BY p.name ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    // Optional MRP if column exists
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM products LIKE 'mrp'");
        if ($chk && $chk->fetch() && $rows) {
            $ids = array_map(static fn($r) => (int) $r['id'], $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $m = $pdo->prepare("SELECT id, mrp FROM products WHERE id IN ($in)");
            $m->execute($ids);
            $map = [];
            foreach ($m->fetchAll() as $mr) {
                $map[(int) $mr['id']] = $mr['mrp'];
            }
            foreach ($rows as &$r) {
                $r['mrp'] = $map[(int) $r['id']] ?? null;
            }
            unset($r);
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $rows;
}

/**
 * Resolve cart lines against live stock/prices.
 * @return array{ok:bool,error:?string,lines:array,subtotal:float,total_bv:float}
 */
function product_orders_build_lines(PDO $pdo, array $cart): array
{
    product_orders_ensure_tables($pdo);
    if (!$cart) {
        return ['ok' => false, 'error' => 'Your cart is empty.', 'lines' => [], 'subtotal' => 0.0, 'total_bv' => 0.0];
    }

    $lines = [];
    $subtotal = 0.0;
    $totalBv = 0.0;

    $stmt = $pdo->prepare("
        SELECT id, name, sku, price, bv, stock_qty, status
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    foreach ($cart as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid < 1 || $qty < 1) {
            continue;
        }
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p || ($p['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'One or more products are unavailable.', 'lines' => [], 'subtotal' => 0.0, 'total_bv' => 0.0];
        }
        $stock = (int) ($p['stock_qty'] ?? 0);
        if ($stock < $qty) {
            return [
                'ok' => false,
                'error' => ($p['name'] ?? 'Product') . ' has only ' . $stock . ' in stock.',
                'lines' => [],
                'subtotal' => 0.0,
                'total_bv' => 0.0,
            ];
        }
        $unit = round((float) $p['price'], 2);
        $bv = round((float) ($p['bv'] ?? 0), 2);
        $lineTotal = round($unit * $qty, 2);
        $lineBv = round($bv * $qty, 2);
        $lines[] = [
            'product_id' => $pid,
            'product_name' => (string) $p['name'],
            'sku' => (string) ($p['sku'] ?? ''),
            'qty' => $qty,
            'unit_price' => $unit,
            'unit_bv' => $bv,
            'line_total' => $lineTotal,
            'line_bv' => $lineBv,
        ];
        $subtotal += $lineTotal;
        $totalBv += $lineBv;
    }

    if (!$lines) {
        return ['ok' => false, 'error' => 'Your cart is empty.', 'lines' => [], 'subtotal' => 0.0, 'total_bv' => 0.0];
    }

    return [
        'ok' => true,
        'error' => null,
        'lines' => $lines,
        'subtotal' => round($subtotal, 2),
        'total_bv' => round($totalBv, 2),
    ];
}

function product_orders_next_invoice(PDO $pdo): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT invoice_no FROM product_orders WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $seq = 1;
    if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
}

/**
 * Place order and debit Shopping Wallet.
 * @return array{ok:bool,error:?string,order_id:?int,invoice_no:?string}
 */
function product_orders_checkout(
    PDO $pdo,
    array $member,
    array $cart,
    string $shippingName,
    string $shippingPhone,
    string $shippingAddress,
    string $note = ''
): array {
    product_orders_ensure_tables($pdo);
    $memberId = (int) ($member['id'] ?? 0);
    if ($memberId < 1) {
        return ['ok' => false, 'error' => 'Invalid member.', 'order_id' => null, 'invoice_no' => null];
    }
    if (($member['status'] ?? '') === 'blocked') {
        return ['ok' => false, 'error' => 'Your account is blocked.', 'order_id' => null, 'invoice_no' => null];
    }

    $shippingName = trim($shippingName);
    $shippingPhone = trim($shippingPhone);
    $shippingAddress = trim($shippingAddress);
    if ($shippingName === '') {
        return ['ok' => false, 'error' => 'Shipping name is required.', 'order_id' => null, 'invoice_no' => null];
    }
    if ($shippingAddress === '') {
        return ['ok' => false, 'error' => 'Shipping address is required.', 'order_id' => null, 'invoice_no' => null];
    }

    try {
        $pdo->beginTransaction();

        $built = product_orders_build_lines($pdo, $cart);
        if (!$built['ok']) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $built['error'], 'order_id' => null, 'invoice_no' => null];
        }

        $total = (float) $built['subtotal'];
        $totalBv = (float) $built['total_bv'];
        $bal = wallet_balance($pdo, $memberId, 'shopping');
        if ($bal + 0.00001 < $total) {
            $pdo->rollBack();
            return [
                'ok' => false,
                'error' => 'Insufficient Shopping Wallet balance. Need ' . strip_tags(currency($total)) . ', available ' . strip_tags(currency($bal)) . '.',
                'order_id' => null,
                'invoice_no' => null,
            ];
        }

        $invoiceNo = product_orders_next_invoice($pdo);
        $pdo->prepare("
            INSERT INTO product_orders
                (invoice_no, member_id, wallet_type, subtotal, discount_amount, total_amount, total_bv,
                 status, shipping_name, shipping_phone, shipping_address, note)
            VALUES (?, ?, 'shopping', ?, 0, ?, ?, 'paid', ?, ?, ?, ?)
        ")->execute([
            $invoiceNo,
            $memberId,
            $total,
            $total,
            $totalBv,
            $shippingName,
            $shippingPhone !== '' ? $shippingPhone : null,
            $shippingAddress,
            $note !== '' ? $note : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemIns = $pdo->prepare("
            INSERT INTO product_order_items
                (order_id, product_id, product_name, sku, qty, unit_price, unit_bv, line_total, line_bv)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stockUp = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');

        foreach ($built['lines'] as $line) {
            $itemIns->execute([
                $orderId,
                $line['product_id'],
                $line['product_name'],
                $line['sku'] !== '' ? $line['sku'] : null,
                $line['qty'],
                $line['unit_price'],
                $line['unit_bv'],
                $line['line_total'],
                $line['line_bv'],
            ]);
            $stockUp->execute([$line['qty'], $line['product_id'], $line['qty']]);
            if ($stockUp->rowCount() < 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Stock changed for ' . $line['product_name'] . '. Try again.', 'order_id' => null, 'invoice_no' => null];
            }
        }

        $debit = wallet_debit(
            $pdo,
            $memberId,
            'shopping',
            $total,
            'product_order',
            $orderId,
            'Product order ' . $invoiceNo
        );
        if (!$debit['ok']) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $debit['error'] ?: 'Could not debit Shopping Wallet.', 'order_id' => null, 'invoice_no' => null];
        }

        $pdo->prepare('UPDATE product_orders SET ledger_id = ? WHERE id = ?')
            ->execute([(int) ($debit['ledger_id'] ?? 0) ?: null, $orderId]);

        $pdo->commit();
        product_orders_cart_clear();

        return ['ok' => true, 'error' => null, 'order_id' => $orderId, 'invoice_no' => $invoiceNo];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Checkout failed. Please try again.', 'order_id' => null, 'invoice_no' => null];
    }
}

function product_order_get(PDO $pdo, int $orderId, ?int $memberId = null): ?array
{
    product_orders_ensure_tables($pdo);
    $sql = 'SELECT * FROM product_orders WHERE id = ?';
    $params = [$orderId];
    if ($memberId !== null) {
        $sql .= ' AND member_id = ?';
        $params[] = $memberId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function product_order_items(PDO $pdo, int $orderId): array
{
    product_orders_ensure_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM product_order_items WHERE order_id = ? ORDER BY id ASC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function product_orders_for_member(PDO $pdo, int $memberId, int $limit = 50, int $offset = 0, string $q = ''): array
{
    product_orders_ensure_tables($pdo);
    $where = 'member_id = ?';
    $params = [$memberId];
    if ($q !== '') {
        $where .= ' AND invoice_no LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare("
        SELECT * FROM product_orders
        WHERE {$where}
        ORDER BY id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function product_orders_count_for_member(PDO $pdo, int $memberId, string $q = ''): int
{
    product_orders_ensure_tables($pdo);
    $where = 'member_id = ?';
    $params = [$memberId];
    if ($q !== '') {
        $where .= ' AND invoice_no LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_orders WHERE {$where}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function product_orders_stats(PDO $pdo, int $memberId): array
{
    product_orders_ensure_tables($pdo);
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid_total,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_bv ELSE 0 END), 0) AS paid_bv
        FROM product_orders
        WHERE member_id = ?
    ");
    $stmt->execute([$memberId]);
    $row = $stmt->fetch() ?: [];
    return [
        'order_count' => (int) ($row['order_count'] ?? 0),
        'paid_total' => (float) ($row['paid_total'] ?? 0),
        'paid_bv' => (float) ($row['paid_bv'] ?? 0),
    ];
}

function product_thumb_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '../' . ltrim(str_replace('\\', '/', $path), '/');
}

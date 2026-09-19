<?php
/**
 * Franchisee Master — types, franchisees, purchases, stock.
 */

function franchise_ensure_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sqlFile = dirname(__DIR__) . '/sql/franchise_tables.sql';
    if (!is_file($sqlFile)) {
        return;
    }

    $sql = (string) file_get_contents($sqlFile);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($parts as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            // ignore already-exists / FK timing on partial installs
        }
    }

    // Extra columns for wizard registration (safe on older installs)
    $cols = [
        'sponsor_id' => 'INT NULL AFTER type_id',
        'gender' => "VARCHAR(20) NULL AFTER contact_person",
        'dob' => 'DATE NULL AFTER gender',
        'aadhaar_no' => 'VARCHAR(20) NULL AFTER email',
        'pan_no' => 'VARCHAR(20) NULL AFTER aadhaar_no',
        'aadhaar_file' => 'VARCHAR(255) NULL AFTER pan_no',
        'pan_file' => 'VARCHAR(255) NULL AFTER aadhaar_file',
        'photo_file' => 'VARCHAR(255) NULL AFTER pan_file',
    ];
    foreach ($cols as $col => $def) {
        try {
            $exists = $pdo->query('SHOW COLUMNS FROM franchisees LIKE ' . $pdo->quote($col))->fetch();
            if (!$exists) {
                $pdo->exec("ALTER TABLE franchisees ADD COLUMN `$col` $def");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}

/** Lookup franchisee by code for sponsor field. */
function franchise_find_by_code(PDO $pdo, string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT f.id, f.franchisee_code, f.name, f.status, t.name AS type_name
        FROM franchisees f
        LEFT JOIN franchisee_types t ON t.id = f.type_id
        WHERE f.franchisee_code = ?
        LIMIT 1
    ');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Store franchisee document upload.
 * @return array{ok:bool,path?:?string,error?:string}
 */
function franchise_store_doc(array $file, string $kind, int $franchiseeId = 0): array
{
    if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed for ' . $kind . '.'];
    }
    $max = 2 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $max) {
        return ['ok' => false, 'error' => ucfirst($kind) . ' must be under 2MB.'];
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => ucfirst($kind) . ': use JPG, PNG, WebP or PDF.'];
    }
    $dir = dirname(__DIR__) . '/uploads/franchisee';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create upload folder.'];
    }
    $name = $kind . '_' . ($franchiseeId > 0 ? $franchiseeId . '_' : '') . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save ' . $kind . ' file.'];
    }
    return ['ok' => true, 'path' => 'uploads/franchisee/' . $name];
}

function franchise_doc_url(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    return '../' . ltrim($path, '/');
}

function franchise_next_code(PDO $pdo, string $prefix = 'FR'): string
{
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix) ?: 'FR');
    $stmt = $pdo->query("SELECT franchisee_code FROM franchisees WHERE franchisee_code LIKE " . $pdo->quote($prefix . '%') . " ORDER BY id DESC LIMIT 1");
    $last = $stmt ? (string) $stmt->fetchColumn() : '';
    $n = 1;
    if (preg_match('/(\d+)$/', $last, $m)) {
        $n = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/** @return list<array<string,mixed>> */
function franchise_types(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM franchisee_types';
    if ($activeOnly) {
        $sql .= " WHERE status='active'";
    }
    $sql .= ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

/** @return list<array<string,mixed>> */
function franchise_list(PDO $pdo, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['status'])) {
        $where[] = 'f.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['type_id'])) {
        $where[] = 'f.type_id = ?';
        $params[] = (int) $filters['type_id'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(f.name LIKE ? OR f.franchisee_code LIKE ? OR f.phone LIKE ? OR f.email LIKE ? OR f.city LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        array_push($params, $q, $q, $q, $q, $q);
    }
    $sql = '
        SELECT f.*, t.name AS type_name
        FROM franchisees f
        LEFT JOIN franchisee_types t ON t.id = f.type_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY f.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function franchise_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT f.*, t.name AS type_name,
               s.franchisee_code AS sponsor_code, s.name AS sponsor_name, st.name AS sponsor_type_name
        FROM franchisees f
        LEFT JOIN franchisee_types t ON t.id = f.type_id
        LEFT JOIN franchisees s ON s.id = f.sponsor_id
        LEFT JOIN franchisee_types st ON st.id = s.type_id
        WHERE f.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Save franchisee purchase: debit company stock, credit franchisee stock.
 *
 * @param list<array{product_id:int,qty:int,rate:float}> $items
 * @return array{ok:bool,id?:int,error?:string}
 */
function franchise_save_purchase(PDO $pdo, int $franchiseeId, string $purchaseDate, string $invoiceNo, string $note, array $items, string $role = 'admin', ?int $actorId = null): array
{
    if ($franchiseeId < 1) {
        return ['ok' => false, 'error' => 'Select a franchisee.'];
    }
    if ($purchaseDate === '') {
        return ['ok' => false, 'error' => 'Purchase date is required.'];
    }
    if (!$items) {
        return ['ok' => false, 'error' => 'Add at least one product line.'];
    }

    $fr = franchise_get($pdo, $franchiseeId);
    if (!$fr || ($fr['status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'Franchisee not found or inactive.'];
    }

    $total = 0.0;
    foreach ($items as $it) {
        $total += (float) $it['amount'];
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('
            INSERT INTO franchisee_purchases
                (franchisee_id, invoice_no, purchase_date, total_amount, note, status, created_by_role, created_by_id)
            VALUES (?,?,?,?,?,?,?,?)
        ')->execute([
            $franchiseeId,
            $invoiceNo !== '' ? $invoiceNo : null,
            $purchaseDate,
            round($total, 2),
            $note !== '' ? $note : null,
            'completed',
            in_array($role, ['admin', 'superadmin'], true) ? $role : 'admin',
            $actorId,
        ]);
        $purchaseId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('
            INSERT INTO franchisee_purchase_items (purchase_id, product_id, qty, rate, amount)
            VALUES (?,?,?,?,?)
        ');
        $stockCheck = $pdo->prepare('SELECT id, name, stock_qty FROM products WHERE id = ? FOR UPDATE');
        $stockDebit = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
        $stockCredit = $pdo->prepare('
            INSERT INTO franchisee_stock (franchisee_id, product_id, qty)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
        ');

        foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (int) $it['qty'];
            $rate = (float) $it['rate'];
            $amount = (float) $it['amount'];

            $stockCheck->execute([$pid]);
            $prod = $stockCheck->fetch();
            if (!$prod) {
                throw new RuntimeException('Product #' . $pid . ' not found.');
            }
            if ((int) $prod['stock_qty'] < $qty) {
                throw new RuntimeException('Insufficient company stock for ' . $prod['name'] . ' (have ' . (int) $prod['stock_qty'] . ').');
            }

            $itemStmt->execute([$purchaseId, $pid, $qty, $rate, $amount]);
            $stockDebit->execute([$qty, $pid, $qty]);
            if ($stockDebit->rowCount() < 1) {
                throw new RuntimeException('Could not debit stock for ' . $prod['name'] . '.');
            }
            $stockCredit->execute([$franchiseeId, $pid, $qty]);
        }

        $pdo->commit();
        log_activity('franchise_purchase', "Franchisee purchase #$purchaseId for FR#$franchiseeId total $total");
        return ['ok' => true, 'id' => $purchaseId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** @return list<array<string,mixed>> */
function franchise_purchases(PDO $pdo, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['franchisee_id'])) {
        $where[] = 'p.franchisee_id = ?';
        $params[] = (int) $filters['franchisee_id'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'p.purchase_date >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'p.purchase_date <= ?';
        $params[] = $filters['to'];
    }
    $sql = '
        SELECT p.*, f.name AS franchisee_name, f.franchisee_code
        FROM franchisee_purchases p
        JOIN franchisees f ON f.id = p.franchisee_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.id DESC
        LIMIT 500
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function franchise_purchase_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT p.*, f.name AS franchisee_name, f.franchisee_code
        FROM franchisee_purchases p
        JOIN franchisees f ON f.id = p.franchisee_id
        WHERE p.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function franchise_purchase_items(PDO $pdo, int $purchaseId): array
{
    $stmt = $pdo->prepare('
        SELECT i.*, pr.name AS product_name, pr.sku
        FROM franchisee_purchase_items i
        JOIN products pr ON pr.id = i.product_id
        WHERE i.purchase_id = ?
        ORDER BY i.id
    ');
    $stmt->execute([$purchaseId]);
    return $stmt->fetchAll();
}

/** @return list<array<string,mixed>> */
function franchise_stock_rows(PDO $pdo, ?int $franchiseeId = null): array
{
    $where = 's.qty > 0';
    $params = [];
    if ($franchiseeId && $franchiseeId > 0) {
        $where = 's.franchisee_id = ?';
        $params[] = $franchiseeId;
    }
    $sql = '
        SELECT s.*, f.name AS franchisee_name, f.franchisee_code,
               pr.name AS product_name, pr.sku, pr.price
        FROM franchisee_stock s
        JOIN franchisees f ON f.id = s.franchisee_id
        JOIN products pr ON pr.id = s.product_id
        WHERE ' . $where . '
        ORDER BY f.name, pr.name
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

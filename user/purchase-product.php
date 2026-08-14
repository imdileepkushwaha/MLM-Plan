<?php
$pageTitle = 'Purchase Product';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/product_orders.php';
require_once __DIR__ . '/../includes/wallet.php';

require_user();
feature_guard_user_page('purchase-product');
$user = current_user($pdo);
if (!$user || ($user['status'] ?? '') === 'blocked') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
    header('Location: login.php');
    exit;
}

product_orders_ensure_tables($pdo);
wallet_ensure_schema($pdo);

$uid = (int) $user['id'];
$errors = [];
$q = trim($_GET['q'] ?? $_POST['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $found = null;
        foreach (product_orders_catalog($pdo) as $p) {
            if ((int) $p['id'] === $pid) {
                $found = $p;
                break;
            }
        }
        if (!$found) {
            flash('error', 'Product not found or inactive.');
        } elseif ((int) ($found['stock_qty'] ?? 0) < 1) {
            flash('error', 'Product is out of stock.');
        } else {
            $max = (int) $found['stock_qty'];
            $cart = product_orders_cart_get();
            $newQty = min($max, ($cart[$pid] ?? 0) + $qty);
            product_orders_cart_update($pid, $newQty);
            flash('success', $found['name'] . ' added to cart.');
        }
        header('Location: purchase-product.php' . ($q !== '' ? '?q=' . rawurlencode($q) : ''));
        exit;
    }

    if ($action === 'update_cart') {
        $qtys = $_POST['qty'] ?? [];
        if (!is_array($qtys)) {
            $qtys = [];
        }
        foreach ($qtys as $pid => $qty) {
            product_orders_cart_update((int) $pid, (int) $qty);
        }
        flash('success', 'Cart updated.');
        header('Location: purchase-product.php' . ($q !== '' ? '?q=' . rawurlencode($q) : '') . '#cart');
        exit;
    }

    if ($action === 'clear_cart') {
        product_orders_cart_clear();
        flash('success', 'Cart cleared.');
        header('Location: purchase-product.php');
        exit;
    }

    if ($action === 'checkout') {
        $cart = product_orders_cart_get();
        $shipName = trim((string) ($_POST['shipping_name'] ?? ($user['full_name'] ?? '')));
        $shipPhone = trim((string) ($_POST['shipping_phone'] ?? ($user['phone'] ?? '')));
        $shipAddr = trim((string) ($_POST['shipping_address'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        $res = product_orders_checkout($pdo, $user, $cart, $shipName, $shipPhone, $shipAddr, $note);
        if ($res['ok']) {
            flash('success', 'Order placed successfully. Invoice ' . $res['invoice_no'] . '.');
            header('Location: purchase-invoice.php?id=' . (int) $res['order_id']);
            exit;
        }
        $errors[] = $res['error'] ?? 'Checkout failed.';
    }
}

$catalog = product_orders_catalog($pdo, $q);
$cart = product_orders_cart_get();
$cartBuilt = product_orders_build_lines($pdo, $cart);
$shopBal = wallet_balance($pdo, $uid, 'shopping');
$cartCount = product_orders_cart_count();

$cartById = [];
if (!empty($cartBuilt['lines'])) {
    foreach ($cartBuilt['lines'] as $line) {
        $cartById[(int) $line['product_id']] = $line;
    }
}

require_once __DIR__ . '/includes/header.php';
$flash = get_flash();
?>
<div class="up-page-head">
    <div>
        <h1>Purchase Product</h1>
        <p>Buy admin products using your Shopping Wallet balance.</p>
    </div>
    <div class="team-head-actions">
        <a href="wallet-shopping.php" class="up-btn up-btn-outline">Shopping Wallet</a>
        <a href="purchase-report.php" class="up-btn up-btn-primary">Purchase Report</a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="up-alert up-alert-<?= $flash['type'] === 'error' ? 'err' : 'ok' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="up-alert up-alert-err"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="shop-stats">
    <article class="shop-stat g-purple">
        <span class="shop-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2H6"/></svg></span>
        <div>
            <span class="shop-stat-label">Shopping Wallet</span>
            <strong><?= currency($shopBal) ?></strong>
            <small><a href="wallet-transfer.php">Transfer funds →</a></small>
        </div>
    </article>
    <article class="shop-stat g-blue">
        <span class="shop-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
        <div>
            <span class="shop-stat-label">In Cart</span>
            <strong><?= $cartCount ?></strong>
            <small>item<?= $cartCount === 1 ? '' : 's' ?></small>
        </div>
    </article>
    <article class="shop-stat g-green">
        <span class="shop-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg></span>
        <div>
            <span class="shop-stat-label">Cart Total</span>
            <strong><?= currency((float) ($cartBuilt['subtotal'] ?? 0)) ?></strong>
            <small>BV <?= number_format((float) ($cartBuilt['total_bv'] ?? 0), 2) ?></small>
        </div>
    </article>
    <article class="shop-stat g-orange">
        <span class="shop-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <div>
            <span class="shop-stat-label">Products</span>
            <strong><?= count($catalog) ?></strong>
            <small>available</small>
        </div>
    </article>
</div>

<div class="shop-layout">
    <section class="shop-card">
        <div class="shop-banner">
            <div>
                <span class="shop-kicker">Catalog</span>
                <h2>Products</h2>
                <p>Select quantity and add to cart.</p>
            </div>
            <form method="get" class="shop-search">
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU">
                <button type="submit" class="up-btn up-btn-primary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="purchase-product.php" class="up-btn up-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$catalog): ?>
            <div class="shop-empty">
                <strong>No products found</strong>
                <p>Admin has not published active products yet<?= $q !== '' ? ' matching your search' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="shop-grid">
                <?php foreach ($catalog as $p):
                    $pid = (int) $p['id'];
                    $stock = (int) ($p['stock_qty'] ?? 0);
                    $thumb = product_thumb_url($p['thumbnail'] ?? null);
                    $inCart = (int) ($cart[$pid] ?? 0);
                    $oos = $stock < 1;
                ?>
                <article class="shop-product<?= $oos ? ' is-oos' : '' ?>">
                    <div class="shop-product-media">
                        <?php if ($thumb !== ''): ?>
                            <img src="<?= e($thumb) ?>" alt="<?= e($p['name']) ?>">
                        <?php else: ?>
                            <span class="shop-product-ph" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                            </span>
                        <?php endif; ?>
                        <?php if ($oos): ?><span class="shop-stock-tag is-out">Out of stock</span>
                        <?php elseif ($stock <= 5): ?><span class="shop-stock-tag is-low">Only <?= $stock ?> left</span>
                        <?php endif; ?>
                    </div>
                    <div class="shop-product-body">
                        <small class="shop-product-cat"><?= e($p['category_name'] ?? 'General') ?><?= !empty($p['sku']) ? ' · ' . e($p['sku']) : '' ?></small>
                        <h3><?= e($p['name']) ?></h3>
                        <div class="shop-product-price">
                            <strong><?= currency((float) $p['price']) ?></strong>
                            <?php if (!empty($p['mrp']) && (float) $p['mrp'] > (float) $p['price']): ?>
                                <s><?= currency((float) $p['mrp']) ?></s>
                            <?php endif; ?>
                            <span class="shop-bv">BV <?= number_format((float) ($p['bv'] ?? 0), 0) ?></span>
                        </div>
                        <form method="post" class="shop-add-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                            <input type="number" name="qty" min="1" max="<?= max(1, $stock) ?>" value="1" <?= $oos ? 'disabled' : '' ?>>
                            <button type="submit" class="up-btn up-btn-primary" <?= $oos ? 'disabled' : '' ?>>
                                <?= $inCart > 0 ? 'Add more' : 'Add to cart' ?>
                            </button>
                        </form>
                        <?php if ($inCart > 0): ?>
                            <p class="shop-in-cart"><?= $inCart ?> in cart</p>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="shop-side" id="cart">
        <section class="shop-card shop-cart-card">
            <div class="shop-banner is-cart">
                <div>
                    <span class="shop-kicker">Checkout</span>
                    <h2>Your Cart</h2>
                    <p>Pay from Shopping Wallet on place order.</p>
                </div>
            </div>

            <?php if (empty($cartBuilt['lines'])): ?>
                <div class="shop-empty is-compact">
                    <strong>Cart is empty</strong>
                    <p>Add products from the catalog to continue.</p>
                </div>
            <?php else: ?>
                <form method="post" class="shop-cart-form">
                    <input type="hidden" name="action" value="update_cart">
                    <div class="shop-cart-list">
                        <?php foreach ($cartBuilt['lines'] as $line): ?>
                            <div class="shop-cart-row">
                                <div>
                                    <strong><?= e($line['product_name']) ?></strong>
                                    <small><?= e($line['sku'] ?: '—') ?> · <?= currency($line['unit_price']) ?></small>
                                </div>
                                <input type="number" name="qty[<?= (int) $line['product_id'] ?>]" min="0" max="999" value="<?= (int) $line['qty'] ?>">
                                <span class="shop-cart-line"><?= currency($line['line_total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="shop-cart-actions">
                        <button type="submit" class="up-btn up-btn-outline">Update cart</button>
                        <button type="submit" name="action" value="clear_cart" class="up-btn up-btn-outline" formaction="purchase-product.php">Clear</button>
                    </div>
                </form>

                <div class="shop-cart-summary">
                    <div><span>Subtotal</span><strong><?= currency((float) $cartBuilt['subtotal']) ?></strong></div>
                    <div><span>Total BV</span><strong><?= number_format((float) $cartBuilt['total_bv'], 2) ?></strong></div>
                    <div><span>Wallet balance</span><strong><?= currency($shopBal) ?></strong></div>
                    <?php if ($shopBal + 0.00001 < (float) $cartBuilt['subtotal']): ?>
                        <p class="shop-warn">Insufficient balance. <a href="wallet-transfer.php">Transfer to Shopping Wallet</a>.</p>
                    <?php endif; ?>
                </div>

                <form method="post" class="shop-checkout-form">
                    <input type="hidden" name="action" value="checkout">
                    <div class="form-group">
                        <label for="shipping_name">Shipping name *</label>
                        <input type="text" name="shipping_name" id="shipping_name" required value="<?= e($_POST['shipping_name'] ?? ($user['full_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="shipping_phone">Phone</label>
                        <input type="text" name="shipping_phone" id="shipping_phone" value="<?= e($_POST['shipping_phone'] ?? ($user['phone'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">Shipping address *</label>
                        <textarea name="shipping_address" id="shipping_address" rows="3" required placeholder="House / street, city, state, PIN"><?= e($_POST['shipping_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="note">Order note</label>
                        <input type="text" name="note" id="note" value="<?= e($_POST['note'] ?? '') ?>" placeholder="Optional">
                    </div>
                    <button type="submit" class="up-btn up-btn-primary up-btn-block" <?= $shopBal + 0.00001 < (float) $cartBuilt['subtotal'] ? 'disabled' : '' ?>>
                        Place Order · <?= currency((float) $cartBuilt['subtotal']) ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

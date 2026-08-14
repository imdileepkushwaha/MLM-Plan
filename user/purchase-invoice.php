<?php
$pageTitle = 'Purchase Invoice';
require_once __DIR__ . '/../includes/product_orders.php';
require_once __DIR__ . '/includes/header.php';

product_orders_ensure_tables($pdo);
$uid = (int) $user['id'];
$orderId = (int) ($_GET['id'] ?? 0);
$company = setting('company_name', 'Binary MLM');
$support = setting('support_email', setting('contact_email', ''));
$supportPhone = setting('contact_phone', '');

$order = null;
$items = [];
if ($orderId > 0) {
    $order = product_order_get($pdo, $orderId, $uid);
    if ($order) {
        $items = product_order_items($pdo, $orderId);
    }
}

$recent = [];
if (!$order) {
    $recent = product_orders_for_member($pdo, $uid, 20, 0);
}
?>
<div class="doc-page shop-invoice-page">
    <div class="doc-toolbar no-print">
        <div>
            <h1 class="doc-title">Purchase Invoice</h1>
            <p class="doc-sub"><?= $order ? 'Invoice ' . e($order['invoice_no']) : 'Select an order to view / print invoice' ?></p>
        </div>
        <div class="doc-toolbar-actions">
            <?php if ($order): ?>
                <button type="button" class="up-btn up-btn-primary" onclick="window.print()">Print / Save PDF</button>
            <?php endif; ?>
            <a href="purchase-report.php" class="up-btn up-btn-outline">Purchase Report</a>
            <a href="purchase-product.php" class="up-btn up-btn-outline">Buy more</a>
        </div>
    </div>

    <?php if (!$order): ?>
        <section class="shop-card no-print">
            <div class="shop-banner">
                <div>
                    <span class="shop-kicker">Invoices</span>
                    <h2>Select invoice</h2>
                    <p>Open any paid order to print the invoice.</p>
                </div>
            </div>
            <?php if (!$recent): ?>
                <div class="shop-empty">
                    <strong>No invoices yet</strong>
                    <p><a href="purchase-product.php">Purchase a product</a> to generate your first invoice.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="shop-table">
                        <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><code class="shop-inv"><?= e($r['invoice_no']) ?></code></td>
                                <td><?= !empty($r['created_at']) ? e(date('d M Y', strtotime((string) $r['created_at']))) : '—' ?></td>
                                <td><?= currency((float) $r['total_amount']) ?></td>
                                <td><span class="shop-pill is-<?= e($r['status']) ?>"><?= e(ucfirst((string) $r['status'])) ?></span></td>
                                <td class="shop-td-actions">
                                    <a class="up-btn up-btn-primary up-btn-sm" href="purchase-invoice.php?id=<?= (int) $r['id'] ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="shop-invoice">
            <header class="shop-inv-head">
                <div>
                    <strong class="shop-inv-brand"><?= e($company) ?></strong>
                    <p>Tax / Purchase Invoice</p>
                    <?php if ($support !== '' || $supportPhone !== ''): ?>
                        <small>
                            <?= $support !== '' ? e($support) : '' ?>
                            <?= ($support !== '' && $supportPhone !== '') ? ' · ' : '' ?>
                            <?= $supportPhone !== '' ? e($supportPhone) : '' ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="shop-inv-meta">
                    <div><span>Invoice No</span><strong><?= e($order['invoice_no']) ?></strong></div>
                    <div><span>Date</span><strong><?= !empty($order['created_at']) ? e(date('d M Y H:i', strtotime((string) $order['created_at']))) : '—' ?></strong></div>
                    <div><span>Status</span><strong><?= e(ucfirst((string) $order['status'])) ?></strong></div>
                    <div><span>Paid via</span><strong>Shopping Wallet</strong></div>
                </div>
            </header>

            <div class="shop-inv-parties">
                <div>
                    <span class="shop-inv-label">Bill To</span>
                    <strong><?= e($user['full_name']) ?></strong>
                    <p><?= e($user['member_id']) ?> · @<?= e($user['username']) ?></p>
                    <?php if (!empty($user['email'])): ?><p><?= e($user['email']) ?></p><?php endif; ?>
                    <?php if (!empty($user['phone'])): ?><p><?= e($user['phone']) ?></p><?php endif; ?>
                </div>
                <div>
                    <span class="shop-inv-label">Ship To</span>
                    <strong><?= e($order['shipping_name'] ?? $user['full_name']) ?></strong>
                    <?php if (!empty($order['shipping_phone'])): ?><p><?= e($order['shipping_phone']) ?></p><?php endif; ?>
                    <p><?= nl2br(e((string) ($order['shipping_address'] ?? '—'))) ?></p>
                </div>
            </div>

            <table class="shop-inv-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>BV</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($it['product_name']) ?></td>
                        <td><?= e($it['sku'] ?: '—') ?></td>
                        <td><?= (int) $it['qty'] ?></td>
                        <td><?= currency((float) $it['unit_price']) ?></td>
                        <td><?= number_format((float) $it['line_bv'], 2) ?></td>
                        <td><?= currency((float) $it['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="shop-inv-totals">
                <div>
                    <?php if (!empty($order['note'])): ?>
                        <span class="shop-inv-label">Note</span>
                        <p><?= e($order['note']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="shop-inv-sum">
                    <div><span>Subtotal</span><strong><?= currency((float) $order['subtotal']) ?></strong></div>
                    <div><span>Discount</span><strong><?= currency((float) $order['discount_amount']) ?></strong></div>
                    <div><span>Total BV</span><strong><?= number_format((float) $order['total_bv'], 2) ?></strong></div>
                    <div class="is-grand"><span>Grand Total</span><strong><?= currency((float) $order['total_amount']) ?></strong></div>
                </div>
            </div>

            <footer class="shop-inv-foot">
                <p>This is a computer-generated invoice for Shopping Wallet purchase. Thank you for your order.</p>
            </footer>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

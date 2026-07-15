<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utility.php';
$pageTitle = 'Stock Report';

$rows = $pdo->query("
    SELECT p.id, p.name, p.sku, p.stock_qty, p.status,
           c.name AS category_name,
           ss.min_stock_alert
    FROM products p
    LEFT JOIN product_categories c ON c.id = p.category_id
    LEFT JOIN subcategory_settings ss ON ss.subcategory_id = p.subcategory_id AND ss.status = 'active'
    ORDER BY p.stock_qty ASC, p.name
")->fetchAll();

$totalProducts = count($rows);
$totalStock = 0;
$lowStock = 0;
$outOfStock = 0;
foreach ($rows as &$r) {
    $alert = $r['min_stock_alert'] !== null ? (int)$r['min_stock_alert'] : 5;
    $r['_alert'] = $alert;
    $r['_low'] = (int)$r['stock_qty'] <= $alert;
    $totalStock += (int)$r['stock_qty'];
    if ((int)$r['stock_qty'] <= 0) $outOfStock++;
    if ($r['_low']) $lowStock++;
}
unset($r);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid" style="margin-bottom:1.25rem">
    <div class="stat-card g-blue">
        <div class="value"><?= $totalProducts ?></div>
        <div class="label">Total Products</div>
    </div>
    <div class="stat-card g-cyan">
        <div class="value"><?= $totalStock ?></div>
        <div class="label">Total Stock Units</div>
    </div>
    <div class="stat-card g-orange">
        <div class="value"><?= $lowStock ?></div>
        <div class="label">Low Stock</div>
    </div>
    <div class="stat-card g-red">
        <div class="value"><?= $outOfStock ?></div>
        <div class="label">Out of Stock</div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Stock Levels</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Stock Qty</th>
                    <th>Min Alert</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6">No products yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?= $r['_low'] ? ' style="background:rgba(225,29,72,.06)"' : '' ?>>
                    <td>
                        <strong><?= e($r['name']) ?></strong>
                        <?php if ($r['_low']): ?>
                            <span class="badge badge-inactive" style="margin-left:.35rem">Low</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['sku'] ?? '—') ?></td>
                    <td><?= e($r['category_name'] ?? '—') ?></td>
                    <td><strong><?= (int)$r['stock_qty'] ?></strong></td>
                    <td><?= (int)$r['_alert'] ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

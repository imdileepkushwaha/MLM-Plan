<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/report_helpers.php';
$pageTitle = 'Overview';

[$from, $to] = report_parse_dates();

$joinsStmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE DATE(join_date) BETWEEN ? AND ?');
$joinsStmt->execute([$from, $to]);
$joins = (int) $joinsStmt->fetchColumn();

$commStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?");
$commStmt->execute([$from, $to]);
$commTotal = (float) $commStmt->fetchColumn();

$wdStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status IN ('approved','paid') AND DATE(COALESCE(processed_at, requested_at)) BETWEEN ? AND ?");
$wdStmt->execute([$from, $to]);
$wdTotal = (float) $wdStmt->fetchColumn();

$pendingWdStmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM withdrawals WHERE status = 'pending' AND DATE(requested_at) BETWEEN ? AND ?");
$pendingWdStmt->execute([$from, $to]);
$pendingWdRow = $pendingWdStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
$pendingWdCount = (int) $pendingWdRow[0];
$pendingWdSum = (float) $pendingWdRow[1];
$net = $commTotal - $wdTotal;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel tpin-panel">
    <div class="panel-header">
        <div>
            <h2>Reports Overview</h2>
            <p class="tpin-panel-sub">Period summary — open detailed reports from the Reports submenu</p>
        </div>
    </div>
    <div class="panel-body">
        <?php report_filter_form('reports.php', $from, $to); ?>

        <div class="stats-grid tpin-stats">
            <div class="stat-card accent">
                <div class="label">New Joins</div>
                <div class="value"><?= $joins ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Commissions</div>
                <div class="value" style="font-size:1.25rem"><?= currency($commTotal) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Payouts</div>
                <div class="value" style="font-size:1.25rem"><?= currency($wdTotal) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Net</div>
                <div class="value" style="font-size:1.25rem"><?= currency($net) ?></div>
            </div>
        </div>

        <div class="alert alert-info" style="margin-top:1rem">
            Pending withdrawals: <strong><?= $pendingWdCount ?></strong> · <?= currency($pendingWdSum) ?>
            · <a href="withdrawals.php?status=pending">Review payouts</a>
        </div>

        <div class="tpin-gen-grid" style="margin-top:1rem">
            <a class="btn btn-outline" href="report-commission.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Commission Report</a>
            <a class="btn btn-outline" href="report-joining.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Joining Report</a>
            <a class="btn btn-outline" href="report-package-sales.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Package Sales</a>
            <a class="btn btn-outline" href="report-top-earners.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Top Earners</a>
            <a class="btn btn-outline" href="report-binary-closing.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Binary Closing</a>
            <a class="btn btn-outline" href="tds-report.php?from=<?= e($from) ?>&to=<?= e($to) ?>">TDS Report</a>
            <a class="btn btn-outline" href="tpin-report.php">T-Pin Report</a>
            <a class="btn btn-outline" href="stock-report.php">Stock Report</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

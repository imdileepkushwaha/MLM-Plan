<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/withdrawal.php';
$pageTitle = 'TDS Report';

wd_ensure_columns($pdo);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("
        SELECT w.*, m.full_name, m.member_id AS mid
        FROM withdrawals w
        JOIN members m ON m.id = w.member_id
        WHERE w.status IN ('approved','paid')
          AND DATE(COALESCE(w.processed_at, w.requested_at)) BETWEEN ? AND ?
          AND w.tds_amount > 0
        ORDER BY w.id ASC
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tds-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['ID', 'Date', 'Member ID', 'Name', 'Gross', 'TDS', 'Fee', 'Other', 'Net', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (int) $r['id'],
            $r['processed_at'] ?? $r['requested_at'],
            $r['mid'],
            $r['full_name'],
            number_format((float) $r['amount'], 2, '.', ''),
            number_format((float) $r['tds_amount'], 2, '.', ''),
            number_format((float) $r['fee_amount'], 2, '.', ''),
            number_format((float) $r['other_deduction'], 2, '.', ''),
            number_format(wd_net_display($r), 2, '.', ''),
            $r['status'],
        ]);
    }
    fclose($out);
    exit;
}

$sumStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(amount),0) AS gross,
        COALESCE(SUM(tds_amount),0) AS tds,
        COALESCE(SUM(fee_amount),0) AS fee,
        COALESCE(SUM(other_deduction),0) AS other_ded,
        COALESCE(SUM(COALESCE(net_amount, amount)),0) AS net
    FROM withdrawals
    WHERE status IN ('approved','paid')
      AND DATE(COALESCE(processed_at, requested_at)) BETWEEN ? AND ?
");
$sumStmt->execute([$from, $to]);
$sums = $sumStmt->fetch() ?: [];

$list = $pdo->prepare("
    SELECT w.*, m.full_name, m.member_id AS mid
    FROM withdrawals w
    JOIN members m ON m.id = w.member_id
    WHERE w.status IN ('approved','paid')
      AND DATE(COALESCE(w.processed_at, w.requested_at)) BETWEEN ? AND ?
    ORDER BY w.id DESC
    LIMIT 200
");
$list->execute([$from, $to]);
$rows = $list->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
        <div>
            <h2>TDS &amp; Deduction Report</h2>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem">Approved / paid withdrawals in period</p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <a class="btn btn-outline btn-sm" href="withdrawals.php">← Withdrawals</a>
            <a class="btn btn-primary btn-sm" href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv">Export CSV</a>
        </div>
    </div>
    <div class="panel-body">
        <form class="filters" method="get" style="margin-bottom:1rem">
            <div class="form-group">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>

        <div class="stats-grid" style="margin-bottom:1rem">
            <div class="stat-card"><div class="label">Payouts</div><div class="value"><?= (int) ($sums['cnt'] ?? 0) ?></div></div>
            <div class="stat-card"><div class="label">Gross</div><div class="value"><?= currency((float) ($sums['gross'] ?? 0)) ?></div></div>
            <div class="stat-card accent"><div class="label">TDS</div><div class="value"><?= currency((float) ($sums['tds'] ?? 0)) ?></div></div>
            <div class="stat-card"><div class="label">Fees + Other</div><div class="value"><?= currency((float) ($sums['fee'] ?? 0) + (float) ($sums['other_ded'] ?? 0)) ?></div></div>
            <div class="stat-card"><div class="label">Net Remitted</div><div class="value"><?= currency((float) ($sums['net'] ?? 0)) ?></div></div>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Gross</th>
                    <th>TDS</th>
                    <th>Fee</th>
                    <th>Other</th>
                    <th>Net</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9">No approved/paid withdrawals in this period.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int) $r['id'] ?></td>
                    <td><?= e($r['full_name']) ?><br><small><?= e($r['mid']) ?></small></td>
                    <td><?= currency((float) $r['amount']) ?></td>
                    <td><strong><?= currency((float) $r['tds_amount']) ?></strong></td>
                    <td><?= currency((float) $r['fee_amount']) ?></td>
                    <td><?= currency((float) $r['other_deduction']) ?></td>
                    <td><strong><?= currency(wd_net_display($r)) ?></strong></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime((string) ($r['processed_at'] ?? $r['requested_at'])))) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

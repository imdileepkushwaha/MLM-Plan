<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Reports';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$joinsStmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE DATE(join_date) BETWEEN ? AND ?');
$joinsStmt->execute([$from, $to]);
$joins = (int) $joinsStmt->fetchColumn();

$commStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?");
$commStmt->execute([$from, $to]);
$commTotal = (float) $commStmt->fetchColumn();

$wdStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status IN ('approved','paid') AND DATE(COALESCE(processed_at, requested_at)) BETWEEN ? AND ?");
$wdStmt->execute([$from, $to]);
$wdTotal = (float) $wdStmt->fetchColumn();

$pkgSales = $pdo->prepare("
    SELECT p.name, COUNT(m.id) AS cnt, COALESCE(SUM(p.amount),0) AS revenue
    FROM packages p
    LEFT JOIN members m ON m.package_id = p.id AND DATE(m.join_date) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY revenue DESC
");
$pkgSales->execute([$from, $to]);
$packages = $pkgSales->fetchAll();

$topEarners = $pdo->prepare("
    SELECT m.member_id, m.full_name, m.total_earnings, m.wallet_balance
    FROM members m
    ORDER BY m.total_earnings DESC
    LIMIT 10
");
$topEarners->execute();
$earners = $topEarners->fetchAll();

$dailyJoins = $pdo->prepare("
    SELECT DATE(join_date) AS d, COUNT(*) AS cnt
    FROM members
    WHERE DATE(join_date) BETWEEN ? AND ?
    GROUP BY DATE(join_date)
    ORDER BY d
");
$dailyJoins->execute([$from, $to]);
$daily = $dailyJoins->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header"><h2>Date Range</h2></div>
    <div class="panel-body">
        <form class="filters" method="get">
            <div class="form-group">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Generate</button>
        </form>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="label">New Joins</div><div class="value"><?= $joins ?></div></div>
    <div class="stat-card accent"><div class="label">Commissions</div><div class="value"><?= currency($commTotal) ?></div></div>
    <div class="stat-card"><div class="label">Payouts</div><div class="value"><?= currency($wdTotal) ?></div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Package Sales (Period)</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Package</th><th>Members</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= (int)$p['cnt'] ?></td>
                    <td><?= currency((float)$p['revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Top Earners</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>#</th><th>Member</th><th>Total Earnings</th><th>Wallet</th></tr></thead>
            <tbody>
            <?php foreach ($earners as $i => $e): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($e['member_id'] . ' — ' . $e['full_name']) ?></td>
                    <td><?= currency((float)$e['total_earnings']) ?></td>
                    <td><?= currency((float)$e['wallet_balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Daily Joins</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Date</th><th>Joins</th></tr></thead>
            <tbody>
            <?php if (!$daily): ?>
                <tr><td colspan="2">No joins in this period.</td></tr>
            <?php else: foreach ($daily as $d): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($d['d'])) ?></td>
                    <td><?= (int)$d['cnt'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

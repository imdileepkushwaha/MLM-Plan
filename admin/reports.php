<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Reports';

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

$pkgSales = $pdo->prepare("
    SELECT p.name, COUNT(m.id) AS cnt, COALESCE(SUM(p.amount),0) AS revenue
    FROM packages p
    LEFT JOIN members m ON m.package_id = p.id AND DATE(m.join_date) BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY revenue DESC
");
$pkgSales->execute([$from, $to]);
$packages = $pkgSales->fetchAll();
$pkgMax = 0.0;
foreach ($packages as $p) {
    $pkgMax = max($pkgMax, (float) $p['revenue']);
}

$topEarners = $pdo->prepare("
    SELECT m.id, m.member_id, m.full_name, m.total_earnings, m.wallet_balance, m.status, m.package_id,
           p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON p.id = m.package_id
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
$dailyMax = 1;
foreach ($daily as $d) {
    $dailyMax = max($dailyMax, (int) $d['cnt']);
}

$periodLabel = date('d M Y', strtotime($from)) . ' → ' . date('d M Y', strtotime($to));
$daysSpan = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);

$icoInr = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg>';
$icoUsers = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
$icoOut = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
$icoNet = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 3 5-6"/></svg>';
$icoCal = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
$icoPkg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>';
$icoTrophy = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M17 4h2a2 2 0 012 2v1a4 4 0 01-4 4M7 4H5a2 2 0 00-2 2v1a4 4 0 004 4"/></svg>';
$icoChart = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="rpt">
    <section class="rpt-hero">
        <div class="rpt-hero-main">
            <span class="rpt-hero-ico" aria-hidden="true"><?= $icoChart ?></span>
            <div>
                <span class="rpt-kicker">Analytics</span>
                <h1>Business Reports</h1>
                <p>Period overview for joins, commissions, payouts and package performance.</p>
            </div>
        </div>
        <form method="get" class="rpt-filters">
            <label>
                <span>From</span>
                <input type="date" name="from" value="<?= e($from) ?>" required>
            </label>
            <label>
                <span>To</span>
                <input type="date" name="to" value="<?= e($to) ?>" required>
            </label>
            <button type="submit" class="btn btn-primary rpt-go">Generate</button>
        </form>
        <div class="rpt-hero-glow" aria-hidden="true"></div>
    </section>

    <div class="rpt-period">
        <span class="rpt-period-ico" aria-hidden="true"><?= $icoCal ?></span>
        <div>
            <strong><?= e($periodLabel) ?></strong>
            <small><?= $daysSpan ?> day<?= $daysSpan === 1 ? '' : 's' ?> selected</small>
        </div>
        <div class="rpt-period-actions">
            <a class="rpt-chip" href="?from=<?= e(date('Y-m-d')) ?>&to=<?= e(date('Y-m-d')) ?>">Today</a>
            <a class="rpt-chip" href="?from=<?= e(date('Y-m-01')) ?>&to=<?= e(date('Y-m-d')) ?>">This month</a>
            <a class="rpt-chip" href="?from=<?= e(date('Y-m-d', strtotime('-6 days'))) ?>&to=<?= e(date('Y-m-d')) ?>">7 days</a>
            <a class="rpt-chip" href="?from=<?= e(date('Y-m-d', strtotime('-29 days'))) ?>&to=<?= e(date('Y-m-d')) ?>">30 days</a>
        </div>
    </div>

    <div class="rpt-stats">
        <article class="rpt-stat g-blue">
            <span class="rpt-stat-ico" aria-hidden="true"><?= $icoUsers ?></span>
            <div>
                <span class="rpt-stat-label">New Joins</span>
                <strong><?= $joins ?></strong>
                <small>Members in period</small>
            </div>
        </article>
        <article class="rpt-stat g-green">
            <span class="rpt-stat-ico" aria-hidden="true"><?= $icoInr ?></span>
            <div>
                <span class="rpt-stat-label">Commissions</span>
                <strong class="is-sm"><?= currency($commTotal) ?></strong>
                <small>Credited income</small>
            </div>
        </article>
        <article class="rpt-stat g-orange">
            <span class="rpt-stat-ico" aria-hidden="true"><?= $icoOut ?></span>
            <div>
                <span class="rpt-stat-label">Payouts</span>
                <strong class="is-sm"><?= currency($wdTotal) ?></strong>
                <small>Approved / paid</small>
            </div>
        </article>
        <article class="rpt-stat g-navy">
            <span class="rpt-stat-ico" aria-hidden="true"><?= $icoNet ?></span>
            <div>
                <span class="rpt-stat-label">Net</span>
                <strong class="is-sm"><?= currency($net) ?></strong>
                <small>Commission − payouts</small>
            </div>
        </article>
    </div>

    <div class="rpt-note">
        <span>Pending withdrawals in period: <strong><?= $pendingWdCount ?></strong> · <?= currency($pendingWdSum) ?></span>
        <a href="withdrawals.php?status=pending">Review payouts →</a>
    </div>

    <div class="rpt-grid">
        <section class="rpt-panel">
            <div class="rpt-panel-head is-blue">
                <div class="rpt-panel-main">
                    <span class="rpt-panel-ico" aria-hidden="true"><?= $icoPkg ?></span>
                    <div>
                        <span class="rpt-kicker">Packages</span>
                        <h2>Package Sales</h2>
                    </div>
                </div>
            </div>
            <div class="rpt-panel-body">
                <?php if (!$packages): ?>
                    <div class="rpt-empty"><strong>No package data</strong><p>Packages will appear here once configured.</p></div>
                <?php else: ?>
                    <div class="rpt-pkg-list">
                        <?php foreach ($packages as $p):
                            $rev = (float) $p['revenue'];
                            $pct = $pkgMax > 0 ? round(($rev / $pkgMax) * 100) : 0;
                        ?>
                            <article class="rpt-pkg">
                                <div class="rpt-pkg-top">
                                    <strong><?= e($p['name']) ?></strong>
                                    <span><?= (int) $p['cnt'] ?> members</span>
                                </div>
                                <div class="rpt-bar"><i style="width:<?= $pct ?>%"></i></div>
                                <div class="rpt-pkg-foot">
                                    <span>Revenue</span>
                                    <strong><?= currency($rev) ?></strong>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="rpt-panel">
            <div class="rpt-panel-head is-gold">
                <div class="rpt-panel-main">
                    <span class="rpt-panel-ico" aria-hidden="true"><?= $icoTrophy ?></span>
                    <div>
                        <span class="rpt-kicker">Leaderboard</span>
                        <h2>Top Earners</h2>
                    </div>
                </div>
            </div>
            <div class="rpt-panel-body rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member</th>
                            <th>Earnings</th>
                            <th>Wallet</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$earners): ?>
                        <tr><td colspan="4"><div class="rpt-empty"><strong>No members yet</strong></div></td></tr>
                    <?php else: foreach ($earners as $i => $e): ?>
                        <tr>
                            <td><span class="rpt-rank"><?= $i + 1 ?></span></td>
                            <td>
                                <a class="rpt-member" href="member-view.php?id=<?= (int) $e['id'] ?>">
                                    <strong><?= e($e['full_name']) ?></strong>
                                    <small><?= e($e['member_id']) ?><?= !empty($e['package_name']) ? ' · ' . e($e['package_name']) : '' ?></small>
                                </a>
                            </td>
                            <td><strong class="rpt-amt"><?= currency((float) $e['total_earnings']) ?></strong></td>
                            <td><?= currency((float) $e['wallet_balance']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="rpt-panel">
        <div class="rpt-panel-head is-coral">
            <div class="rpt-panel-main">
                <span class="rpt-panel-ico" aria-hidden="true"><?= $icoCal ?></span>
                <div>
                    <span class="rpt-kicker">Trend</span>
                    <h2>Daily Joins</h2>
                </div>
            </div>
            <span class="rpt-head-meta"><?= count($daily) ?> day<?= count($daily) === 1 ? '' : 's' ?> with joins</span>
        </div>
        <div class="rpt-panel-body">
            <?php if (!$daily): ?>
                <div class="rpt-empty">
                    <strong>No joins in this period</strong>
                    <p>Try expanding the date range to see activity.</p>
                </div>
            <?php else: ?>
                <div class="rpt-bars" role="img" aria-label="Daily joins chart">
                    <?php foreach ($daily as $d):
                        $cnt = (int) $d['cnt'];
                        $h = max(8, (int) round(($cnt / $dailyMax) * 100));
                    ?>
                        <div class="rpt-bars-col" title="<?= e(date('d M Y', strtotime($d['d']))) ?>: <?= $cnt ?>">
                            <span class="rpt-bars-val"><?= $cnt ?></span>
                            <span class="rpt-bars-fill" style="height:<?= $h ?>%"></span>
                            <span class="rpt-bars-label"><?= e(date('d M', strtotime($d['d']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="rpt-table-wrap rpt-daily-table">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Joins</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daily as $d): ?>
                            <tr>
                                <td><?= e(date('d M Y', strtotime($d['d']))) ?></td>
                                <td><strong><?= (int) $d['cnt'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

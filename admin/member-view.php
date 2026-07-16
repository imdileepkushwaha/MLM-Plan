<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activation.php';
require_once __DIR__ . '/../includes/closing.php';
require_once __DIR__ . '/../includes/withdrawal.php';
$pageTitle = 'Member Details';

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate_package') {
    $pkgId = (int) ($_POST['package_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $mem = $stmt->fetch();
    if (!$mem) {
        flash('error', 'Member not found.');
        header('Location: members.php');
        exit;
    }
    $result = activation_apply($pdo, $mem, $pkgId);
    if ($result['ok']) {
        $pkgName = $result['package']['name'] ?? 'package';
        log_activity('member_activate_package', "Activated member #{$id} with {$pkgName}");
        flash('success', 'Package activated. Referral, BV, and level income processed.');
    } else {
        flash('error', $result['error'] ?? 'Activation failed.');
    }
    header('Location: member-view.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, p.name AS package_name, p.amount AS package_amount,
           s.full_name AS sponsor_name, s.member_id AS sponsor_mid, s.id AS sponsor_db_id,
           pl.full_name AS placement_name, pl.member_id AS placement_mid
    FROM members m
    LEFT JOIN packages p ON p.id = m.package_id
    LEFT JOIN members s ON s.id = m.sponsor_id
    LEFT JOIN members pl ON pl.id = m.placement_id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    flash('error', 'Member not found.');
    header('Location: members.php');
    exit;
}

$pageTitle = $member['full_name'];

$packages = [];
if (empty($member['package_id'])) {
    $packages = activation_packages($pdo);
}

$pairBv = closing_pair_bv();
$flush = max(0, (int) setting('binary_flush_pairs', '0'));
$openMatch = closing_compute_match((float) $member['left_bv'], (float) $member['right_bv'], $pairBv, $flush);

$leftChild = $pdo->prepare('SELECT * FROM members WHERE placement_id = ? AND position = ?');
$leftChild->execute([$id, 'left']);
$left = $leftChild->fetch();

$rightChild = $pdo->prepare('SELECT * FROM members WHERE placement_id = ? AND position = ?');
$rightChild->execute([$id, 'right']);
$right = $rightChild->fetch();

$comms = $pdo->prepare('SELECT * FROM commissions WHERE member_id = ? ORDER BY created_at DESC LIMIT 20');
$comms->execute([$id]);
$commissions = $comms->fetchAll();

$withdrawals = $pdo->prepare('SELECT * FROM withdrawals WHERE member_id = ? ORDER BY requested_at DESC LIMIT 10');
$withdrawals->execute([$id]);
$wdList = $withdrawals->fetchAll();

$ct = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE member_id = ? AND status != 'cancelled'");
$ct->execute([$id]);
$commTotal = (float) $ct->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mv-hero">
    <div class="mv-hero-main">
        <div class="mv-avatar"><?= strtoupper(substr($member['full_name'], 0, 1)) ?></div>
        <div class="mv-hero-info">
            <div class="mv-id-row">
                <span class="mv-id"><?= e($member['member_id']) ?></span>
                <span class="badge badge-<?= e($member['status']) ?>"><?= e($member['status']) ?></span>
            </div>
            <h2><?= e($member['full_name']) ?></h2>
            <p>@<?= e($member['username']) ?> · Joined <?= date('d M Y', strtotime($member['join_date'])) ?></p>
            <div class="mv-tags">
                <?php if ($member['package_name']): ?>
                <span class="mv-tag pink"><?= e($member['package_name']) ?></span>
                <?php endif; ?>
                <?php if ($member['email']): ?>
                <span class="mv-tag"><?= e($member['email']) ?></span>
                <?php endif; ?>
                <?php if ($member['phone']): ?>
                <span class="mv-tag"><?= e($member['phone']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="mv-hero-actions">
        <a href="member-edit.php?id=<?= (int)$member['id'] ?>" class="btn btn-primary btn-sm">Edit Member</a>
        <a href="tree-view.php?root=<?= (int)$member['id'] ?>" class="btn btn-outline btn-sm">View Tree</a>
        <a href="direct-member-login.php?q=<?= urlencode($member['member_id']) ?>" class="btn btn-outline btn-sm">Login As</a>
        <a href="members.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
</div>

<?php if (empty($member['package_id'])): ?>
<div class="panel" style="margin-bottom:1rem">
    <div class="panel-header"><h2>Activate Package</h2></div>
    <div class="panel-body">
        <?php if (!$packages): ?>
            <p class="muted">No active packages available. Add one under Packages first.</p>
        <?php else: ?>
            <form method="post" class="filters" style="align-items:flex-end" onsubmit="return confirm('Activate this member package? Referral, BV and level income will run.');">
                <input type="hidden" name="action" value="activate_package">
                <div class="form-group">
                    <label>Package</label>
                    <select name="package_id" required>
                        <option value="">Select package…</option>
                        <?php foreach ($packages as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= currency((float) $p['amount']) ?> (BV <?= number_format((float) $p['bv'], 0) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Activate now</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="mv-kpis">
    <div class="mv-kpi g-blue">
        <span class="mv-kpi-label">Left Count</span>
        <strong><?= (int)$member['left_count'] ?></strong>
    </div>
    <div class="mv-kpi g-pink">
        <span class="mv-kpi-label">Right Count</span>
        <strong><?= (int)$member['right_count'] ?></strong>
    </div>
    <div class="mv-kpi g-green">
        <span class="mv-kpi-label">Left BV</span>
        <strong><?= number_format((float)$member['left_bv'], 0) ?></strong>
    </div>
    <div class="mv-kpi g-orange">
        <span class="mv-kpi-label">Right BV</span>
        <strong><?= number_format((float)$member['right_bv'], 0) ?></strong>
    </div>
    <div class="mv-kpi g-purple">
        <span class="mv-kpi-label">Open Pairs</span>
        <strong><?= number_format((float)$openMatch['pairs'], 2) ?></strong>
        <small style="display:block;font-size:0.7rem;font-weight:600;color:#8392ab;margin-top:0.2rem">Match BV <?= number_format((float)$openMatch['matched_bv'], 0) ?></small>
    </div>
    <div class="mv-kpi g-red">
        <span class="mv-kpi-label">Wallet</span>
        <strong><?= currency((float)$member['wallet_balance']) ?></strong>
    </div>
    <div class="mv-kpi g-mint">
        <span class="mv-kpi-label">Total Earnings</span>
        <strong><?= currency((float)$member['total_earnings']) ?></strong>
    </div>
</div>

<div class="mv-grid">
    <div class="panel mv-card">
        <div class="panel-header"><h2>Profile Details</h2></div>
        <div class="panel-body">
            <div class="mv-detail-list">
                <div class="mv-detail"><span>Username</span><strong><?= e($member['username']) ?></strong></div>
                <div class="mv-detail"><span>Email</span><strong><?= e($member['email']) ?></strong></div>
                <div class="mv-detail"><span>Phone</span><strong><?= e($member['phone'] ?: '—') ?></strong></div>
                <div class="mv-detail"><span>Package</span><strong><?= e($member['package_name'] ?? '—') ?><?= $member['package_amount'] ? ' (' . currency((float)$member['package_amount']) . ')' : '' ?></strong></div>
                <div class="mv-detail">
                    <span>Sponsor</span>
                    <strong>
                        <?php if ($member['sponsor_mid']): ?>
                            <a href="member-view.php?id=<?= (int)$member['sponsor_db_id'] ?>"><?= e($member['sponsor_mid'] . ' — ' . $member['sponsor_name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </strong>
                </div>
                <div class="mv-detail">
                    <span>Placement</span>
                    <strong><?= $member['placement_mid'] ? e($member['placement_mid'] . ' (' . ucfirst((string)$member['position']) . ')') : 'Root Member' ?></strong>
                </div>
                <div class="mv-detail"><span>Joined</span><strong><?= date('d M Y, h:i A', strtotime($member['join_date'])) ?></strong></div>
                <div class="mv-detail"><span>Commissions Earned</span><strong><?= currency($commTotal) ?></strong></div>
            </div>
        </div>
    </div>

    <div class="panel mv-card">
        <div class="panel-header"><h2>Binary Children</h2></div>
        <div class="panel-body">
            <div class="mv-children">
                <div class="mv-child left">
                    <div class="mv-child-head">
                        <span class="lr left">Left</span>
                        <small>Direct leg</small>
                    </div>
                    <?php if ($left): ?>
                        <a href="member-view.php?id=<?= (int)$left['id'] ?>" class="mv-child-body">
                            <span class="member-avatar"><?= strtoupper(substr($left['full_name'], 0, 1)) ?></span>
                            <div>
                                <strong><?= e($left['full_name']) ?></strong>
                                <small><?= e($left['member_id']) ?></small>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="mv-child-empty">Slot empty</div>
                    <?php endif; ?>
                </div>
                <div class="mv-child right">
                    <div class="mv-child-head">
                        <span class="lr right">Right</span>
                        <small>Direct leg</small>
                    </div>
                    <?php if ($right): ?>
                        <a href="member-view.php?id=<?= (int)$right['id'] ?>" class="mv-child-body">
                            <span class="member-avatar"><?= strtoupper(substr($right['full_name'], 0, 1)) ?></span>
                            <div>
                                <strong><?= e($right['full_name']) ?></strong>
                                <small><?= e($right['member_id']) ?></small>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="mv-child-empty">Slot empty</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mv-grid">
    <div class="panel mv-card">
        <div class="panel-header">
            <h2>Commission History</h2>
            <a href="commissions.php" class="btn btn-outline btn-sm">View all</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (!$commissions): ?>
                    <tr><td colspan="4"><div class="empty-state" style="padding:1.5rem"><strong>No commissions</strong></div></td></tr>
                <?php else: foreach ($commissions as $c): ?>
                    <tr>
                        <td>
                            <strong><?= e(ucfirst($c['type'])) ?></strong>
                            <?php if (!empty($c['description'])): ?>
                            <br><small class="muted"><?= e($c['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= currency((float)$c['amount']) ?></strong></td>
                        <td><span class="badge badge-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                        <td><span class="muted"><?= date('d M Y', strtotime($c['created_at'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel mv-card">
        <div class="panel-header">
            <h2>Withdrawals</h2>
            <a href="withdrawals.php" class="btn btn-outline btn-sm">View all</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Gross</th><th>Net</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (!$wdList): ?>
                    <tr><td colspan="4"><div class="empty-state" style="padding:1.5rem"><strong>No withdrawals</strong></div></td></tr>
                <?php else: foreach ($wdList as $w): ?>
                    <tr>
                        <td><strong><?= currency((float)$w['amount']) ?></strong></td>
                        <td><?= currency(wd_net_display($w)) ?></td>
                        <td><span class="badge badge-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></td>
                        <td><span class="muted"><?= date('d M Y', strtotime($w['requested_at'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

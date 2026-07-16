<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/procedures.php';
$pageTitle = 'Withdrawals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ?');
    $stmt->execute([$id]);
    $wd = $stmt->fetch();

    if ($wd && $wd['status'] === 'pending') {
        if ($action === 'approve') {
            $sp = sp_call_approve_withdrawal($pdo, $id, $note);
            if ($sp['message'] !== 'Procedure unavailable' && $sp['ok']) {
                log_activity('withdrawal_approve', "Approved withdrawal #$id");
                flash('success', $sp['message']);
            } elseif ($sp['message'] !== 'Procedure unavailable' && !$sp['ok']) {
                flash('error', $sp['message']);
            } else {
                // PHP fallback
                $pdo->prepare("UPDATE withdrawals SET status = 'approved', admin_note = ?, processed_at = NOW() WHERE id = ?")
                    ->execute([$note, $id]);
                $pdo->prepare('UPDATE members SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?')
                    ->execute([(float) $wd['amount'], (int) $wd['member_id'], (float) $wd['amount']]);
                log_activity('withdrawal_approve', "Approved withdrawal #$id");
                flash('success', 'Withdrawal approved. Wallet deducted.');
            }
        } elseif ($action === 'reject') {
            $sp = sp_call_reject_withdrawal($pdo, $id, $note);
            if ($sp['message'] !== 'Procedure unavailable' && $sp['ok']) {
                log_activity('withdrawal_reject', "Rejected withdrawal #$id");
                flash('success', $sp['message']);
            } elseif ($sp['message'] !== 'Procedure unavailable' && !$sp['ok']) {
                flash('error', $sp['message']);
            } else {
                $pdo->prepare("UPDATE withdrawals SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ?")
                    ->execute([$note, $id]);
                log_activity('withdrawal_reject', "Rejected withdrawal #$id");
                flash('success', 'Withdrawal rejected.');
            }
        } elseif ($action === 'paid') {
            $pdo->prepare("UPDATE withdrawals SET status = 'paid', admin_note = ?, processed_at = NOW() WHERE id = ?")
                ->execute([$note, $id]);
            flash('success', 'Marked as paid.');
        }
    }

    // Allow marking approved -> paid
    if ($wd && $wd['status'] === 'approved' && $action === 'paid') {
        $pdo->prepare("UPDATE withdrawals SET status = 'paid', admin_note = ?, processed_at = NOW() WHERE id = ?")
            ->execute([$note, $id]);
        flash('success', 'Marked as paid.');
    }

    header('Location: withdrawals.php');
    exit;
}

// Quick create sample withdrawal for testing (admin tool)
if (isset($_GET['create_test']) && isset($_GET['member_id'])) {
    $mid = (int) $_GET['member_id'];
    $amt = (float) ($_GET['amount'] ?? 500);
    $m = $pdo->prepare('SELECT wallet_balance FROM members WHERE id = ?');
    $m->execute([$mid]);
    $mem = $m->fetch();
    if ($mem && (float)$mem['wallet_balance'] >= $amt) {
        $pdo->prepare('INSERT INTO withdrawals (member_id, amount, payment_method, account_details, status) VALUES (?,?,?,?,?)')
            ->execute([$mid, $amt, 'Bank Transfer', 'Test account', 'pending']);
        flash('success', 'Test withdrawal request created.');
    } else {
        flash('error', 'Insufficient wallet balance.');
    }
    header('Location: withdrawals.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'paid'], true)) {
    $where[] = 'w.status = ?';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals w WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT w.*, m.full_name, m.member_id AS mid, m.wallet_balance
    FROM withdrawals w
    JOIN members m ON m.id = w.member_id
    WHERE $whereSql
    ORDER BY w.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();
$pendingSum = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status = 'pending'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Pending Requests</div><div class="value"><?= $pendingCount ?></div></div>
    <div class="stat-card"><div class="label">Pending Amount</div><div class="value"><?= currency($pendingSum) ?></div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Withdrawals (<?= $total ?>)</h2></div>
    <div class="panel-body">
        <form class="filters" method="get">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['pending','approved','rejected','paid'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8">No withdrawal requests.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td>
                        <?= e($r['full_name']) ?><br>
                        <small><?= e($r['mid']) ?> · Wallet: <?= currency((float)$r['wallet_balance']) ?></small>
                    </td>
                    <td><strong><?= currency((float)$r['amount']) ?></strong></td>
                    <td><?= e($r['payment_method'] ?? '—') ?></td>
                    <td style="max-width:160px;font-size:0.8rem"><?= e($r['account_details'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= date('d M Y H:i', strtotime($r['requested_at'])) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" style="display:inline-flex;gap:0.35rem;flex-wrap:wrap">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="text" name="admin_note" placeholder="Note" style="width:90px;padding:0.3rem;font-size:0.8rem;border:1px solid var(--border);border-radius:6px">
                            <button name="action" value="approve" class="btn btn-success btn-sm" data-confirm="Approve and deduct wallet?">Approve</button>
                            <button name="action" value="reject" class="btn btn-danger btn-sm" data-confirm="Reject this request?">Reject</button>
                        </form>
                        <?php elseif ($r['status'] === 'approved'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button name="action" value="paid" class="btn btn-primary btn-sm">Mark Paid</button>
                        </form>
                        <?php else: ?>
                        <small><?= e($r['admin_note'] ?? '') ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

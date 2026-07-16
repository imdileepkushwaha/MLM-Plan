<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Downline';

$search = trim($_GET['q'] ?? '');
$leg = $_GET['leg'] ?? 'all';
if (!in_array($leg, ['all', 'left', 'right'], true)) {
    $leg = 'all';
}

$root = null;
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT id, member_id, full_name, username, status, left_count, right_count, join_date FROM members WHERE member_id = ? OR username = ? LIMIT 1');
    $stmt->execute([$search, $search]);
    $root = $stmt->fetch() ?: null;
} else {
    $root = $pdo->query('SELECT id, member_id, full_name, username, status, left_count, right_count, join_date FROM members ORDER BY id ASC LIMIT 1')->fetch() ?: null;
    if ($root) {
        $search = $root['member_id'];
    }
}

/**
 * Collect placement downline (BFS) under a member.
 * @return array<int, array>
 */
function collect_downline(PDO $pdo, int $rootId, string $leg = 'all'): array
{
    $list = [];
    $queue = [];

    if ($leg === 'all' || $leg === 'left') {
        $queue[] = ['parent' => $rootId, 'position' => 'left', 'level' => 1, 'leg' => 'left'];
    }
    if ($leg === 'all' || $leg === 'right') {
        $queue[] = ['parent' => $rootId, 'position' => 'right', 'level' => 1, 'leg' => 'right'];
    }

    $stmtChild = $pdo->prepare('SELECT id FROM members WHERE placement_id = ? AND position = ? LIMIT 1');
    $stmtMember = $pdo->prepare("
        SELECT m.id, m.member_id, m.full_name, m.username, m.phone, m.status, m.position,
               m.left_count, m.right_count, m.join_date, m.wallet_balance,
               p.name AS package_name,
               s.member_id AS sponsor_mid, s.full_name AS sponsor_name
        FROM members m
        LEFT JOIN packages p ON p.id = m.package_id
        LEFT JOIN members s ON s.id = m.sponsor_id
        WHERE m.id = ?
    ");

    while ($queue) {
        $item = array_shift($queue);
        $stmtChild->execute([$item['parent'], $item['position']]);
        $childId = $stmtChild->fetchColumn();
        if (!$childId) {
            continue;
        }
        $stmtMember->execute([(int) $childId]);
        $member = $stmtMember->fetch();
        if (!$member) {
            continue;
        }
        $member['level'] = $item['level'];
        $member['leg'] = $item['leg'];
        $list[] = $member;

        $queue[] = ['parent' => (int) $member['id'], 'position' => 'left', 'level' => $item['level'] + 1, 'leg' => $item['leg']];
        $queue[] = ['parent' => (int) $member['id'], 'position' => 'right', 'level' => $item['level'] + 1, 'leg' => $item['leg']];
    }

    return $list;
}

$downline = [];
$leftCount = 0;
$rightCount = 0;
if ($root) {
    if ($leg === 'all') {
        $leftList = collect_downline($pdo, (int) $root['id'], 'left');
        $rightList = collect_downline($pdo, (int) $root['id'], 'right');
        $leftCount = count($leftList);
        $rightCount = count($rightList);
        $downline = array_merge($leftList, $rightList);
    } else {
        $downline = collect_downline($pdo, (int) $root['id'], $leg);
        $leftCount = $leg === 'left' ? count($downline) : count(collect_downline($pdo, (int) $root['id'], 'left'));
        $rightCount = $leg === 'right' ? count($downline) : count(collect_downline($pdo, (int) $root['id'], 'right'));
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel members-panel">
    <div class="panel-header members-toolbar">
        <div>
            <h2>Downline</h2>
            <p class="members-sub">Placement downline under a member</p>
        </div>
        <?php if ($root): ?>
        <a href="tree-view.php?root=<?= (int) $root['id'] ?>" class="btn btn-outline btn-sm">Tree View</a>
        <?php endif; ?>
    </div>
    <div class="panel-body members-filters">
        <form class="members-filter-form" method="get">
            <div class="form-group">
                <label>Member ID / Username</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(member_id_prefix() . str_pad('1', member_id_pad(), '0', STR_PAD_LEFT)) ?>">
            </div>
            <div class="form-group">
                <label>Leg</label>
                <select name="leg">
                    <option value="all" <?= $leg === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="left" <?= $leg === 'left' ? 'selected' : '' ?>>Left</option>
                    <option value="right" <?= $leg === 'right' ? 'selected' : '' ?>>Right</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Show Downline</button>
        </form>
    </div>

    <?php if ($root): ?>
    <div class="members-stats" style="padding:0 1.25rem 1rem">
        <div class="m-stat is-on">
            <span class="m-stat-ico blue"><?= icon_svg('view') ?></span>
            <div>
                <strong><?= e($root['member_id']) ?></strong>
                <span><?= e($root['full_name']) ?></span>
            </div>
        </div>
        <div class="m-stat">
            <span class="m-stat-ico green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg>
            </span>
            <div>
                <strong><?= $leftCount ?></strong>
                <span>Left Downline</span>
            </div>
        </div>
        <div class="m-stat">
            <span class="m-stat-ico orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg>
            </span>
            <div>
                <strong><?= $rightCount ?></strong>
                <span>Right Downline</span>
            </div>
        </div>
        <div class="m-stat">
            <span class="m-stat-ico red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </span>
            <div>
                <strong><?= count($downline) ?></strong>
                <span><?= $leg === 'all' ? 'Total' : ucfirst($leg) ?> Shown</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data members-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Level</th>
                    <th>Leg</th>
                    <th>Position</th>
                    <th>Sponsor</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$root): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <strong>No member found</strong>
                            <span>Search by Member ID or username.</span>
                        </div>
                    </td>
                </tr>
            <?php elseif (!$downline): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <strong>No downline</strong>
                            <span><?= e($root['full_name']) ?> has no members on this leg yet.</span>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($downline as $i => $m): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="member-cell">
                            <strong><a href="member-view.php?id=<?= (int) $m['id'] ?>"><?= e($m['full_name']) ?></a></strong>
                            <span><?= e($m['member_id']) ?> · <?= e($m['username']) ?></span>
                        </div>
                    </td>
                    <td><span class="level-badge">L<?= (int) $m['level'] ?></span></td>
                    <td><?= e(ucfirst($m['leg'])) ?></td>
                    <td><?= e(ucfirst($m['position'] ?? '—')) ?></td>
                    <td>
                        <?php if (!empty($m['sponsor_mid'])): ?>
                            <?= e($m['sponsor_mid']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['package_name'] ?? '—') ?></td>
                    <td><?= status_badge($m['status']) ?></td>
                    <td><?= e(date('d M Y', strtotime($m['join_date']))) ?></td>
                    <td>
                        <div class="action-icons">
                            <a href="tree-view.php?root=<?= (int) $m['id'] ?>" class="btn-icon" title="Tree"><?= icon_svg('package') ?></a>
                            <a href="downline.php?q=<?= urlencode($m['member_id']) ?>" class="btn-icon" title="Downline"><?= icon_svg('view') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

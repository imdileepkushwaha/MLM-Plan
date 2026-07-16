<?php
$pageTitle = 'My Treeview';
require_once __DIR__ . '/../includes/team.php';
require_once __DIR__ . '/includes/header.php';

$uid = (int) $user['id'];
$viewRootId = (int) ($_GET['root'] ?? $uid);
if ($viewRootId <= 0 || !team_is_under($pdo, $uid, $viewRootId)) {
    $viewRootId = $uid;
}

$root = team_get_member($pdo, $viewRootId);
$maxLevels = 4;
$isSelf = $viewRootId === $uid;

$parent = null;
if ($root && !empty($root['placement_id'])) {
    $pid = (int) $root['placement_id'];
    if ($pid > 0 && team_is_under($pdo, $uid, $pid)) {
        $parent = team_get_member($pdo, $pid);
    } elseif ($pid === $uid) {
        $parent = team_get_member($pdo, $uid);
    }
}
?>
<div class="up-page-head">
    <div>
        <h1>My Treeview</h1>
        <p>Binary placement tree — 4 levels. Click a member to open their subtree.</p>
    </div>
    <div class="team-head-actions">
        <?php if (!$isSelf): ?>
            <a href="my-treeview.php" class="up-btn up-btn-outline">Back to Me</a>
        <?php endif; ?>
        <?php if ($parent): ?>
            <a href="my-treeview.php?root=<?= (int) $parent['id'] ?>" class="up-btn up-btn-outline">Upline</a>
        <?php endif; ?>
        <a href="my-downline.php" class="up-btn up-btn-primary">Downline List</a>
    </div>
</div>

<?php if ($root): ?>
<div class="team-stats">
    <article class="team-stat g-gold">
        <span class="team-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <div>
            <span class="team-stat-label">Root</span>
            <strong class="is-sm"><?= e($root['username']) ?></strong>
            <small><?= e($root['member_id']) ?></small>
        </div>
    </article>
    <article class="team-stat g-green">
        <span class="team-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg></span>
        <div>
            <span class="team-stat-label">Left Count</span>
            <strong><?= (int) $root['left_count'] ?></strong>
        </div>
    </article>
    <article class="team-stat g-orange">
        <span class="team-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg></span>
        <div>
            <span class="team-stat-label">Right Count</span>
            <strong><?= (int) $root['right_count'] ?></strong>
        </div>
    </article>
    <article class="team-stat g-blue">
        <span class="team-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></span>
        <div>
            <span class="team-stat-label">Package</span>
            <strong class="is-sm"><?= e($root['package_name'] ?? '—') ?></strong>
        </div>
    </article>
</div>
<?php endif; ?>

<section class="team-card ut-panel">
    <div class="team-banner is-gold">
        <div class="team-banner-main">
            <span class="team-banner-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </span>
            <div>
                <span class="team-banner-kicker">Visual binary</span>
                <h2>Tree Board</h2>
            </div>
        </div>
        <div class="team-banner-legend">
            <span><i class="dot root"></i> Root</span>
            <span><i class="dot filled"></i> Member</span>
            <span><i class="dot noplan"></i> No Plan</span>
            <span><i class="dot vacant"></i> Vacant</span>
        </div>
    </div>
    <div class="ut-board">
        <div class="ut-scroll">
            <?php if ($root): ?>
            <div class="ut-tree">
                <ul>
                    <?php team_render_tree($pdo, $root, 0, $maxLevels, $uid); ?>
                </ul>
            </div>
            <?php else: ?>
                <div class="team-empty-state"><strong>Unable to load tree</strong></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

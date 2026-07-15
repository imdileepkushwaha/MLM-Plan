<?php
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['member_id'])) {
    header('Location: ../admin/direct-member-login.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT m.*, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON p.id = m.package_id
    WHERE m.id = ?
');
$stmt->execute([(int) $_SESSION['member_id']]);
$member = $stmt->fetch();

if (!$member) {
    unset($_SESSION['member_id'], $_SESSION['member_code'], $_SESSION['member_name'], $_SESSION['member_login_by_admin']);
    header('Location: ../admin/direct-member-login.php');
    exit;
}

$news = $pdo->query("SELECT title, content, published_at FROM news WHERE status='active' ORDER BY published_at DESC, id DESC LIMIT 5")->fetchAll();
$byAdmin = !empty($_SESSION['member_login_by_admin']);
$company = setting('company_name', 'Binary MLM');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | <?= e($company) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="app" style="display:block;margin:0">
    <header class="topbar" style="margin-left:0">
        <h1><?= e($company) ?> — Member Panel</h1>
        <div style="margin-left:auto;display:flex;gap:0.75rem;align-items:center">
            <span style="font-size:0.9rem;color:var(--ink-muted)"><?= e($member['full_name']) ?> (<?= e($member['member_id']) ?>)</span>
            <?php if ($byAdmin): ?>
            <a href="../admin/direct-member-login.php" class="btn btn-outline btn-sm">Back to Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout Member</a>
        </div>
    </header>
    <main class="content" style="max-width:1100px;margin:0 auto">
        <?php if ($byAdmin): ?>
        <div class="alert alert-info">You are viewing this dashboard as admin (Direct Member Login).</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">Left / Right</div><div class="value"><?= (int)$member['left_count'] ?> / <?= (int)$member['right_count'] ?></div></div>
            <div class="stat-card accent"><div class="label">Wallet</div><div class="value"><?= currency((float)$member['wallet_balance']) ?></div></div>
            <div class="stat-card"><div class="label">Total Earnings</div><div class="value"><?= currency((float)$member['total_earnings']) ?></div></div>
            <div class="stat-card"><div class="label">Package</div><div class="value" style="font-size:1.1rem"><?= e($member['package_name'] ?? '—') ?></div></div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Profile</h2></div>
            <div class="panel-body form-grid">
                <div><strong>Username:</strong> <?= e($member['username']) ?></div>
                <div><strong>Email:</strong> <?= e($member['email']) ?></div>
                <div><strong>Phone:</strong> <?= e($member['phone'] ?: '—') ?></div>
                <div><strong>Status:</strong> <span class="badge badge-<?= e($member['status']) ?>"><?= e($member['status']) ?></span></div>
                <div><strong>Joined:</strong> <?= date('d M Y', strtotime($member['join_date'])) ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Latest News</h2></div>
            <div class="panel-body">
                <?php if (!$news): ?>
                    <p style="color:var(--ink-muted)">No news published.</p>
                <?php else: foreach ($news as $n): ?>
                    <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">
                        <strong><?= e($n['title']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--ink-muted);margin:0.25rem 0"><?= $n['published_at'] ? date('d M Y', strtotime($n['published_at'])) : '' ?></div>
                        <p style="font-size:0.9rem"><?= nl2br(e($n['content'])) ?></p>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>

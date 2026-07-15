<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/password-reset.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

ensure_password_resets_table($pdo);

$company = setting('company_name', 'Binary MLM');
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$reset = $token !== '' ? pw_reset_find_valid($pdo, $token) : null;

if ($token === '' || !$reset) {
    $invalid = true;
} else {
    $invalid = false;
}

if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Re-validate token
    $reset = pw_reset_find_valid($pdo, $token);
    if (!$reset) {
        $invalid = true;
        $errors[] = 'This reset link is invalid or has expired.';
    }

    if (!$errors && !$invalid) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE members SET password = ? WHERE id = ?')
            ->execute([$hash, (int) $reset['mid']]);
        pw_reset_mark_used($pdo, (int) $reset['reset_id']);

        try {
            log_activity('password_reset_complete', 'Password reset for member #' . (int) $reset['mid']);
        } catch (Throwable $e) {
            // ignore
        }

        flash('success', 'Password updated successfully. Please sign in.');
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?= e($company) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/user.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/user.css') ?>">
</head>
<body class="ulog-body">
<div class="ulog">
    <section class="ulog-hero" aria-label="Brand">
        <div class="ulog-hero-bg" aria-hidden="true">
            <span class="ulog-orb ulog-orb-a"></span>
            <span class="ulog-orb ulog-orb-b"></span>
            <svg class="ulog-mesh" viewBox="0 0 600 760" fill="none" preserveAspectRatio="xMidYMid slice">
                <g stroke="currentColor" stroke-width="1.2" opacity="0.28">
                    <path d="M300 80 L180 220 L300 360 L420 220 Z"/>
                    <path d="M180 220 L80 380 L180 520"/>
                    <path d="M420 220 L520 380 L420 520"/>
                    <path d="M180 520 L300 660 L420 520"/>
                    <path d="M300 360 L180 520"/>
                    <path d="M300 360 L420 520"/>
                </g>
                <g fill="currentColor">
                    <circle cx="300" cy="80" r="7" class="ulog-node"/>
                    <circle cx="180" cy="220" r="6" class="ulog-node"/>
                    <circle cx="420" cy="220" r="6" class="ulog-node"/>
                    <circle cx="300" cy="360" r="8" class="ulog-node ulog-node-core"/>
                    <circle cx="80" cy="380" r="5" class="ulog-node"/>
                    <circle cx="520" cy="380" r="5" class="ulog-node"/>
                    <circle cx="180" cy="520" r="6" class="ulog-node"/>
                    <circle cx="420" cy="520" r="6" class="ulog-node"/>
                    <circle cx="300" cy="660" r="7" class="ulog-node"/>
                </g>
            </svg>
        </div>
        <div class="ulog-hero-content">
            <p class="ulog-brand"><?= e($company) ?></p>
            <h1>Choose a new<br>secure password.</h1>
            <p class="ulog-tagline">Link is valid for one hour and can be used only once.</p>
        </div>
        <p class="ulog-hero-foot">&copy; <?= date('Y') ?> <?= e($company) ?></p>
    </section>

    <section class="ulog-panel">
        <div class="ulog-panel-inner">
            <header class="ulog-head">
                <span class="ulog-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <div>
                    <h2>Reset password</h2>
                    <?php if (!$invalid): ?>
                        <p>Hi <?= e($reset['full_name'] ?? 'Member') ?> — set a new password for <strong>@<?= e($reset['username'] ?? '') ?></strong></p>
                    <?php else: ?>
                        <p>This reset link is invalid or expired</p>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($invalid): ?>
                <div class="up-alert up-alert-err">This password reset link is invalid or has expired. Please request a new one.</div>
                <a href="forgot-password.php" class="ulog-submit" style="text-decoration:none;margin-top:0.5rem">Request new link</a>
                <p class="ulog-panel-foot"><a href="login.php" class="ulog-link">← Back to Sign in</a></p>
            <?php else: ?>
                <?php foreach ($errors as $err): ?>
                    <div class="up-alert up-alert-err"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" class="ulog-form" autocomplete="off">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="up-field">
                        <label for="password">New password</label>
                        <div class="up-password-wrap">
                            <input type="password" id="password" name="password" placeholder="At least 6 characters" required autofocus>
                            <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="up-field">
                        <label for="password_confirm">Confirm password</label>
                        <div class="up-password-wrap">
                            <input type="password" id="password_confirm" name="password_confirm" placeholder="Re-enter password" required>
                            <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="ulog-submit">
                        <span>Update password</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <p class="ulog-panel-foot"><a href="login.php" class="ulog-link">← Back to Sign in</a></p>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="assets/js/user.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/user.js') ?>"></script>
</body>
</html>

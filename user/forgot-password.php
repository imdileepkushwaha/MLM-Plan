<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/password-reset.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

ensure_password_resets_table($pdo);

$company = setting('company_name', 'Binary MLM');
$error = '';
$done = false;
$devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');

    if ($login === '') {
        $error = 'Enter your username, email, or Member ID.';
    } else {
        // Always show success-style result for valid OR invalid to reduce enumeration timing only slightly;
        // still process real account when found.
        $member = pw_reset_find_member($pdo, $login);

        if ($member) {
            $token = pw_reset_create_token($pdo, (int) $member['id']);
            $url = pw_reset_url($token);
            $mailed = pw_reset_send_mail($member, $url);

            // Local / failed mail: keep one-time link in session for this browser
            if (!$mailed || pw_reset_is_local()) {
                $_SESSION['pw_reset_dev_link'] = $url;
            }

            try {
                log_activity('password_reset_request', 'Reset requested for member #' . (int) $member['id']);
            } catch (Throwable $e) {
                // ignore
            }
        }

        $done = true;
        $devLink = (string) ($_SESSION['pw_reset_dev_link'] ?? '');
        unset($_SESSION['pw_reset_dev_link']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?= e($company) ?></title>
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
            <h1>Reset access<br>in a few steps.</h1>
            <p class="ulog-tagline">We’ll send a secure link to the email on your member account.</p>
        </div>
        <p class="ulog-hero-foot">&copy; <?= date('Y') ?> <?= e($company) ?></p>
    </section>

    <section class="ulog-panel">
        <div class="ulog-panel-inner">
            <header class="ulog-head">
                <span class="ulog-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/></svg>
                </span>
                <div>
                    <h2>Forgot password</h2>
                    <p>Enter your account details to receive a reset link</p>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="up-alert up-alert-err"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($done): ?>
                <div class="up-alert up-alert-ok">
                    If an account matches, a password reset link has been sent to the registered email. The link expires in 1 hour.
                </div>
                <?php if ($devLink !== ''): ?>
                    <div class="upw-dev">
                        <strong>Local / mail unavailable</strong>
                        <p>Use this one-time link to continue:</p>
                        <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
                    </div>
                <?php endif; ?>
                <a href="login.php" class="ulog-submit" style="margin-top:1rem;text-decoration:none">Back to Sign in</a>
            <?php else: ?>
                <form method="post" class="ulog-form" autocomplete="off">
                    <div class="up-field">
                        <label for="login">Username / Email / Member ID</label>
                        <input type="text" id="login" name="login" value="<?= e($_POST['login'] ?? '') ?>" placeholder="e.g. member001 or you@email.com" required autofocus>
                    </div>

                    <button type="submit" class="ulog-submit">
                        <span>Send reset link</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            <?php endif; ?>

            <p class="ulog-panel-foot"><a href="login.php" class="ulog-link">← Back to Sign in</a></p>
        </div>
    </section>
</div>
<script src="assets/js/user.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/user.js') ?>"></script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    session_enforce_idle('user', 'login.php');
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Username / email and password are required.';
    } else {
        $stmt = $pdo->prepare('
            SELECT * FROM members
            WHERE (username = ? OR email = ? OR member_id = ?)
            LIMIT 1
        ');
        $stmt->execute([$login, $login, $login]);
        $member = $stmt->fetch();

        if ($member && password_verify($password, $member['password'])) {
            if (($member['status'] ?? '') === 'blocked') {
                $error = 'Your account has been blocked. Contact support.';
            } else {
                $_SESSION['user_id'] = (int) $member['id'];
                $_SESSION['user_name'] = $member['full_name'];
                $_SESSION['user_code'] = $member['member_id'];
                session_touch('user');
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$company = setting('company_name', 'Binary MLM');
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | <?= e($company) ?></title>
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
            <h1>Grow your network.<br>Track every reward.</h1>
            <p class="ulog-tagline">Secure member access to team, wallet, and withdrawals.</p>
        </div>
        <p class="ulog-hero-foot">&copy; <?= date('Y') ?> <?= e($company) ?></p>
    </section>

    <section class="ulog-panel">
        <div class="ulog-panel-inner">
            <header class="ulog-head">
                <span class="ulog-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l4 8h8l-6.5 5.2L20 22l-8-5-8 5 1.5-6.8L0 10h8z"/></svg>
                </span>
                <div>
                    <h2>Sign in</h2>
                    <p>Enter your member credentials to continue</p>
                </div>
            </header>

            <?php if ($flash):
                $ftype = $flash['type'] === 'success' ? 'ok' : ($flash['type'] === 'error' ? 'err' : 'info');
            ?>
                <div class="up-alert up-alert-<?= e($ftype) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="up-alert up-alert-err"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="ulog-form" autocomplete="off">
                <div class="up-field">
                    <label for="login">Username / Email / Member ID</label>
                    <input type="text" id="login" name="login" value="<?= e($_POST['login'] ?? '') ?>" placeholder="e.g. member001 or you@email.com" required autofocus>
                </div>

                <div class="up-field">
                    <div class="ulog-label-row">
                        <label for="password">Password</label>
                        <a href="forgot-password.php" class="ulog-forgot">Forgot password?</a>
                    </div>
                    <div class="up-password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="ulog-submit">
                    <span>Sign in to dashboard</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="ulog-panel-foot">Don't have an account? <a href="register.php" class="ulog-link">Create Account</a></p>
            <p class="ulog-panel-foot soft">Need help? Contact your upline or support.</p>
        </div>
    </section>
</div>
<script src="assets/js/user.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/user.js') ?>"></script>
</body>
</html>

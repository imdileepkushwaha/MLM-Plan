<?php
require_once __DIR__ . '/includes/auth.php';

$maintenanceOn = is_maintenance_mode();
$wasSignedOut = false;

// If maintenance just turned on, kick any existing member session immediately
if ($maintenanceOn && !empty($_SESSION['user_id'])) {
    user_logout_session();
    $wasSignedOut = true;
    if (empty($_SESSION['flash'])) {
        flash('error', 'Portal is under maintenance. You have been signed out. Please try again later.');
    }
}

if (!$maintenanceOn && !empty($_SESSION['user_id'])) {
    session_enforce_idle('user', 'login.php');
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hard block — even if someone hides the popup via DevTools
    if ($maintenanceOn || is_maintenance_mode()) {
        clear_setting_cache('maintenance_mode');
        $error = 'Portal is under maintenance. Login is temporarily disabled.';
        $maintenanceOn = true;
    } else {
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

            clear_setting_cache('maintenance_mode');
            if (is_maintenance_mode()) {
                $error = 'Portal is under maintenance. Login is temporarily disabled.';
                $maintenanceOn = true;
            } elseif ($member && password_verify($password, $member['password'])) {
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
}

$company = setting('company_name', 'Binary MLM');
$flash = get_flash();
$signedOutMsg = '';
if ($flash && $flash['type'] === 'error' && stripos((string) $flash['message'], 'maintenance') !== false) {
    $signedOutMsg = (string) $flash['message'];
    $wasSignedOut = true;
    $flash = null;
}
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
<body class="ulog-body<?= $maintenanceOn ? ' is-maintenance' : '' ?>">
<div class="ulog"<?= $maintenanceOn ? ' aria-hidden="true"' : '' ?>>
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

            <?php if ($error && !$maintenanceOn): ?>
                <div class="up-alert up-alert-err"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="ulog-form" autocomplete="off"<?= $maintenanceOn ? ' inert' : '' ?>>
                <div class="up-field">
                    <label for="login">Username / Email / Member ID</label>
                    <input type="text" id="login" name="login" value="<?= e($_POST['login'] ?? '') ?>" placeholder="e.g. member001 or you@email.com" required<?= $maintenanceOn ? ' disabled' : ' autofocus' ?>>
                </div>

                <div class="up-field">
                    <div class="ulog-label-row">
                        <label for="password">Password</label>
                        <a href="forgot-password.php" class="ulog-forgot">Forgot password?</a>
                    </div>
                    <div class="up-password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required<?= $maintenanceOn ? ' disabled' : '' ?>>
                        <button type="button" class="up-eye" data-password-toggle aria-label="Show password"<?= $maintenanceOn ? ' disabled' : '' ?>>
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="ulog-submit"<?= $maintenanceOn ? ' disabled' : '' ?>>
                    <span>Sign in to dashboard</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="ulog-panel-foot">Don't have an account? <a href="register.php" class="ulog-link">Create Account</a></p>
            <p class="ulog-panel-foot soft">Need help? Contact your upline or support.</p>
        </div>
    </section>
</div>

<?php if ($maintenanceOn): ?>
<div class="maint-overlay" role="dialog" aria-modal="true" aria-labelledby="maintTitle" data-locked="1">
    <div class="maint-backdrop" aria-hidden="true"></div>
    <div class="maint-modal">
        <div class="maint-glow" aria-hidden="true"></div>
        <div class="maint-icon-wrap" aria-hidden="true">
            <span class="maint-ring"></span>
            <span class="maint-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>
                </svg>
            </span>
        </div>
        <header class="maint-head">
            <p class="maint-kicker">Temporarily offline</p>
            <h2 id="maintTitle">Under maintenance</h2>
            <p class="maint-sub">Member portal is temporarily unavailable</p>
        </header>

        <div class="maint-box is-warn">
            <span class="maint-box-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </span>
            <div>
                <strong>Maintenance mode is ON</strong>
                <span>Login is disabled right now. Please check back later.</span>
            </div>
        </div>

        <?php if ($wasSignedOut || $signedOutMsg !== ''): ?>
        <div class="maint-box is-err">
            <span class="maint-box-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </span>
            <div>
                <strong>Signed out</strong>
                <span><?= e($signedOutMsg !== '' ? $signedOutMsg : 'Portal is under maintenance. You have been signed out. Please try again later.') ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="maint-box is-err">
            <span class="maint-box-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </span>
            <div>
                <strong>Login blocked</strong>
                <span><?= e($error) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    var overlay = document.querySelector('.maint-overlay');
    if (!overlay) return;

    document.querySelectorAll('.ulog-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
        }, true);
    });

    var observer = new MutationObserver(function () {
        if (!document.querySelector('.maint-overlay[data-locked="1"]')) {
            document.body.classList.add('is-maintenance');
            if (!document.querySelector('.maint-overlay')) {
                document.body.appendChild(overlay);
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>
<?php endif; ?>

<script src="assets/js/user.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/user.js') ?>"></script>
</body>
</html>

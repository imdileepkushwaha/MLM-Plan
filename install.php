<?php
/**
 * Binary MLM - One-time Installer
 * Run once, then DELETE this file.
 * Safe to re-run if tables already exist (only resets admin password).
 */
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'binarymlm_db';
$adminUser = 'admin';
$adminEmail = 'admin@binarymlm.com';
$adminPass = 'admin123';

$error = '';
$isInstalled = false;
$setupMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $dbName = trim($_POST['db_name'] ?? 'binarymlm_db');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@binarymlm.com');

    try {
        // Attempt to create database if it does not already exist
        try {
            $pdoRoot = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $cleanDbName = str_replace('`', '``', $dbName);
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$cleanDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            unset($pdoRoot);
        } catch (Throwable $ignore) {
            // Server might restrict root connection without database or lacks CREATE privilege
        }

        $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        require_once __DIR__ . '/includes/schema_setup.php';
        $setup = mlm_run_schema_setup($pdo);

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $check = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $check->execute([$adminUser]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare('UPDATE admins SET email = ?, password = ?, full_name = ? WHERE id = ?');
            $stmt->execute([$adminEmail, $hash, 'Super Admin', $existing['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE admins SET username = ?, email = ?, password = ?, full_name = ? WHERE id = 1');
            $stmt->execute([$adminUser, $adminEmail, $hash, 'Super Admin']);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare('INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)')
                    ->execute([$adminUser, $adminEmail, $hash, 'Super Admin']);
            }
        }

        try {
            $memberHash = password_hash('member123', PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE members SET password = ? WHERE id = 1')->execute([$memberHash]);
        } catch (Throwable $e) {
            // Optional sample member
        }

        // Save DB credentials if db_admin helper exists
        if (file_exists(__DIR__ . '/includes/db_admin.php')) {
            try {
                require_once __DIR__ . '/includes/db_admin.php';
                $existingCreds = db_admin_load_credentials();
                $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $isLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $hostName);
                if ($isLocal) {
                    $existingCreds['offline']['host'] = $host;
                    $existingCreds['offline']['name'] = $dbName;
                    $existingCreds['offline']['user'] = $user;
                    $existingCreds['offline']['pass'] = $pass;
                } else {
                    $existingCreds['online']['host'] = $host;
                    $existingCreds['online']['name'] = $dbName;
                    $existingCreds['online']['user'] = $user;
                    $existingCreds['online']['pass'] = $pass;
                }
                db_admin_save_credentials($existingCreds);
            } catch (Throwable $e) {
                // Non-fatal if config already hardcoded
            }
        }

        $setupMessage = $setup['message'] ?? 'Database and tables created successfully.';
        $isInstalled = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Brand & Logo resolution
$companyName = 'Binary MLM';
$logoUrl = '';
if (file_exists(__DIR__ . '/assets/images/logo.png')) {
    $logoUrl = 'assets/images/logo.png';
} elseif (file_exists(__DIR__ . '/assets/images/logo.jpg')) {
    $logoUrl = 'assets/images/logo.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install &middot; <?= htmlspecialchars($companyName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --ink: #1e293b;
            --muted: #64748b;
            --line: #f1e2e7;
            --brand: #e11d48;
            --brand-deep: #be123c;
            --brand-light: #fb7185;
            --indigo: #4f46e5;
            --violet: #7c3aed;
            --rose: #f43f5e;
            --navy: #0f172a;
            --field-bg: #fff8f9;
            --btn: linear-gradient(135deg, #e11d48 0%, #f43f5e 50%, #fb7185 100%);
            --btn-alt: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --btn-shadow: 0 12px 28px rgba(225, 29, 72, 0.32);
            --page: linear-gradient(165deg, #fff1f2 0%, #fff7ed 24%, #fdf4ff 52%, #f0fdfa 76%, #eef2ff 100%);
            --shadow: 0 24px 56px rgba(190, 18, 60, 0.14), 0 8px 24px rgba(15, 23, 42, 0.06);
            --aside-bg: linear-gradient(160deg, #be123c 0%, #e11d48 42%, #4f46e5 100%);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: Poppins, sans-serif;
            color: var(--ink);
            background: var(--page);
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 10% 16%, rgba(225, 29, 72, 0.18), transparent 32%),
                radial-gradient(circle at 90% 12%, rgba(79, 70, 229, 0.16), transparent 30%),
                radial-gradient(circle at 75% 85%, rgba(124, 58, 237, 0.16), transparent 35%);
        }
        .wrap {
            position: relative;
            z-index: 1;
            width: min(1080px, 100%);
            margin: auto;
        }
        .shell {
            display: grid;
            grid-template-columns: 0.92fr 1.18fr;
            min-height: 640px;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .aside {
            padding: 42px 36px;
            background: var(--aside-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .aside::after {
            content: "";
            position: absolute;
            bottom: 0;
            right: 0;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .aside-logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
            border-radius: 50%;
            background: #fff;
            padding: 6px;
            box-shadow: 0 0 0 6px rgba(255, 255, 255, 0.2);
            margin-bottom: 22px;
        }
        .aside-mark {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff;
            color: var(--brand-deep);
            font-size: 28px;
            margin-bottom: 22px;
            box-shadow: 0 0 0 6px rgba(255, 255, 255, 0.2);
        }
        .aside h1 {
            font-family: "Playfair Display", serif;
            font-size: 34px;
            line-height: 1.15;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }
        .aside p { color: rgba(255, 255, 255, 0.9); font-size: 14px; line-height: 1.6; }
        .steps { margin-top: 36px; display: grid; gap: 14px; }
        .step {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }
        .step i {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff;
            color: var(--brand-deep);
            font-size: 12px;
            flex-shrink: 0;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }
        .step strong { display: block; font-size: 14px; }
        .step span { font-size: 12px; color: rgba(255, 255, 255, 0.82); }
        .aside-note {
            margin-top: auto;
            padding-top: 28px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
        }
        .panel {
            padding: 40px 38px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .panel h2 {
            font-family: "Playfair Display", serif;
            font-size: 28px;
            color: var(--navy);
            letter-spacing: -0.01em;
        }
        .lead { color: var(--muted); font-size: 14px; margin: 6px 0 22px; }
        .alert {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.45;
        }
        .alert-error {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
        }
        .alert-ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .block {
            grid-column: 1 / -1;
            margin: 6px 0 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        label { font-size: 13px; font-weight: 600; color: var(--navy); }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font: 14px/1.4 Poppins, sans-serif;
            background: var(--field-bg);
            color: var(--ink);
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        input:focus {
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(225, 29, 72, 0.14);
        }
        .pass { position: relative; }
        .pass input { padding-right: 44px; }
        .pass-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--muted);
            width: 32px;
            height: 32px;
            cursor: pointer;
            border-radius: 8px;
            display: grid;
            place-items: center;
            transition: color .15s ease, background .15s ease;
        }
        .pass-toggle:hover { color: var(--brand); background: #ffe4e6; }
        .actions { margin-top: 22px; display: grid; gap: 10px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 48px;
            padding: 12px 20px;
            border: 0;
            border-radius: 12px;
            font: 600 15px Poppins, sans-serif;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            transition: filter .15s ease, transform .15s ease;
        }
        .btn-main {
            background: var(--btn);
            box-shadow: var(--btn-shadow);
        }
        .btn-alt { background: var(--btn-alt); }
        .btn-ghost {
            background: #fff;
            color: var(--navy);
            border: 1px solid var(--line);
        }
        .btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .hint { font-size: 12px; color: var(--muted); text-align: center; }
        .done { text-align: center; padding: 12px 0 4px; }
        .done-ico {
            width: 76px;
            height: 76px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            font-size: 30px;
            box-shadow: 0 12px 28px rgba(16, 185, 129, 0.28);
        }
        .creds {
            text-align: left;
            background: #fff8f9;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px 18px;
            margin: 18px 0;
            font-size: 14px;
        }
        .creds dt { color: var(--muted); font-size: 12px; margin-top: 10px; }
        .creds dt:first-child { margin-top: 0; }
        .creds dd { font-weight: 600; color: var(--navy); }
        .warn {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
            text-align: left;
            margin-bottom: 16px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .btn-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
        @media (max-width: 860px) {
            .wrap { margin: 16px auto 28px; }
            .shell { grid-template-columns: 1fr; }
            .aside { padding: 28px 22px; }
            .panel { padding: 26px 20px 24px; }
            .grid, .btn-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="shell">
        <aside class="aside">
            <?php if (!empty($logoUrl)): ?>
                <img class="aside-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($companyName) ?>">
            <?php else: ?>
                <div class="aside-mark"><i class="fa-solid fa-cubes-stacked"></i></div>
            <?php endif; ?>
            <h1><?= htmlspecialchars($companyName) ?></h1>
            <p>One-time installer for the database, tables and your first admin account.</p>
            <div class="steps">
                <div class="step">
                    <i>1</i>
                    <div><strong>Connect database</strong><span>Host, name and MySQL user</span></div>
                </div>
                <div class="step">
                    <i>2</i>
                    <div><strong>Create admin</strong><span>Login used after install</span></div>
                </div>
                <div class="step">
                    <i>3</i>
                    <div><strong>Delete this file</strong><span>Remove install.php for safety</span></div>
                </div>
            </div>
            <p class="aside-note">Safe to re-run if tables already exist. It only resets the admin password you enter here.</p>
        </aside>

        <section class="panel">
            <?php if ($isInstalled): ?>
                <div class="done">
                    <div class="done-ico"><i class="fa-solid fa-check"></i></div>
                    <h2>Installation Complete!</h2>
                    <p class="lead"><?= htmlspecialchars($setupMessage) ?></p>

                    <div class="warn">
                        <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;"></i>
                        <div><strong>Security Notice:</strong> Please delete or rename <code>install.php</code> from your server immediately.</div>
                    </div>

                    <dl class="creds">
                        <dt>Database Name</dt>
                        <dd><?= htmlspecialchars($dbName) ?></dd>
                        <dt>Admin Username</dt>
                        <dd><?= htmlspecialchars($adminUser) ?></dd>
                        <dt>Admin Email</dt>
                        <dd><?= htmlspecialchars($adminEmail) ?></dd>
                        <dt>Admin Password</dt>
                        <dd>•••••••• (Password configured during installation)</dd>
                    </dl>

                    <div class="btn-row">
                        <a href="admin/login.php" class="btn btn-main"><i class="fa-solid fa-arrow-right-to-bracket"></i> Admin Login</a>
                        <a href="index.php" class="btn btn-ghost"><i class="fa-solid fa-house"></i> View Website</a>
                    </div>
                </div>
            <?php else: ?>
                <h2>Setup your platform</h2>
                <p class="lead">Enter MySQL details and the first admin login. Database <strong><?= htmlspecialchars($dbName) ?></strong> will be initialized.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation" style="margin-top:2px;"></i>
                        <div><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="grid">
                        <div class="block"><i class="fa-solid fa-database"></i> Database</div>
                        <div class="field">
                            <label for="db_host">Host</label>
                            <input id="db_host" type="text" name="db_host" value="<?= htmlspecialchars($host) ?>" required>
                        </div>
                        <div class="field">
                            <label for="db_name">Database name</label>
                            <input id="db_name" type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>" required>
                        </div>
                        <div class="field">
                            <label for="db_user">Username</label>
                            <input id="db_user" type="text" name="db_user" value="<?= htmlspecialchars($user) ?>" required>
                        </div>
                        <div class="field">
                            <label for="db_pass">Password</label>
                            <div class="pass">
                                <input id="db_pass" type="password" name="db_pass" value="<?= htmlspecialchars($pass) ?>">
                                <button class="pass-toggle" type="button" data-target="db_pass" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="block"><i class="fa-solid fa-user-gear"></i> Admin account</div>
                        <div class="field">
                            <label for="admin_user">Username</label>
                            <input id="admin_user" type="text" name="admin_user" value="<?= htmlspecialchars($adminUser) ?>" required>
                        </div>
                        <div class="field">
                            <label for="admin_email">Email</label>
                            <input id="admin_email" type="email" name="admin_email" value="<?= htmlspecialchars($adminEmail) ?>" required>
                        </div>
                        <div class="field full">
                            <label for="admin_pass">Password</label>
                            <div class="pass">
                                <input id="admin_pass" type="password" name="admin_pass" value="<?= htmlspecialchars($adminPass) ?>" required>
                                <button class="pass-toggle" type="button" data-target="admin_pass" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-main"><i class="fa-solid fa-wand-magic-sparkles"></i> Install now</button>
                        <p class="hint">After install, sign in and delete this page.</p>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
<script>
document.querySelectorAll('.pass-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-target'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
    });
});
</script>
</body>
</html>

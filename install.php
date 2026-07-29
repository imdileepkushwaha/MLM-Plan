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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $dbName = trim($_POST['db_name'] ?? 'binarymlm_db');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@binarymlm.com');

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
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
            // optional sample member
        }

        $success = 'Installation complete! ' . htmlspecialchars($setup['message'])
            . '<br>Login: <strong>' . htmlspecialchars($adminUser) . '</strong> / your password.'
            . '<br><strong>Delete install.php now.</strong>';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Binary MLM</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Binary MLM Install</h1>
    <p class="auth-sub">Setup database &amp; admin account</p>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?><br><br><a href="admin/login.php" class="btn btn-primary">Go to Admin Login</a></div>
    <?php else: ?>
    <form method="post">
        <label>DB Host</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($host) ?>" required>
        <label>DB Name</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>" required>
        <label>DB User</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($user) ?>" required>
        <label>DB Password</label>
        <input type="password" name="db_pass" value="<?= htmlspecialchars($pass) ?>">
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:1rem 0">
        <label>Admin Username</label>
        <input type="text" name="admin_user" value="admin" required>
        <label>Admin Email</label>
        <input type="email" name="admin_email" value="admin@binarymlm.com" required>
        <label>Admin Password</label>
        <input type="password" name="admin_pass" value="admin123" required>
        <button type="submit" class="btn btn-primary btn-block">Install Now</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>

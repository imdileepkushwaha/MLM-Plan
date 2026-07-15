<?php
/**
 * Binary MLM - One-time Installer
 * Run once: http://localhost/your-folder/install.php
 * Then DELETE this file.
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
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sqlFile = __DIR__ . '/sql/binarymlm_db.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('SQL file not found: sql/binarymlm_db.sql');
        }

        $sql = file_get_contents($sqlFile);
        // Replace password placeholder after import
        $pdo->exec($sql);

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admins SET username = ?, email = ?, password = ?, full_name = ? WHERE id = 1');
        $stmt->execute([$adminUser, $adminEmail, $hash, 'Super Admin']);

        $memberHash = password_hash('member123', PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE members SET password = ? WHERE id = 1')->execute([$memberHash]);

        // Update config file
        $configPath = __DIR__ . '/config/database.php';
        $config = file_get_contents($configPath);
        $config = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/", "define('DB_HOST', '" . addslashes($host) . "')", $config);
        $config = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/", "define('DB_NAME', '" . addslashes($dbName) . "')", $config);
        $config = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', '" . addslashes($user) . "')", $config);
        $config = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '" . addslashes($pass) . "')", $config);
        file_put_contents($configPath, $config);

        $success = "Installation complete! Login: <strong>$adminUser</strong> / your password. Delete install.php now.";
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

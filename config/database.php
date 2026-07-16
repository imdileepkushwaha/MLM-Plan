<?php
/**
 * Database Configuration - Binary MLM
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'binarymlm_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Binary MLM Admin');
define('APP_URL', 'http://localhost/mlm-plan');
define('BASE_PATH', dirname(__DIR__));

date_default_timezone_set('Asia/Kolkata');

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

if (session_status() === PHP_SESSION_NONE) {
    // Keep PHP session cookie alive long enough; idle logout is enforced in-app (15 min).
    ini_set('session.gc_maxlifetime', (string) max(1800, (int) ini_get('session.gc_maxlifetime')));
    session_start();
}

/** Idle timeout in seconds (15 minutes). */
define('SESSION_IDLE_TIMEOUT', 15 * 60);

/**
 * Record last activity for a session scope (user|admin|member).
 */
function session_touch(string $scope): void
{
    $_SESSION[$scope . '_last_activity'] = time();
}

/**
 * Clear keys for a session scope after timeout / logout.
 */
function session_clear_scope(string $scope): void
{
    $map = [
        'user' => ['user_id', 'user_name', 'user_code', 'user_last_activity'],
        'admin' => ['admin_id', 'admin_name', 'admin_username', 'admin_last_activity'],
        'member' => [
            'member_id', 'member_code', 'member_name',
            'member_login_by_admin', 'member_login_admin_id', 'member_last_activity',
        ],
    ];
    foreach ($map[$scope] ?? [] as $key) {
        unset($_SESSION[$key]);
    }
}

/**
 * Enforce idle timeout for an authenticated scope.
 * Call after confirming the primary session id key exists.
 */
function session_enforce_idle(string $scope, string $loginUrl): void
{
    $activityKey = $scope . '_last_activity';
    $now = time();
    $last = (int) ($_SESSION[$activityKey] ?? 0);

    if ($last > 0 && ($now - $last) > SESSION_IDLE_TIMEOUT) {
        session_clear_scope($scope);
        flash('error', 'Your session expired after 15 minutes of inactivity. Please sign in again.');
        header('Location: ' . $loginUrl);
        exit;
    }

    $_SESSION[$activityKey] = $now;
}

function setting(string $key, string $default = ''): string
{
    global $pdo;
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['setting_value'] : $default;
    }
    return $cache[$key];
}

function currency(float $amount): string
{
    $symbol = setting('currency_symbol', '₹');
    $code = setting('currency', 'INR');

    // Prefer HTML entity for INR so encoding never breaks (avoids Ôé╣)
    if (strtoupper($code) === 'INR' || $symbol === '₹' || $symbol === 'Rs' || $symbol === 'Rs.' || preg_match('/Ôé╣|Ã¢/', $symbol)) {
        return '&#8377;' . number_format($amount, 2);
    }

    return htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') . number_format($amount, 2);
}

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
    session_enforce_idle('admin', 'login.php');
}

function log_activity(string $action, string $details = ''): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['admin_id'] ?? null,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function generate_member_id(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM members');
    $count = (int) $stmt->fetch()['cnt'] + 1;
    return 'MLM' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
}

require_once __DIR__ . '/../includes/utility.php';


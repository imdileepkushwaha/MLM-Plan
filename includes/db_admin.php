<?php
/**
 * Super Admin DB credentials, connection resolve, backup & restore helpers.
 */

function db_admin_credentials_path(): string
{
    return dirname(__DIR__) . '/config/db-credentials.php';
}

/** @return array{mode:string,online:array<string,string>,offline:array<string,string>} */
function db_admin_default_credentials(): array
{
    return [
        'mode' => 'auto',
        'online' => [
            'host' => 'localhost',
            'port' => '3306',
            'name' => 'mlmplan_db',
            'user' => 'mlmplan_db',
            'pass' => 'Tf&pW4vhzxMf2%6j',
        ],
        'offline' => [
            'host' => 'localhost',
            'port' => '3306',
            'name' => 'binarymlm_db',
            'user' => 'root',
            'pass' => '',
        ],
    ];
}

/**
 * @param array<string,mixed> $row
 * @return array{host:string,port:string,name:string,user:string,pass:string}
 */
function db_admin_normalize_side(array $row, array $fallback): array
{
    $port = preg_replace('/\D+/', '', (string) ($row['port'] ?? $fallback['port'])) ?: '3306';
    return [
        'host' => trim((string) ($row['host'] ?? $fallback['host'])) ?: 'localhost',
        'port' => $port,
        'name' => trim((string) ($row['name'] ?? $fallback['name'])),
        'user' => trim((string) ($row['user'] ?? $fallback['user'])),
        'pass' => (string) ($row['pass'] ?? $fallback['pass']),
    ];
}

/** @return array{mode:string,online:array<string,string>,offline:array<string,string>} */
function db_admin_load_credentials(): array
{
    $defaults = db_admin_default_credentials();
    $path = db_admin_credentials_path();
    if (!is_file($path)) {
        return $defaults;
    }

    $data = null;
    try {
        $data = include $path;
    } catch (Throwable $e) {
        return $defaults;
    }
    if (!is_array($data)) {
        return $defaults;
    }

    $mode = strtolower((string) ($data['mode'] ?? 'auto'));
    if (!in_array($mode, ['auto', 'online', 'offline'], true)) {
        $mode = 'auto';
    }

    return [
        'mode' => $mode,
        'online' => db_admin_normalize_side(is_array($data['online'] ?? null) ? $data['online'] : [], $defaults['online']),
        'offline' => db_admin_normalize_side(is_array($data['offline'] ?? null) ? $data['offline'] : [], $defaults['offline']),
    ];
}

/**
 * @param array{mode?:string,online?:array<string,mixed>,offline?:array<string,mixed>} $creds
 * @return array{ok:bool,message:string}
 */
function db_admin_save_credentials(array $creds): array
{
    $defaults = db_admin_default_credentials();
    $mode = strtolower((string) ($creds['mode'] ?? 'auto'));
    if (!in_array($mode, ['auto', 'online', 'offline'], true)) {
        $mode = 'auto';
    }

    $online = db_admin_normalize_side(is_array($creds['online'] ?? null) ? $creds['online'] : [], $defaults['online']);
    $offline = db_admin_normalize_side(is_array($creds['offline'] ?? null) ? $creds['offline'] : [], $defaults['offline']);

    if ($online['name'] === '' || $offline['name'] === '') {
        return ['ok' => false, 'message' => 'Database name is required for both online and offline.'];
    }

    $export = var_export([
        'mode' => $mode,
        'online' => $online,
        'offline' => $offline,
    ], true);

    $php = "<?php\n/**\n * DB credentials — managed by Super Admin → Settings → Online & Offline DB.\n * Do not commit real production secrets to public repos.\n */\nreturn " . $export . ";\n";

    $path = db_admin_credentials_path();
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'message' => 'config/ folder is not writable.'];
    }

    if (file_put_contents($path, $php, LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Could not write config/db-credentials.php.'];
    }

    return ['ok' => true, 'message' => 'Database settings saved.'];
}

/**
 * Ordered connection attempts for the current host + mode.
 *
 * @return list<array{side:string,host:string,port:string,name:string,user:string,pass:string}>
 */
function db_admin_connection_attempts(bool $isLocal, ?array $creds = null): array
{
    $creds = $creds ?? db_admin_load_credentials();
    $mode = $creds['mode'];
    $online = $creds['online'] + ['side' => 'online'];
    $offline = $creds['offline'] + ['side' => 'offline'];
    $online['side'] = 'online';
    $offline['side'] = 'offline';

    if ($mode === 'online') {
        return [$online];
    }
    if ($mode === 'offline') {
        return [$offline];
    }

    // Auto: local prefers offline; live prefers online; then fall back.
    return $isLocal ? [$offline, $online] : [$online, $offline];
}

/**
 * @param array{host:string,port?:string,name:string,user:string,pass:string} $cfg
 * @return array{ok:bool,message:string,pdo:?PDO}
 */
function db_admin_try_pdo(array $cfg): array
{
    $host = $cfg['host'];
    $port = $cfg['port'] ?? '3306';
    $name = $cfg['name'];
    $user = $cfg['user'];
    $pass = $cfg['pass'];
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return ['ok' => true, 'message' => 'Connected to ' . $host . ' / ' . $name, 'pdo' => $pdo];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage(), 'pdo' => null];
    }
}

/**
 * @return array{ok:bool,message:string}
 */
function db_admin_test_side(string $side, array $postedSide, array $existingSide): array
{
    $side = $side === 'online' ? 'online' : 'offline';
    $cfg = db_admin_normalize_side($postedSide, $existingSide);
    if (!array_key_exists('pass', $postedSide) || trim((string) $postedSide['pass']) === '') {
        $cfg['pass'] = (string) $existingSide['pass'];
    } else {
        $cfg['pass'] = (string) $postedSide['pass'];
    }

    $try = db_admin_try_pdo($cfg);
    if ($try['ok']) {
        return ['ok' => true, 'message' => ucfirst($side) . ' connection OK — ' . $cfg['host'] . ' / ' . $cfg['name']];
    }
    return ['ok' => false, 'message' => ucfirst($side) . ' connection failed: ' . $try['message']];
}

function db_admin_active_label(): string
{
    $side = defined('DB_ACTIVE_SIDE') ? (string) DB_ACTIVE_SIDE : 'unknown';
    return $side === 'online' ? 'Online Database' : ($side === 'offline' ? 'Offline Database' : 'Database');
}

function db_admin_table_count(PDO $pdo): int
{
    try {
        return count($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    } catch (Throwable $e) {
        return 0;
    }
}

function db_admin_backup_marker(): string
{
    return 'BINARYMLM_BACKUP_META';
}

/**
 * Stream a full SQL dump to the browser. Does not return on success.
 */
function db_admin_stream_backup(PDO $pdo, string $dbName): void
{
    $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($dbName)) ?: 'database';
    $stamp = date('Ymd-His');
    $filename = $safeName . '-backup-' . $stamp . '.sql';

    $tables = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
        $tables[] = (string) $row[0];
    }

    if (function_exists('feature_save')) {
        try {
            feature_save($pdo, 'db_backup_last_download', date('Y-m-d H:i:s'));
            if (function_exists('clear_setting_cache')) {
                clear_setting_cache('db_backup_last_download');
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $out = static function (string $line): void {
        echo $line;
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    };

    $meta = json_encode([
        'app' => 'binarymlm',
        'marker' => db_admin_backup_marker(),
        'database' => $dbName,
        'created' => date('c'),
        'tables' => count($tables),
    ], JSON_UNESCAPED_SLASHES);

    $out("-- " . db_admin_backup_marker() . " " . $meta . "\n");
    $out("-- Binary MLM SQL backup\n");
    $out("-- Generated: " . date('Y-m-d H:i:s') . "\n");
    $out("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
        if (!$create) {
            continue;
        }
        $out("-- ----------------------------\n");
        $out("-- Table structure `{$table}`\n");
        $out("-- ----------------------------\n");
        $out("DROP TABLE IF EXISTS `{$table}`;\n");
        $out($create[1] . ";\n\n");

        $out("-- Dumping data for `{$table}`\n");
        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`', PDO::FETCH_ASSOC);
        $rowCount = 0;
        $buffer = [];
        while ($row = $stmt->fetch()) {
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $buffer[] = '(' . implode(',', $vals) . ')';
            $rowCount++;
            if (count($buffer) >= 80) {
                $out('INSERT INTO `' . $table . '` VALUES ' . implode(",\n", $buffer) . ";\n");
                $buffer = [];
            }
        }
        if ($buffer) {
            $out('INSERT INTO `' . $table . '` VALUES ' . implode(",\n", $buffer) . ";\n");
        }
        $out("\n");
        unset($stmt);
    }

    $out("SET FOREIGN_KEY_CHECKS=1;\n");
    $out("-- End of backup\n");
    exit;
}

/**
 * @return array{ok:bool,message:string}
 */
function db_admin_restore_sql(PDO $pdo, string $sqlPath): array
{
    if (!is_file($sqlPath)) {
        return ['ok' => false, 'message' => 'Upload file missing.'];
    }
    $size = filesize($sqlPath);
    if ($size === false || $size <= 0) {
        return ['ok' => false, 'message' => 'Empty file.'];
    }
    if ($size > 32 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'File too large (max 32 MB).'];
    }

    $sql = file_get_contents($sqlPath);
    if ($sql === false || trim($sql) === '') {
        return ['ok' => false, 'message' => 'Could not read SQL file.'];
    }

    if (strpos($sql, db_admin_backup_marker()) === false) {
        return ['ok' => false, 'message' => 'Only restore files created from this Backup page.'];
    }

    // Strip BOM
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        $sql = substr($sql, 3);
    }

    $sql = preg_replace('/^\s*CREATE DATABASE\b.*?;\s*/im', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*USE\s+\S+;\s*/im', '', $sql) ?? $sql;
    // Keep our marker line but strip other comments for execution
    $sql = preg_replace('/^\s*--(?!.*' . preg_quote(db_admin_backup_marker(), '/') . ').*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;

    $parts = preg_split('/;\s*(?:\r\n|\n|\r|$)/', $sql) ?: [];
    $ran = 0;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($parts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || stripos($stmt, db_admin_backup_marker()) !== false) {
                continue;
            }
            $pdo->exec($stmt);
            $ran++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
    }

    if (function_exists('feature_save')) {
        try {
            feature_save($pdo, 'db_backup_last_restore', date('Y-m-d H:i:s'));
            if (function_exists('clear_setting_cache')) {
                clear_setting_cache('db_backup_last_restore');
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return ['ok' => true, 'message' => 'Restore complete (' . $ran . ' statements). Sign in again if needed.'];
}

/**
 * Merge POST fields into credentials, keeping blank passwords.
 *
 * @param array<string,mixed> $post
 * @return array{mode:string,online:array<string,string>,offline:array<string,string>}
 */
function db_admin_credentials_from_post(array $post, array $existing): array
{
    $mode = strtolower(trim((string) ($post['db_mode'] ?? $existing['mode'])));
    if (!in_array($mode, ['auto', 'online', 'offline'], true)) {
        $mode = 'auto';
    }

    $onlinePost = [
        'host' => $post['online_host'] ?? '',
        'port' => $post['online_port'] ?? '',
        'name' => $post['online_name'] ?? '',
        'user' => $post['online_user'] ?? '',
        'pass' => $post['online_pass'] ?? '',
    ];
    $offlinePost = [
        'host' => $post['offline_host'] ?? '',
        'port' => $post['offline_port'] ?? '',
        'name' => $post['offline_name'] ?? '',
        'user' => $post['offline_user'] ?? '',
        'pass' => $post['offline_pass'] ?? '',
    ];

    $online = db_admin_normalize_side($onlinePost, $existing['online']);
    $offline = db_admin_normalize_side($offlinePost, $existing['offline']);

    if (trim((string) ($post['online_pass'] ?? '')) === '') {
        $online['pass'] = $existing['online']['pass'];
    } else {
        $online['pass'] = (string) $post['online_pass'];
    }
    if (trim((string) ($post['offline_pass'] ?? '')) === '') {
        $offline['pass'] = $existing['offline']['pass'];
    } else {
        $offline['pass'] = (string) $post['offline_pass'];
    }

    return [
        'mode' => $mode,
        'online' => $online,
        'offline' => $offline,
    ];
}

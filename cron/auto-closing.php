<?php
/**
 * Auto binary closing cron endpoint.
 *
 * HTTP:  /cron/auto-closing.php?token=YOUR_TOKEN
 * CLI:   php cron/auto-closing.php
 *        php cron/auto-closing.php --force
 */

if (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/closing.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$isCli = PHP_SAPI === 'cli';
$force = false;
$tokenOk = false;

if ($isCli) {
    $tokenOk = true;
    global $argv;
    $force = in_array('--force', $argv ?? [], true);
} else {
    $expected = setting('closing_auto_cron_token', '');
    $given = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if ($expected !== '' && hash_equals($expected, $given)) {
        $tokenOk = true;
    }
    $force = isset($_GET['force']) || isset($_POST['force']);
}

if (!$tokenOk) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

try {
    $run = closing_schedule_run_auto($pdo, $force);
    $line = ($run['skipped'] ? 'SKIP' : ($run['ok'] ? 'OK' : 'FAIL'))
        . ' slot=' . ($run['slot'] ?? '-')
        . ' ' . ($run['message'] ?? '');
    echo $line . "\n";
    exit($run['ok'] || $run['skipped'] ? 0 : 1);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR ' . $e->getMessage() . "\n";
    exit(1);
}

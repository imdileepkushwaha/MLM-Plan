<?php
/**
 * Public member registration helpers
 */

function ensure_member_registration_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $cols = [
        'name_title' => "VARCHAR(20) NULL AFTER full_name",
        'gender' => "VARCHAR(20) NULL AFTER name_title",
        'date_of_birth' => "DATE NULL AFTER gender",
    ];
    foreach ($cols as $name => $def) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM members LIKE " . $pdo->quote($name));
            if ($check && !$check->fetch()) {
                $pdo->exec("ALTER TABLE members ADD COLUMN {$name} {$def}");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    $done = true;
}

function reg_find_binary_placement(PDO $pdo, int $startId, string $position): ?int
{
    require_once __DIR__ . '/procedures.php';
    $viaSp = sp_call_find_binary_placement($pdo, $startId, $position);
    if ($viaSp !== null) {
        return $viaSp;
    }

    $parentId = $startId;
    for ($i = 0; $i < 50; $i++) {
        $stmt = $pdo->prepare('SELECT id FROM members WHERE placement_id = ? AND position = ? LIMIT 1');
        $stmt->execute([$parentId, $position]);
        $child = $stmt->fetch();
        if (!$child) {
            return $parentId;
        }
        $parentId = (int) $child['id'];
    }
    return null;
}

function reg_update_upline_counts(PDO $pdo, int $placementId, string $position): void
{
    require_once __DIR__ . '/procedures.php';
    if (sp_call_update_upline_counts($pdo, $placementId, $position)) {
        return;
    }

    $current = $placementId;
    $side = $position;
    $guard = 0;

    while ($current && $guard < 100) {
        $col = $side === 'left' ? 'left_count' : 'right_count';
        $pdo->prepare("UPDATE members SET {$col} = {$col} + 1 WHERE id = ?")->execute([$current]);

        $stmt = $pdo->prepare('SELECT placement_id, position FROM members WHERE id = ?');
        $stmt->execute([$current]);
        $row = $stmt->fetch();
        if (!$row || !$row['placement_id']) {
            break;
        }
        $current = (int) $row['placement_id'];
        $side = $row['position'] ?: $side;
        $guard++;
    }
}

function reg_lookup_sponsor(PDO $pdo, string $code): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT id, member_id, full_name, username, status
        FROM members
        WHERE member_id = ? OR username = ?
        LIMIT 1
    ");
    $stmt->execute([$code, $code]);
    $row = $stmt->fetch();
    if (!$row || ($row['status'] ?? '') === 'blocked') {
        return null;
    }
    return $row;
}

function reg_unique_username(PDO $pdo, string $email, string $fullName): string
{
    $base = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0] ?? ''));
    if (strlen($base) < 3) {
        $base = strtolower(preg_replace('/[^a-z0-9]/i', '', $fullName));
    }
    if (strlen($base) < 3) {
        $base = 'member';
    }
    $base = substr($base, 0, 20);
    $candidate = $base;
    $n = 0;
    $check = $pdo->prepare('SELECT id FROM members WHERE username = ? LIMIT 1');
    while (true) {
        $check->execute([$candidate]);
        if (!$check->fetch()) {
            return $candidate;
        }
        $n++;
        $candidate = $base . $n;
        if ($n > 500) {
            return $base . bin2hex(random_bytes(2));
        }
    }
}

function reg_unique_member_id(PDO $pdo): string
{
    for ($i = 0; $i < 20; $i++) {
        $code = generate_member_id($pdo);
        if ($i > 0) {
            $code = 'MLM' . str_pad((string) (random_int(1, 99999)), 5, '0', STR_PAD_LEFT);
        }
        $stmt = $pdo->prepare('SELECT id FROM members WHERE member_id = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    return 'MLM' . strtoupper(bin2hex(random_bytes(3)));
}

function reg_captcha_generate(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['reg_captcha'] = $code;
    return $code;
}

function reg_captcha_code(): string
{
    if (empty($_SESSION['reg_captcha'])) {
        return reg_captcha_generate();
    }
    return (string) $_SESSION['reg_captcha'];
}

function reg_captcha_valid(string $input): bool
{
    $expected = (string) ($_SESSION['reg_captcha'] ?? '');
    return $expected !== '' && strcasecmp(trim($input), $expected) === 0;
}

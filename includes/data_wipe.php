<?php
/**
 * Super Admin one-click client handoff wipe.
 * Keeps: admins, super_admins, settings, catalog masters, root member.
 * Clears: all other members + wallets, income, reports, orders, franchise ops, logs.
 */

/** @return list<string> History tables safe to TRUNCATE (if they exist). */
function data_wipe_truncate_tables(): array
{
    return [
        'product_order_items',
        'product_orders',
        'member_shipping_addresses',
        'wallet_ledger',
        'wallet_transfers',
        'wallet_topup_requests',
        'topup_pin_transfers',
        'topup_pins',
        'password_resets',
        'member_kyc_documents',
        'member_kyc_upi',
        'activation_requests',
        'closing_items',
        'closing_runs',
        'bv_credits',
        'withdrawal_payout_logs',
        'withdrawals',
        'commissions',
        'stock_purchase_items',
        'stock_purchases',
        'franchisee_purchase_items',
        'franchisee_purchases',
        'franchisee_stock',
        'franchisees',
        'activity_logs',
        'contact_inquiries',
        'matrix_placements',
        'matrix_spillover',
    ];
}

/**
 * @return array{ok:bool,root:?array,member_count:int,message:string}
 */
function data_wipe_preview(PDO $pdo): array
{
    $root = null;
    try {
        $root = $pdo->query('SELECT id, member_id, username, full_name, sponsor_id, placement_id FROM members ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return ['ok' => false, 'root' => null, 'member_count' => 0, 'message' => 'Members table not available.'];
    }

    if (!$root) {
        return ['ok' => false, 'root' => null, 'member_count' => 0, 'message' => 'No root member found. Create a root member first.'];
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();

    return [
        'ok' => true,
        'root' => $root,
        'member_count' => $count,
        'message' => 'Ready',
    ];
}

function data_wipe_table_exists(PDO $pdo, string $table): bool
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
                $cache[strtolower((string) $row[0])] = true;
            }
        } catch (Throwable $e) {
            return false;
        }
    }
    return isset($cache[strtolower($table)]);
}

function data_wipe_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $pdo->quote($column));
        return (bool) ($stmt && $stmt->fetch());
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Wipe transactional data; keep admins + root member + catalog/settings.
 *
 * @return array{ok:bool,message:string,deleted_members:int,truncated:list<string>,root_code:string}
 */
function data_wipe_run(PDO $pdo): array
{
    $preview = data_wipe_preview($pdo);
    if (!$preview['ok'] || empty($preview['root'])) {
        return [
            'ok' => false,
            'message' => $preview['message'],
            'deleted_members' => 0,
            'truncated' => [],
            'root_code' => '',
        ];
    }

    $root = $preview['root'];
    $rootId = (int) $root['id'];
    $rootCode = (string) ($root['member_id'] ?? '');
    $beforeMembers = (int) $preview['member_count'];
    $truncated = [];

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach (data_wipe_truncate_tables() as $table) {
            if (!data_wipe_table_exists($pdo, $table)) {
                continue;
            }
            $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
            $truncated[] = $table;
        }

        $del = $pdo->prepare('DELETE FROM members WHERE id <> ?');
        $del->execute([$rootId]);
        $deletedMembers = $del->rowCount();

        // Reset root balances / tree stats (keep login + package so they can sponsor).
        $sets = [
            'sponsor_id = NULL',
            'placement_id = NULL',
            'position = NULL',
            'left_count = 0',
            'right_count = 0',
            'left_bv = 0',
            'right_bv = 0',
            'total_earnings = 0',
            'wallet_balance = 0',
            "kyc_status = 'not_submitted'",
            'kyc_id_type = NULL',
            'kyc_id_number = NULL',
            'kyc_document = NULL',
            'kyc_note = NULL',
            'kyc_submitted_at = NULL',
            'kyc_reviewed_at = NULL',
        ];
        if (data_wipe_column_exists($pdo, 'members', 'topup_wallet_balance')) {
            $sets[] = 'topup_wallet_balance = 0';
        }
        if (data_wipe_column_exists($pdo, 'members', 'shopping_wallet_balance')) {
            $sets[] = 'shopping_wallet_balance = 0';
        }

        $pdo->exec('UPDATE members SET ' . implode(', ', $sets) . ' WHERE id = ' . (int) $rootId);

        // Optional: zero product stock after clearing purchase history
        if (data_wipe_table_exists($pdo, 'products') && data_wipe_column_exists($pdo, 'products', 'stock_qty')) {
            try {
                $pdo->exec('UPDATE products SET stock_qty = 0');
            } catch (Throwable $e) {
                // ignore
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e2) {
        }
        return [
            'ok' => false,
            'message' => 'Wipe failed: ' . $e->getMessage(),
            'deleted_members' => 0,
            'truncated' => $truncated,
            'root_code' => $rootCode,
        ];
    }

    // Best-effort cleanup of uploaded member / franchise docs (keep branding assets).
    data_wipe_clean_upload_dirs();

    // Closing auto last-run markers (settings) — clear so next schedule is fresh
    if (function_exists('feature_save')) {
        try {
            feature_save($pdo, 'closing_auto_last_slot', '');
            feature_save($pdo, 'closing_auto_last_run_at', '');
            feature_save($pdo, 'closing_auto_last_message', '');
            feature_save($pdo, 'db_backup_last_download', '');
            feature_save($pdo, 'db_backup_last_restore', '');
            if (function_exists('clear_setting_cache')) {
                clear_setting_cache();
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $keptAdmins = 0;
    try {
        $keptAdmins = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    } catch (Throwable $e) {
    }

    return [
        'ok' => true,
        'message' => 'Data cleared. Kept ' . $keptAdmins . ' admin(s) and root member '
            . $rootCode . '. Removed ' . $deletedMembers . ' member(s) of ' . $beforeMembers
            . ' and wiped wallets / income / reports.',
        'deleted_members' => $deletedMembers,
        'truncated' => $truncated,
        'root_code' => $rootCode,
    ];
}

function data_wipe_clean_upload_dirs(): void
{
    $base = dirname(__DIR__) . '/uploads';
    $dirs = [
        $base . '/kyc',
        $base . '/franchisee',
        $base . '/members',
        $base . '/payments',
        $base . '/wallet',
        $base . '/products/proofs',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        data_wipe_delete_dir_contents($dir);
    }
}

function data_wipe_delete_dir_contents(string $dir): void
{
    $items = @scandir($dir);
    if (!$items) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === 'index.html') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            data_wipe_delete_dir_contents($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

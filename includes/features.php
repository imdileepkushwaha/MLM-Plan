<?php
/**
 * Feature flags / plan mode for this install (controlled by Super Admin).
 * Client Admin + User panels only see/run what is enabled here.
 */

/** @return array<string, string> default feature settings */
function feature_defaults(): array
{
    return [
        // Plan topology: hybrid | binary | level
        'plan_mode' => 'hybrid',

        // Activation rails
        'feature_package_enabled' => '1',
        'feature_tpin_enabled' => '1',
        'feature_utr_activation_enabled' => '1',
        'feature_wallet_topup_enabled' => '1',
        'feature_product_shop_enabled' => '1',

        // Income modules (also sync binary_income_enabled / level_income_enabled)
        'feature_binary_income' => '1',
        'feature_level_income' => '1',
        'feature_referral_income' => '1',
        'feature_matching_income' => '1',

        // Operational modules
        'feature_withdrawals_enabled' => '1',
        'feature_kyc_enabled' => '1',
        'feature_utility_enabled' => '1',
        'feature_reports_enabled' => '1',

        // Meta
        'feature_preset' => 'hybrid_full',
        'client_locked_note' => 'Plan & modules are managed by Super Admin.',
    ];
}

/**
 * Named presets Super Admin can apply in one click.
 * @return array<string, array{label: string, description: string, settings: array<string, string>}>
 */
function feature_presets(): array
{
    return [
        'hybrid_full' => [
            'label' => 'Hybrid Full',
            'description' => 'Binary + Level, Package, T-PIN, Products, UTR, Wallet — everything on.',
            'settings' => [
                'plan_mode' => 'hybrid',
                'feature_package_enabled' => '1',
                'feature_tpin_enabled' => '1',
                'feature_utr_activation_enabled' => '1',
                'feature_wallet_topup_enabled' => '1',
                'feature_product_shop_enabled' => '1',
                'feature_binary_income' => '1',
                'feature_level_income' => '1',
                'feature_referral_income' => '1',
                'feature_matching_income' => '1',
                'feature_withdrawals_enabled' => '1',
                'feature_kyc_enabled' => '1',
                'feature_utility_enabled' => '1',
                'feature_reports_enabled' => '1',
            ],
        ],
        'binary_package_tpin' => [
            'label' => 'Binary + Package + T-PIN',
            'description' => 'Classic binary MLM. No product shop. Activate via package / T-PIN.',
            'settings' => [
                'plan_mode' => 'binary',
                'feature_package_enabled' => '1',
                'feature_tpin_enabled' => '1',
                'feature_utr_activation_enabled' => '1',
                'feature_wallet_topup_enabled' => '1',
                'feature_product_shop_enabled' => '0',
                'feature_binary_income' => '1',
                'feature_level_income' => '0',
                'feature_referral_income' => '1',
                'feature_matching_income' => '1',
                'feature_withdrawals_enabled' => '1',
                'feature_kyc_enabled' => '1',
                'feature_utility_enabled' => '1',
                'feature_reports_enabled' => '1',
            ],
        ],
        'level_only' => [
            'label' => 'Level Plan Only',
            'description' => 'Sponsor-level income only. No binary tree / closing.',
            'settings' => [
                'plan_mode' => 'level',
                'feature_package_enabled' => '1',
                'feature_tpin_enabled' => '1',
                'feature_utr_activation_enabled' => '1',
                'feature_wallet_topup_enabled' => '0',
                'feature_product_shop_enabled' => '0',
                'feature_binary_income' => '0',
                'feature_level_income' => '1',
                'feature_referral_income' => '1',
                'feature_matching_income' => '0',
                'feature_withdrawals_enabled' => '1',
                'feature_kyc_enabled' => '1',
                'feature_utility_enabled' => '1',
                'feature_reports_enabled' => '1',
            ],
        ],
        'epin_company' => [
            'label' => 'E-Pin / T-PIN Company',
            'description' => 'Hybrid income. T-PIN required style; product shop off.',
            'settings' => [
                'plan_mode' => 'hybrid',
                'feature_package_enabled' => '1',
                'feature_tpin_enabled' => '1',
                'feature_utr_activation_enabled' => '0',
                'feature_wallet_topup_enabled' => '0',
                'feature_product_shop_enabled' => '0',
                'feature_binary_income' => '1',
                'feature_level_income' => '1',
                'feature_referral_income' => '1',
                'feature_matching_income' => '1',
                'feature_withdrawals_enabled' => '1',
                'feature_kyc_enabled' => '1',
                'feature_utility_enabled' => '1',
                'feature_reports_enabled' => '1',
            ],
        ],
        'product_binary' => [
            'label' => 'Product + Binary',
            'description' => 'Product shop + binary. Packages still available for activation.',
            'settings' => [
                'plan_mode' => 'binary',
                'feature_package_enabled' => '1',
                'feature_tpin_enabled' => '0',
                'feature_utr_activation_enabled' => '1',
                'feature_wallet_topup_enabled' => '1',
                'feature_product_shop_enabled' => '1',
                'feature_binary_income' => '1',
                'feature_level_income' => '0',
                'feature_referral_income' => '1',
                'feature_matching_income' => '0',
                'feature_withdrawals_enabled' => '1',
                'feature_kyc_enabled' => '1',
                'feature_utility_enabled' => '1',
                'feature_reports_enabled' => '1',
            ],
        ],
    ];
}

function feature_save(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
    clear_setting_cache($key);
}

/** Ensure default feature keys exist (safe to call often). */
function feature_ensure_defaults(PDO $pdo): void
{
    foreach (feature_defaults() as $key => $val) {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            if (!$stmt->fetchColumn()) {
                feature_save($pdo, $key, $val);
            }
        } catch (Throwable $e) {
            // settings table may not exist yet
        }
    }
}

function feature_enabled(string $key, bool $default = true): bool
{
    $defaults = feature_defaults();
    $fallback = array_key_exists($key, $defaults) ? $defaults[$key] : ($default ? '1' : '0');
    return setting($key, $fallback) === '1';
}

/** @return 'hybrid'|'binary'|'level' */
function plan_mode(): string
{
    $mode = strtolower(setting('plan_mode', 'hybrid'));
    return in_array($mode, ['hybrid', 'binary', 'level'], true) ? $mode : 'hybrid';
}

function plan_uses_binary(): bool
{
    $mode = plan_mode();
    return ($mode === 'binary' || $mode === 'hybrid') && feature_enabled('feature_binary_income');
}

function plan_uses_level(): bool
{
    $mode = plan_mode();
    return ($mode === 'level' || $mode === 'hybrid') && feature_enabled('feature_level_income');
}

/**
 * Apply a preset + keep engine income flags in sync.
 * @param array<string, string>|null $overrides
 */
function feature_apply_preset(PDO $pdo, string $presetKey, ?array $overrides = null): bool
{
    $presets = feature_presets();
    if (!isset($presets[$presetKey])) {
        return false;
    }

    $settings = $presets[$presetKey]['settings'];
    if ($overrides) {
        $settings = array_merge($settings, $overrides);
    }

    foreach ($settings as $key => $val) {
        feature_save($pdo, $key, (string) $val);
    }
    feature_save($pdo, 'feature_preset', $presetKey);

    // Keep legacy closing/income settings aligned
    feature_save($pdo, 'binary_income_enabled', ($settings['feature_binary_income'] ?? '0') === '1' ? '1' : '0');
    feature_save($pdo, 'level_income_enabled', ($settings['feature_level_income'] ?? '0') === '1' ? '1' : '0');

    clear_setting_cache();
    return true;
}

/**
 * Save custom feature form from Super Admin.
 * @param array<string, mixed> $post
 */
function feature_save_from_post(PDO $pdo, array $post): void
{
    $boolKeys = [
        'feature_package_enabled',
        'feature_tpin_enabled',
        'feature_utr_activation_enabled',
        'feature_wallet_topup_enabled',
        'feature_product_shop_enabled',
        'feature_binary_income',
        'feature_level_income',
        'feature_referral_income',
        'feature_matching_income',
        'feature_withdrawals_enabled',
        'feature_kyc_enabled',
        'feature_utility_enabled',
        'feature_reports_enabled',
    ];

    $mode = strtolower(trim((string) ($post['plan_mode'] ?? 'hybrid')));
    if (!in_array($mode, ['hybrid', 'binary', 'level'], true)) {
        $mode = 'hybrid';
    }
    feature_save($pdo, 'plan_mode', $mode);

    foreach ($boolKeys as $key) {
        feature_save($pdo, $key, isset($post[$key]) ? '1' : '0');
    }

    // Mode constraints
    if ($mode === 'binary') {
        feature_save($pdo, 'feature_binary_income', '1');
        feature_save($pdo, 'feature_level_income', isset($post['feature_level_income']) ? '1' : '0');
    } elseif ($mode === 'level') {
        feature_save($pdo, 'feature_binary_income', '0');
        feature_save($pdo, 'feature_level_income', '1');
        feature_save($pdo, 'feature_matching_income', '0');
    }

    feature_save($pdo, 'binary_income_enabled', feature_enabled('feature_binary_income') ? '1' : '0');
    feature_save($pdo, 'level_income_enabled', feature_enabled('feature_level_income') ? '1' : '0');
    feature_save($pdo, 'feature_preset', 'custom');
    clear_setting_cache();
}

/** Admin pages → required feature key (null = always allowed). */
function feature_admin_page_map(): array
{
    return [
        'tree-view' => 'binary_tree',
        'binary-tree' => 'binary_tree',
        'binary-closing' => 'binary_closing',
        'report-binary-closing' => 'binary_closing',
        'packages' => 'packages',
        'package-assign-products' => 'packages',
        'package-plans' => 'packages',
        'plans' => 'packages',
        'activations' => 'activations',
        'tpin' => 'tpin',
        'tpin-transfer' => 'tpin',
        'tpin-report' => 'tpin',
        'product-categories' => 'products',
        'product-subcategories' => 'products',
        'product-sizes' => 'products',
        'product-colors' => 'products',
        'subcategory-settings' => 'products',
        'product-add' => 'products',
        'product-form' => 'products',
        'product-details' => 'products',
        'product-status' => 'products',
        'stock-report' => 'products',
        'vendors' => 'products',
        'stock-purchase' => 'products',
        'purchase-details' => 'products',
        'commodity-prices' => 'products',
        'wallet-topup-requests' => 'wallet_topup',
        'withdrawals' => 'withdrawals',
        'approve-kyc' => 'kyc',
        'countries' => 'utility',
        'states' => 'utility',
        'cities' => 'utility',
        'banks' => 'utility',
        'bank-accounts' => 'utility',
        'news' => 'utility',
        'direct-member-login' => 'utility',
        'reports' => 'reports',
        'report-commission' => 'reports',
        'report-joining' => 'reports',
        'report-package-sales' => 'reports',
        'report-top-earners' => 'reports',
        'tds-report' => 'reports',
    ];
}

/** User pages → required feature key. */
function feature_user_page_map(): array
{
    return [
        'my-treeview' => 'binary_tree',
        'level-tree' => 'level_tree',
        'activate' => 'activations',
        'tpin' => 'tpin',
        'wallet-topup' => 'wallet_topup',
        'wallet-topup-activate' => 'wallet_topup',
        'purchase-product' => 'products',
        'purchase-report' => 'products',
        'purchase-invoice' => 'products',
        'wallet-shopping' => 'products',
        'income-binary' => 'income_binary',
        'income-level' => 'income_level',
        'income-referral' => 'income_referral',
        'income-matching' => 'income_matching',
        'withdrawal-fund' => 'withdrawals',
        'withdrawal-report' => 'withdrawals',
        'kyc-pan' => 'kyc',
        'kyc-bank' => 'kyc',
        'kyc-aadhar' => 'kyc',
        'kyc-upi' => 'kyc',
    ];
}

/**
 * Resolve abstract module keys used by page maps.
 */
function feature_module_allowed(string $module): bool
{
    switch ($module) {
        case 'binary_tree':
        case 'binary_closing':
            return plan_uses_binary();
        case 'level_tree':
            return plan_uses_level() || plan_mode() === 'level';
        case 'packages':
            return feature_enabled('feature_package_enabled');
        case 'activations':
            return feature_activation_any_rail();
        case 'tpin':
            return feature_enabled('feature_tpin_enabled');
        case 'products':
            return feature_enabled('feature_product_shop_enabled');
        case 'wallet_topup':
            return feature_enabled('feature_wallet_topup_enabled');
        case 'withdrawals':
            return feature_enabled('feature_withdrawals_enabled');
        case 'kyc':
            return feature_enabled('feature_kyc_enabled');
        case 'utility':
            return feature_enabled('feature_utility_enabled');
        case 'reports':
            return feature_enabled('feature_reports_enabled');
        case 'income_binary':
            return plan_uses_binary();
        case 'income_level':
            return plan_uses_level();
        case 'income_referral':
            return feature_enabled('feature_referral_income');
        case 'income_matching':
            return feature_enabled('feature_matching_income') && plan_uses_binary();
        default:
            return true;
    }
}

function feature_guard_admin_page(?string $page = null): void
{
    $page = $page ?? basename($_SERVER['PHP_SELF'] ?? '', '.php');
    $map = feature_admin_page_map();
    if (!isset($map[$page])) {
        return;
    }
    if (!feature_module_allowed($map[$page])) {
        flash('error', 'This module is disabled for this client. Contact Super Admin.');
        header('Location: index.php');
        exit;
    }
}

function feature_guard_user_page(?string $page = null): void
{
    $page = $page ?? basename($_SERVER['PHP_SELF'] ?? '', '.php');
    $map = feature_user_page_map();
    if (!isset($map[$page])) {
        return;
    }
    if (!feature_module_allowed($map[$page])) {
        flash('error', 'This feature is not available on your plan.');
        header('Location: index.php');
        exit;
    }
}

/** Human-readable summary for Super Admin dashboard. */
function feature_summary(): array
{
    return [
        'plan_mode' => plan_mode(),
        'preset' => setting('feature_preset', 'hybrid_full'),
        'binary' => plan_uses_binary(),
        'level' => plan_uses_level(),
        'package' => feature_enabled('feature_package_enabled'),
        'tpin' => feature_enabled('feature_tpin_enabled'),
        'utr' => feature_enabled('feature_utr_activation_enabled'),
        'wallet_topup' => feature_enabled('feature_wallet_topup_enabled'),
        'products' => feature_enabled('feature_product_shop_enabled'),
        'referral' => feature_enabled('feature_referral_income'),
        'matching' => feature_enabled('feature_matching_income'),
        'withdrawals' => feature_enabled('feature_withdrawals_enabled'),
        'kyc' => feature_enabled('feature_kyc_enabled'),
    ];
}

/** Allowed activation pay modes for this install: utr|tpin|wallet */
function feature_activation_pay_modes(): array
{
    $modes = [];
    if (feature_enabled('feature_utr_activation_enabled') && feature_enabled('feature_package_enabled')) {
        $modes[] = 'utr';
    }
    if (feature_enabled('feature_tpin_enabled')) {
        $modes[] = 'tpin';
    }
    if (feature_enabled('feature_wallet_topup_enabled') && feature_enabled('feature_package_enabled')) {
        $modes[] = 'wallet';
    }
    return $modes;
}

function feature_activation_default_pay_mode(): string
{
    $modes = feature_activation_pay_modes();
    return $modes[0] ?? '';
}

function feature_activation_any_rail(): bool
{
    return feature_activation_pay_modes() !== [];
}

/** True when registration should collect left/right binary position. */
function feature_registration_uses_binary_placement(): bool
{
    return plan_mode() !== 'level';
}

/**
 * Create super_admins table + default login (superadmin / superadmin123).
 */
function feature_ensure_superadmin_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $hash = password_hash('superadmin123', PASSWORD_DEFAULT);
    try {
        $row = $pdo->query("SELECT id FROM super_admins WHERE username = 'superadmin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM super_admins')->fetchColumn();
            if ($count === 0) {
                $pdo->prepare('INSERT INTO super_admins (username, email, password, full_name, status) VALUES (?, ?, ?, ?, ?)')
                    ->execute(['superadmin', 'superadmin@binarymlm.com', $hash, 'Platform Super Admin', 'active']);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

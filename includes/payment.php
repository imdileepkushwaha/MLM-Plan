<?php
/**
 * Payment gateway settings (Cashfree / Razorpay / Paytm / PhonePe).
 * Stored in settings table; checkout hooks can use these later.
 */

/** @return array<string,string> */
function payment_gateway_providers(): array
{
    return [
        'none' => 'None (disabled)',
        'cashfree' => 'Cashfree',
        'razorpay' => 'Razorpay',
        'paytm' => 'Paytm',
        'phonepe' => 'PhonePe',
    ];
}

/** @return array<string,string> */
function payment_setting_defaults(): array
{
    return [
        'payment_gateway_enabled' => '0',
        'payment_gateway_provider' => 'none',
        'payment_gateway_mode' => 'sandbox',

        'pay_razorpay_key_id' => '',
        'pay_razorpay_key_secret' => '',
        'pay_razorpay_webhook_secret' => '',

        'pay_cashfree_app_id' => '',
        'pay_cashfree_secret_key' => '',
        'pay_cashfree_api_version' => '2023-08-01',

        'pay_paytm_merchant_id' => '',
        'pay_paytm_merchant_key' => '',
        'pay_paytm_website' => 'WEBSTAGING',
        'pay_paytm_industry_type' => 'Retail',
        'pay_paytm_channel_id' => 'WEB',

        'pay_phonepe_merchant_id' => '',
        'pay_phonepe_salt_key' => '',
        'pay_phonepe_salt_index' => '1',
        'pay_phonepe_client_id' => '',
    ];
}

function payment_ensure_defaults(PDO $pdo): void
{
    foreach (payment_setting_defaults() as $key => $val) {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            if (!$stmt->fetchColumn()) {
                $ins = $pdo->prepare(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                $ins->execute([$key, $val]);
            }
        } catch (Throwable $e) {
            // ignore during setup
        }
    }
}

/** @return array<string,string> */
function payment_get_all(): array
{
    $out = payment_setting_defaults();
    foreach ($out as $key => $_) {
        $out[$key] = (string) setting($key, $out[$key]);
    }
    $providers = payment_gateway_providers();
    if (!isset($providers[$out['payment_gateway_provider']])) {
        $out['payment_gateway_provider'] = 'none';
    }
    if (!in_array($out['payment_gateway_mode'], ['sandbox', 'live'], true)) {
        $out['payment_gateway_mode'] = 'sandbox';
    }
    return $out;
}

function payment_save_key(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
    if (function_exists('clear_setting_cache')) {
        clear_setting_cache($key);
    }
}

/**
 * Save secret only when a new non-empty value is posted (blank = keep existing).
 */
function payment_save_secret(PDO $pdo, string $key, string $posted): void
{
    if (trim($posted) !== '') {
        payment_save_key($pdo, $key, trim($posted));
    }
}

/**
 * @param array<string,mixed> $post
 */
function payment_save_gateway(PDO $pdo, array $post): void
{
    $enabled = isset($post['payment_gateway_enabled']) ? '1' : '0';
    $provider = strtolower(trim((string) ($post['payment_gateway_provider'] ?? 'none')));
    $providers = payment_gateway_providers();
    if (!isset($providers[$provider])) {
        $provider = 'none';
    }
    if ($provider === 'none') {
        $enabled = '0';
    }

    $mode = strtolower(trim((string) ($post['payment_gateway_mode'] ?? 'sandbox')));
    if (!in_array($mode, ['sandbox', 'live'], true)) {
        $mode = 'sandbox';
    }

    payment_save_key($pdo, 'payment_gateway_enabled', $enabled);
    payment_save_key($pdo, 'payment_gateway_provider', $provider);
    payment_save_key($pdo, 'payment_gateway_mode', $mode);

    // Always persist visible (non-secret) fields for all gateways so switching back keeps values.
    payment_save_key($pdo, 'pay_razorpay_key_id', trim((string) ($post['pay_razorpay_key_id'] ?? '')));
    payment_save_secret($pdo, 'pay_razorpay_key_secret', (string) ($post['pay_razorpay_key_secret'] ?? ''));
    payment_save_secret($pdo, 'pay_razorpay_webhook_secret', (string) ($post['pay_razorpay_webhook_secret'] ?? ''));

    payment_save_key($pdo, 'pay_cashfree_app_id', trim((string) ($post['pay_cashfree_app_id'] ?? '')));
    payment_save_secret($pdo, 'pay_cashfree_secret_key', (string) ($post['pay_cashfree_secret_key'] ?? ''));
    $cfVer = trim((string) ($post['pay_cashfree_api_version'] ?? '2023-08-01'));
    payment_save_key($pdo, 'pay_cashfree_api_version', $cfVer !== '' ? $cfVer : '2023-08-01');

    payment_save_key($pdo, 'pay_paytm_merchant_id', trim((string) ($post['pay_paytm_merchant_id'] ?? '')));
    payment_save_secret($pdo, 'pay_paytm_merchant_key', (string) ($post['pay_paytm_merchant_key'] ?? ''));
    payment_save_key($pdo, 'pay_paytm_website', trim((string) ($post['pay_paytm_website'] ?? 'WEBSTAGING')));
    payment_save_key($pdo, 'pay_paytm_industry_type', trim((string) ($post['pay_paytm_industry_type'] ?? 'Retail')));
    payment_save_key($pdo, 'pay_paytm_channel_id', trim((string) ($post['pay_paytm_channel_id'] ?? 'WEB')));

    payment_save_key($pdo, 'pay_phonepe_merchant_id', trim((string) ($post['pay_phonepe_merchant_id'] ?? '')));
    payment_save_secret($pdo, 'pay_phonepe_salt_key', (string) ($post['pay_phonepe_salt_key'] ?? ''));
    $saltIdx = trim((string) ($post['pay_phonepe_salt_index'] ?? '1'));
    payment_save_key($pdo, 'pay_phonepe_salt_index', $saltIdx !== '' ? $saltIdx : '1');
    payment_save_key($pdo, 'pay_phonepe_client_id', trim((string) ($post['pay_phonepe_client_id'] ?? '')));
}

function payment_mask_secret(string $value): string
{
    if (function_exists('messaging_mask_secret')) {
        return messaging_mask_secret($value);
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('•', $len);
    }
    return str_repeat('•', max(0, $len - 4)) . substr($value, -4);
}

function payment_gateway_active_label(?array $cfg = null): string
{
    $cfg = $cfg ?? payment_get_all();
    if (($cfg['payment_gateway_enabled'] ?? '0') !== '1') {
        return 'Payments off';
    }
    $providers = payment_gateway_providers();
    $p = $cfg['payment_gateway_provider'] ?? 'none';
    $name = $providers[$p] ?? 'None';
    $mode = ($cfg['payment_gateway_mode'] ?? 'sandbox') === 'live' ? 'Live' : 'Sandbox';
    if ($p === 'none') {
        return 'No gateway selected';
    }
    return $name . ' · ' . $mode;
}

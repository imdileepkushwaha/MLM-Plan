<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/messaging.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/db_admin.php';
require_once __DIR__ . '/../includes/schema_setup.php';
require_once __DIR__ . '/../includes/data_wipe.php';
require_superadmin();

$pageTitle = 'Settings';
messaging_ensure_defaults($pdo);
payment_ensure_defaults($pdo);

$tab = $_GET['tab'] ?? 'smtp';
$allowedTabs = ['smtp', 'whatsapp', 'sms', 'payment', 'database', 'backup', 'wipe', 'security', 'activity'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'smtp';
}

$dbCreds = db_admin_load_credentials();
$company = setting('company_name', 'Binary MLM');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTab = (string) ($_POST['tab'] ?? 'smtp');
    if (!in_array($postTab, $allowedTabs, true)) {
        $postTab = 'smtp';
    }
    $postAction = (string) ($_POST['action'] ?? 'save');

    if ($postAction === 'test' || str_starts_with($postAction, 'test_')) {
        $testType = (string) ($_POST['test_type'] ?? $postTab);
        if (str_starts_with($postAction, 'test_')) {
            $testType = substr($postAction, 5);
        }
        if ($testType === 'smtp') {
            $result = messaging_test_smtp();
        } elseif ($testType === 'whatsapp') {
            $result = messaging_test_whatsapp();
        } elseif ($testType === 'sms') {
            $result = messaging_test_sms();
        } elseif ($testType === 'online' || $testType === 'offline') {
            $merged = db_admin_credentials_from_post($_POST, $dbCreds);
            $result = db_admin_test_side($testType, $merged[$testType], $dbCreds[$testType]);
            $postTab = 'database';
        } else {
            $result = ['ok' => false, 'message' => 'Unknown test type.'];
        }
        log_superadmin_activity('settings_test_' . $testType, ($result['ok'] ? 'OK: ' : 'FAIL: ') . ($result['message'] ?? ''));
        flash($result['ok'] ? 'success' : 'error', $result['message'] ?? 'Test failed.');
        header('Location: settings.php?tab=' . urlencode($postTab));
        exit;
    }

    if ($postTab === 'database') {
        if ($postAction === 'run_setup') {
            try {
                $result = mlm_run_schema_setup($pdo);
                log_superadmin_activity('db_schema_setup', $result['message'] ?? '');
                flash($result['ok'] ? 'success' : 'error', $result['message'] ?? 'Setup finished.');
            } catch (Throwable $e) {
                flash('error', 'Setup failed: ' . $e->getMessage());
            }
            header('Location: settings.php?tab=database');
            exit;
        }

        $merged = db_admin_credentials_from_post($_POST, $dbCreds);
        $saved = db_admin_save_credentials($merged);
        log_superadmin_activity('settings_database', $saved['message']);
        flash($saved['ok'] ? 'success' : 'error', $saved['message'] . ($saved['ok'] ? ' Reload uses the new connection.' : ''));
        header('Location: settings.php?tab=database');
        exit;
    }

    if ($postTab === 'backup') {
        if ($postAction === 'download_backup') {
            log_superadmin_activity('db_backup_download', 'Downloaded SQL backup of ' . DB_NAME);
            db_admin_stream_backup($pdo, (string) DB_NAME);
        }

        if ($postAction === 'restore_backup') {
            if (empty($_POST['confirm_restore'])) {
                flash('error', 'Tick the confirmation checkbox to restore.');
                header('Location: settings.php?tab=backup');
                exit;
            }
            $file = $_FILES['backup_file'] ?? null;
            if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                flash('error', 'Choose a .sql backup file to upload.');
                header('Location: settings.php?tab=backup');
                exit;
            }
            $name = (string) ($file['name'] ?? '');
            if (!preg_match('/\.sql$/i', $name)) {
                flash('error', 'Only .sql files are allowed.');
                header('Location: settings.php?tab=backup');
                exit;
            }
            $result = db_admin_restore_sql($pdo, (string) $file['tmp_name']);
            log_superadmin_activity('db_backup_restore', $result['message']);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            header('Location: settings.php?tab=backup');
            exit;
        }
    }

    if ($postTab === 'wipe') {
        $confirm = strtoupper(trim((string) ($_POST['confirm_wipe'] ?? '')));
        if (empty($_POST['confirm_wipe_check'])) {
            flash('error', 'Tick the confirmation checkbox first.');
            header('Location: settings.php?tab=wipe');
            exit;
        }
        if ($confirm !== 'CLEAR DATA') {
            flash('error', 'Type CLEAR DATA exactly to confirm.');
            header('Location: settings.php?tab=wipe');
            exit;
        }
        $result = data_wipe_run($pdo);
        log_superadmin_activity('data_wipe', $result['message']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        header('Location: settings.php?tab=wipe');
        exit;
    }

    if ($postTab === 'smtp') {
        messaging_save_smtp($pdo, $_POST);
        clear_setting_cache();
        log_superadmin_activity('settings_smtp', 'Updated Email SMTP settings');
        flash('success', 'Email SMTP settings saved.');
    } elseif ($postTab === 'whatsapp') {
        messaging_save_whatsapp($pdo, $_POST);
        clear_setting_cache();
        log_superadmin_activity('settings_whatsapp', 'Updated WhatsApp API settings');
        flash('success', 'WhatsApp API settings saved.');
    } elseif ($postTab === 'sms') {
        messaging_save_sms($pdo, $_POST);
        clear_setting_cache();
        log_superadmin_activity('settings_sms', 'Updated SMS API settings');
        flash('success', 'SMS API settings saved.');
    } elseif ($postTab === 'payment') {
        payment_save_gateway($pdo, $_POST);
        clear_setting_cache();
        log_superadmin_activity('settings_payment', 'Updated payment gateway settings');
        flash('success', 'Payment gateway settings saved.');
    } elseif ($postTab === 'security') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($new === '' && $confirm === '') {
            flash('error', 'Enter a new password to update.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New password and confirmation do not match.');
        } else {
            $stmt = $pdo->prepare('SELECT * FROM super_admins WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['superadmin_id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, $row['password'])) {
                flash('error', 'Current password is incorrect.');
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE super_admins SET password = ? WHERE id = ?')->execute([$hash, $row['id']]);
                log_superadmin_activity('password_change', 'Super Admin password changed');
                flash('success', 'Password updated successfully.');
            }
        }
        header('Location: settings.php?tab=security');
        exit;
    }

    header('Location: settings.php?tab=' . urlencode($postTab));
    exit;
}

$msg = messaging_get_all();
$smtpOn = ($msg['smtp_enabled'] ?? '0') === '1';
$waOn = ($msg['wa_api_enabled'] ?? '0') === '1';
$smsOn = ($msg['sms_api_enabled'] ?? '0') === '1';
$pay = payment_get_all();
$payOn = ($pay['payment_gateway_enabled'] ?? '0') === '1'
    && ($pay['payment_gateway_provider'] ?? 'none') !== 'none';
$payProviders = payment_gateway_providers();

$dbCreds = db_admin_load_credentials();
$dbMode = $dbCreds['mode'];
$dbSide = defined('DB_ACTIVE_SIDE') ? (string) DB_ACTIVE_SIDE : 'offline';
$dbTableCount = ($tab === 'backup') ? db_admin_table_count($pdo) : 0;
$dbLastDownload = setting('db_backup_last_download', '');
$dbLastRestore = setting('db_backup_last_restore', '');
$backupSampleName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) DB_NAME)) . '-backup-' . date('Ymd-His') . '.sql';
$wipePreview = ($tab === 'wipe') ? data_wipe_preview($pdo) : ['ok' => false, 'root' => null, 'member_count' => 0];
$wipeAdminCount = 0;
if ($tab === 'wipe') {
    try {
        $wipeAdminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    } catch (Throwable $e) {
        $wipeAdminCount = 0;
    }
}

$logs = [];
if ($tab === 'activity') {
    try {
        $logs = $pdo->query("
            SELECT id, action, details, ip_address, created_at
            FROM activity_logs
            WHERE action LIKE 'superadmin:%'
            ORDER BY id DESC
            LIMIT 50
        ")->fetchAll();
    } catch (Throwable $e) {
        $logs = [];
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="sa-hero">
    <div>
        <span class="sa-hero-kicker">Platform</span>
        <h1>Settings</h1>
        <p>Messaging, payments, database, backup, clear data, security, and Super Admin activity.</p>
    </div>
</section>

<div class="settings-layout sa-settings">
    <aside class="settings-nav">
        <div class="settings-nav-group">
            <span class="settings-nav-label">Messaging</span>
            <a href="settings.php?tab=smtp" class="settings-nav-item <?= $tab === 'smtp' ? 'active' : '' ?>">
                <span class="sni-ico red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                Email SMTP
            </a>
            <a href="settings.php?tab=whatsapp" class="settings-nav-item <?= $tab === 'whatsapp' ? 'active' : '' ?>">
                <span class="sni-ico teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                </span>
                WhatsApp API
            </a>
            <a href="settings.php?tab=sms" class="settings-nav-item <?= $tab === 'sms' ? 'active' : '' ?>">
                <span class="sni-ico pink">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </span>
                SMS API
            </a>
        </div>
        <div class="settings-nav-group">
            <span class="settings-nav-label">Payments</span>
            <a href="settings.php?tab=payment" class="settings-nav-item <?= $tab === 'payment' ? 'active' : '' ?>">
                <span class="sni-ico green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </span>
                Payment Gateway
            </a>
        </div>
        <div class="settings-nav-group">
            <span class="settings-nav-label">Database</span>
            <a href="settings.php?tab=database" class="settings-nav-item <?= $tab === 'database' ? 'active' : '' ?>">
                <span class="sni-ico orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 11v6c0 1.7 4 3 9 3s9-1.3 9-3v-6"/></svg>
                </span>
                Online &amp; Offline DB
            </a>
            <a href="settings.php?tab=backup" class="settings-nav-item <?= $tab === 'backup' ? 'active' : '' ?>">
                <span class="sni-ico teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </span>
                Backup &amp; Restore
            </a>
            <a href="settings.php?tab=wipe" class="settings-nav-item <?= $tab === 'wipe' ? 'active' : '' ?>">
                <span class="sni-ico red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                </span>
                Clear Data
            </a>
        </div>
        <div class="settings-nav-group">
            <span class="settings-nav-label">Account</span>
            <a href="settings.php?tab=security" class="settings-nav-item <?= $tab === 'security' ? 'active' : '' ?>">
                <span class="sni-ico green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                </span>
                Security
            </a>
            <a href="settings.php?tab=activity" class="settings-nav-item <?= $tab === 'activity' ? 'active' : '' ?>">
                <span class="sni-ico orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </span>
                Activity Log
            </a>
        </div>
    </aside>

    <section class="settings-main">
        <?php if ($tab === 'smtp'): ?>
        <form method="post" class="settings-card" autocomplete="off">
            <input type="hidden" name="tab" value="smtp">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <div>
                        <h2>Email SMTP</h2>
                        <p>Outgoing mail server for OTP, reset links, and system emails.</p>
                    </div>
                </div>
                <span class="status-pill <?= $smtpOn ? 'online' : 'offline' ?>"><?= $smtpOn ? 'Enabled' : 'Disabled' ?></span>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
                    <h3>Connection</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="sa-check-label">
                            <input type="checkbox" name="smtp_enabled" value="1" <?= $smtpOn ? 'checked' : '' ?>>
                            Enable SMTP (use instead of PHP mail)
                        </label>
                    </div>
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= e($msg['smtp_host']) ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="smtp_port" min="1" max="65535" value="<?= e($msg['smtp_port']) ?>" placeholder="587">
                    </div>
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption">
                            <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $k => $lab): ?>
                            <option value="<?= $k ?>" <?= ($msg['smtp_encryption'] ?? '') === $k ? 'selected' : '' ?>><?= $lab ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="smtp_username" value="<?= e($msg['smtp_username']) ?>" placeholder="email@domain.com" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-field">
                            <input type="password" name="smtp_password" value="" placeholder="<?= $msg['smtp_password'] !== '' ? 'Leave blank to keep current' : 'App password / SMTP password' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($msg['smtp_password'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(messaging_mask_secret($msg['smtp_password'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <h3>From identity</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?= e($msg['smtp_from_name']) ?>" placeholder="Company Support">
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from_email" value="<?= e($msg['smtp_from_email']) ?>" placeholder="noreply@domain.com">
                    </div>
                </div>
            </div>

            <div class="settings-card-foot">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><polyline points="20 6 9 17 4 12"/></svg>
                    Save SMTP settings
                </button>
                <button type="submit" name="action" value="test" class="btn btn-outline" formnovalidate>
                    Test connection
                </button>
                <input type="hidden" name="test_type" value="smtp">
            </div>
            <p class="sa-field-hint">Test uses last saved settings (save first if you changed fields).</p>
        </form>

        <?php elseif ($tab === 'whatsapp'): ?>
        <form method="post" class="settings-card" autocomplete="off">
            <input type="hidden" name="tab" value="whatsapp">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico teal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                    </span>
                    <div>
                        <h2>WhatsApp API</h2>
                        <p>Business API credentials for order / OTP WhatsApp messages.</p>
                    </div>
                </div>
                <span class="status-pill <?= $waOn ? 'online' : 'offline' ?>"><?= $waOn ? 'Enabled' : 'Disabled' ?></span>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2"/></svg></span>
                    <h3>Provider &amp; credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="sa-check-label">
                            <input type="checkbox" name="wa_api_enabled" value="1" <?= $waOn ? 'checked' : '' ?>>
                            Enable WhatsApp API messaging
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="wa_api_provider">
                            <?php foreach (['meta' => 'Meta Cloud API', 'twilio' => 'Twilio', 'gupshup' => 'Gupshup', 'custom' => 'Custom HTTP'] as $k => $lab): ?>
                            <option value="<?= $k ?>" <?= ($msg['wa_api_provider'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>WhatsApp number</label>
                        <input type="text" name="wa_api_number" value="<?= e($msg['wa_api_number']) ?>" placeholder="919876543210">
                    </div>
                    <div class="form-group">
                        <label>API Key / App ID</label>
                        <input type="text" name="wa_api_key" value="<?= e($msg['wa_api_key']) ?>" placeholder="API key or App ID" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Access Token</label>
                        <div class="password-field">
                            <input type="password" name="wa_api_token" value="" placeholder="<?= $msg['wa_api_token'] !== '' ? 'Leave blank to keep current' : 'Bearer / access token' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($msg['wa_api_token'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(messaging_mask_secret($msg['wa_api_token'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Custom endpoint (optional)</label>
                        <input type="url" name="wa_api_endpoint" value="<?= e($msg['wa_api_endpoint']) ?>" placeholder="https://graph.facebook.com/v19.0/.../messages">
                    </div>
                </div>
                <div class="settings-info">
                    <span class="si-ico">i</span>
                    <p>Credentials are stored for this install. Message send hooks can use these later for OTP / order alerts.</p>
                </div>
            </div>

            <div class="settings-card-foot">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><polyline points="20 6 9 17 4 12"/></svg>
                    Save WhatsApp settings
                </button>
                <button type="submit" name="action" value="test" class="btn btn-outline" formnovalidate>
                    Test connection
                </button>
                <input type="hidden" name="test_type" value="whatsapp">
            </div>
            <p class="sa-field-hint">Test uses last saved settings (save first if you changed fields).</p>
        </form>

        <?php elseif ($tab === 'sms'): ?>
        <form method="post" class="settings-card" autocomplete="off">
            <input type="hidden" name="tab" value="sms">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico pink">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    </span>
                    <div>
                        <h2>SMS API</h2>
                        <p>Transactional SMS via MSG91, Twilio, Textlocal, and more.</p>
                    </div>
                </div>
                <span class="status-pill <?= $smsOn ? 'online' : 'offline' ?>"><?= $smsOn ? 'Enabled' : 'Disabled' ?></span>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                    <h3>Provider &amp; credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="sa-check-label">
                            <input type="checkbox" name="sms_api_enabled" value="1" <?= $smsOn ? 'checked' : '' ?>>
                            Enable SMS API messaging
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="sms_api_provider">
                            <?php foreach (['msg91' => 'MSG91', 'twilio' => 'Twilio', 'textlocal' => 'Textlocal', 'fast2sms' => 'Fast2SMS', 'custom' => 'Custom HTTP'] as $k => $lab): ?>
                            <option value="<?= $k ?>" <?= ($msg['sms_api_provider'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sender ID</label>
                        <input type="text" name="sms_api_sender_id" value="<?= e($msg['sms_api_sender_id']) ?>" placeholder="e.g. MYCOMP" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>API Key / Account SID</label>
                        <div class="password-field">
                            <input type="password" name="sms_api_key" value="" placeholder="<?= $msg['sms_api_key'] !== '' ? 'Leave blank to keep current' : 'API key or Account SID' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($msg['sms_api_key'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(messaging_mask_secret($msg['sms_api_key'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Auth Token (Twilio etc.)</label>
                        <div class="password-field">
                            <input type="password" name="sms_api_auth_token" value="" placeholder="<?= $msg['sms_api_auth_token'] !== '' ? 'Leave blank to keep current' : 'Optional auth token' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($msg['sms_api_auth_token'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(messaging_mask_secret($msg['sms_api_auth_token'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Custom endpoint (optional)</label>
                        <input type="url" name="sms_api_endpoint" value="<?= e($msg['sms_api_endpoint']) ?>" placeholder="https://api.msg91.com/api/v5/flow/">
                    </div>
                </div>
            </div>

            <div class="settings-card-foot">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><polyline points="20 6 9 17 4 12"/></svg>
                    Save SMS settings
                </button>
                <button type="submit" name="action" value="test" class="btn btn-outline" formnovalidate>
                    Test connection
                </button>
                <input type="hidden" name="test_type" value="sms">
            </div>
            <p class="sa-field-hint">Test uses last saved settings (save first if you changed fields).</p>
        </form>

        <?php elseif ($tab === 'payment'): ?>
        <form method="post" class="settings-card" autocomplete="off" id="paymentGatewayForm">
            <input type="hidden" name="tab" value="payment">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    <div>
                        <h2>Payment Gateway</h2>
                        <p>Choose one provider and enter its API credentials. Checkout can use these later.</p>
                    </div>
                </div>
                <span class="status-pill <?= $payOn ? 'online' : 'offline' ?>"><?= $payOn ? 'Enabled' : 'Disabled' ?></span>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
                    <h3>Active provider</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="sa-check-label">
                            <input type="checkbox" name="payment_gateway_enabled" value="1" <?= ($pay['payment_gateway_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            Enable online payments
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Gateway</label>
                        <select name="payment_gateway_provider" id="paymentGatewayProvider">
                            <?php foreach ($payProviders as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($pay['payment_gateway_provider'] ?? 'none') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode</label>
                        <select name="payment_gateway_mode">
                            <option value="sandbox" <?= ($pay['payment_gateway_mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox / Test</option>
                            <option value="live" <?= ($pay['payment_gateway_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live / Production</option>
                        </select>
                    </div>
                </div>
                <p class="sa-field-hint">Current: <strong><?= e(payment_gateway_active_label($pay)) ?></strong></p>
            </div>

            <div class="settings-section pay-gw-panel" data-gateway="cashfree" hidden>
                <div class="settings-section-head">
                    <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    <h3>Cashfree credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group">
                        <label>App ID / Client ID</label>
                        <input type="text" name="pay_cashfree_app_id" value="<?= e($pay['pay_cashfree_app_id']) ?>" placeholder="Cashfree App ID" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <div class="password-field">
                            <input type="password" name="pay_cashfree_secret_key" value="" placeholder="<?= $pay['pay_cashfree_secret_key'] !== '' ? 'Leave blank to keep current' : 'Secret key' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($pay['pay_cashfree_secret_key'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(payment_mask_secret($pay['pay_cashfree_secret_key'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>API version</label>
                        <input type="text" name="pay_cashfree_api_version" value="<?= e($pay['pay_cashfree_api_version']) ?>" placeholder="2023-08-01">
                    </div>
                </div>
            </div>

            <div class="settings-section pay-gw-panel" data-gateway="razorpay" hidden>
                <div class="settings-section-head">
                    <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    <h3>Razorpay credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group">
                        <label>Key ID</label>
                        <input type="text" name="pay_razorpay_key_id" value="<?= e($pay['pay_razorpay_key_id']) ?>" placeholder="rzp_test_…" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Key Secret</label>
                        <div class="password-field">
                            <input type="password" name="pay_razorpay_key_secret" value="" placeholder="<?= $pay['pay_razorpay_key_secret'] !== '' ? 'Leave blank to keep current' : 'Key secret' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($pay['pay_razorpay_key_secret'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(payment_mask_secret($pay['pay_razorpay_key_secret'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Webhook secret (optional)</label>
                        <div class="password-field">
                            <input type="password" name="pay_razorpay_webhook_secret" value="" placeholder="<?= $pay['pay_razorpay_webhook_secret'] !== '' ? 'Leave blank to keep current' : 'whsec_…' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($pay['pay_razorpay_webhook_secret'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(payment_mask_secret($pay['pay_razorpay_webhook_secret'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="settings-section pay-gw-panel" data-gateway="paytm" hidden>
                <div class="settings-section-head">
                    <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    <h3>Paytm credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group">
                        <label>Merchant ID</label>
                        <input type="text" name="pay_paytm_merchant_id" value="<?= e($pay['pay_paytm_merchant_id']) ?>" placeholder="Merchant ID" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Merchant Key</label>
                        <div class="password-field">
                            <input type="password" name="pay_paytm_merchant_key" value="" placeholder="<?= $pay['pay_paytm_merchant_key'] !== '' ? 'Leave blank to keep current' : 'Merchant key' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($pay['pay_paytm_merchant_key'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(payment_mask_secret($pay['pay_paytm_merchant_key'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="pay_paytm_website" value="<?= e($pay['pay_paytm_website']) ?>" placeholder="WEBSTAGING / DEFAULT">
                    </div>
                    <div class="form-group">
                        <label>Industry type</label>
                        <input type="text" name="pay_paytm_industry_type" value="<?= e($pay['pay_paytm_industry_type']) ?>" placeholder="Retail">
                    </div>
                    <div class="form-group">
                        <label>Channel ID</label>
                        <input type="text" name="pay_paytm_channel_id" value="<?= e($pay['pay_paytm_channel_id']) ?>" placeholder="WEB">
                    </div>
                </div>
            </div>

            <div class="settings-section pay-gw-panel" data-gateway="phonepe" hidden>
                <div class="settings-section-head">
                    <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    <h3>PhonePe credentials</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group">
                        <label>Merchant ID</label>
                        <input type="text" name="pay_phonepe_merchant_id" value="<?= e($pay['pay_phonepe_merchant_id']) ?>" placeholder="Merchant ID" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Salt key</label>
                        <div class="password-field">
                            <input type="password" name="pay_phonepe_salt_key" value="" placeholder="<?= $pay['pay_phonepe_salt_key'] !== '' ? 'Leave blank to keep current' : 'Salt key' ?>" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if ($pay['pay_phonepe_salt_key'] !== ''): ?>
                        <span class="sa-field-hint">Saved: <?= e(payment_mask_secret($pay['pay_phonepe_salt_key'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Salt index</label>
                        <input type="text" name="pay_phonepe_salt_index" value="<?= e($pay['pay_phonepe_salt_index']) ?>" placeholder="1">
                    </div>
                    <div class="form-group">
                        <label>Client ID (optional)</label>
                        <input type="text" name="pay_phonepe_client_id" value="<?= e($pay['pay_phonepe_client_id']) ?>" placeholder="Client ID" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="settings-info">
                <span class="si-ico">i</span>
                <p>Only one gateway is active at a time. Secrets stay saved if you leave the password fields blank. Live checkout / webhooks can be wired next where payments are needed.</p>
            </div>

            <div class="settings-card-foot">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><polyline points="20 6 9 17 4 12"/></svg>
                    Save payment settings
                </button>
            </div>
        </form>

        <?php elseif ($tab === 'database'): ?>
        <form method="post" class="settings-card sa-db-card" autocomplete="off">
            <input type="hidden" name="tab" value="database">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 11v6c0 1.7 4 3 9 3s9-1.3 9-3v-6"/></svg>
                    </span>
                    <div>
                        <h2>Online &amp; Offline DB</h2>
                        <p>Cloud and local XAMPP connection used by this <?= e($company) ?> install.</p>
                    </div>
                </div>
                <span class="status-pill online">Access OK</span>
            </div>

            <div class="sa-db-status">
                <strong>Connected to <?= e(db_admin_active_label()) ?>.</strong>
                <span>Mode: <?= e(ucfirst($dbMode)) ?> · <?= e(DB_HOST) ?> / <?= e(DB_NAME) ?></span>
            </div>

            <div class="sa-db-setup-row">
                <div>
                    <h3>Database setup</h3>
                    <p>Verify tables and columns.</p>
                </div>
                <button type="submit" name="action" value="run_setup" class="btn btn-primary" onclick="return confirm('Run database setup now?');">Run database setup</button>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2"/></svg></span>
                    <h3>Connection mode</h3>
                </div>
                <div class="sa-db-mode-grid">
                    <?php
                    $modes = [
                        'auto' => ['Auto', 'Online when available, then offline.'],
                        'online' => ['Online only', 'Always use cloud server.'],
                        'offline' => ['Offline only', 'Always use local XAMPP.'],
                    ];
                    foreach ($modes as $key => [$label, $desc]):
                    ?>
                    <label class="sa-db-mode <?= $dbMode === $key ? 'is-active' : '' ?>">
                        <input type="radio" name="db_mode" value="<?= e($key) ?>" <?= $dbMode === $key ? 'checked' : '' ?>>
                        <strong><?= e($label) ?></strong>
                        <span><?= e($desc) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sa-db-creds-grid">
                <div class="settings-section sa-db-side">
                    <div class="settings-section-head">
                        <span class="ssh-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                        <div>
                            <h3>Online Database</h3>
                            <p class="sa-db-side-sub">Hosting / VPS / cloud MySQL</p>
                        </div>
                    </div>
                    <div class="settings-fields two">
                        <div class="form-group">
                            <label>Host</label>
                            <input type="text" name="online_host" value="<?= e($dbCreds['online']['host']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="text" name="online_port" value="<?= e($dbCreds['online']['port']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Database</label>
                            <input type="text" name="online_name" value="<?= e($dbCreds['online']['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="online_user" value="<?= e($dbCreds['online']['user']) ?>" required autocomplete="off">
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="online_pass" value="" placeholder="<?= $dbCreds['online']['pass'] !== '' ? 'Leave blank to keep current' : 'Database password' ?>" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="action" value="test_online" class="btn btn-outline sa-db-test-btn" formnovalidate>
                        Test online connection
                    </button>
                </div>

                <div class="settings-section sa-db-side">
                    <div class="settings-section-head">
                        <span class="ssh-ico teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                        <div>
                            <h3>Offline Database</h3>
                            <p class="sa-db-side-sub">Local XAMPP / WAMP on this computer</p>
                        </div>
                    </div>
                    <div class="settings-fields two">
                        <div class="form-group">
                            <label>Host</label>
                            <input type="text" name="offline_host" value="<?= e($dbCreds['offline']['host']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="text" name="offline_port" value="<?= e($dbCreds['offline']['port']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Database</label>
                            <input type="text" name="offline_name" value="<?= e($dbCreds['offline']['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="offline_user" value="<?= e($dbCreds['offline']['user']) ?>" required autocomplete="off">
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="offline_pass" value="" placeholder="<?= $dbCreds['offline']['pass'] !== '' ? 'Leave blank to keep current' : 'Usually empty on XAMPP' ?>" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="action" value="test_offline" class="btn btn-outline sa-db-test-btn" formnovalidate>
                        Test offline connection
                    </button>
                </div>
            </div>

            <div class="settings-info">
                <span class="si-ico">i</span>
                <p>Saved in <code>config/db-credentials.php</code>. On a local PC Auto prefers offline; on the live server it prefers online.</p>
            </div>

            <div class="settings-card-foot">
                <button type="submit" name="action" value="save" class="btn btn-primary">Save database settings</button>
            </div>
        </form>

        <?php elseif ($tab === 'backup'): ?>
        <div class="settings-card sa-backup-card">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico teal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    <div>
                        <h2>Backup &amp; Restore</h2>
                        <p>SQL dump for this install. Restore only on a maintenance window.</p>
                    </div>
                </div>
                <span class="status-pill online">Access OK</span>
            </div>

            <div class="sa-backup-stats">
                <article>
                    <span>Active database</span>
                    <strong><?= e(DB_NAME) ?></strong>
                </article>
                <article>
                    <span>Tables included</span>
                    <strong><?= (int) $dbTableCount ?></strong>
                </article>
                <article>
                    <span>Last download</span>
                    <strong><?= $dbLastDownload !== '' ? e(date('d M Y H:i', strtotime($dbLastDownload))) : 'Never' ?></strong>
                </article>
                <article>
                    <span>Last restore</span>
                    <strong><?= $dbLastRestore !== '' ? e(date('d M Y H:i', strtotime($dbLastRestore))) : 'Never' ?></strong>
                </article>
            </div>

            <div class="sa-backup-actions">
                <form method="post" class="sa-backup-panel is-safe">
                    <input type="hidden" name="tab" value="backup">
                    <input type="hidden" name="action" value="download_backup">
                    <span class="sa-backup-badge is-safe">Safe</span>
                    <h3>Download backup</h3>
                    <p>Exports every table in the active database as a <code>.sql</code> file. Store it off this server.</p>
                    <ul>
                        <li>Structure and data for all tables</li>
                        <li>Filename like <code><?= e($backupSampleName) ?></code></li>
                        <li>Does not change anything on this install</li>
                    </ul>
                    <button type="submit" class="btn btn-primary">Download SQL backup</button>
                </form>

                <form method="post" enctype="multipart/form-data" class="sa-backup-panel is-danger" id="restoreBackupForm">
                    <input type="hidden" name="tab" value="backup">
                    <input type="hidden" name="action" value="restore_backup">
                    <span class="sa-backup-badge is-danger">Destructive</span>
                    <h3>Restore backup</h3>
                    <p>Replaces current data with the uploaded dump. Members, wallets and settings will match the file.</p>
                    <label class="sa-backup-drop" for="backupFileInput">
                        <span class="sa-backup-drop-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </span>
                        <strong>Drop a .sql backup here</strong>
                        <span>or click to browse · max 32 MB · dumps from this page only</span>
                        <span class="btn btn-outline sa-backup-browse">Browse file</span>
                        <input type="file" name="backup_file" id="backupFileInput" accept=".sql,application/sql,text/plain" required>
                        <em class="sa-backup-file-name" id="backupFileName"></em>
                    </label>
                    <label class="sa-check-label sa-backup-confirm">
                        <input type="checkbox" name="confirm_restore" value="1" required>
                        I understand this overwrites the current database.
                    </label>
                    <button type="submit" class="btn sa-backup-restore-btn" onclick="return confirm('Restore will overwrite the current database. Continue?');">Restore now</button>
                </form>
            </div>

            <div class="settings-info">
                <span class="si-ico">i</span>
                <p>Only restore files created from this page. Super Admin access stays after a restore — you may need to sign in again. Active connection is <code><?= e(DB_NAME) ?></code>.</p>
            </div>
        </div>

        <?php elseif ($tab === 'wipe'): ?>
        <?php
            $wipeRoot = is_array($wipePreview['root'] ?? null) ? $wipePreview['root'] : null;
            $wipeMembers = (int) ($wipePreview['member_count'] ?? 0);
            $wipeRemove = max(0, $wipeMembers - ($wipeRoot ? 1 : 0));
        ?>
        <div class="settings-card sa-wipe-card">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    </span>
                    <div>
                        <h2>Clear Data</h2>
                        <p>One-click wipe for client handoff. Keeps admin + root member only.</p>
                    </div>
                </div>
                <span class="status-pill <?= !empty($wipePreview['ok']) ? 'online' : 'offline' ?>"><?= !empty($wipePreview['ok']) ? 'Ready' : 'Blocked' ?></span>
            </div>

            <div class="sa-wipe-banner">
                <strong>Destructive — cannot undo.</strong>
                <span>Download a backup first from Backup &amp; Restore if you may need this data again.</span>
            </div>

            <div class="sa-wipe-stats">
                <article>
                    <span>Client admins kept</span>
                    <strong><?= (int) $wipeAdminCount ?></strong>
                </article>
                <article>
                    <span>Root member kept</span>
                    <strong><?= $wipeRoot ? e((string) $wipeRoot['member_id']) : '—' ?></strong>
                    <?php if ($wipeRoot): ?>
                    <small><?= e((string) ($wipeRoot['full_name'] ?? '')) ?></small>
                    <?php endif; ?>
                </article>
                <article>
                    <span>Members to remove</span>
                    <strong><?= (int) $wipeRemove ?></strong>
                </article>
                <article>
                    <span>Total members now</span>
                    <strong><?= (int) $wipeMembers ?></strong>
                </article>
            </div>

            <div class="sa-wipe-grid">
                <div class="sa-wipe-panel is-keep">
                    <span class="sa-backup-badge is-safe">Kept</span>
                    <h3>What stays</h3>
                    <ul>
                        <li>Super Admin + Client Admin accounts</li>
                        <li>Root member login (wallets / BV reset to 0)</li>
                        <li>Settings, branding, plan &amp; commission rates</li>
                        <li>Packages, products, geo, banks, franchise types</li>
                    </ul>
                </div>
                <div class="sa-wipe-panel is-clear">
                    <span class="sa-backup-badge is-danger">Cleared</span>
                    <h3>What is deleted</h3>
                    <ul>
                        <li>All members except root</li>
                        <li>Wallets, ledger, topups, transfers</li>
                        <li>Income / commissions / withdrawals / closing</li>
                        <li>T-PIN, KYC, orders, stock purchases, franchisees, logs</li>
                    </ul>
                </div>
            </div>

            <form method="post" class="sa-wipe-form" autocomplete="off">
                <input type="hidden" name="tab" value="wipe">
                <input type="hidden" name="action" value="wipe_data">
                <div class="settings-section">
                    <div class="settings-section-head">
                        <span class="ssh-ico orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                        <h3>Confirm wipe</h3>
                    </div>
                    <div class="settings-fields two">
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="sa-check-label">
                                <input type="checkbox" name="confirm_wipe_check" value="1" required <?= empty($wipePreview['ok']) ? 'disabled' : '' ?>>
                                I understand this permanently deletes member, wallet, income and report data.
                            </label>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Type <kbd>CLEAR DATA</kbd> to confirm</label>
                            <input type="text" name="confirm_wipe" autocomplete="off" placeholder="CLEAR DATA" required <?= empty($wipePreview['ok']) ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>
                <div class="settings-card-foot">
                    <button type="submit" class="btn sa-wipe-btn" <?= empty($wipePreview['ok']) ? 'disabled' : '' ?>
                        onclick="return confirm('Clear ALL member data except admin + root? This cannot be undone.');">
                        Clear all data now
                    </button>
                    <a class="btn btn-outline" href="settings.php?tab=backup">Go to Backup first</a>
                </div>
            </form>
        </div>

        <?php elseif ($tab === 'security'): ?>
        <form method="post" class="settings-card" autocomplete="off">
            <input type="hidden" name="tab" value="security">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico teal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    </span>
                    <div>
                        <h2>Security</h2>
                        <p>Change Super Admin login password.</p>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-head">
                    <span class="ssh-ico green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    <h3>Admin Password</h3>
                </div>
                <div class="settings-fields two">
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="current_password">Current Password</label>
                        <div class="password-field">
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password" placeholder="Current password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-field">
                            <input type="password" id="new_password" name="new_password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password" placeholder="Repeat new password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="settings-info">
                    <span class="si-ico">i</span>
                    <p>Minimum 8 characters. Don’t reuse the default <code>superadmin123</code>. Logout on shared devices after changing.</p>
                </div>
            </div>

            <div class="settings-card-foot">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><polyline points="20 6 9 17 4 12"/></svg>
                    Update password
                </button>
            </div>
        </form>

        <?php else: ?>
        <div class="settings-card">
            <div class="settings-card-head">
                <div class="settings-title-block">
                    <span class="settings-title-ico orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </span>
                    <div>
                        <h2>Activity Log</h2>
                        <p>Recent Super Admin actions on this install.</p>
                    </div>
                </div>
            </div>
            <div class="table-wrap" style="border:0;border-radius:0;box-shadow:none">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$logs): ?>
                        <tr><td colspan="4" class="empty-state">No Super Admin activity yet.</td></tr>
                    <?php else: foreach ($logs as $l):
                        $act = (string) ($l['action'] ?? '');
                        $act = str_starts_with($act, 'superadmin:') ? substr($act, 11) : $act;
                    ?>
                        <tr>
                            <td><span class="pkg-chip"><?= e($act) ?></span></td>
                            <td><?= e((string) ($l['details'] ?? '')) ?></td>
                            <td><span class="muted"><?= e((string) ($l['ip_address'] ?? '')) ?></span></td>
                            <td><span class="muted"><?= !empty($l['created_at']) ? e(date('d M Y H:i', strtotime((string) $l['created_at']))) : '—' ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.password-field');
        var input = wrap && wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.setAttribute('title', show ? 'Hide password' : 'Show password');
    });
});

(function () {
    var sel = document.getElementById('paymentGatewayProvider');
    if (!sel) return;
    var panels = document.querySelectorAll('.pay-gw-panel');
    function sync() {
        var v = sel.value;
        panels.forEach(function (p) {
            var show = p.getAttribute('data-gateway') === v;
            p.hidden = !show;
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();

document.querySelectorAll('.sa-db-mode-grid').forEach(function (grid) {
    grid.querySelectorAll('.sa-db-mode').forEach(function (card) {
        card.addEventListener('change', function () {
            grid.querySelectorAll('.sa-db-mode').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
        });
    });
});

(function () {
    var input = document.getElementById('backupFileInput');
    var nameEl = document.getElementById('backupFileName');
    if (!input || !nameEl) return;
    input.addEventListener('change', function () {
        nameEl.textContent = input.files && input.files[0] ? input.files[0].name : '';
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

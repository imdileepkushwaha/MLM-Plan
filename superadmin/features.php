<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Plan & Features';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'preset') {
        $preset = trim((string) ($_POST['preset'] ?? ''));
        if (feature_apply_preset($pdo, $preset)) {
            log_superadmin_activity('feature_preset', 'Applied preset: ' . $preset);
            flash('success', 'Preset applied. Client Admin & User menus will update immediately.');
        } else {
            flash('error', 'Unknown preset.');
        }
    } else {
        feature_save_from_post($pdo, $_POST);
        log_superadmin_activity('feature_save', 'Updated plan mode / feature flags');
        flash('success', 'Features saved. Disabled modules are hidden from Client Admin and User panels.');
    }

    header('Location: features.php');
    exit;
}

require __DIR__ . '/includes/header.php';

$presets = feature_presets();
$currentPreset = setting('feature_preset', 'hybrid_full');
$mode = plan_mode();

$secIco = static function (string $d): string {
    return '<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . $d . '</svg></span>';
};
?>
<section class="sa-hero">
    <div>
        <span class="sa-hero-kicker">Client package</span>
        <h1>Plan &amp; Features</h1>
        <p>Apply a ready preset in one click, or fine-tune activation rails and income modules for this install.</p>
    </div>
</section>

<div class="sa-panel">
    <div class="sa-panel-head">
        <div>
            <h2>Quick presets</h2>
            <p>One-click templates for common client types</p>
        </div>
    </div>
    <div class="sa-panel-body">
        <div class="sa-preset-grid">
            <?php foreach ($presets as $key => $p): ?>
            <form method="post" class="sa-preset-card <?= $currentPreset === $key ? 'is-active' : '' ?>">
                <input type="hidden" name="action" value="preset">
                <input type="hidden" name="preset" value="<?= e($key) ?>">
                <?php if ($currentPreset === $key): ?>
                <span class="sa-preset-badge">Active</span>
                <?php endif; ?>
                <strong><?= e($p['label']) ?></strong>
                <p><?= e($p['description']) ?></p>
                <button type="submit" class="btn btn-sm <?= $currentPreset === $key ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $currentPreset === $key ? 'Currently active' : 'Apply preset' ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form method="post" class="sa-panel">
    <input type="hidden" name="action" value="save">
    <div class="sa-panel-head">
        <div>
            <h2>Custom configuration</h2>
            <p>Overrides become preset “custom” after save</p>
        </div>
    </div>
    <div class="sa-panel-body">
        <h3 class="sa-section-title"><?= $secIco('<circle cx="12" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><circle cx="18" cy="19" r="3"/>') ?> Plan mode</h3>
        <div class="sa-mode-grid">
            <label class="sa-mode-opt <?= $mode === 'hybrid' ? 'is-on' : '' ?>">
                <input type="radio" name="plan_mode" value="hybrid" <?= $mode === 'hybrid' ? 'checked' : '' ?>>
                <strong>Hybrid</strong>
                <small>Binary tree + Level income together</small>
            </label>
            <label class="sa-mode-opt <?= $mode === 'binary' ? 'is-on' : '' ?>">
                <input type="radio" name="plan_mode" value="binary" <?= $mode === 'binary' ? 'checked' : '' ?>>
                <strong>Binary only</strong>
                <small>Left/Right tree &amp; pair closing focus</small>
            </label>
            <label class="sa-mode-opt <?= $mode === 'level' ? 'is-on' : '' ?>">
                <input type="radio" name="plan_mode" value="level" <?= $mode === 'level' ? 'checked' : '' ?>>
                <strong>Level only</strong>
                <small>Sponsor levels — hide binary tree / closing</small>
            </label>
        </div>

        <h3 class="sa-section-title"><?= $secIco('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h4M7 13h10"/>') ?> Activation</h3>
        <div class="sa-checks">
            <label><input type="checkbox" name="feature_package_enabled" value="1" <?= feature_enabled('feature_package_enabled') ? 'checked' : '' ?>> Packages</label>
            <label><input type="checkbox" name="feature_tpin_enabled" value="1" <?= feature_enabled('feature_tpin_enabled') ? 'checked' : '' ?>> T-PIN / E-Pin</label>
            <label><input type="checkbox" name="feature_utr_activation_enabled" value="1" <?= feature_enabled('feature_utr_activation_enabled') ? 'checked' : '' ?>> UTR activation requests</label>
            <label><input type="checkbox" name="feature_wallet_topup_enabled" value="1" <?= feature_enabled('feature_wallet_topup_enabled') ? 'checked' : '' ?>> Wallet topup activate</label>
            <label><input type="checkbox" name="feature_product_shop_enabled" value="1" <?= feature_enabled('feature_product_shop_enabled') ? 'checked' : '' ?>> Product shop</label>
        </div>

        <h3 class="sa-section-title"><?= $secIco('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>') ?> Income modules</h3>
        <div class="sa-checks">
            <label><input type="checkbox" name="feature_binary_income" value="1" <?= feature_enabled('feature_binary_income') ? 'checked' : '' ?>> Binary income</label>
            <label><input type="checkbox" name="feature_level_income" value="1" <?= feature_enabled('feature_level_income') ? 'checked' : '' ?>> Level income</label>
            <label><input type="checkbox" name="feature_referral_income" value="1" <?= feature_enabled('feature_referral_income') ? 'checked' : '' ?>> Referral income</label>
            <label><input type="checkbox" name="feature_matching_income" value="1" <?= feature_enabled('feature_matching_income') ? 'checked' : '' ?>> Matching income</label>
        </div>

        <h3 class="sa-section-title"><?= $secIco('<circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>') ?> Operations</h3>
        <div class="sa-checks">
            <label><input type="checkbox" name="feature_withdrawals_enabled" value="1" <?= feature_enabled('feature_withdrawals_enabled') ? 'checked' : '' ?>> Withdrawals</label>
            <label><input type="checkbox" name="feature_kyc_enabled" value="1" <?= feature_enabled('feature_kyc_enabled') ? 'checked' : '' ?>> KYC</label>
            <label><input type="checkbox" name="feature_utility_enabled" value="1" <?= feature_enabled('feature_utility_enabled') ? 'checked' : '' ?>> Utility management</label>
            <label><input type="checkbox" name="feature_reports_enabled" value="1" <?= feature_enabled('feature_reports_enabled') ? 'checked' : '' ?>> Reports</label>
        </div>

        <div class="sa-form-actions">
            <button type="submit" class="btn btn-primary">Save configuration</button>
            <a href="index.php" class="btn btn-outline">Back to dashboard</a>
        </div>
    </div>
</form>
<script>
document.querySelectorAll('.sa-mode-opt input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('.sa-mode-opt').forEach(function (el) { el.classList.remove('is-on'); });
        if (input.checked && input.closest('.sa-mode-opt')) {
            input.closest('.sa-mode-opt').classList.add('is-on');
        }
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Client Branding';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['company_name', 'support_email', 'currency', 'currency_symbol', 'member_id_prefix', 'member_id_pad'];
    foreach ($keys as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $val = trim((string) $_POST[$key]);
        if ($key === 'member_id_prefix') {
            $val = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $val) ?? '');
            if ($val === '') {
                $val = 'MLM';
            }
            $val = substr($val, 0, 10);
        }
        if ($key === 'member_id_pad') {
            $val = (string) max(3, min(8, (int) $val));
        }
        feature_save($pdo, $key, $val);
    }
    clear_setting_cache();
    log_superadmin_activity('branding_save', 'Updated client branding');
    flash('success', 'Client branding updated.');
    header('Location: branding.php');
    exit;
}

require __DIR__ . '/includes/header.php';

$coName = setting('company_name', 'Binary MLM');
$prefix = setting('member_id_prefix', 'MLM');
$pad = (int) setting('member_id_pad', '5');
$sampleId = $prefix . str_pad('1', $pad, '0', STR_PAD_LEFT);
$currency = setting('currency', 'INR');
$symbol = setting('currency_symbol', '₹');
?>
<section class="sa-hero">
    <div>
        <span class="sa-hero-kicker">White-label</span>
        <h1>Client Branding</h1>
        <p>Company name, currency and member ID format shown across Admin &amp; User panels.</p>
    </div>
</section>

<form method="post" class="sa-brand-layout">
    <div class="sa-panel" style="margin:0">
        <div class="sa-panel-head">
            <div>
                <h2>Brand settings</h2>
                <p>Visible to the client’s members and admins</p>
            </div>
        </div>
        <div class="sa-panel-body">
            <div class="sa-form-grid">
                <div class="form-group span-2">
                    <label>Company name</label>
                    <input type="text" name="company_name" id="saBrandName" value="<?= e($coName) ?>" required>
                </div>
                <div class="form-group span-2">
                    <label>Support email</label>
                    <input type="email" name="support_email" value="<?= e(setting('support_email', '')) ?>">
                </div>
                <div class="form-group">
                    <label>Currency code</label>
                    <input type="text" name="currency" id="saCurrency" value="<?= e($currency) ?>">
                </div>
                <div class="form-group">
                    <label>Currency symbol</label>
                    <input type="text" name="currency_symbol" id="saSymbol" value="<?= e($symbol) ?>">
                </div>
                <div class="form-group">
                    <label>Member ID prefix</label>
                    <input type="text" name="member_id_prefix" id="saPrefix" value="<?= e($prefix) ?>" maxlength="10">
                    <span class="sa-field-hint">Letters/numbers only, max 10</span>
                </div>
                <div class="form-group">
                    <label>Member ID pad</label>
                    <input type="number" min="3" max="8" name="member_id_pad" id="saPad" value="<?= e((string) $pad) ?>">
                    <span class="sa-field-hint">Digits after prefix (3–8)</span>
                </div>
            </div>
            <div class="sa-form-actions">
                <button type="submit" class="btn btn-primary">Save branding</button>
            </div>
        </div>
    </div>

    <aside class="sa-panel" style="margin:0">
        <div class="sa-panel-head">
            <div>
                <h2>Live preview</h2>
                <p>How it may appear on panels</p>
            </div>
        </div>
        <div class="sa-panel-body">
            <div class="sa-brand-preview">
                <span>Company</span>
                <strong id="saPreviewName"><?= e($coName) ?></strong>
                <em>Sample ID · <span id="saPreviewId"><?= e($sampleId) ?></span> · <span id="saPreviewCur"><?= e($symbol . ' / ' . $currency) ?></span></em>
            </div>
            <div class="sa-note" style="margin-top:1rem">Changes apply immediately after save on both Client Admin and User panels.</div>
        </div>
    </aside>
</form>
<script>
(function () {
    var name = document.getElementById('saBrandName');
    var prefix = document.getElementById('saPrefix');
    var pad = document.getElementById('saPad');
    var cur = document.getElementById('saCurrency');
    var sym = document.getElementById('saSymbol');
    var pName = document.getElementById('saPreviewName');
    var pId = document.getElementById('saPreviewId');
    var pCur = document.getElementById('saPreviewCur');
    function sync() {
        if (pName && name) pName.textContent = name.value || 'Company';
        var pre = (prefix && prefix.value ? prefix.value : 'MLM').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10) || 'MLM';
        var n = Math.max(3, Math.min(8, parseInt(pad && pad.value ? pad.value : '5', 10) || 5));
        if (pId) pId.textContent = pre + String(1).padStart(n, '0');
        if (pCur) pCur.textContent = ((sym && sym.value) || '₹') + ' / ' + ((cur && cur.value) || 'INR');
    }
    [name, prefix, pad, cur, sym].forEach(function (el) {
        if (el) el.addEventListener('input', sync);
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

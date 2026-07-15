<?php
$pageTitle = 'Activate Account';
require_once __DIR__ . '/../includes/activation.php';
require_once __DIR__ . '/includes/auth.php';
require_user();

$user = current_user($pdo);
if (!$user || ($user['status'] ?? '') === 'blocked') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
    header('Location: login.php');
    exit;
}

if (!empty($user['package_id'])) {
    flash('success', 'Your account is already activated with ' . ($user['package_name'] ?? 'a package') . '.');
    header('Location: index.php');
    exit;
}

$errors = [];
$packages = activation_packages($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = (int) ($_POST['package_id'] ?? 0);
    if ($packageId <= 0) {
        $errors[] = 'Please select a package to activate.';
    } else {
        $result = activation_apply($pdo, $user, $packageId);
        if ($result['ok']) {
            $pkgName = $result['package']['name'] ?? 'package';
            flash('success', "Account activated successfully with {$pkgName}!");
            header('Location: index.php');
            exit;
        }
        $errors[] = $result['error'] ?? 'Activation failed.';
        $user = current_user($pdo, true) ?? $user;
    }
}

require_once __DIR__ . '/includes/header.php';

$selected = (int) ($_POST['package_id'] ?? 0);
if ($selected <= 0 && $packages) {
    // Prefer middle / highest popular slot
    $mid = (int) floor(count($packages) / 2);
    $selected = (int) ($packages[$mid]['id'] ?? $packages[0]['id']);
}

$selectedPkg = null;
foreach ($packages as $pkg) {
    if ((int) $pkg['id'] === $selected) {
        $selectedPkg = $pkg;
        break;
    }
}
if (!$selectedPkg && $packages) {
    $selectedPkg = $packages[0];
    $selected = (int) $selectedPkg['id'];
}

$featuredId = $packages ? (int) ($packages[(int) floor(count($packages) / 2)]['id'] ?? 0) : 0;
?>
<div class="up-page-head">
    <div>
        <h1>Activate Account</h1>
        <p>Pick a package to go live and unlock your full member dashboard.</p>
    </div>
    <a href="index.php" class="up-btn up-btn-outline">Back to Dashboard</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="up-alert up-alert-err"><?= e($err) ?></div>
<?php endforeach; ?>

<section class="actx-hero">
    <div class="actx-hero-copy">
        <span class="actx-kicker">Membership activation</span>
        <h2>Choose your growth plan</h2>
        <p>One selection activates your ID, assigns business volume, and unlocks Active status across the panel.</p>
        <ol class="actx-steps">
            <li class="is-on"><span>1</span> Select package</li>
            <li><span>2</span> Confirm &amp; activate</li>
            <li><span>3</span> Start earning</li>
        </ol>
    </div>
    <aside class="actx-hero-user">
        <div class="actx-user-top">
            <?= user_avatar_html($user, 'up-avatar actx-avatar', false) ?>
            <div class="actx-user-meta">
                <strong><?= e($user['full_name']) ?></strong>
                <small><?= e($user['member_id']) ?> · @<?= e($user['username']) ?></small>
            </div>
        </div>
        <div class="actx-user-stats">
            <div class="actx-user-stat">
                <span>Status</span>
                <strong class="actx-pill is-wait">Inactive</strong>
            </div>
            <div class="actx-user-stat">
                <span>Package</span>
                <strong>Not assigned</strong>
            </div>
            <div class="actx-user-stat">
                <span>Wallet</span>
                <strong class="actx-wallet"><?= currency((float) $user['wallet_balance']) ?></strong>
            </div>
        </div>
    </aside>
</section>

<?php if (!$packages): ?>
    <section class="actx-empty">
        <span class="actx-empty-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </span>
        <strong>No packages available</strong>
        <p>Please contact admin to enable activation packages.</p>
        <a href="index.php" class="up-btn up-btn-outline">Return to Dashboard</a>
    </section>
<?php else: ?>
<form method="post" class="actx-form" id="actxForm">
    <div class="actx-section-head">
        <h3>Available packages</h3>
        <p>Tap a card to select — highlighted plan will be activated.</p>
    </div>

    <div class="actx-grid">
        <?php foreach ($packages as $i => $pkg):
            $pid = (int) $pkg['id'];
            $isOn = $selected === $pid;
            $isFeatured = $featuredId === $pid;
            $tones = ['tone-a', 'tone-b', 'tone-c', 'tone-d'];
            $tone = $tones[$i % count($tones)];
            $pricePlain = html_entity_decode(strip_tags(currency((float) $pkg['amount'])), ENT_QUOTES, 'UTF-8');
            ?>
            <label class="actx-card <?= e($tone) ?><?= $isOn ? ' is-on' : '' ?><?= $isFeatured ? ' is-featured' : '' ?>" data-actx-card
                   data-name="<?= e($pkg['name']) ?>"
                   data-price="<?= e($pricePlain) ?>"
                   data-bv="<?= e(number_format((float) $pkg['bv'], 0)) ?>"
                   data-days="<?= (int) $pkg['validity_days'] ?>">
                <input type="radio" name="package_id" value="<?= $pid ?>" <?= $isOn ? 'checked' : '' ?> required>
                <?php if ($isFeatured): ?>
                    <span class="actx-badge">Popular</span>
                <?php endif; ?>
                <span class="actx-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span class="actx-card-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                </span>
                <span class="actx-name"><?= e($pkg['name']) ?></span>
                <span class="actx-price"><?= currency((float) $pkg['amount']) ?></span>
                <span class="actx-stats">
                    <span><small>BV</small><strong><?= number_format((float) $pkg['bv'], 0) ?></strong></span>
                    <span><small>ROI / day</small><strong><?= number_format((float) $pkg['daily_roi'], 2) ?>%</strong></span>
                    <span><small>Validity</small><strong><?= (int) $pkg['validity_days'] ?>d</strong></span>
                </span>
                <?php if (!empty($pkg['description'])): ?>
                    <span class="actx-desc"><?= e($pkg['description']) ?></span>
                <?php else: ?>
                    <span class="actx-desc">Full access to team tools, wallet &amp; withdrawal after activation.</span>
                <?php endif; ?>
                <span class="actx-select-label"><?= $isOn ? 'Selected' : 'Select plan' ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="actx-bar">
        <div class="actx-bar-summary">
            <span class="actx-bar-label">Selected plan</span>
            <strong id="actxSumName"><?= e($selectedPkg['name'] ?? '—') ?></strong>
            <div class="actx-bar-meta">
                <span id="actxSumPrice"><?= $selectedPkg ? currency((float) $selectedPkg['amount']) : '—' ?></span>
                <span>·</span>
                <span>BV <em id="actxSumBv"><?= $selectedPkg ? number_format((float) $selectedPkg['bv'], 0) : '0' ?></em></span>
                <span>·</span>
                <span><em id="actxSumDays"><?= $selectedPkg ? (int) $selectedPkg['validity_days'] : 0 ?></em> days</span>
            </div>
        </div>
        <button type="submit" class="actx-submit">
            <span>Activate My Account</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
        </button>
    </div>
</form>
<script>
(function () {
    const cards = document.querySelectorAll('[data-actx-card]');
    const nameEl = document.getElementById('actxSumName');
    const priceEl = document.getElementById('actxSumPrice');
    const bvEl = document.getElementById('actxSumBv');
    const daysEl = document.getElementById('actxSumDays');

    function sync(card) {
        cards.forEach((c) => {
            const on = c === card;
            c.classList.toggle('is-on', on);
            const lbl = c.querySelector('.actx-select-label');
            if (lbl) lbl.textContent = on ? 'Selected' : 'Select plan';
        });
        if (nameEl) nameEl.textContent = card.getAttribute('data-name') || '—';
        if (priceEl) priceEl.textContent = card.getAttribute('data-price') || '—';
        if (bvEl) bvEl.textContent = card.getAttribute('data-bv') || '0';
        if (daysEl) daysEl.textContent = card.getAttribute('data-days') || '0';
    }

    cards.forEach((card) => {
        const input = card.querySelector('input');
        input.addEventListener('change', () => sync(card));
        card.addEventListener('click', () => {
            if (!input.checked) {
                input.checked = true;
                sync(card);
            }
        });
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

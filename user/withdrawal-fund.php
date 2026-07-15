<?php
$pageTitle = 'Withdrawal Fund';
require_once __DIR__ . '/../includes/withdrawal.php';
require_once __DIR__ . '/../includes/kyc.php';

require_once __DIR__ . '/includes/auth.php';
require_user();

$user = current_user($pdo);
if (!$user || ($user['status'] ?? '') === 'blocked') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
    header('Location: login.php');
    exit;
}

$uid = (int) $user['id'];
$errors = [];
$minAmt = wd_min_amount();
$wallet = (float) $user['wallet_balance'];
$pendingSum = wd_pending_sum($pdo, $uid);
$available = wd_available_balance($pdo, $user);
$prefills = wd_kyc_bank_prefills($pdo, $uid);

$form = [
    'amount' => $_POST['amount'] ?? '',
    'payment_method' => $_POST['payment_method'] ?? ($prefills['method'] ?? 'Bank Transfer'),
    'account_details' => $_POST['account_details'] ?? ($prefills['details'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? '');
    $details = trim($_POST['account_details'] ?? '');

    // Refresh balances under lock-ish re-read
    $user = current_user($pdo, true) ?? $user;
    $available = wd_available_balance($pdo, $user);
    $wallet = (float) $user['wallet_balance'];

    if ($amount <= 0) {
        $errors[] = 'Enter a valid withdrawal amount.';
    } elseif ($amount < $minAmt) {
        $errors[] = 'Minimum withdrawal is ' . strip_tags(currency($minAmt)) . '.';
    } elseif ($amount > $available + 0.00001) {
        $errors[] = 'Amount exceeds available balance (wallet minus pending requests).';
    }
    if ($method === '') {
        $errors[] = 'Select a payment method.';
    }
    if ($details === '' || strlen($details) < 8) {
        $errors[] = 'Enter complete account / payout details.';
    }

    if (!$errors) {
        $pdo->prepare('INSERT INTO withdrawals (member_id, amount, payment_method, account_details, status) VALUES (?,?,?,?,?)')
            ->execute([$uid, $amount, $method, $details, 'pending']);
        flash('success', 'Withdrawal request submitted. Waiting for admin approval.');
        header('Location: withdrawal-report.php');
        exit;
    }

    $form = [
        'amount' => (string) ($_POST['amount'] ?? ''),
        'payment_method' => $method,
        'account_details' => $details,
    ];
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="up-page-head">
    <div>
        <h1>Withdrawal Fund</h1>
        <p>Request a payout from your wallet balance.</p>
    </div>
    <a href="withdrawal-report.php" class="up-btn up-btn-outline">View Report</a>
</div>

<div class="wd-stats">
    <article class="wd-stat g-green">
        <span class="wd-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
        <div>
            <span class="wd-stat-label">Wallet Balance</span>
            <strong><?= currency($wallet) ?></strong>
        </div>
    </article>
    <article class="wd-stat g-orange">
        <span class="wd-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
        <div>
            <span class="wd-stat-label">Pending Requests</span>
            <strong><?= currency($pendingSum) ?></strong>
        </div>
    </article>
    <article class="wd-stat g-blue">
        <span class="wd-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></span>
        <div>
            <span class="wd-stat-label">Available to Withdraw</span>
            <strong><?= currency($available) ?></strong>
        </div>
    </article>
    <article class="wd-stat g-purple">
        <span class="wd-stat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></span>
        <div>
            <span class="wd-stat-label">Minimum</span>
            <strong><?= currency($minAmt) ?></strong>
        </div>
    </article>
</div>

<div class="wd-layout">
    <section class="wd-card">
        <div class="wd-banner is-report">
            <div>
                <span class="wd-kicker">Payout request</span>
                <h2>Withdrawal Fund</h2>
                <p>Admin will review and approve. Wallet is deducted only after approval.</p>
            </div>
            <span class="wd-banner-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="16" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg></span>
        </div>
        <div class="wd-body">
            <?php foreach ($errors as $err): ?>
                <div class="up-alert up-alert-err"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?php if ($available < $minAmt): ?>
                <div class="up-alert up-alert-info">Available balance is below the minimum withdrawal of <?= currency($minAmt) ?>.</div>
            <?php endif; ?>

            <form method="post" class="wd-form" autocomplete="off">
                <div class="up-form-grid">
                    <div class="up-field">
                        <label for="amount" class="wd-label-row">
                            <span>Amount</span>
                            <small class="wd-hint">Max available: <?= currency($available) ?></small>
                        </label>
                        <input type="number" step="0.01" min="<?= e((string) $minAmt) ?>" max="<?= e((string) $available) ?>"
                               id="amount" name="amount" value="<?= e($form['amount']) ?>"
                               placeholder="0.00" required <?= $available < $minAmt ? 'disabled' : '' ?>>
                    </div>
                    <div class="up-field">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required <?= $available < $minAmt ? 'disabled' : '' ?>>
                            <?php foreach (['Bank Transfer', 'UPI', 'PayPal', 'Crypto', 'Other'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['payment_method'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="up-field full">
                        <label for="account_details" class="wd-label-row">
                            <span>Account / Payout Details</span>
                            <?php if ($prefills): ?>
                                <small class="wd-hint">Prefilled from approved KYC bank details</small>
                            <?php endif; ?>
                        </label>
                        <textarea id="account_details" name="account_details" rows="4"
                                  placeholder="Account holder, number, IFSC / UPI ID / wallet address…"
                                  required <?= $available < $minAmt ? 'disabled' : '' ?>><?= e($form['account_details']) ?></textarea>
                    </div>
                </div>

                <div class="up-actions">
                    <button type="submit" class="up-btn up-btn-primary" <?= $available < $minAmt ? 'disabled' : '' ?>>
                        Submit Request
                    </button>
                    <a href="withdrawal-report.php" class="up-btn up-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside class="wd-side">
        <div class="wd-side-card">
            <h3>How it works</h3>
            <ol class="wd-steps">
                <li>Enter amount &amp; payout details</li>
                <li>Request stays <strong>Pending</strong></li>
                <li>Admin reviews &amp; approves</li>
                <li>Wallet deducted on approval</li>
                <li>Marked <strong>Paid</strong> after payout</li>
            </ol>
        </div>
        <div class="wd-side-card soft">
            <h3>Tips</h3>
            <ul class="wd-tips">
                <li>Pending amounts reduce your available balance</li>
                <li>Keep bank / UPI details accurate</li>
                <li>Track status in Withdrawal Report</li>
            </ul>
            <a href="kyc-bank.php" class="wd-side-link">Update bank KYC →</a>
        </div>
    </aside>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

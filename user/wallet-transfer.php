<?php
$pageTitle = 'Wallet Transfer';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/includes/auth.php';
require_user();

$user = current_user($pdo);
if (!$user || ($user['status'] ?? '') === 'blocked') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
    header('Location: login.php');
    exit;
}

wallet_ensure_schema($pdo);
$uid = (int) $user['id'];
$balances = wallet_get_balances($pdo, $uid);
$types = wallet_types();
$errors = [];

$from = (string) ($_POST['from_wallet'] ?? 'income');
$to = (string) ($_POST['to_wallet'] ?? 'topup');
$amount = (float) ($_POST['amount'] ?? 0);
$note = trim((string) ($_POST['note'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = wallet_transfer($pdo, $uid, $from, $to, $amount, $note !== '' ? $note : null);
    if ($res['ok']) {
        flash('success', 'Transferred successfully to ' . wallet_label($to) . '.');
        header('Location: wallet-transfer.php');
        exit;
    }
    $errors[] = $res['error'] ?? 'Transfer failed.';
    $balances = wallet_get_balances($pdo, $uid);
}

$recent = wallet_transfer_rows($pdo, $uid, 12);
$toneMap = ['income' => 'g-green', 'topup' => 'g-blue', 'shopping' => 'g-purple'];

require_once __DIR__ . '/includes/header.php';
$flash = get_flash();
?>
<div class="up-page-head">
    <div>
        <h1>Wallet Transfer</h1>
        <p>Move funds between your wallets. Income → Topup / Shopping, or Topup → Shopping.</p>
    </div>
    <div class="up-head-actions">
        <a href="wallet.php" class="up-btn up-btn-outline">All Wallets</a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="up-alert up-alert-<?= $flash['type'] === 'error' ? 'err' : 'ok' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="wal-stats">
    <?php foreach ($types as $key => $meta): ?>
    <article class="wal-stat <?= e($toneMap[$key] ?? 'g-blue') ?>">
        <span class="wal-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        </span>
        <div class="wal-stat-copy">
            <span class="wal-stat-label"><?= e($meta['short']) ?></span>
            <strong><?= currency($balances[$key] ?? 0) ?></strong>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<div class="wal-layout">
    <section class="wal-panel">
        <div class="wal-banner is-blue">
            <div class="wal-banner-main">
                <span class="wal-banner-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                </span>
                <div>
                    <span class="wal-kicker">New transfer</span>
                    <h2>Move funds</h2>
                    <p>Instant transfer between your own wallets</p>
                </div>
            </div>
        </div>
        <div class="wal-form-body">
            <?php foreach ($errors as $err): ?>
                <div class="up-alert up-alert-err"><?= e($err) ?></div>
            <?php endforeach; ?>
            <form method="post" class="wal-form">
                <div class="up-form-grid">
                    <div class="up-field">
                        <label for="from_wallet">From wallet</label>
                        <select name="from_wallet" id="from_wallet" required>
                            <?php foreach (['income', 'topup'] as $k): ?>
                                <option value="<?= e($k) ?>" <?= $from === $k ? 'selected' : '' ?>><?= e(wallet_label($k)) ?> (<?= strip_tags(currency($balances[$k] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="up-field">
                        <label for="to_wallet">To wallet</label>
                        <select name="to_wallet" id="to_wallet" required>
                            <?php foreach (['topup', 'shopping'] as $k): ?>
                                <option value="<?= e($k) ?>" <?= $to === $k ? 'selected' : '' ?>><?= e(wallet_label($k)) ?> (<?= strip_tags(currency($balances[$k] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="up-field">
                        <label for="amount">Amount</label>
                        <input type="number" name="amount" id="amount" min="1" step="0.01" value="<?= $amount > 0 ? e(number_format($amount, 2, '.', '')) : '' ?>" required>
                    </div>
                    <div class="up-field full">
                        <label for="note">Note (optional)</label>
                        <input type="text" name="note" id="note" maxlength="200" value="<?= e($note) ?>" placeholder="e.g. Move for shopping">
                    </div>
                </div>
                <div class="up-actions" style="margin-top:1rem">
                    <button type="submit" class="up-btn up-btn-primary">Transfer Now</button>
                </div>
                <p class="wal-hint">Allowed: Income → Topup, Income → Shopping, Topup → Shopping. Withdrawals use Income Wallet only.</p>
            </form>
        </div>
    </section>

    <section class="wal-panel">
        <div class="wal-banner is-navy">
            <div class="wal-banner-main">
                <span class="wal-banner-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                </span>
                <div>
                    <span class="wal-kicker">History</span>
                    <h2>Recent transfers</h2>
                    <p>Your last wallet moves</p>
                </div>
            </div>
        </div>
        <div class="wal-table-wrap">
            <table class="wal-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Route</th>
                        <th>Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recent): ?>
                    <tr><td colspan="4" class="wal-empty">No transfers yet.</td></tr>
                <?php else: foreach ($recent as $t): ?>
                    <tr>
                        <td><?= e(date('d M Y, h:i A', strtotime($t['created_at']))) ?></td>
                        <td><?= e(wallet_label($t['from_wallet'])) ?> → <?= e(wallet_label($t['to_wallet'])) ?></td>
                        <td><?= currency((float) $t['amount']) ?></td>
                        <td><?= e($t['note'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

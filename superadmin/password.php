<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Change Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new) < 8) {
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
            flash('success', 'Password updated.');
        }
    }
    header('Location: password.php');
    exit;
}

require __DIR__ . '/includes/header.php';
?>
<section class="sa-hero">
    <div>
        <span class="sa-hero-kicker">Security</span>
        <h1>Change Password</h1>
        <p>Protect the platform control panel. Use a strong password only you know.</p>
    </div>
</section>

<div class="sa-pass-wrap">
    <form method="post" class="sa-panel">
        <div class="sa-panel-head">
            <div>
                <h2>Update login password</h2>
                <p>Account · <?= e($_SESSION['superadmin_username'] ?? 'superadmin') ?></p>
            </div>
        </div>
        <div class="sa-panel-body">
            <div class="form-group" style="margin-bottom:1rem">
                <label for="current_password">Current password</label>
                <div class="password-field">
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label for="new_password">New password</label>
                <div class="password-field">
                    <input type="password" id="new_password" name="new_password" minlength="8" required autocomplete="new-password">
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <span class="sa-field-hint">Minimum 8 characters</span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password">
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" title="Show password">
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <div class="sa-form-actions">
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
            <div class="sa-pass-tips">
                <strong>Tips</strong>
                <ul>
                    <li>Don’t reuse the default <code>superadmin123</code></li>
                    <li>Prefer a unique password for Super Admin only</li>
                    <li>Logout after changing on shared devices</li>
                </ul>
            </div>
        </div>
    </form>
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
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

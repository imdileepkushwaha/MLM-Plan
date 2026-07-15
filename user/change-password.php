<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $errors[] = 'All password fields are required.';
    } elseif (!password_verify($current, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE members SET password = ? WHERE id = ?')->execute([$hash, (int) $user['id']]);
        flash('success', 'Password changed successfully.');
        header('Location: profile.php');
        exit;
    }
}
?>
<div class="up-page-head">
    <div>
        <h1>Change Password</h1>
        <p>Choose a strong password and keep your account secure.</p>
    </div>
    <a href="profile.php" class="up-btn up-btn-outline">Back to Profile</a>
</div>

<div class="up-card" style="max-width:520px">
    <?php foreach ($errors as $err): ?>
        <div class="up-alert up-alert-err"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
        <div class="up-field" style="margin-bottom:0.9rem">
            <label for="current_password">Current Password</label>
            <div class="up-password-wrap">
                <input type="password" id="current_password" name="current_password" required>
                <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
        </div>

        <div class="up-field" style="margin-bottom:0.9rem">
            <label for="new_password">New Password</label>
            <div class="up-password-wrap">
                <input type="password" id="new_password" name="new_password" minlength="6" required>
                <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
        </div>

        <div class="up-field" style="margin-bottom:1.1rem">
            <label for="confirm_password">Confirm New Password</label>
            <div class="up-password-wrap">
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                <button type="button" class="up-eye" data-password-toggle aria-label="Show password">
                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
        </div>

        <div class="up-actions">
            <button type="submit" class="up-btn up-btn-primary">Update Password</button>
            <a href="profile.php" class="up-btn up-btn-outline">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

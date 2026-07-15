<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utility.php';
$pageTitle = 'Bank Account Add';

if (isset($_GET['toggle'])) {
    utility_toggle_status($pdo, 'bank_accounts', (int) $_GET['toggle']);
    header('Location: bank-accounts.php');
    exit;
}
if (isset($_GET['delete'])) {
    utility_delete($pdo, 'bank_accounts', (int) $_GET['delete']);
    header('Location: bank-accounts.php');
    exit;
}

$banks = $pdo->query("SELECT id, name FROM banks WHERE status='active' ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $bankId = (int) ($_POST['bank_id'] ?? 0);
    $accountName = trim($_POST['account_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $ifsc = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $branch = trim($_POST['branch_name'] ?? '');
    $accountType = trim($_POST['account_type'] ?? 'Current');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if (!$bankId) $errors[] = 'Select a bank.';
    if ($accountName === '') $errors[] = 'Account name is required.';
    if ($accountNumber === '') $errors[] = 'Account number is required.';
    if ($ifsc === '') $errors[] = 'IFSC code is required.';

    if (!$errors) {
        if ($id > 0) {
            $pdo->prepare('UPDATE bank_accounts SET bank_id=?, account_name=?, account_number=?, ifsc_code=?, branch_name=?, account_type=?, status=? WHERE id=?')
                ->execute([$bankId, $accountName, $accountNumber, $ifsc, $branch ?: null, $accountType, $status, $id]);
            flash('success', 'Bank account updated.');
        } else {
            $pdo->prepare('INSERT INTO bank_accounts (bank_id, account_name, account_number, ifsc_code, branch_name, account_type, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$bankId, $accountName, $accountNumber, $ifsc, $branch ?: null, $accountType, $status]);
            flash('success', 'Bank account added.');
        }
        log_activity($id ? 'bank_account_edit' : 'bank_account_add', $accountNumber);
        header('Location: bank-accounts.php');
        exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$rows = $pdo->query('
    SELECT a.*, b.name AS bank_name
    FROM bank_accounts a
    JOIN banks b ON b.id = a.bank_id
    ORDER BY a.id DESC
')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header"><h2><?= $edit ? 'Edit Bank Account' : 'Add Bank Account' ?></h2></div>
    <div class="panel-body">
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank *</label>
                    <select name="bank_id" required>
                        <option value="">— Select Bank —</option>
                        <?php foreach ($banks as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit['bank_id'] ?? $_POST['bank_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Holder Name *</label>
                    <input type="text" name="account_name" value="<?= e($edit['account_name'] ?? $_POST['account_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Account Number *</label>
                    <input type="text" name="account_number" value="<?= e($edit['account_number'] ?? $_POST['account_number'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>IFSC Code *</label>
                    <input type="text" name="ifsc_code" value="<?= e($edit['ifsc_code'] ?? $_POST['ifsc_code'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="branch_name" value="<?= e($edit['branch_name'] ?? $_POST['branch_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Account Type</label>
                    <select name="account_type">
                        <?php foreach (['Current', 'Savings', 'OD'] as $t): ?>
                        <option value="<?= $t ?>" <?= (($edit['account_type'] ?? 'Current') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($edit['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Add Account' ?></button>
                <?php if ($edit): ?><a href="bank-accounts.php" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Company Bank Accounts (<?= count($rows) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Bank</th><th>Account Name</th><th>Number</th><th>IFSC</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7">No accounts yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['bank_name']) ?></td>
                    <td><strong><?= e($r['account_name']) ?></strong></td>
                    <td><?= e($r['account_number']) ?></td>
                    <td><?= e($r['ifsc_code']) ?></td>
                    <td><?= e($r['branch_name'] ?? '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= action_buttons((int)$r['id'], 'Delete this account?', '', $r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

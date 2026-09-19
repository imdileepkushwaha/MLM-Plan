<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utility.php';
$pageTitle = 'Bank Account Add';

bank_accounts_ensure_columns($pdo);

$section = ($_GET['section'] ?? 'bank') === 'upi' ? 'upi' : 'bank';

if (isset($_GET['toggle'])) {
    utility_toggle_status($pdo, 'bank_accounts', (int) $_GET['toggle']);
    header('Location: bank-accounts.php?section=' . urlencode($section));
    exit;
}
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    try {
        $st = $pdo->prepare('SELECT qr_code FROM bank_accounts WHERE id = ?');
        $st->execute([$delId]);
        $oldQr = $st->fetchColumn();
        if ($oldQr) {
            bank_delete_qr_file((string) $oldQr);
        }
    } catch (Throwable $e) {
        // continue delete
    }
    utility_delete($pdo, 'bank_accounts', $delId);
    header('Location: bank-accounts.php?section=bank');
    exit;
}

$banks = $pdo->query("SELECT id, name FROM banks WHERE status='active' ORDER BY name")->fetchAll();
$errors = [];
$formType = 'bank';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = ($_POST['form_type'] ?? 'bank') === 'upi' ? 'upi' : 'bank';
    $section = $formType;
    $id = (int) ($_POST['id'] ?? 0);
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($formType === 'bank') {
        $bankId = (int) ($_POST['bank_id'] ?? 0);
        $accountName = trim($_POST['account_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $ifsc = strtoupper(trim($_POST['ifsc_code'] ?? ''));
        $branch = trim($_POST['branch_name'] ?? '');
        $accountType = trim($_POST['account_type'] ?? 'Current');

        if (!$bankId) {
            $errors[] = 'Select a bank.';
        }
        if ($accountName === '') {
            $errors[] = 'Account name is required.';
        }
        if ($accountNumber === '') {
            $errors[] = 'Account number is required.';
        }
        if ($ifsc === '') {
            $errors[] = 'IFSC code is required.';
        }

        if (!$errors) {
            if ($id > 0) {
                $pdo->prepare('UPDATE bank_accounts SET bank_id=?, account_name=?, account_number=?, ifsc_code=?, branch_name=?, account_type=?, status=? WHERE id=?')
                    ->execute([$bankId, $accountName, $accountNumber, $ifsc, $branch ?: null, $accountType, $status, $id]);
                flash('success', 'Bank account updated.');
                log_activity('bank_account_edit', $accountNumber);
            } else {
                $pdo->prepare('INSERT INTO bank_accounts (bank_id, account_name, account_number, ifsc_code, branch_name, account_type, status) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$bankId, $accountName, $accountNumber, $ifsc, $branch ?: null, $accountType, $status]);
                flash('success', 'Bank account added.');
                log_activity('bank_account_add', $accountNumber);
            }
            header('Location: bank-accounts.php?section=bank');
            exit;
        }
    } else {
        // UPI / QR only — attach to existing bank account
        $accountId = (int) ($_POST['account_id'] ?? 0);
        $upiId = trim((string) ($_POST['upi_id'] ?? ''));
        $removeQr = !empty($_POST['remove_qr']);

        if ($accountId < 1) {
            $errors[] = 'Select a bank account.';
        }

        $existing = null;
        if ($accountId > 0) {
            $es = $pdo->prepare('SELECT id, qr_code, account_number FROM bank_accounts WHERE id = ?');
            $es->execute([$accountId]);
            $existing = $es->fetch() ?: null;
            if (!$existing) {
                $errors[] = 'Bank account not found.';
            }
        }

        $qrPath = $existing ? ($existing['qr_code'] ? (string) $existing['qr_code'] : null) : null;
        $qrUp = bank_store_qr($_FILES['qr_code'] ?? [], $accountId);
        if (!$qrUp['ok']) {
            $errors[] = $qrUp['error'] ?? 'QR upload failed.';
        } elseif (!empty($qrUp['path'])) {
            if ($qrPath && $qrPath !== $qrUp['path']) {
                bank_delete_qr_file($qrPath);
            }
            $qrPath = $qrUp['path'];
        } elseif ($removeQr) {
            if ($qrPath) {
                bank_delete_qr_file($qrPath);
            }
            $qrPath = null;
        }

        if (!$errors && $existing) {
            $pdo->prepare('UPDATE bank_accounts SET upi_id=?, qr_code=?, status=? WHERE id=?')
                ->execute([
                    $upiId !== '' ? $upiId : null,
                    $qrPath,
                    $status,
                    $accountId,
                ]);
            flash('success', 'UPI / QR details saved.');
            log_activity('bank_upi_qr_edit', 'Account #' . $accountId);
            header('Location: bank-accounts.php?section=upi');
            exit;
        }
    }
}

$edit = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
    if ($edit && !isset($_POST['form_type'])) {
        $section = ($_GET['section'] ?? '') === 'upi' ? 'upi' : 'bank';
    }
}

$rows = $pdo->query('
    SELECT a.*, b.name AS bank_name
    FROM bank_accounts a
    JOIN banks b ON b.id = a.bank_id
    ORDER BY a.id DESC
')->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$editUpi = (string) ($edit['upi_id'] ?? $_POST['upi_id'] ?? '');
$editQr = $edit['qr_code'] ?? null;
$editQrUrl = bank_qr_url($editQr ? (string) $editQr : null);
$selectedAccountId = (int) ($edit['id'] ?? $_POST['account_id'] ?? 0);
?>

<div class="panel">
    <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem">
        <h2>Company payment details</h2>
        <div class="ba-tabs" role="tablist">
            <a href="bank-accounts.php?section=bank" class="btn btn-sm <?= $section === 'bank' ? 'btn-primary' : 'btn-outline' ?>">Bank Account</a>
            <a href="bank-accounts.php?section=upi" class="btn btn-sm <?= $section === 'upi' ? 'btn-primary' : 'btn-outline' ?>">UPI / QR</a>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

        <?php if ($section === 'bank'): ?>
        <p style="margin:0 0 1rem;color:var(--ink-muted);font-size:0.9rem">Add company bank account details only. UPI and QR are managed in the other tab.</p>
        <form method="post">
            <input type="hidden" name="form_type" value="bank">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
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
                        <option value="<?= $t ?>" <?= (($edit['account_type'] ?? $_POST['account_type'] ?? 'Current') === $t) ? 'selected' : '' ?>><?= $t ?></option>
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
                <button type="submit" class="btn btn-primary"><?= !empty($edit['id']) && $section === 'bank' ? 'Update Bank Account' : 'Add Bank Account' ?></button>
                <?php if (!empty($edit['id'])): ?><a href="bank-accounts.php?section=bank" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>

        <?php else: ?>
        <p style="margin:0 0 1rem;color:var(--ink-muted);font-size:0.9rem">Attach UPI ID and payment QR to an existing bank account. Create the bank account first if needed.</p>
        <?php if (!$rows): ?>
            <div class="alert alert-info">No bank accounts yet. <a href="bank-accounts.php?section=bank">Add a bank account</a> first.</div>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="form_type" value="upi">
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Account *</label>
                    <select name="account_id" required onchange="if (this.value) { window.location = 'bank-accounts.php?section=upi&edit=' + this.value; }">
                        <option value="">— Select account —</option>
                        <?php foreach ($rows as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $selectedAccountId === (int) $r['id'] ? 'selected' : '' ?>>
                            <?= e($r['bank_name'] . ' · ' . $r['account_name'] . ' · ' . $r['account_number']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>UPI ID</label>
                    <input type="text" name="upi_id" value="<?= e($editUpi) ?>" placeholder="e.g. company@upi">
                    <small class="field-hint">Shown on member activation payment screen</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($edit['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Payment QR Code</label>
                    <div class="ba-upbox<?= $editQrUrl ? ' has-file' : '' ?>" id="baQrBox">
                        <input type="file" name="qr_code" id="baQrInput" class="ba-upbox-input" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="ba-upbox-thumb">
                            <div class="ba-upbox-empty" id="baQrEmpty"<?= $editQrUrl ? ' hidden' : '' ?>>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20v.01"/></svg>
                            </div>
                            <img src="<?= $editQrUrl ? e($editQrUrl) : '' ?>" alt="QR preview" id="baQrImg"<?= $editQrUrl ? '' : ' hidden' ?>>
                        </div>
                        <div class="ba-upbox-body">
                            <strong id="baQrTitle"><?= $editQrUrl ? 'Current QR uploaded' : 'Drop QR image or browse' ?></strong>
                            <small>JPG / PNG / WebP · max 2MB · shown on member activate</small>
                            <div class="ba-upbox-actions">
                                <button type="button" class="btn btn-primary btn-sm" id="baQrBrowse"><?= $editQrUrl ? 'Replace QR' : 'Choose QR' ?></button>
                                <?php if ($editQrUrl): ?>
                                <label class="ba-upbox-remove">
                                    <input type="checkbox" name="remove_qr" value="1" id="baQrRemove"> Remove current QR
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save UPI / QR</button>
                <a href="bank-accounts.php?section=upi" class="btn btn-outline">Clear</a>
            </div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Company Bank Accounts (<?= count($rows) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Bank</th>
                    <th>Account Name</th>
                    <th>Number</th>
                    <th>IFSC</th>
                    <th>UPI</th>
                    <th>QR</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8">No accounts yet.</td></tr>
            <?php else: foreach ($rows as $r):
                $rowQr = bank_qr_url($r['qr_code'] ?? null);
            ?>
                <tr>
                    <td><?= e($r['bank_name']) ?></td>
                    <td><strong><?= e($r['account_name']) ?></strong></td>
                    <td><?= e($r['account_number']) ?></td>
                    <td><?= e($r['ifsc_code']) ?></td>
                    <td><?= !empty($r['upi_id']) ? e($r['upi_id']) : '—' ?></td>
                    <td>
                        <?php if ($rowQr): ?>
                            <a href="<?= e($rowQr) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= e($rowQr) ?>" alt="QR" style="width:40px;height:40px;object-fit:contain;border-radius:6px;border:1px solid #e5e7eb;background:#fff">
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <div class="action-icons">
                            <a class="btn btn-outline btn-sm" href="bank-accounts.php?section=bank&edit=<?= (int) $r['id'] ?>">Bank</a>
                            <a class="btn btn-outline btn-sm" href="bank-accounts.php?section=upi&edit=<?= (int) $r['id'] ?>">UPI/QR</a>
                            <?= action_toggle('?toggle=' . (int) $r['id'] . '&section=' . urlencode($section), $r['status']) ?>
                            <?= action_delete('?delete=' . (int) $r['id'] . '&section=bank', 'Delete this account?') ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($section === 'upi'): ?>
<script>
(function () {
    const box = document.getElementById('baQrBox');
    const input = document.getElementById('baQrInput');
    const browse = document.getElementById('baQrBrowse');
    const img = document.getElementById('baQrImg');
    const empty = document.getElementById('baQrEmpty');
    const title = document.getElementById('baQrTitle');
    const remove = document.getElementById('baQrRemove');
    let objectUrl = null;

    function showFile(file) {
        if (!file || !img) return;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        img.hidden = false;
        if (empty) empty.hidden = true;
        if (title) title.textContent = file.name;
        if (box) box.classList.add('has-file');
        if (remove) remove.checked = false;
        if (browse) browse.textContent = 'Replace QR';
    }

    if (browse && input) {
        browse.addEventListener('click', (e) => {
            e.preventDefault();
            input.click();
        });
    }
    if (input) {
        input.addEventListener('change', () => {
            const f = input.files && input.files[0];
            if (f) showFile(f);
        });
    }
    if (box) {
        ['dragenter', 'dragover'].forEach((ev) => {
            box.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                box.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach((ev) => {
            box.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                box.classList.remove('is-drag');
            });
        });
        box.addEventListener('drop', (e) => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length || !input) return;
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            showFile(files[0]);
        });
    }
    if (remove && img) {
        remove.addEventListener('change', () => {
            if (!remove.checked) return;
            if (input) input.value = '';
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            img.hidden = true;
            img.src = '';
            if (empty) empty.hidden = false;
            if (title) title.textContent = 'Drop QR image or browse';
            if (browse) browse.textContent = 'Choose QR';
            if (box) box.classList.remove('has-file');
        });
    }
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

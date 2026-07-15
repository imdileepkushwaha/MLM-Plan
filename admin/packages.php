<?php
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Packages';

// Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $used = $pdo->prepare('SELECT COUNT(*) FROM members WHERE package_id = ?');
    $used->execute([$id]);
    if ((int) $used->fetchColumn() > 0) {
        flash('error', 'Cannot delete: package is assigned to members. Deactivate instead.');
    } else {
        $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([$id]);
        log_activity('package_delete', "Deleted package #$id");
        flash('success', 'Package deleted.');
    }
    header('Location: packages.php');
    exit;
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $pdo->prepare("UPDATE packages SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    flash('success', 'Package status updated.');
    header('Location: packages.php' . (!empty($_GET['edit']) ? '?edit=' . (int) $_GET['edit'] : ''));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $bv = (float) ($_POST['bv'] ?? 0);
    $dailyRoi = (float) ($_POST['daily_roi'] ?? 0);
    $validityDays = (int) ($_POST['validity_days'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') {
        $errors[] = 'Plan name is required.';
    }
    if ($amount <= 0) {
        $errors[] = 'Investment amount must be greater than 0.';
    }
    if ($bv < 0) {
        $errors[] = 'BV cannot be negative.';
    }
    if ($bv === 0.0 && $amount > 0) {
        $bv = $amount;
    }
    if ($dailyRoi < 0) {
        $errors[] = 'Daily ROI cannot be negative.';
    }
    if ($validityDays < 1) {
        $errors[] = 'Validity days must be at least 1.';
    }

    if (!$errors) {
        if ($id > 0) {
            $pdo->prepare('UPDATE packages SET name=?, amount=?, bv=?, daily_roi=?, validity_days=?, description=?, status=? WHERE id=?')
                ->execute([$name, $amount, $bv, $dailyRoi, $validityDays, $description, $status, $id]);
            log_activity('package_edit', "Updated package #$id");
            flash('success', 'Package updated.');
        } else {
            $pdo->prepare('INSERT INTO packages (name, amount, bv, daily_roi, validity_days, description, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $amount, $bv, $dailyRoi, $validityDays, $description, $status]);
            log_activity('package_add', "Added package $name");
            flash('success', 'Package added.');
        }
        header('Location: packages.php');
        exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$packages = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM members m WHERE m.package_id = p.id) AS member_count FROM packages p ORDER BY p.amount')->fetchAll();

$formName = $edit['name'] ?? $_POST['name'] ?? '';
$formAmount = $edit['amount'] ?? $_POST['amount'] ?? '';
$formBv = $edit['bv'] ?? $_POST['bv'] ?? '';
$formRoi = $edit['daily_roi'] ?? $_POST['daily_roi'] ?? '1.00';
$formDays = $edit['validity_days'] ?? $_POST['validity_days'] ?? '30';
$formDesc = $edit['description'] ?? $_POST['description'] ?? '';
$formStatus = $edit['status'] ?? $_POST['status'] ?? 'active';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel pkg-form-panel">
    <div class="panel-header">
        <div>
            <h2><?= $edit ? 'Edit Package' : 'Add Package' ?></h2>
            <p class="members-sub">Plan name, investment, BV, daily ROI, validity and description</p>
        </div>
        <?php if ($edit): ?>
        <a href="packages.php" class="btn btn-outline btn-sm">Cancel edit</a>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-grid pkg-form-grid">
                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" value="<?= e((string) $formName) ?>" placeholder="e.g. Starter Plan" required>
                </div>
                <div class="form-group">
                    <label>Investment Amount (₹) *</label>
                    <input type="number" step="0.01" min="0" name="amount" value="<?= e((string) $formAmount) ?>" placeholder="1000" required>
                </div>
                <div class="form-group">
                    <label>BV (Business Volume)</label>
                    <input type="number" step="0.01" min="0" name="bv" value="<?= e((string) $formBv) ?>" placeholder="Same as amount if blank">
                    <small class="field-hint">Leave blank to use investment amount</small>
                </div>
                <div class="form-group">
                    <label>Daily ROI (%) *</label>
                    <input type="number" step="0.01" min="0" name="daily_roi" value="<?= e((string) $formRoi) ?>" required>
                </div>
                <div class="form-group">
                    <label>Validity (Days) *</label>
                    <input type="number" step="1" min="1" name="validity_days" value="<?= e((string) $formDays) ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $formStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $formStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group pkg-form-desc">
                    <label>Description</label>
                    <input type="text" name="description" value="<?= e((string) $formDesc) ?>" placeholder="Short plan benefits…">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $edit ? 'Update Package' : 'Add Package' ?></button>
                <?php if ($edit): ?><a href="packages.php" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="pkg-cards-head">
    <h2>All Packages</h2>
    <span><?= count($packages) ?> plan<?= count($packages) === 1 ? '' : 's' ?></span>
</div>

<div class="pkg-cards">
<?php if (!$packages): ?>
    <div class="empty-state" style="grid-column:1/-1">
        <strong>No packages yet</strong>
        <span>Add your first investment plan above.</span>
    </div>
<?php else: foreach ($packages as $p):
    $isActive = ($p['status'] === 'active');
?>
    <article class="pkg-card <?= $isActive ? '' : 'is-inactive' ?>">
        <div class="pkg-card-top">
            <span class="pkg-id">ID #<?= (int) $p['id'] ?></span>
            <span class="pkg-status <?= $isActive ? 'on' : 'off' ?>">
                <span class="pkg-status-dot"></span>
                <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
            </span>
        </div>
        <h3 class="pkg-name"><?= e($p['name']) ?></h3>
        <div class="pkg-price-box">
            <div class="pkg-price"><?= currency((float) $p['amount']) ?></div>
            <div class="pkg-price-label">One-time Investment</div>
        </div>
        <div class="pkg-metrics">
            <div class="pkg-metric">
                <span class="pkg-metric-ico blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </span>
                <div>
                    <strong><?= number_format((float) $p['bv'], 0) ?></strong>
                    <span>BV</span>
                </div>
            </div>
            <div class="pkg-metric">
                <span class="pkg-metric-ico green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 7-7"/><path d="M14 8h7v7"/></svg>
                </span>
                <div>
                    <strong><?= number_format((float) $p['daily_roi'], 2) ?>%</strong>
                    <span>Daily ROI</span>
                </div>
            </div>
            <div class="pkg-metric">
                <span class="pkg-metric-ico orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div>
                    <strong><?= (int) $p['validity_days'] ?></strong>
                    <span>Days</span>
                </div>
            </div>
        </div>
        <p class="pkg-desc"><?= e($p['description'] ?: 'No description') ?></p>
        <div class="pkg-actions">
            <a href="?edit=<?= (int) $p['id'] ?>" class="pkg-btn edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Edit
            </a>
            <a href="?toggle=<?= (int) $p['id'] ?>" class="pkg-btn <?= $isActive ? 'disable' : 'enable' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v10"/><path d="M18.36 6.64A9 9 0 1112 3"/></svg>
                <?= $isActive ? 'Disable' : 'Enable' ?>
            </a>
        </div>
    </article>
<?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

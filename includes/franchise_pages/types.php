<?php
require_once __DIR__ . '/_boot.php';
$pageTitle = 'Franchisee Type Master';

if (isset($_GET['toggle'])) {
    utility_toggle_status($pdo, 'franchisee_types', (int) $_GET['toggle']);
    header('Location: franchisee-types.php');
    exit;
}
if (isset($_GET['delete'])) {
    utility_delete($pdo, 'franchisee_types', (int) $_GET['delete']);
    header('Location: franchisee-types.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') {
        $errors[] = 'Type name is required.';
    }

    $commPercent = max(0.0, (float) ($_POST['commission_percent'] ?? 0));
    $level = max(1, min(10, (int) ($_POST['hierarchy_level'] ?? 1)));
    $canCreate = trim($_POST['can_create_types'] ?? '');

    if (!$errors) {
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE franchisee_types SET name=?, code=?, description=?, commission_percent=?, hierarchy_level=?, can_create_types=?, status=? WHERE id=?')
                    ->execute([$name, $code !== '' ? $code : null, $description !== '' ? $description : null, $commPercent, $level, $canCreate !== '' ? $canCreate : null, $status, $id]);
                log_activity('franchise_type_edit', "Updated franchisee type #$id");
                flash('success', 'Franchisee type updated.');
            } else {
                $pdo->prepare('INSERT INTO franchisee_types (name, code, description, commission_percent, hierarchy_level, can_create_types, status) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name, $code !== '' ? $code : null, $description !== '' ? $description : null, $commPercent, $level, $canCreate !== '' ? $canCreate : null, $status]);
                log_activity('franchise_type_add', "Added franchisee type $name");
                flash('success', 'Franchisee type added.');
            }
            header('Location: franchisee-types.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Type already exists or could not be saved.';
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM franchisee_types WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$rows = franchise_types($pdo);
franchise_header();
?>

<div class="panel">
    <div class="panel-header"><h2><?= $edit ? 'Edit Franchisee Type' : 'Add Franchisee Type' ?></h2></div>
    <div class="panel-body">
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Type Name *</label>
                    <input type="text" name="name" value="<?= e($edit['name'] ?? $_POST['name'] ?? '') ?>" placeholder="e.g. Super Distributer" required>
                </div>
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e($edit['code'] ?? $_POST['code'] ?? '') ?>" placeholder="e.g. SDIST">
                </div>
                <div class="form-group">
                    <label>Commission Margin (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_percent" value="<?= e($edit['commission_percent'] ?? $_POST['commission_percent'] ?? '0.00') ?>" placeholder="e.g. 10.00">
                    <small style="color:#64748b">Percent margin earned on billing / sales (e.g. 10% for Super Distributer, 5% for Distributer)</small>
                </div>
                <div class="form-group">
                    <label>Hierarchy Level</label>
                    <select name="hierarchy_level">
                        <option value="1" <?= (($edit['hierarchy_level'] ?? 1) == 1) ? 'selected' : '' ?>>Level 1 — Top Head (e.g. BHEO)</option>
                        <option value="2" <?= (($edit['hierarchy_level'] ?? '') == 2) ? 'selected' : '' ?>>Level 2 — Super Distributer</option>
                        <option value="3" <?= (($edit['hierarchy_level'] ?? '') == 3) ? 'selected' : '' ?>>Level 3 — Distributer</option>
                        <option value="4" <?= (($edit['hierarchy_level'] ?? '') == 4) ? 'selected' : '' ?>>Level 4 — Retailer (Billing Counter)</option>
                    </select>
                    <small style="color:#64748b">Rank order in downline hierarchy (1 is highest)</small>
                </div>
                <div class="form-group">
                    <label>Allowed Child Creation Types (Codes)</label>
                    <input type="text" name="can_create_types" value="<?= e($edit['can_create_types'] ?? $_POST['can_create_types'] ?? '') ?>" placeholder="e.g. SDIST,DIST,RETAIL">
                    <small style="color:#64748b">Comma-separated codes of types this role can register (leave empty for automatic)</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($edit['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Description</label>
                    <textarea name="description" rows="2"><?= e($edit['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Add Type' ?></button>
                <?php if ($edit): ?><a href="franchisee-types.php" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>All Types (<?= count($rows) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Commission %</th>
                    <th>Can Create</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7">No franchisee types yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><span class="badge badge-info">Level <?= (int) ($r['hierarchy_level'] ?? 1) ?></span></td>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><code><?= e($r['code'] ?? '—') ?></code></td>
                    <td><strong style="color:#059669"><?= number_format((float) ($r['commission_percent'] ?? 0), 2) ?>%</strong></td>
                    <td><small style="color:#64748b"><?= e($r['can_create_types'] ?: 'Auto (Below)') ?></small></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= action_buttons((int) $r['id'], 'Delete this franchisee type?', '', $r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php franchise_footer(); ?>

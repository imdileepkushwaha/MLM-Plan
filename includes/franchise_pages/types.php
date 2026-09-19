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

    if (!$errors) {
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE franchisee_types SET name=?, code=?, description=?, status=? WHERE id=?')
                    ->execute([$name, $code !== '' ? $code : null, $description !== '' ? $description : null, $status, $id]);
                log_activity('franchise_type_edit', "Updated franchisee type #$id");
                flash('success', 'Franchisee type updated.');
            } else {
                $pdo->prepare('INSERT INTO franchisee_types (name, code, description, status) VALUES (?,?,?,?)')
                    ->execute([$name, $code !== '' ? $code : null, $description !== '' ? $description : null, $status]);
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
                    <input type="text" name="name" value="<?= e($edit['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e($edit['code'] ?? $_POST['code'] ?? '') ?>" placeholder="e.g. STATE">
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
                    <textarea name="description" rows="3"><?= e($edit['description'] ?? $_POST['description'] ?? '') ?></textarea>
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
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5">No franchisee types yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><?= e($r['code'] ?? '—') ?></td>
                    <td><?= e($r['description'] ?? '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= action_buttons((int) $r['id'], 'Delete this franchisee type?', '', $r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php franchise_footer(); ?>

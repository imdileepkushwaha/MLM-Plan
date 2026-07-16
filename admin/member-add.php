<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/procedures.php';
$pageTitle = 'Add Member';

$packages = $pdo->query("SELECT id, name, amount FROM packages WHERE status = 'active' ORDER BY amount")->fetchAll();
$parents = $pdo->query("SELECT id, member_id, full_name FROM members WHERE status = 'active' ORDER BY id")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $sponsorId = (int) ($_POST['sponsor_id'] ?? 0) ?: null;
    $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;
    $position = $_POST['position'] ?? '';
    $packageId = (int) ($_POST['package_id'] ?? 0) ?: null;
    $autoPlace = isset($_POST['auto_place']);

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($username === '') $errors[] = 'Username is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Parent placement rules
    if ($parentId) {
        if (!in_array($position, ['left', 'right'], true)) {
            $errors[] = 'Select Left or Right position under Parent.';
        }
    } elseif ($sponsorId && in_array($position, ['left', 'right'], true)) {
        // Parent empty but sponsor + position given → place under sponsor
        $parentId = $sponsorId;
    } elseif ($sponsorId && $position === '') {
        $errors[] = 'Select Parent ID and Position for binary placement (or choose position under sponsor).';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM members WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors[] = 'Username or email already exists.';
        }
    }

    if (!$errors && $parentId) {
        $parentCheck = $pdo->prepare("SELECT id, member_id FROM members WHERE id = ? AND status = 'active'");
        $parentCheck->execute([$parentId]);
        if (!$parentCheck->fetch()) {
            $errors[] = 'Selected Parent ID is invalid or inactive.';
        }
    }

    if (!$errors && $sponsorId) {
        $spCheck = $pdo->prepare("SELECT id FROM members WHERE id = ? AND status = 'active'");
        $spCheck->execute([$sponsorId]);
        if (!$spCheck->fetch()) {
            $errors[] = 'Selected Sponsor is invalid or inactive.';
        }
    }

    $placementId = null;
    if (!$errors && $parentId && $position) {
        $slot = $pdo->prepare('SELECT id FROM members WHERE placement_id = ? AND position = ? LIMIT 1');
        $slot->execute([$parentId, $position]);
        $occupied = $slot->fetch();

        if ($occupied) {
            if ($autoPlace) {
                $placementId = findBinaryPlacement($pdo, $parentId, $position);
                if (!$placementId) {
                    $errors[] = 'Could not find a free slot on this leg.';
                }
            } else {
                $errors[] = 'Selected Parent already has a member on the ' . strtoupper($position) . ' side. Choose another parent/position or enable Auto Place.';
            }
        } else {
            $placementId = $parentId;
        }
    }

    if (!$errors) {
        $memberCode = generate_member_id($pdo);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO members (member_id, username, email, password, full_name, phone, sponsor_id, placement_id, position, package_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $memberCode, $username, $email, $hash, $fullName, $phone,
            $sponsorId, $placementId, $placementId ? $position : null, $packageId
        ]);
        $newId = (int) $pdo->lastInsertId();

        if ($placementId && $position) {
            updateUplineCounts($pdo, $placementId, $position);
        }

        if ($sponsorId && $packageId) {
            $pkg = $pdo->prepare('SELECT amount FROM packages WHERE id = ?');
            $pkg->execute([$packageId]);
            $amount = (float) ($pkg->fetch()['amount'] ?? 0);
            $pct = (float) setting('referral_commission_percent', '5');
            $comm = round($amount * $pct / 100, 2);
            if ($comm > 0) {
                $pdo->prepare('INSERT INTO commissions (member_id, from_member_id, type, amount, description, status) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$sponsorId, $newId, 'referral', $comm, "Referral bonus from $memberCode", 'paid']);
                $pdo->prepare('UPDATE members SET wallet_balance = wallet_balance + ?, total_earnings = total_earnings + ? WHERE id = ?')
                    ->execute([$comm, $comm, $sponsorId]);
            }
        }

        log_activity('member_add', "Added member $memberCode (sponsor #$sponsorId, parent #$placementId, $position)");
        flash('success', "Member $memberCode created successfully.");
        header('Location: member-view.php?id=' . $newId);
        exit;
    }
}

function findBinaryPlacement(PDO $pdo, int $startId, string $position): ?int
{
    $viaSp = sp_call_find_binary_placement($pdo, $startId, $position);
    if ($viaSp !== null) {
        return $viaSp;
    }

    $parentId = $startId;
    for ($i = 0; $i < 50; $i++) {
        $stmt = $pdo->prepare('SELECT id FROM members WHERE placement_id = ? AND position = ? LIMIT 1');
        $stmt->execute([$parentId, $position]);
        $child = $stmt->fetch();
        if (!$child) {
            return $parentId;
        }
        $parentId = (int) $child['id'];
    }
    return null;
}

function updateUplineCounts(PDO $pdo, int $placementId, string $position): void
{
    if (sp_call_update_upline_counts($pdo, $placementId, $position)) {
        return;
    }

    $current = $placementId;
    $side = $position;
    $guard = 0;

    while ($current && $guard < 100) {
        $col = $side === 'left' ? 'left_count' : 'right_count';
        $pdo->prepare("UPDATE members SET $col = $col + 1 WHERE id = ?")->execute([$current]);

        $stmt = $pdo->prepare('SELECT placement_id, position FROM members WHERE id = ?');
        $stmt->execute([$current]);
        $row = $stmt->fetch();
        if (!$row || !$row['placement_id']) break;
        $current = (int) $row['placement_id'];
        $side = $row['position'] ?: $side;
        $guard++;
    }
}

// Slot availability map for UI hints
$slotMap = [];
$slotRows = $pdo->query("
    SELECT placement_id, position, COUNT(*) AS cnt
    FROM members
    WHERE placement_id IS NOT NULL AND position IS NOT NULL
    GROUP BY placement_id, position
")->fetchAll();
foreach ($slotRows as $r) {
    $slotMap[(int)$r['placement_id']][$r['position']] = (int)$r['cnt'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>Register New Member</h2>
        <a href="members.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?>
        <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <div class="alert alert-info" style="margin-bottom:1.1rem">
            <strong>Sponsor</strong> = who referred the member (commission).
            <strong>Parent ID</strong> = binary tree placement (Left / Right under parent).
        </div>

        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Package</label>
                    <select name="package_id">
                        <option value="">— Select —</option>
                        <?php foreach ($packages as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int)($_POST['package_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (<?= currency((float)$p['amount']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3 class="form-section-title">Sponsor &amp; Parent Placement</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Sponsor ID</label>
                    <select name="sponsor_id" id="sponsor_id">
                        <option value="">— None / Root —</option>
                        <?php foreach ($parents as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= ((int)($_POST['sponsor_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                            <?= e($s['member_id'] . ' — ' . $s['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint">Referrer (gets referral commission)</small>
                </div>
                <div class="form-group">
                    <label>Parent ID (Placement)</label>
                    <select name="parent_id" id="parent_id">
                        <option value="">— None / Root —</option>
                        <?php foreach ($parents as $s):
                            $leftTaken = !empty($slotMap[(int)$s['id']]['left']);
                            $rightTaken = !empty($slotMap[(int)$s['id']]['right']);
                            $slotLabel = ($leftTaken ? 'L:Full' : 'L:Free') . ' / ' . ($rightTaken ? 'R:Full' : 'R:Free');
                        ?>
                        <option
                            value="<?= (int) $s['id'] ?>"
                            data-left="<?= $leftTaken ? '1' : '0' ?>"
                            data-right="<?= $rightTaken ? '1' : '0' ?>"
                            <?= ((int)($_POST['parent_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>
                        >
                            <?= e($s['member_id'] . ' — ' . $s['full_name'] . ' (' . $slotLabel . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint">Binary parent under whom member will sit</small>
                </div>
                <div class="form-group">
                    <label>Position under Parent *</label>
                    <select name="position" id="position">
                        <option value="">— Select —</option>
                        <option value="left" <?= ($_POST['position'] ?? '') === 'left' ? 'selected' : '' ?>>Left</option>
                        <option value="right" <?= ($_POST['position'] ?? '') === 'right' ? 'selected' : '' ?>>Right</option>
                    </select>
                    <small class="field-hint" id="slotHint">Select parent to see Left/Right availability</small>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <label class="check-inline">
                        <input type="checkbox" name="auto_place" value="1" <?= isset($_POST['auto_place']) ? 'checked' : '' ?>>
                        Auto Place if position is full (next free on same leg)
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Member</button>
                <a href="members.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const parent = document.getElementById('parent_id');
    const sponsor = document.getElementById('sponsor_id');
    const hint = document.getElementById('slotHint');

    const updateHint = () => {
        const opt = parent.options[parent.selectedIndex];
        if (!opt || !opt.value) {
            hint.textContent = 'Select parent to see Left/Right availability';
            return;
        }
        const left = opt.dataset.left === '1' ? 'Full' : 'Free';
        const right = opt.dataset.right === '1' ? 'Full' : 'Free';
        hint.textContent = `Availability — Left: ${left}, Right: ${right}`;
    };

    parent.addEventListener('change', updateHint);
    updateHint();

    // Convenience: copying sponsor into parent when parent empty
    sponsor.addEventListener('change', () => {
        if (!parent.value && sponsor.value) {
            parent.value = sponsor.value;
            updateHint();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

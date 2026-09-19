<?php
require_once __DIR__ . '/_boot.php';
$pageTitle = 'Franchisee Add';

// AJAX sponsor lookup
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sponsor') {
    header('Content-Type: application/json; charset=utf-8');
    $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
    $row = franchise_find_by_code($pdo, $code);
    if (!$row || ($row['status'] ?? '') !== 'active') {
        echo json_encode(['ok' => false, 'error' => 'Sponsor not found or inactive.']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'id' => (int) $row['id'],
        'code' => $row['franchisee_code'],
        'name' => $row['name'],
        'type' => $row['type_name'] ?? '—',
    ]);
    exit;
}

$types = franchise_types($pdo, true);
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? franchise_get($pdo, $editId) : null;
$isEdit = !empty($edit['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $typeId = (int) ($_POST['type_id'] ?? 0);
    $sponsorCode = strtoupper(trim((string) ($_POST['sponsor_code'] ?? '')));
    $code = strtoupper(trim($_POST['franchisee_code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $aadhaarNo = trim($_POST['aadhaar_no'] ?? '');
    $panNo = strtoupper(trim($_POST['pan_no'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_confirm'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $sponsorId = null;
    if ($sponsorCode !== '') {
        $sp = franchise_find_by_code($pdo, $sponsorCode);
        if (!$sp || ($sp['status'] ?? '') !== 'active') {
            $errors[] = 'Invalid sponsor ID.';
        } elseif ($id > 0 && (int) $sp['id'] === $id) {
            $errors[] = 'Sponsor cannot be the same franchisee.';
        } else {
            $sponsorId = (int) $sp['id'];
        }
    }

    if ($typeId < 1) {
        $errors[] = 'Select a franchisee type.';
    }
    if ($name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($phone === '') {
        $errors[] = 'Mobile number is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if ($city === '') {
        $errors[] = 'City is required.';
    }
    if ($state === '') {
        $errors[] = 'State is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if ($id < 1 && $password === '') {
        $errors[] = 'Password is required.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== '' && $password !== $password2) {
        $errors[] = 'Password and confirm password do not match.';
    }
    if ($code === '') {
        $code = franchise_next_code($pdo);
    }

    // Existing files when editing
    $existing = $id > 0 ? franchise_get($pdo, $id) : null;
    $aadhaarFile = $existing['aadhaar_file'] ?? null;
    $panFile = $existing['pan_file'] ?? null;
    $photoFile = $existing['photo_file'] ?? null;

    $upA = franchise_store_doc($_FILES['aadhaar_file'] ?? [], 'aadhaar', $id);
    $upP = franchise_store_doc($_FILES['pan_file'] ?? [], 'pan', $id);
    $upPh = franchise_store_doc($_FILES['photo_file'] ?? [], 'photo', $id);
    if (!$upA['ok']) {
        $errors[] = $upA['error'] ?? 'Aadhaar upload failed.';
    } elseif (!empty($upA['path'])) {
        $aadhaarFile = $upA['path'];
    }
    if (!$upP['ok']) {
        $errors[] = $upP['error'] ?? 'PAN upload failed.';
    } elseif (!empty($upP['path'])) {
        $panFile = $upP['path'];
    }
    if (!$upPh['ok']) {
        $errors[] = $upPh['error'] ?? 'Photo upload failed.';
    } elseif (!empty($upPh['path'])) {
        $photoFile = $upPh['path'];
    }

    if (!$errors) {
        try {
            $dobVal = ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) ? $dob : null;
            if ($id > 0) {
                $sql = 'UPDATE franchisees SET type_id=?, sponsor_id=?, franchisee_code=?, name=?, contact_person=?, gender=?, dob=?, phone=?, email=?, aadhaar_no=?, pan_no=?, aadhaar_file=?, pan_file=?, photo_file=?, address=?, city=?, state=?, pincode=?, username=?, status=?';
                $params = [
                    $typeId, $sponsorId, $code, $name,
                    $contact !== '' ? $contact : null,
                    $gender !== '' ? $gender : null,
                    $dobVal,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $aadhaarNo !== '' ? $aadhaarNo : null,
                    $panNo !== '' ? $panNo : null,
                    $aadhaarFile, $panFile, $photoFile,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                    $pincode !== '' ? $pincode : null,
                    $username !== '' ? $username : null,
                    $status,
                ];
                if ($password !== '') {
                    $sql .= ', password=?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=?';
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                log_activity('franchise_edit', "Updated franchisee #$id");
                flash('success', 'Franchisee updated.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('
                    INSERT INTO franchisees
                        (type_id, sponsor_id, franchisee_code, name, contact_person, gender, dob, phone, email,
                         aadhaar_no, pan_no, aadhaar_file, pan_file, photo_file,
                         address, city, state, pincode, username, password, status, created_by_role, created_by_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ')->execute([
                    $typeId, $sponsorId, $code, $name,
                    $contact !== '' ? $contact : null,
                    $gender !== '' ? $gender : null,
                    $dobVal,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $aadhaarNo !== '' ? $aadhaarNo : null,
                    $panNo !== '' ? $panNo : null,
                    $aadhaarFile, $panFile, $photoFile,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                    $pincode !== '' ? $pincode : null,
                    $username, $hash, $status,
                    $franchise_role,
                    $franchise_actor_id ?: null,
                ]);
                log_activity('franchise_add', "Added franchisee $code");
                flash('success', 'Franchisee registered successfully.');
            }
            header('Location: franchisee-report.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Could not save. Code or username may already exist.';
        }
    }

    $edit = [
        'id' => $id,
        'type_id' => $typeId,
        'sponsor_id' => $sponsorId,
        'sponsor_code' => $sponsorCode,
        'sponsor_name' => $_POST['sponsor_name'] ?? '',
        'sponsor_type_name' => $_POST['sponsor_type'] ?? '',
        'franchisee_code' => $code,
        'name' => $name,
        'contact_person' => $contact,
        'gender' => $gender,
        'dob' => $dob,
        'phone' => $phone,
        'email' => $email,
        'aadhaar_no' => $aadhaarNo,
        'pan_no' => $panNo,
        'aadhaar_file' => $aadhaarFile,
        'pan_file' => $panFile,
        'photo_file' => $photoFile,
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'pincode' => $pincode,
        'username' => $username,
        'status' => $status,
    ];
    $isEdit = $id > 0;
}

if (!$edit) {
    $edit = [
        'franchisee_code' => franchise_next_code($pdo),
        'status' => 'active',
        'gender' => '',
    ];
}

$sponsorCodeVal = (string) ($edit['sponsor_code'] ?? '');
$sponsorNameVal = (string) ($edit['sponsor_name'] ?? '');
$sponsorTypeVal = (string) ($edit['sponsor_type_name'] ?? '');

franchise_header();
?>

<div class="panel fr-wiz-panel">
    <div class="panel-header">
        <h2><?= $isEdit ? 'Edit Franchisee' : 'Register Franchisee' ?></h2>
    </div>
    <div class="panel-body fr-wiz">
        <?php if (!$types): ?>
            <div class="alert alert-info">Create a <a href="franchisee-types.php">Franchisee Type</a> first.</div>
        <?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

        <div class="fr-wiz-note">Complete all steps to register a new franchisee. Required fields are validated at each step.</div>

        <nav class="fr-steps" id="frSteps" aria-label="Registration steps">
            <button type="button" class="fr-step is-active" data-step="1"><span class="fr-step-num">1</span><span class="fr-step-label">Sponsor &amp; Type</span></button>
            <button type="button" class="fr-step" data-step="2"><span class="fr-step-num">2</span><span class="fr-step-label">Personal</span></button>
            <button type="button" class="fr-step" data-step="3"><span class="fr-step-num">3</span><span class="fr-step-label">Documents</span></button>
            <button type="button" class="fr-step" data-step="4"><span class="fr-step-num">4</span><span class="fr-step-label">Address</span></button>
            <button type="button" class="fr-step" data-step="5"><span class="fr-step-num">5</span><span class="fr-step-label">Password</span></button>
        </nav>

        <form method="post" enctype="multipart/form-data" id="frWizForm" autocomplete="off"<?= !$types ? ' class="is-disabled"' : '' ?>>
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <input type="hidden" name="franchisee_code" value="<?= e($edit['franchisee_code'] ?? '') ?>">
            <input type="hidden" name="sponsor_name" id="sponsorNameHidden" value="<?= e($sponsorNameVal) ?>">
            <input type="hidden" name="sponsor_type" id="sponsorTypeHidden" value="<?= e($sponsorTypeVal) ?>">

            <!-- Step 1 -->
            <section class="fr-pane is-active" data-pane="1">
                <h3 class="fr-pane-title">
                    <span class="fr-pane-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span>
                    Sponsor &amp; Franchisee Type
                </h3>
                <div class="form-grid fr-grid">
                    <div class="form-group">
                        <label>Franchisee Type *</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v4M12 11L5 17M12 11l7 6"/></svg></span>
                            <select name="type_id" id="frType" required>
                                <option value="">— Select type —</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= ((int) ($edit['type_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sponsor ID</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                            <input type="text" name="sponsor_code" id="frSponsorCode" value="<?= e($sponsorCodeVal) ?>" placeholder="e.g. FR0001" autocomplete="off">
                        </div>
                        <small class="field-hint" id="frSponsorHint">Optional — leave blank for root / company</small>
                    </div>
                    <div class="form-group">
                        <label>Sponsor Name</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <input type="text" id="frSponsorName" value="<?= e($sponsorNameVal) ?>" readonly placeholder="Auto-filled">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sponsor Type</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                            <input type="text" id="frSponsorType" value="<?= e($sponsorTypeVal) ?>" readonly placeholder="Auto-filled">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 2 -->
            <section class="fr-pane" data-pane="2" hidden>
                <h3 class="fr-pane-title">
                    <span class="fr-pane-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    Personal Details
                </h3>
                <div class="form-grid fr-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" id="frName" value="<?= e($edit['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" value="<?= e($edit['contact_person'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">— Select —</option>
                            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                            <option value="<?= $g ?>" <?= (($edit['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?= e($edit['dob'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Mobile *</label>
                        <input type="text" name="phone" id="frPhone" value="<?= e($edit['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="frEmail" value="<?= e($edit['email'] ?? '') ?>">
                    </div>
                </div>
            </section>

            <!-- Step 3 -->
            <section class="fr-pane" data-pane="3" hidden>
                <h3 class="fr-pane-title">
                    <span class="fr-pane-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    Documents
                </h3>
                <p class="fr-doc-lead">Enter ID numbers, then upload clear scans or photos. JPG, PNG, WebP or PDF — max 2MB each.</p>

                <div class="fr-doc-ids">
                    <div class="form-group">
                        <label>Aadhaar Number</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
                            <input type="text" name="aadhaar_no" value="<?= e($edit['aadhaar_no'] ?? '') ?>" maxlength="16" placeholder="12-digit Aadhaar" inputmode="numeric">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <div class="fr-input-ico">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/></svg></span>
                            <input type="text" name="pan_no" value="<?= e($edit['pan_no'] ?? '') ?>" maxlength="10" placeholder="ABCDE1234F" style="text-transform:uppercase">
                        </div>
                    </div>
                </div>

                <div class="fr-doc-grid">
                    <?php
                    $docCards = [
                        [
                            'key' => 'aadhaar',
                            'name' => 'aadhaar_file',
                            'title' => 'Aadhaar Card',
                            'hint' => 'Front / back scan or PDF',
                            'accept' => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
                            'file' => $edit['aadhaar_file'] ?? null,
                            'photo' => false,
                        ],
                        [
                            'key' => 'pan',
                            'name' => 'pan_file',
                            'title' => 'PAN Card',
                            'hint' => 'Clear card image or PDF',
                            'accept' => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
                            'file' => $edit['pan_file'] ?? null,
                            'photo' => false,
                        ],
                        [
                            'key' => 'photo',
                            'name' => 'photo_file',
                            'title' => 'Profile Photo',
                            'hint' => 'Passport-size face photo',
                            'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
                            'file' => $edit['photo_file'] ?? null,
                            'photo' => true,
                        ],
                    ];
                    foreach ($docCards as $card):
                        $url = !empty($card['file']) ? franchise_doc_url((string) $card['file']) : null;
                        $isPdf = $url && preg_match('/\.pdf$/i', (string) $card['file']);
                        $has = (bool) $url;
                    ?>
                    <div class="fr-doc-card<?= $has ? ' has-file' : '' ?>" data-fr-doc="<?= e($card['key']) ?>">
                        <input type="file" name="<?= e($card['name']) ?>" id="frDoc_<?= e($card['key']) ?>" class="fr-doc-input" accept="<?= e($card['accept']) ?>">
                        <div class="fr-doc-preview">
                            <div class="fr-doc-empty"<?= $has ? ' hidden' : '' ?>>
                                <?php if ($card['photo']): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <?php endif; ?>
                            </div>
                            <img class="fr-doc-img" alt="" <?= ($has && !$isPdf) ? 'src="' . e($url) . '"' : 'hidden' ?>>
                            <div class="fr-doc-pdf"<?= ($has && $isPdf) ? '' : ' hidden' ?>>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>PDF</span>
                            </div>
                        </div>
                        <div class="fr-doc-meta">
                            <strong class="fr-doc-title"><?= e($card['title']) ?></strong>
                            <span class="fr-doc-name"><?= $has ? e(basename((string) $card['file'])) : e($card['hint']) ?></span>
                            <div class="fr-doc-actions">
                                <button type="button" class="btn btn-primary btn-sm fr-doc-browse"><?= $has ? 'Replace' : 'Upload' ?></button>
                                <?php if ($has): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">View</a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline btn-sm fr-doc-clear" hidden>Clear</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Step 4 -->
            <section class="fr-pane" data-pane="4" hidden>
                <h3 class="fr-pane-title">
                    <span class="fr-pane-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                    Address
                </h3>
                <div class="form-grid fr-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Full Address *</label>
                        <textarea name="address" id="frAddress" rows="3" required><?= e($edit['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" id="frCity" value="<?= e($edit['city'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>State *</label>
                        <input type="text" name="state" id="frState" value="<?= e($edit['state'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" value="<?= e($edit['pincode'] ?? '') ?>">
                    </div>
                </div>
            </section>

            <!-- Step 5 -->
            <section class="fr-pane" data-pane="5" hidden>
                <h3 class="fr-pane-title">
                    <span class="fr-pane-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
                    Login Password
                </h3>
                <div class="form-grid fr-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="frUsername" value="<?= e($edit['username'] ?? '') ?>" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (($edit['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password <?= $isEdit ? '' : '*' ?></label>
                        <input type="password" name="password" id="frPassword" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $isEdit ? 'Leave blank to keep' : 'Min 6 characters' ?>">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <?= $isEdit ? '' : '*' ?></label>
                        <input type="password" name="password_confirm" id="frPassword2" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Franchisee Code</label>
                        <input type="text" value="<?= e($edit['franchisee_code'] ?? '') ?>" readonly>
                        <small class="field-hint">Auto-generated ID</small>
                    </div>
                </div>
            </section>

            <div class="fr-wiz-actions">
                <a href="franchisee-report.php" class="btn btn-outline">Cancel</a>
                <div class="fr-wiz-nav">
                    <button type="button" class="btn btn-outline" id="frPrev" hidden>Previous</button>
                    <button type="button" class="btn btn-primary" id="frNext" <?= !$types ? 'disabled' : '' ?>>Next</button>
                    <button type="submit" class="btn btn-primary" id="frSubmit" hidden <?= !$types ? 'disabled' : '' ?>><?= $isEdit ? 'Update Franchisee' : 'Register Franchisee' ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var step = 1;
    var max = 5;
    var steps = document.querySelectorAll('#frSteps .fr-step');
    var panes = document.querySelectorAll('.fr-pane');
    var prevBtn = document.getElementById('frPrev');
    var nextBtn = document.getElementById('frNext');
    var submitBtn = document.getElementById('frSubmit');
    var sponsorTimer = null;

    function showStep(n) {
        step = n;
        steps.forEach(function (el) {
            var s = parseInt(el.getAttribute('data-step'), 10);
            el.classList.toggle('is-active', s === step);
            el.classList.toggle('is-done', s < step);
        });
        panes.forEach(function (pane) {
            var p = parseInt(pane.getAttribute('data-pane'), 10);
            var on = p === step;
            pane.hidden = !on;
            pane.classList.toggle('is-active', on);
        });
        prevBtn.hidden = step === 1;
        nextBtn.hidden = step === max;
        submitBtn.hidden = step !== max;
        prevBtn.style.display = step === 1 ? 'none' : '';
        nextBtn.style.display = step === max ? 'none' : '';
        submitBtn.style.display = step !== max ? 'none' : '';
    }

    function validateStep(n) {
        if (n === 1) {
            var type = document.getElementById('frType');
            if (!type.value) { alert('Select franchisee type.'); type.focus(); return false; }
            return true;
        }
        if (n === 2) {
            var name = document.getElementById('frName');
            var phone = document.getElementById('frPhone');
            if (!name.value.trim()) { alert('Full name is required.'); name.focus(); return false; }
            if (!phone.value.trim()) { alert('Mobile number is required.'); phone.focus(); return false; }
            var email = document.getElementById('frEmail');
            if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                alert('Enter a valid email.'); email.focus(); return false;
            }
            return true;
        }
        if (n === 4) {
            var address = document.getElementById('frAddress');
            var city = document.getElementById('frCity');
            var state = document.getElementById('frState');
            if (!address.value.trim()) { alert('Address is required.'); address.focus(); return false; }
            if (!city.value.trim()) { alert('City is required.'); city.focus(); return false; }
            if (!state.value.trim()) { alert('State is required.'); state.focus(); return false; }
            return true;
        }
        if (n === 5) {
            var user = document.getElementById('frUsername');
            var pass = document.getElementById('frPassword');
            var pass2 = document.getElementById('frPassword2');
            var isEdit = <?= $isEdit ? 'true' : 'false' ?>;
            if (!user.value.trim()) { alert('Username is required.'); user.focus(); return false; }
            if (!isEdit && !pass.value) { alert('Password is required.'); pass.focus(); return false; }
            if (pass.value && pass.value.length < 6) { alert('Password must be at least 6 characters.'); pass.focus(); return false; }
            if (pass.value && pass.value !== pass2.value) { alert('Passwords do not match.'); pass2.focus(); return false; }
            return true;
        }
        return true;
    }

    nextBtn.addEventListener('click', function () {
        if (!validateStep(step)) return;
        if (step < max) showStep(step + 1);
    });
    prevBtn.addEventListener('click', function () {
        if (step > 1) showStep(step - 1);
    });
    steps.forEach(function (el) {
        el.addEventListener('click', function () {
            var target = parseInt(el.getAttribute('data-step'), 10);
            if (target < step) { showStep(target); return; }
            // only advance if current steps valid in order
            for (var i = step; i < target; i++) {
                if (!validateStep(i)) return;
            }
            showStep(target);
        });
    });

    document.getElementById('frWizForm').addEventListener('submit', function (e) {
        if (!validateStep(5)) e.preventDefault();
    });

    function lookupSponsor() {
        var code = (document.getElementById('frSponsorCode').value || '').trim();
        var nameEl = document.getElementById('frSponsorName');
        var typeEl = document.getElementById('frSponsorType');
        var hint = document.getElementById('frSponsorHint');
        var hName = document.getElementById('sponsorNameHidden');
        var hType = document.getElementById('sponsorTypeHidden');
        if (!code) {
            nameEl.value = ''; typeEl.value = '';
            hName.value = ''; hType.value = '';
            hint.textContent = 'Optional — leave blank for root / company';
            hint.style.color = '';
            return;
        }
        fetch('franchisee-add.php?ajax=sponsor&code=' + encodeURIComponent(code), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    nameEl.value = data.name || '';
                    typeEl.value = data.type || '';
                    hName.value = nameEl.value;
                    hType.value = typeEl.value;
                    hint.textContent = 'Sponsor found';
                    hint.style.color = '#059669';
                } else {
                    nameEl.value = ''; typeEl.value = '';
                    hName.value = ''; hType.value = '';
                    hint.textContent = (data && data.error) ? data.error : 'Sponsor not found';
                    hint.style.color = '#e11d48';
                }
            })
            .catch(function () {
                hint.textContent = 'Could not look up sponsor';
                hint.style.color = '#e11d48';
            });
    }

    document.getElementById('frSponsorCode').addEventListener('input', function () {
        clearTimeout(sponsorTimer);
        sponsorTimer = setTimeout(lookupSponsor, 400);
    });
    document.getElementById('frSponsorCode').addEventListener('blur', lookupSponsor);

    // Document upload cards
    document.querySelectorAll('[data-fr-doc]').forEach(function (card) {
        var input = card.querySelector('.fr-doc-input');
        var browse = card.querySelector('.fr-doc-browse');
        var clearBtn = card.querySelector('.fr-doc-clear');
        var empty = card.querySelector('.fr-doc-empty');
        var img = card.querySelector('.fr-doc-img');
        var pdf = card.querySelector('.fr-doc-pdf');
        var nameEl = card.querySelector('.fr-doc-name');
        var objectUrl = null;

        function resetPreview() {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            if (img) { img.hidden = true; img.removeAttribute('src'); }
            if (pdf) pdf.hidden = true;
            if (empty) empty.hidden = false;
            card.classList.remove('has-file');
            if (browse) browse.textContent = 'Upload';
            if (clearBtn) clearBtn.hidden = true;
        }

        function showFile(file) {
            if (!file) return;
            var isPdf = /\.pdf$/i.test(file.name) || file.type === 'application/pdf';
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            if (empty) empty.hidden = true;
            if (isPdf) {
                if (img) { img.hidden = true; img.removeAttribute('src'); }
                if (pdf) pdf.hidden = false;
            } else {
                objectUrl = URL.createObjectURL(file);
                if (pdf) pdf.hidden = true;
                if (img) { img.hidden = false; img.src = objectUrl; }
            }
            card.classList.add('has-file');
            if (nameEl) nameEl.textContent = file.name;
            if (browse) browse.textContent = 'Replace';
            if (clearBtn) clearBtn.hidden = false;
        }

        if (browse && input) {
            browse.addEventListener('click', function (e) {
                e.preventDefault();
                input.click();
            });
        }
        if (input) {
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (f) showFile(f);
            });
        }
        if (clearBtn && input) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                input.value = '';
                resetPreview();
                if (nameEl) nameEl.textContent = nameEl.getAttribute('data-hint') || 'No file selected';
            });
            if (nameEl && !nameEl.getAttribute('data-hint')) {
                nameEl.setAttribute('data-hint', nameEl.textContent);
            }
        }

        ['dragenter', 'dragover'].forEach(function (ev) {
            card.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                card.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            card.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                card.classList.remove('is-drag');
            });
        });
        card.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length || !input) return;
            var dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            showFile(files[0]);
        });
    });

    showStep(1);
})();
</script>
<?php franchise_footer(); ?>

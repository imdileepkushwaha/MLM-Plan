<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kyc.php';
$pageTitle = 'Approve KYC';

ensure_kyc_documents_table($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim((string) ($_POST['kyc_note'] ?? ''));

    if ($docId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare('SELECT * FROM member_kyc_documents WHERE id = ? LIMIT 1');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if ($doc) {
            $pdo->prepare('UPDATE member_kyc_documents SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, $note !== '' ? $note : null, $docId]);
            kyc_sync_member_status($pdo, (int) $doc['member_id']);
            $typeLabel = kyc_doc_types()[$doc['doc_type']]['label'] ?? $doc['doc_type'];
            log_activity('kyc_' . $action, "KYC $status ($typeLabel) doc #$docId member #{$doc['member_id']}");
            flash('success', $typeLabel . ' ' . $status . ' successfully.');
        } else {
            flash('error', 'KYC document not found.');
        }
    } else {
        flash('error', 'Invalid KYC action.');
    }
    $backStatus = $_POST['back_status'] ?? 'pending';
    $backType = $_POST['back_type'] ?? 'all';
    $redirect = 'approve-kyc.php?status=' . urlencode((string) $backStatus) . '&type=' . urlencode((string) $backType);
    header('Location: ' . $redirect);
    exit;
}

$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'pending';
$typeFilter = $_GET['type'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(m.member_id LIKE ? OR m.username LIKE ? OR m.full_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?
        OR d.pan_number LIKE ? OR d.account_number LIKE ? OR d.aadhar_number LIKE ? OR d.ifsc_code LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like, $like]);
}

if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'not_submitted'], true)) {
    $where[] = 'd.status = ?';
    $params[] = $statusFilter;
}

if (in_array($typeFilter, ['pan', 'bank', 'aadhar'], true)) {
    $where[] = 'd.doc_type = ?';
    $params[] = $typeFilter;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM member_kyc_documents d
    JOIN members m ON m.id = d.member_id
    WHERE $whereSql
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT d.*,
           m.member_id AS member_code,
           m.username, m.full_name, m.email, m.phone, m.status AS member_status
    FROM member_kyc_documents d
    JOIN members m ON m.id = d.member_id
    WHERE $whereSql
    ORDER BY
        CASE d.status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END,
        d.submitted_at DESC,
        d.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statPending = (int) $pdo->query("SELECT COUNT(*) FROM member_kyc_documents WHERE status = 'pending'")->fetchColumn();
$statApproved = (int) $pdo->query("SELECT COUNT(*) FROM member_kyc_documents WHERE status = 'approved'")->fetchColumn();
$statRejected = (int) $pdo->query("SELECT COUNT(*) FROM member_kyc_documents WHERE status = 'rejected'")->fetchColumn();
$statNotSubmitted = (int) $pdo->query("SELECT COUNT(*) FROM member_kyc_documents WHERE status = 'not_submitted'")->fetchColumn();

$types = kyc_doc_types();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="members-stats">
    <a href="approve-kyc.php?status=pending&type=<?= e($typeFilter) ?>" class="m-stat <?= $statusFilter === 'pending' ? 'is-on' : '' ?>">
        <span class="m-stat-ico orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </span>
        <div>
            <strong><?= $statPending ?></strong>
            <span>Pending</span>
        </div>
    </a>
    <a href="approve-kyc.php?status=approved&type=<?= e($typeFilter) ?>" class="m-stat <?= $statusFilter === 'approved' ? 'is-on' : '' ?>">
        <span class="m-stat-ico green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </span>
        <div>
            <strong><?= $statApproved ?></strong>
            <span>Approved</span>
        </div>
    </a>
    <a href="approve-kyc.php?status=rejected&type=<?= e($typeFilter) ?>" class="m-stat <?= $statusFilter === 'rejected' ? 'is-on' : '' ?>">
        <span class="m-stat-ico red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </span>
        <div>
            <strong><?= $statRejected ?></strong>
            <span>Rejected</span>
        </div>
    </a>
    <a href="approve-kyc.php?status=not_submitted&type=<?= e($typeFilter) ?>" class="m-stat <?= $statusFilter === 'not_submitted' ? 'is-on' : '' ?>">
        <span class="m-stat-ico blue"><?= icon_svg('view') ?></span>
        <div>
            <strong><?= $statNotSubmitted ?></strong>
            <span>Not Submitted</span>
        </div>
    </a>
</div>

<div class="panel members-panel">
    <div class="panel-header members-toolbar">
        <div>
            <h2>Approve KYC</h2>
            <p class="members-sub">Review PAN, Bank and Aadhaar documents submitted by members</p>
        </div>
    </div>
    <div class="panel-body members-filters">
        <form class="members-filter-form" method="get">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <div class="form-group">
                <label>Document</label>
                <select name="type">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All types</option>
                    <?php foreach ($types as $key => $info): ?>
                        <option value="<?= e($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Member ID, name, PAN, Aadhaar, account…">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="approve-kyc.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data members-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Document</th>
                    <th>Details</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <strong>No KYC documents</strong>
                            <span>No records match this filter.</span>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r):
                $typeKey = $r['doc_type'];
                $typeLabel = $types[$typeKey]['label'] ?? $typeKey;
                $fileUrl = kyc_doc_admin_url($r['document_file'] ?? null);
                $fileBackUrl = kyc_doc_admin_url($r['document_back'] ?? null);
                ?>
                <tr>
                    <td>
                        <div class="member-cell">
                            <strong><a href="member-view.php?id=<?= (int) $r['member_id'] ?>"><?= e($r['full_name']) ?></a></strong>
                            <span><?= e($r['member_code']) ?> · <?= e($r['username']) ?></span>
                            <span><?= e($r['phone'] ?: $r['email']) ?></span>
                        </div>
                    </td>
                    <td>
                        <strong><?= e($typeLabel) ?></strong>
                        <?php if ($fileUrl): ?>
                            <div><a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= $typeKey === 'aadhar' ? 'Front' : 'View file' ?></a></div>
                        <?php else: ?>
                            <div class="ink-muted">No file</div>
                        <?php endif; ?>
                        <?php if ($typeKey === 'aadhar' && $fileBackUrl): ?>
                            <div><a href="<?= e($fileBackUrl) ?>" target="_blank" rel="noopener">Back</a></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="member-cell">
                            <?php if ($typeKey === 'pan'): ?>
                                <strong><?= e($r['pan_number'] ?: '—') ?></strong>
                                <span><?= e($r['pan_name'] ?: '—') ?></span>
                            <?php elseif ($typeKey === 'bank'): ?>
                                <strong><?= e($r['bank_name'] ?: '—') ?></strong>
                                <span><?= e($r['account_holder'] ?: '—') ?></span>
                                <span><?= e($r['account_number'] ?: '—') ?> · <?= e($r['ifsc_code'] ?: '') ?></span>
                                <?php if (!empty($r['branch_name'])): ?>
                                    <span><?= e($r['branch_name']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong><?= e($r['aadhar_number'] ?: '—') ?></strong>
                                <span><?= e($r['address_line'] ?: '—') ?></span>
                                <?php if (!empty($r['city']) || !empty($r['state'])): ?>
                                    <span><?= e(trim(($r['area'] ? $r['area'] . ', ' : '') . ($r['city'] ?? '') . ', ' . ($r['state'] ?? '') . ' ' . ($r['pincode'] ?? ''), ' ,')) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($r['country'])): ?>
                                    <span><?= e($r['country']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?= $r['submitted_at'] ? e(date('d M Y, h:i A', strtotime($r['submitted_at']))) : '—' ?>
                    </td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" class="kyc-actions">
                            <input type="hidden" name="doc_id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="back_status" value="<?= e($statusFilter) ?>">
                            <input type="hidden" name="back_type" value="<?= e($typeFilter) ?>">
                            <input type="text" name="kyc_note" placeholder="Note (optional)" class="kyc-note-input">
                            <div class="action-icons">
                                <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm" data-confirm="Reject this document?">Reject</button>
                            </div>
                        </form>
                        <?php else: ?>
                            <span class="ink-muted"><?= $r['reviewed_at'] ? e(date('d M Y', strtotime($r['reviewed_at']))) : '—' ?></span>
                            <?php if (!empty($r['admin_note'])): ?>
                                <div class="field-hint"><?= e($r['admin_note']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination members-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>&status=<?= e($statusFilter) ?>&type=<?= e($typeFilter) ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

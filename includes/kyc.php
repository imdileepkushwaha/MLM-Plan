<?php
/**
 * User KYC document helpers (PAN / Bank / Aadhaar)
 */

function ensure_kyc_documents_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS member_kyc_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                doc_type ENUM('pan','bank','aadhar') NOT NULL,
                status ENUM('not_submitted','pending','approved','rejected') NOT NULL DEFAULT 'not_submitted',
                pan_number VARCHAR(20) NULL,
                pan_name VARCHAR(100) NULL,
                account_holder VARCHAR(100) NULL,
                account_number VARCHAR(50) NULL,
                ifsc_code VARCHAR(20) NULL,
                bank_name VARCHAR(100) NULL,
                branch_name VARCHAR(100) NULL,
                aadhar_number VARCHAR(20) NULL,
                address_line TEXT NULL,
                document_file VARCHAR(255) NULL,
                admin_note TEXT NULL,
                submitted_at DATETIME NULL,
                reviewed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_member_doc (member_id, doc_type),
                KEY idx_kyc_status (status),
                KEY idx_kyc_type (doc_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        // ignore
    }

    // Aadhaar extras (front/back + residential address)
    $extraCols = [
        'document_back' => 'VARCHAR(255) NULL AFTER document_file',
        'country' => 'VARCHAR(100) NULL AFTER address_line',
        'state' => 'VARCHAR(100) NULL AFTER country',
        'city' => 'VARCHAR(100) NULL AFTER state',
        'area' => 'VARCHAR(100) NULL AFTER city',
        'pincode' => 'VARCHAR(20) NULL AFTER area',
    ];
    foreach ($extraCols as $col => $def) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM member_kyc_documents LIKE " . $pdo->quote($col));
            if ($chk && !$chk->fetch()) {
                $pdo->exec("ALTER TABLE member_kyc_documents ADD COLUMN {$col} {$def}");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $done = true;
}

function kyc_doc_types(): array
{
    return [
        'pan' => [
            'label' => 'Pan Card',
            'page' => 'kyc-pan.php',
            'title' => 'PAN Card',
            'desc' => 'Submit your PAN number and document for verification.',
        ],
        'bank' => [
            'label' => 'Bank Detail',
            'page' => 'kyc-bank.php',
            'title' => 'Bank Detail',
            'desc' => 'Add bank account details for withdrawals and payouts.',
        ],
        'aadhar' => [
            'label' => 'Address Proof/Aadhar',
            'page' => 'kyc-aadhar.php',
            'title' => 'Address Proof / Aadhaar',
            'desc' => 'Upload Aadhaar or address proof for KYC verification.',
        ],
    ];
}

function kyc_ensure_member_rows(PDO $pdo, int $memberId): void
{
    ensure_kyc_documents_table($pdo);
    $ins = $pdo->prepare('INSERT IGNORE INTO member_kyc_documents (member_id, doc_type) VALUES (?, ?)');
    foreach (array_keys(kyc_doc_types()) as $type) {
        $ins->execute([$memberId, $type]);
    }
}

function kyc_get_doc(PDO $pdo, int $memberId, string $type): ?array
{
    kyc_ensure_member_rows($pdo, $memberId);
    $stmt = $pdo->prepare('SELECT * FROM member_kyc_documents WHERE member_id = ? AND doc_type = ? LIMIT 1');
    $stmt->execute([$memberId, $type]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function kyc_get_all(PDO $pdo, int $memberId): array
{
    kyc_ensure_member_rows($pdo, $memberId);
    $stmt = $pdo->prepare('SELECT * FROM member_kyc_documents WHERE member_id = ?');
    $stmt->execute([$memberId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['doc_type']] = $row;
    }
    return $out;
}

/** Docs still not fully approved (for menu badge). */
function kyc_incomplete_count(PDO $pdo, int $memberId): int
{
    kyc_ensure_member_rows($pdo, $memberId);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_kyc_documents WHERE member_id = ? AND status != 'approved'");
    $stmt->execute([$memberId]);
    return (int) $stmt->fetchColumn();
}

function kyc_can_edit(?array $doc): bool
{
    $status = strtolower((string) ($doc['status'] ?? 'not_submitted'));
    return in_array($status, ['not_submitted', 'rejected'], true);
}

function kyc_status_label(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Not Submitted',
    };
}

function kyc_doc_fs_path(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    $rel = str_replace(['\\', '..'], ['/', ''], $path);
    $full = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    return is_file($full) ? $full : null;
}

/** Web path from user/ pages. */
function kyc_doc_url(?string $path): ?string
{
    if (!kyc_doc_fs_path($path)) {
        return null;
    }
    return '../' . ltrim(str_replace('\\', '/', (string) $path), '/');
}

/** Web path from admin/ pages. */
function kyc_doc_admin_url(?string $path): ?string
{
    if (!kyc_doc_fs_path($path)) {
        return null;
    }
    return '../' . ltrim(str_replace('\\', '/', (string) $path), '/');
}

function kyc_store_document(array $file, int $memberId, string $docType, string $uploadDir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Please upload a document file.', 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Document upload failed. Try again.', 'path' => null];
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Document must be under 3MB.', 'path' => null];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WebP or PDF allowed.', 'path' => null];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Could not create upload folder.', 'path' => null];
    }

    $name = $docType . '_m' . $memberId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save document.', 'path' => null];
    }

    return ['ok' => true, 'error' => null, 'path' => 'uploads/kyc/' . $name];
}

/** Optional upload — ok with no file (keeps previous). */
function kyc_store_document_optional(array $file, int $memberId, string $docType, string $uploadDir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'error' => null, 'path' => null];
    }
    return kyc_store_document($file, $memberId, $docType, $uploadDir);
}

function kyc_delete_file(?string $path): void
{
    $full = kyc_doc_fs_path($path);
    if ($full) {
        @unlink($full);
    }
}

function kyc_sync_member_status(PDO $pdo, int $memberId): void
{
    $stmt = $pdo->prepare('SELECT status FROM member_kyc_documents WHERE member_id = ?');
    $stmt->execute([$memberId]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($statuses) < 3) {
        kyc_ensure_member_rows($pdo, $memberId);
        $stmt->execute([$memberId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $overall = 'not_submitted';
    if ($statuses) {
        $hasPending = in_array('pending', $statuses, true);
        $hasRejected = in_array('rejected', $statuses, true);
        $allApproved = count(array_filter($statuses, fn ($s) => $s === 'approved')) === count($statuses);
        $anySubmitted = (bool) array_filter($statuses, fn ($s) => $s !== 'not_submitted');

        if ($allApproved) {
            $overall = 'approved';
        } elseif ($hasPending) {
            $overall = 'pending';
        } elseif ($hasRejected) {
            $overall = 'rejected';
        } elseif ($anySubmitted) {
            $overall = 'pending';
        }
    }

    try {
        $pdo->prepare('UPDATE members SET kyc_status = ?, kyc_reviewed_at = IF(? = \'approved\', NOW(), kyc_reviewed_at) WHERE id = ?')
            ->execute([$overall, $overall, $memberId]);
    } catch (Throwable $e) {
        // ignore if column missing
    }
}

function kyc_status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'approved' => 'is-ok',
        'pending' => 'is-wait',
        'rejected' => 'is-bad',
        default => 'is-muted',
    };
}

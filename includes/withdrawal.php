<?php
/**
 * User withdrawal helpers
 */

function wd_pending_sum(PDO $pdo, int $memberId): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE member_id = ? AND status = 'pending'");
    $stmt->execute([$memberId]);
    return (float) $stmt->fetchColumn();
}

function wd_pending_count(PDO $pdo, int $memberId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE member_id = ? AND status = 'pending'");
    $stmt->execute([$memberId]);
    return (int) $stmt->fetchColumn();
}

function wd_available_balance(PDO $pdo, array $user): float
{
    $wallet = (float) ($user['wallet_balance'] ?? 0);
    $pending = wd_pending_sum($pdo, (int) $user['id']);
    return max(0, $wallet - $pending);
}

function wd_min_amount(): float
{
    return max(0, (float) setting('min_withdrawal', '500'));
}

function wd_status_pill(string $status): string
{
    $s = strtolower($status);
    $cls = match ($s) {
        'pending' => 'is-wait',
        'approved' => 'is-ok',
        'paid' => 'is-ok',
        'rejected' => 'is-bad',
        default => 'is-muted',
    };
    return '<span class="wd-pill ' . $cls . '">' . e(ucfirst($s)) . '</span>';
}

/** Prefill bank details from approved KYC bank doc if available. */
function wd_kyc_bank_prefills(PDO $pdo, int $memberId): ?array
{
    if (!function_exists('ensure_kyc_documents_table')) {
        return null;
    }
    try {
        ensure_kyc_documents_table($pdo);
        $stmt = $pdo->prepare("SELECT * FROM member_kyc_documents WHERE member_id = ? AND doc_type = 'bank' AND status = 'approved' LIMIT 1");
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $lines = array_filter([
            !empty($row['account_holder']) ? 'Holder: ' . $row['account_holder'] : null,
            !empty($row['account_number']) ? 'A/C: ' . $row['account_number'] : null,
            !empty($row['ifsc_code']) ? 'IFSC: ' . $row['ifsc_code'] : null,
            !empty($row['bank_name']) ? 'Bank: ' . $row['bank_name'] : null,
            !empty($row['branch_name']) ? 'Branch: ' . $row['branch_name'] : null,
        ]);
        return [
            'method' => 'Bank Transfer',
            'details' => implode("\n", $lines),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

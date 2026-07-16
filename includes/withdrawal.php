<?php
/**
 * User withdrawal helpers
 */

function wd_ensure_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $cols = [
        'tds_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount",
        'fee_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tds_amount",
        'other_deduction' => "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER fee_amount",
        'net_amount' => "DECIMAL(12,2) NULL AFTER other_deduction",
    ];
    foreach ($cols as $name => $def) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM withdrawals LIKE " . $pdo->quote($name));
            if ($check && !$check->fetch()) {
                $pdo->exec("ALTER TABLE withdrawals ADD COLUMN {$name} {$def}");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    // Backfill net for old rows
    try {
        $pdo->exec("UPDATE withdrawals SET net_amount = ROUND(amount - tds_amount - fee_amount - other_deduction, 2) WHERE net_amount IS NULL");
    } catch (Throwable $e) {
        // ignore
    }
    $done = true;
}

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

function wd_tds_percent(): float
{
    return max(0.0, (float) setting('tds_deduction_percent', '5'));
}

function wd_fee_percent(): float
{
    return max(0.0, (float) setting('processing_fee_percent', '1'));
}

/**
 * Calculate TDS, processing fee, other deductions, and net payout.
 *
 * @return array{
 *   gross:float,tds_percent:float,fee_percent:float,
 *   tds_amount:float,fee_amount:float,other_deduction:float,net_amount:float,
 *   lines:array<int,array{name:string,amount:float}>
 * }
 */
function wd_calc_breakdown(PDO $pdo, float $gross): array
{
    wd_ensure_columns($pdo);
    $gross = round(max(0, $gross), 2);
    $tdsPct = wd_tds_percent();
    $feePct = wd_fee_percent();
    $tds = round($gross * $tdsPct / 100, 2);
    $fee = round($gross * $feePct / 100, 2);
    $other = 0.0;
    $otherPct = 0.0;
    $otherFixed = 0.0;
    $lines = [];

    if ($tds > 0) {
        $lines[] = ['name' => 'TDS (' . rtrim(rtrim(number_format($tdsPct, 2, '.', ''), '0'), '.') . '%)', 'amount' => $tds];
    }
    if ($fee > 0) {
        $lines[] = ['name' => 'Processing fee (' . rtrim(rtrim(number_format($feePct, 2, '.', ''), '0'), '.') . '%)', 'amount' => $fee];
    }

    try {
        $rows = $pdo->query("SELECT name, deduction_type, value FROM deductions WHERE status = 'active' ORDER BY id ASC")->fetchAll();
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $lower = strtolower($name);
            // Avoid double-counting with settings TDS / processing fee
            if ($tdsPct > 0 && (str_contains($lower, 'tds') || str_contains($lower, 'tax'))) {
                continue;
            }
            if ($feePct > 0 && (str_contains($lower, 'processing') || str_contains($lower, 'admin charge') || $lower === 'admin')) {
                continue;
            }
            $val = (float) ($row['value'] ?? 0);
            if ($val <= 0) {
                continue;
            }
            if (($row['deduction_type'] ?? '') === 'fixed') {
                $amt = round($val, 2);
                $otherFixed = round($otherFixed + $amt, 2);
            } else {
                $amt = round($gross * $val / 100, 2);
                $otherPct = round($otherPct + $val, 4);
            }
            if ($amt <= 0) {
                continue;
            }
            $other = round($other + $amt, 2);
            $lines[] = ['name' => $name !== '' ? $name : 'Deduction', 'amount' => $amt];
        }
    } catch (Throwable $e) {
        // deductions table may not exist
    }

    $net = round(max(0, $gross - $tds - $fee - $other), 2);

    return [
        'gross' => $gross,
        'tds_percent' => $tdsPct,
        'fee_percent' => $feePct,
        'tds_amount' => $tds,
        'fee_amount' => $fee,
        'other_deduction' => $other,
        'other_percent' => $otherPct,
        'other_fixed' => $otherFixed,
        'net_amount' => $net,
        'lines' => $lines,
    ];
}

function wd_net_display(array $row): float
{
    if (isset($row['net_amount']) && $row['net_amount'] !== null && $row['net_amount'] !== '') {
        return (float) $row['net_amount'];
    }
    $gross = (float) ($row['amount'] ?? 0);
    $tds = (float) ($row['tds_amount'] ?? 0);
    $fee = (float) ($row['fee_amount'] ?? 0);
    $other = (float) ($row['other_deduction'] ?? 0);
    return round(max(0, $gross - $tds - $fee - $other), 2);
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

<?php
/**
 * Member plan activation helpers
 */

require_once __DIR__ . '/procedures.php';

function activation_packages(PDO $pdo): array
{
    try {
        return $pdo->query("SELECT * FROM packages WHERE status = 'active' ORDER BY amount ASC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function activation_pay_referral(PDO $pdo, int $sponsorId, int $fromMemberId, string $memberCode, float $packageAmount): void
{
    if ($sponsorId <= 0 || $packageAmount <= 0) {
        return;
    }
    $pct = (float) setting('referral_commission_percent', '5');
    $comm = round($packageAmount * $pct / 100, 2);
    if ($comm <= 0) {
        return;
    }
    $pdo->prepare('INSERT INTO commissions (member_id, from_member_id, type, amount, description, status) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$sponsorId, $fromMemberId, 'referral', $comm, "Referral bonus from $memberCode", 'paid']);
    $pdo->prepare('UPDATE members SET wallet_balance = wallet_balance + ?, total_earnings = total_earnings + ? WHERE id = ?')
        ->execute([$comm, $comm, $sponsorId]);
}

/**
 * Activate member with selected package. Uses SP when available, else PHP fallback.
 * Returns ['ok'=>bool,'error'=>?string,'package'=>?array]
 */
function activation_apply(PDO $pdo, array $user, int $packageId): array
{
    if (!empty($user['package_id'])) {
        return ['ok' => false, 'error' => 'Your account is already activated.'];
    }
    if (($user['status'] ?? '') === 'blocked') {
        return ['ok' => false, 'error' => 'Your account is blocked.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$packageId]);
    $pkg = $stmt->fetch();
    if (!$pkg) {
        return ['ok' => false, 'error' => 'Selected package is not available.'];
    }

    $uid = (int) $user['id'];

    // Prefer stored procedure (atomic activation + referral)
    $sp = sp_call_activate_member($pdo, $uid, $packageId);
    if ($sp['message'] !== 'Procedure unavailable') {
        if ($sp['ok']) {
            return ['ok' => true, 'error' => null, 'package' => $pkg];
        }
        return ['ok' => false, 'error' => $sp['message'] ?: 'Activation failed.'];
    }

    // PHP fallback
    $amount = (float) $pkg['amount'];
    $memberCode = (string) ($user['member_id'] ?? '');

    try {
        $pdo->beginTransaction();
        $upd = $pdo->prepare("UPDATE members SET package_id = ?, status = 'active' WHERE id = ? AND package_id IS NULL");
        $upd->execute([$packageId, $uid]);

        if ($upd->rowCount() < 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Your account is already activated.'];
        }

        if (!empty($user['sponsor_id'])) {
            activation_pay_referral($pdo, (int) $user['sponsor_id'], $uid, $memberCode, $amount);
        }

        $pdo->commit();
        return ['ok' => true, 'error' => null, 'package' => $pkg];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Activation failed. Please try again.'];
    }
}

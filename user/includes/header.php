<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../includes/kyc.php';
require_once __DIR__ . '/../../includes/withdrawal.php';
require_once __DIR__ . '/../../includes/activation.php';
require_user();

$user = current_user($pdo);
if (!$user || ($user['status'] ?? '') === 'blocked') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
    header('Location: login.php');
    exit;
}

$company = setting('company_name', 'Binary MLM');
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$initials = user_initials($user['full_name'] ?? 'User');
$needsActivationNav = empty($user['package_id']);
$canUpgradeNav = !$needsActivationNav && activation_can_upgrade($pdo, $user);
$actPendingNav = activation_pending_request($pdo, (int) $user['id']);
$showPlanNav = $needsActivationNav || $canUpgradeNav || $actPendingNav;
$planNavIsUpgrade = !$needsActivationNav;
$planNavLabel = $planNavIsUpgrade ? 'Upgrade' : 'Activate';
$planNavPending = (bool) $actPendingNav;

$isDash = ($currentPage === 'index');
$isActivate = ($currentPage === 'activate');
$isSupport = ($currentPage === 'support');
$profilePages = ['profile', 'edit-profile', 'change-password', 'upload-photo'];
$profileOpen = in_array($currentPage, $profilePages, true);
$profileBadge = count($profilePages);
$kycPages = ['kyc-pan', 'kyc-bank', 'kyc-aadhar'];
$kycOpen = in_array($currentPage, $kycPages, true);
$kycBadge = kyc_incomplete_count($pdo, (int) $user['id']);
$teamPages = ['my-direct', 'my-downline', 'my-treeview', 'level-tree'];
$teamOpen = in_array($currentPage, $teamPages, true);
$teamBadge = count($teamPages);
$wdPages = ['withdrawal-fund', 'withdrawal-report'];
$wdOpen = in_array($currentPage, $wdPages, true);
$wdBadge = count($wdPages);
$wdPendingBadge = wd_pending_count($pdo, (int) $user['id']);
$incomePages = ['income-summary', 'income-binary', 'income-referral', 'income-matching', 'income-level', 'income-other'];
$incomeOpen = in_array($currentPage, $incomePages, true);
$incomeBadge = count($incomePages);
$reportPages = ['transaction-report'];
$reportOpen = in_array($currentPage, $reportPages, true);
$reportBadge = count($reportPages);

$userNotifyItems = [];
if ($needsActivationNav) {
    if ($actPendingNav) {
        $userNotifyItems[] = [
            'href' => 'activate.php',
            'title' => 'Activation pending approval',
            'small' => 'UTR submitted — waiting for admin',
            'tone' => 'amber',
        ];
    } else {
        $userNotifyItems[] = [
            'href' => 'activate.php',
            'title' => 'Activate your account',
            'small' => 'Pay package amount and submit UTR',
            'tone' => 'red',
        ];
    }
} elseif ($actPendingNav && (($actPendingNav['request_type'] ?? '') === 'upgrade')) {
    $userNotifyItems[] = [
        'href' => 'activate.php',
        'title' => 'Upgrade pending approval',
        'small' => 'Difference payment under review',
        'tone' => 'amber',
    ];
} elseif ($canUpgradeNav) {
    $userNotifyItems[] = [
        'href' => 'activate.php',
        'title' => 'Upgrade your plan',
        'small' => 'Pay only the package difference',
        'tone' => 'blue',
    ];
}
if ($wdPendingBadge > 0) {
    $userNotifyItems[] = [
        'href' => 'withdrawal-report.php',
        'title' => $wdPendingBadge . ' withdrawal request' . ($wdPendingBadge > 1 ? 's' : '') . ' pending',
        'small' => 'Track payout status',
        'tone' => 'blue',
    ];
}
try {
    $newsNotify = $pdo->query("
        SELECT title, published_at
        FROM news
        WHERE status = 'active'
        ORDER BY COALESCE(published_at, '1970-01-01') DESC, id DESC
        LIMIT 3
    ")->fetchAll();
    foreach ($newsNotify as $nn) {
        $userNotifyItems[] = [
            'href' => 'index.php#newsTitle',
            'title' => (string) $nn['title'],
            'small' => !empty($nn['published_at']) ? date('d M Y', strtotime((string) $nn['published_at'])) : 'Company news',
            'tone' => 'green',
        ];
    }
} catch (Throwable $e) {
    // ignore
}
$userNotifyCount = count($userNotifyItems);

$icoDash = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>';
$icoUser = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$icoKyc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>';
$icoTeam = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
$icoIncome = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg>';
$icoWd = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="16" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg>';
$icoReports = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>';
$icoActivate = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>';
$icoSupport = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>';
$icoLogout = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
$chevron = '<svg class="up-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | User Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/user.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/user.css') ?>">
</head>
<body class="up-body">
<div class="up-app">
    <aside class="up-sidebar" id="upSidebar">
        <div class="up-brand">
            <span class="up-brand-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <div class="up-brand-text">User <span>Panel</span></div>
        </div>

        <nav class="up-nav">
            <div class="up-nav-section">
                <div class="up-nav-label">Dashboard</div>
                <a href="index.php" class="up-nav-item<?= $isDash ? ' is-active' : '' ?>">
                    <span class="up-nav-ico"><?= $icoDash ?></span>
                    <span class="up-nav-text">Dashboard</span>
                </a>
                <?php if ($showPlanNav): ?>
                <a href="activate.php" class="up-nav-item<?= $isActivate ? ' is-active' : '' ?>">
                    <span class="up-nav-ico"><?= $icoActivate ?></span>
                    <span class="up-nav-text"><?= e($planNavLabel) ?></span>
                    <?php if ($planNavPending): ?>
                        <span class="up-nav-badge">1</span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>

            <div class="up-nav-section">
                <div class="up-nav-label">My Profile</div>
                <div class="up-nav-group<?= $profileOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $profileOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $profileOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoUser ?></span>
                        <span class="up-nav-text">My Profile</span>
                        <span class="up-nav-badge"><?= (int) $profileBadge ?></span>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubProfile">
                        <div class="up-nav-sub-inner">
                            <a href="profile.php" class="up-nav-sublink<?= $currentPage === 'profile' ? ' is-active' : '' ?>">View Profile</a>
                            <a href="edit-profile.php" class="up-nav-sublink<?= $currentPage === 'edit-profile' ? ' is-active' : '' ?>">Edit Profile</a>
                            <a href="upload-photo.php" class="up-nav-sublink<?= $currentPage === 'upload-photo' ? ' is-active' : '' ?>">Upload Photo</a>
                            <a href="change-password.php" class="up-nav-sublink<?= $currentPage === 'change-password' ? ' is-active' : '' ?>">Change Password</a>
                        </div>
                    </div>
                </div>

                <div class="up-nav-group<?= $kycOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $kycOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $kycOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoKyc ?></span>
                        <span class="up-nav-text">KYC</span>
                        <?php if ($kycBadge > 0): ?>
                            <span class="up-nav-badge"><?= (int) $kycBadge ?></span>
                        <?php endif; ?>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubKyc">
                        <div class="up-nav-sub-inner">
                            <a href="kyc-pan.php" class="up-nav-sublink<?= $currentPage === 'kyc-pan' ? ' is-active' : '' ?>">Pan Card</a>
                            <a href="kyc-bank.php" class="up-nav-sublink<?= $currentPage === 'kyc-bank' ? ' is-active' : '' ?>">Bank Detail</a>
                            <a href="kyc-aadhar.php" class="up-nav-sublink<?= $currentPage === 'kyc-aadhar' ? ' is-active' : '' ?>">Address Proof/Aadhar</a>
                        </div>
                    </div>
                </div>

                <div class="up-nav-group<?= $teamOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $teamOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $teamOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoTeam ?></span>
                        <span class="up-nav-text">My Team</span>
                        <span class="up-nav-badge"><?= (int) $teamBadge ?></span>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubTeam">
                        <div class="up-nav-sub-inner">
                            <a href="my-direct.php" class="up-nav-sublink<?= $currentPage === 'my-direct' ? ' is-active' : '' ?>">My Direct</a>
                            <a href="my-downline.php" class="up-nav-sublink<?= $currentPage === 'my-downline' ? ' is-active' : '' ?>">My Downline</a>
                            <a href="my-treeview.php" class="up-nav-sublink<?= $currentPage === 'my-treeview' ? ' is-active' : '' ?>">My Treeview</a>
                            <a href="level-tree.php" class="up-nav-sublink<?= $currentPage === 'level-tree' ? ' is-active' : '' ?>">Level Tree</a>
                        </div>
                    </div>
                </div>

                <div class="up-nav-group<?= $incomeOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $incomeOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $incomeOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoIncome ?></span>
                        <span class="up-nav-text">My Income</span>
                        <span class="up-nav-badge"><?= (int) $incomeBadge ?></span>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubIncome">
                        <div class="up-nav-sub-inner">
                            <a href="income-summary.php" class="up-nav-sublink<?= $currentPage === 'income-summary' ? ' is-active' : '' ?>">Income Summary</a>
                            <a href="income-binary.php" class="up-nav-sublink<?= $currentPage === 'income-binary' ? ' is-active' : '' ?>">Binary Income</a>
                            <a href="income-referral.php" class="up-nav-sublink<?= $currentPage === 'income-referral' ? ' is-active' : '' ?>">Referral Income</a>
                            <a href="income-matching.php" class="up-nav-sublink<?= $currentPage === 'income-matching' ? ' is-active' : '' ?>">Matching Income</a>
                            <a href="income-level.php" class="up-nav-sublink<?= $currentPage === 'income-level' ? ' is-active' : '' ?>">Level Income</a>
                            <a href="income-other.php" class="up-nav-sublink<?= $currentPage === 'income-other' ? ' is-active' : '' ?>">Other Income</a>
                        </div>
                    </div>
                </div> 

            </div>

            <div class="up-nav-section">
                <div class="up-nav-label">Withdrawal</div>
                <div class="up-nav-group<?= $wdOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $wdOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $wdOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoWd ?></span>
                        <span class="up-nav-text">Withdrawal</span>
                        <span class="up-nav-badge"><?= (int) ($wdPendingBadge > 0 ? $wdPendingBadge : $wdBadge) ?></span>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubWithdrawal">
                        <div class="up-nav-sub-inner">
                            <a href="withdrawal-fund.php" class="up-nav-sublink<?= $currentPage === 'withdrawal-fund' ? ' is-active' : '' ?>">Withdrawal Fund</a>
                            <a href="withdrawal-report.php" class="up-nav-sublink<?= $currentPage === 'withdrawal-report' ? ' is-active' : '' ?>">Withdrawal Report</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="up-nav-section">
                <div class="up-nav-label">Reports</div>
                <div class="up-nav-group<?= $reportOpen ? ' is-open' : '' ?>" data-up-nav-group>
                    <button type="button" class="up-nav-item up-nav-toggle<?= $reportOpen ? ' is-active' : '' ?>" data-up-nav-toggle aria-expanded="<?= $reportOpen ? 'true' : 'false' ?>">
                        <span class="up-nav-ico"><?= $icoReports ?></span>
                        <span class="up-nav-text">Reports</span>
                        <span class="up-nav-badge"><?= (int) $reportBadge ?></span>
                        <?= $chevron ?>
                    </button>
                    <div class="up-nav-sub" id="upNavSubReports">
                        <div class="up-nav-sub-inner">
                            <a href="transaction-report.php" class="up-nav-sublink<?= $currentPage === 'transaction-report' ? ' is-active' : '' ?>">Transaction Report</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="up-nav-section">
                <div class="up-nav-label">Account</div>
                <a href="support.php" class="up-nav-item<?= $isSupport ? ' is-active' : '' ?>">
                    <span class="up-nav-ico"><?= $icoSupport ?></span>
                    <span class="up-nav-text">Support</span>
                </a>
                <a href="logout.php" class="up-nav-item">
                    <span class="up-nav-ico"><?= $icoLogout ?></span>
                    <span class="up-nav-text">Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <div class="up-main">
        <header class="up-topbar">
            <button type="button" class="up-menu-btn" id="upMenuBtn" aria-label="Toggle menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="up-search" data-up-search>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="search" id="upSearchInput" placeholder="Search pages…" autocomplete="off" aria-label="Search pages">
                <div class="up-search-drop" id="upSearchDrop" hidden>
                    <a href="index.php" data-search="dashboard home">Dashboard</a>
                    <a href="profile.php" data-search="profile account">My Profile</a>
                    <a href="edit-profile.php" data-search="edit profile">Edit Profile</a>
                    <a href="my-treeview.php" data-search="team tree binary">My Treeview</a>
                    <a href="my-direct.php" data-search="direct team">My Direct</a>
                    <a href="income-summary.php" data-search="income earnings">Income Summary</a>
                    <a href="withdrawal-fund.php" data-search="withdraw payout">Withdrawal Fund</a>
                    <a href="withdrawal-report.php" data-search="withdraw report">Withdrawal Report</a>
                    <a href="transaction-report.php" data-search="transaction report">Transaction Report</a>
                    <a href="support.php" data-search="support help contact">Support</a>
                    <?php if ($needsActivationNav): ?>
                    <a href="activate.php" data-search="activate package payment">Activate Account</a>
                    <?php elseif ($canUpgradeNav || $planNavPending): ?>
                    <a href="activate.php" data-search="upgrade package difference">Upgrade Plan</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="up-top-actions">
                <button type="button" class="up-icon-btn" id="upFullscreenBtn" title="Fullscreen" aria-label="Fullscreen">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/></svg>
                </button>
                <div class="up-user-dd up-notify-dd" data-up-dropdown>
                    <button type="button" class="up-icon-btn" data-up-dropdown-toggle title="Notifications" aria-label="Notifications" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                        <?php if ($userNotifyCount > 0): ?>
                        <span class="up-notify-badge"><?= $userNotifyCount > 9 ? '9+' : $userNotifyCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="up-user-menu up-notify-menu" data-up-dropdown-menu role="menu">
                        <div class="up-notify-head">Notifications</div>
                        <?php if (!$userNotifyItems): ?>
                            <div class="up-notify-empty">No new notifications</div>
                        <?php else: foreach ($userNotifyItems as $ni): ?>
                            <a href="<?= e($ni['href']) ?>" class="up-notify-item" role="menuitem">
                                <span class="up-notify-dot <?= e($ni['tone']) ?>"></span>
                                <div>
                                    <strong><?= e($ni['title']) ?></strong>
                                    <small><?= e($ni['small']) ?></small>
                                </div>
                            </a>
                        <?php endforeach; endif; ?>
                        <a href="index.php" class="up-notify-foot">Open dashboard</a>
                    </div>
                </div>
                <div class="up-user-dd" data-up-dropdown>
                    <button type="button" class="up-user-chip" data-up-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                        <div class="up-user-meta">
                            <strong><?= e($user['full_name']) ?></strong>
                            <small><?= e($user['member_id']) ?></small>
                        </div>
                        <?= user_avatar_html($user, 'up-avatar', true) ?>
                        <svg class="up-user-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="up-user-menu" data-up-dropdown-menu role="menu">
                        <div class="up-user-menu-head">
                            <?= user_avatar_html($user, 'up-avatar sm') ?>
                            <div>
                                <strong><?= e($user['full_name']) ?></strong>
                                <small>@<?= e($user['member_id']) ?></small>
                            </div>
                        </div>
                        <a href="profile.php" class="up-user-item" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            My Profile
                        </a>
                        <a href="edit-profile.php" class="up-user-item" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            Edit Profile
                        </a>
                        <a href="upload-photo.php" class="up-user-item" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            Upload Photo
                        </a>
                        <a href="change-password.php" class="up-user-item" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            Change Password
                        </a>
                        <div class="up-user-divider"></div>
                        <a href="logout.php" class="up-user-item danger" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="up-content">
            <?php user_flash_render(); ?>

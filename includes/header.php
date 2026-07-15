<?php
/**
 * Shared header / sidebar for admin panel
 */
require_admin();
$company = setting('company_name', 'Binary MLM');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$adminName = $_SESSION['admin_name'] ?? 'Admin';

$utilityPages = [
    'countries', 'states', 'cities', 'banks', 'bank-accounts',
    'deductions', 'news', 'plans', 'package-plans', 'direct-member-login',
];
$utilityOpen = in_array($currentPage, $utilityPages, true);

$memberPages = [
    'members', 'member-view', 'member-add',
    'approve-kyc', 'tree-view', 'binary-tree', 'downline',
];
$membersOpen = in_array($currentPage, $memberPages, true);

$productPages = [
    'product-categories', 'product-subcategories', 'product-sizes', 'product-colors',
    'subcategory-settings', 'product-add', 'product-form', 'product-details', 'product-status',
    'stock-report', 'vendors', 'stock-purchase', 'purchase-details', 'commodity-prices',
];
$productOpen = in_array($currentPage, $productPages, true);

$notifyCount = 0;
try {
    $notifyCount = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();
} catch (Throwable $e) {
    $notifyCount = 0;
}
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

function nav_ico(string $svg): string
{
    return '<span class="nav-ico">' . $svg . '</span>';
}

$icoDash = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 14a2 2 0 100-4 2 2 0 000 4z"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>';
$icoUsers = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
$icoTree = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><circle cx="18" cy="19" r="3"/><line x1="12" y1="8" x2="6" y2="16"/><line x1="12" y1="8" x2="18" y2="16"/></svg>';
$icoUtil = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>';
$icoPkg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>';
$icoProduct = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
$icoMoney = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>';
$icoCard = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
$icoChart = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>';
$icoGear = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';

$chevronDown = '<svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> | <?= e($company) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-text"><?= e($company) ?></div>
            <div class="browse-card">
                <div class="browse-card-text">
                    <span class="browse-label">Browse</span>
                    <strong class="browse-nav">Navigation</strong>
                </div>
                <span class="browse-pill">Admin</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-head">
                    <span class="nav-section-label">Main</span>
                    <span class="nav-section-line"></span>
                </div>

                <a href="index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoDash) ?>
                        <span class="nav-label">Dashboard</span>
                    </span>
                </a>

                <div class="nav-group <?= $membersOpen ? 'open' : '' ?>" data-nav-group>
                    <button type="button" class="nav-link nav-group-toggle <?= $membersOpen ? 'active' : '' ?>" data-nav-toggle>
                        <span class="nav-link-left">
                            <?= nav_ico($icoUsers) ?>
                            <span class="nav-label">Members</span>
                        </span>
                        <?= $chevronDown ?>
                    </button>
                    <div class="nav-submenu">
                        <a href="members.php" class="<?= in_array($currentPage, ['members', 'member-view', 'member-add'], true) ? 'active' : '' ?>"><span class="dot"></span>Member List</a>
                        <a href="approve-kyc.php" class="<?= $currentPage === 'approve-kyc' ? 'active' : '' ?>"><span class="dot"></span>Approve KYC</a>
                        <a href="tree-view.php" class="<?= in_array($currentPage, ['tree-view', 'binary-tree'], true) ? 'active' : '' ?>"><span class="dot"></span>Tree View</a>
                        <a href="downline.php" class="<?= $currentPage === 'downline' ? 'active' : '' ?>"><span class="dot"></span>Downline</a>
                    </div>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-head">
                    <span class="nav-section-label">Modules</span>
                    <span class="nav-section-line"></span>
                </div>

                <div class="nav-group <?= $utilityOpen ? 'open' : '' ?>" data-nav-group>
                    <button type="button" class="nav-link nav-group-toggle <?= $utilityOpen ? 'active' : '' ?>" data-nav-toggle>
                        <span class="nav-link-left">
                            <?= nav_ico($icoUtil) ?>
                            <span class="nav-label">Utility Management</span>
                        </span>
                        <?= $chevronDown ?>
                    </button>
                    <div class="nav-submenu">
                        <a href="countries.php" class="<?= $currentPage === 'countries' ? 'active' : '' ?>"><span class="dot"></span>Add Country</a>
                        <a href="states.php" class="<?= $currentPage === 'states' ? 'active' : '' ?>"><span class="dot"></span>Add State</a>
                        <a href="cities.php" class="<?= $currentPage === 'cities' ? 'active' : '' ?>"><span class="dot"></span>Add City</a>
                        <a href="banks.php" class="<?= $currentPage === 'banks' ? 'active' : '' ?>"><span class="dot"></span>Add Bank</a>
                        <a href="bank-accounts.php" class="<?= $currentPage === 'bank-accounts' ? 'active' : '' ?>"><span class="dot"></span>Bank Account Add</a>
                        <a href="deductions.php" class="<?= $currentPage === 'deductions' ? 'active' : '' ?>"><span class="dot"></span>Deduction Master</a>
                        <a href="news.php" class="<?= $currentPage === 'news' ? 'active' : '' ?>"><span class="dot"></span>News Add</a>
                        <a href="plans.php" class="<?= $currentPage === 'plans' ? 'active' : '' ?>"><span class="dot"></span>Add Plan</a>
                        <a href="package-plans.php" class="<?= $currentPage === 'package-plans' ? 'active' : '' ?>"><span class="dot"></span>Package Plan Master</a>
                        <a href="direct-member-login.php" class="<?= $currentPage === 'direct-member-login' ? 'active' : '' ?>"><span class="dot"></span>Direct Member Login</a>
                    </div>
                </div>

                <div class="nav-group <?= $productOpen ? 'open' : '' ?>" data-nav-group>
                    <button type="button" class="nav-link nav-group-toggle <?= $productOpen ? 'active' : '' ?>" data-nav-toggle>
                        <span class="nav-link-left">
                            <?= nav_ico($icoProduct) ?>
                            <span class="nav-label">Product Management</span>
                        </span>
                        <?= $chevronDown ?>
                    </button>
                    <div class="nav-submenu">
                        <a href="product-categories.php" class="<?= $currentPage === 'product-categories' ? 'active' : '' ?>"><span class="dot"></span>Add Category</a>
                        <a href="product-subcategories.php" class="<?= $currentPage === 'product-subcategories' ? 'active' : '' ?>"><span class="dot"></span>Add Sub-Category</a>
                        <a href="product-sizes.php" class="<?= $currentPage === 'product-sizes' ? 'active' : '' ?>"><span class="dot"></span>Add Size</a>
                        <a href="product-colors.php" class="<?= $currentPage === 'product-colors' ? 'active' : '' ?>"><span class="dot"></span>Add Color</a>
                        <a href="subcategory-settings.php" class="<?= $currentPage === 'subcategory-settings' ? 'active' : '' ?>"><span class="dot"></span>Subcategory Setting</a>
                        <a href="product-form.php" class="<?= $currentPage === 'product-form' ? 'active' : '' ?>"><span class="dot"></span>Add Product</a>
                        <a href="product-add.php" style="display: none;" class="<?= $currentPage === 'product-add' ? 'active' : '' ?>"><span class="dot"></span>Quick Add Product</a>
                        <a href="product-details.php" class="<?= $currentPage === 'product-details' ? 'active' : '' ?>"><span class="dot"></span>Product Details</a>
                        <a href="product-status.php" style="display: none;" class="<?= $currentPage === 'product-status' ? 'active' : '' ?>"><span class="dot"></span>Change Status</a>
                        <a href="stock-report.php" class="<?= $currentPage === 'stock-report' ? 'active' : '' ?>"><span class="dot"></span>Stock Report</a>
                        <a href="vendors.php" class="<?= $currentPage === 'vendors' ? 'active' : '' ?>"><span class="dot"></span>Vendor Master</a>
                        <a href="stock-purchase.php" class="<?= $currentPage === 'stock-purchase' ? 'active' : '' ?>"><span class="dot"></span>Stock Purchase</a>
                        <a href="purchase-details.php" class="<?= $currentPage === 'purchase-details' ? 'active' : '' ?>"><span class="dot"></span>Purchase Details</a>
                        <a href="commodity-prices.php" class="<?= $currentPage === 'commodity-prices' ? 'active' : '' ?>"><span class="dot"></span>Add Commodities Price</a>
                    </div>
                </div>

                <a href="packages.php" class="nav-link <?= $currentPage === 'packages' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoPkg) ?>
                        <span class="nav-label">Packages</span>
                    </span>
                    <?= $chevronDown ?>
                </a>

                <a href="commissions.php" class="nav-link <?= $currentPage === 'commissions' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoMoney) ?>
                        <span class="nav-label">Commissions</span>
                    </span>
                </a>

                <a href="withdrawals.php" class="nav-link <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoCard) ?>
                        <span class="nav-label">Withdrawals</span>
                    </span>
                </a>

                <a href="reports.php" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoChart) ?>
                        <span class="nav-label">Reports</span>
                    </span>
                    <?= $chevronDown ?>
                </a>

                <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="nav-link-left">
                        <?= nav_ico($icoGear) ?>
                        <span class="nav-label">Settings</span>
                    </span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle" aria-label="Menu">☰</button>
            <div class="topbar-welcome">
                <span class="eyebrow">Welcome back</span>
            </div>

            <div class="topbar-right">
                <button type="button" class="topbar-btn" id="fullscreenBtn" title="Toggle fullscreen" aria-label="Fullscreen">
                    <svg class="ico-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    <svg class="ico-compress" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" hidden><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>

                <div class="topbar-dropdown" data-dropdown>
                    <button type="button" class="topbar-btn" data-dropdown-toggle title="Notifications" aria-label="Notifications" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                        <?php if ($notifyCount > 0): ?>
                        <span class="notify-badge"><?= $notifyCount > 9 ? '9+' : $notifyCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-notify" data-dropdown-menu>
                        <div class="dropdown-head">Notifications</div>
                        <?php if ($notifyCount > 0): ?>
                        <a href="withdrawals.php?status=pending" class="dropdown-item">
                            <span class="ni red"></span>
                            <div>
                                <strong><?= $notifyCount ?> pending withdrawal<?= $notifyCount > 1 ? 's' : '' ?></strong>
                                <small>Action required</small>
                            </div>
                        </a>
                        <?php else: ?>
                        <div class="dropdown-empty">No new notifications</div>
                        <?php endif; ?>
                        <a href="withdrawals.php" class="dropdown-foot">View all withdrawals</a>
                    </div>
                </div>

                <div class="topbar-dropdown" data-dropdown>
                    <button type="button" class="user-pill" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                        <span class="user-avatar">
                            <?= strtoupper(substr($adminName, 0, 1)) ?>
                            <span class="online-dot"></span>
                        </span>
                        <span class="user-meta">
                            <strong><?= e($adminUsername) ?></strong>
                            <small>Administrator</small>
                        </span>
                        <svg class="user-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="dropdown-menu dropdown-user" data-dropdown-menu>
                        <div class="dropdown-user-head">
                            <span class="user-avatar sm"><?= strtoupper(substr($adminName, 0, 1)) ?></span>
                            <div>
                                <strong><?= e($adminName) ?></strong>
                                <small>@<?= e($adminUsername) ?></small>
                            </div>
                        </div>
                        <a href="settings.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                            Settings
                        </a>
                        <a href="reports.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                            Reports
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item danger">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <main class="content">
            <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php
            $pageLabel = $pageTitle ?? 'Dashboard';
            $breadcrumbs = [['label' => 'Dashboard', 'href' => 'index.php']];

            if ($currentPage === 'index') {
                $breadcrumbs = [['label' => 'Dashboard', 'href' => null]];
            } elseif ($membersOpen) {
                $breadcrumbs[] = ['label' => 'Members', 'href' => $currentPage === 'members' ? null : 'members.php'];
                if ($currentPage !== 'members') {
                    $breadcrumbs[] = ['label' => $pageLabel, 'href' => null];
                } else {
                    $breadcrumbs[count($breadcrumbs) - 1] = ['label' => $pageLabel, 'href' => null];
                }
            } elseif ($utilityOpen) {
                $breadcrumbs[] = ['label' => 'Utility', 'href' => null];
                $breadcrumbs[] = ['label' => $pageLabel, 'href' => null];
            } elseif ($productOpen) {
                $breadcrumbs[] = ['label' => 'Products', 'href' => $currentPage === 'product-details' ? null : 'product-details.php'];
                if ($currentPage === 'purchase-details') {
                    $breadcrumbs[] = ['label' => 'Stock Purchase', 'href' => 'stock-purchase.php'];
                    $breadcrumbs[] = ['label' => $pageLabel, 'href' => null];
                } elseif ($currentPage === 'product-details') {
                    $breadcrumbs[count($breadcrumbs) - 1] = ['label' => $pageLabel, 'href' => null];
                } else {
                    $breadcrumbs[] = ['label' => $pageLabel, 'href' => null];
                }
            } else {
                $breadcrumbs[] = ['label' => $pageLabel, 'href' => null];
            }
            ?>
            <div class="page-banner">
                <div class="page-banner-left">
                    <div class="page-banner-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 14a2 2 0 100-4 2 2 0 000 4z"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>
                    </div>
                    <div class="page-banner-meta">
                        <span class="page-banner-badge"><span class="dot"></span> Admin Panel</span>
                        <h2><?= e($pageLabel) ?></h2>
                    </div>
                </div>
                <nav class="page-breadcrumb" aria-label="Breadcrumb">
                    <ol>
                        <li class="page-breadcrumb-home" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 14a2 2 0 100-4 2 2 0 000 4z"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>
                        </li>
                        <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <li>
                            <span class="page-breadcrumb-sep" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                            <?php if (!empty($crumb['href'])): ?>
                                <a href="<?= e($crumb['href']) ?>"><?= e($crumb['label']) ?></a>
                            <?php else: ?>
                                <span class="current" aria-current="page"><?= e($crumb['label']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </div>

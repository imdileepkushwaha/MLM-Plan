<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$directCount = 0;
try {
    $ds = $pdo->prepare('SELECT COUNT(*) FROM members WHERE sponsor_id = ?');
    $ds->execute([(int) $user['id']]);
    $directCount = (int) $ds->fetchColumn();
} catch (Throwable $e) {
    $directCount = 0;
}

$refBase = rtrim(APP_URL, '/') . '/user/register.php';
$memberCode = (string) ($user['member_id'] ?? '');
$referralLeft = $refBase . '?ref=' . rawurlencode($memberCode) . '&pos=left';
$referralRight = $refBase . '?ref=' . rawurlencode($memberCode) . '&pos=right';
$waLeftText = 'Join with my Left referral link (' . $memberCode . '): ' . $referralLeft;
$waRightText = 'Join with my Right referral link (' . $memberCode . '): ' . $referralRight;
$waLeftUrl = 'https://wa.me/?text=' . rawurlencode($waLeftText);
$waRightUrl = 'https://wa.me/?text=' . rawurlencode($waRightText);
$needsActivation = empty($user['package_id']);
?>
<div class="up-page-head">
    <div>
        <h1>Member Dashboard</h1>
        <p>Overview of your wallet, team and account status.</p>
    </div>
    <?php if ($needsActivation): ?>
        <a href="activate.php" class="up-btn up-btn-primary">Activate Account</a>
    <?php else: ?>
        <a href="edit-profile.php" class="up-btn up-btn-primary">Edit Profile</a>
    <?php endif; ?>
</div>

<?php if ($needsActivation): ?>
<section class="up-activate-banner">
    <div class="up-activate-copy">
        <span class="up-activate-kicker">Action required</span>
        <h2>Activate your account</h2>
        <p>You have not selected a plan yet. Activate a package to go live and unlock team &amp; earnings features.</p>
        <div class="up-activate-actions">
            <a href="activate.php" class="up-btn up-btn-primary">Activate Now</a>
            <a href="profile.php" class="up-btn up-btn-outline up-activate-ghost">View Profile</a>
        </div>
    </div>
    <div class="up-activate-visual" aria-hidden="true">
        <span class="up-activate-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        </span>
        <strong>Inactive</strong>
        <small>No package assigned</small>
    </div>
</section>
<?php endif; ?>

<div class="up-stats">
    <article class="up-stat g-blue">
        <div class="up-stat-bg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        </div>
        <div class="up-stat-inner">
            <span class="up-stat-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            </span>
            <div class="up-stat-copy">
                <div class="up-stat-label">Wallet Balance</div>
                <div class="up-stat-value"><?= currency((float) $user['wallet_balance']) ?></div>
                <div class="up-stat-foot"><span>+ ready</span> Available payout</div>
            </div>
        </div>
    </article>
    <article class="up-stat g-green">
        <div class="up-stat-bg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg>
        </div>
        <div class="up-stat-inner">
            <span class="up-stat-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg>
            </span>
            <div class="up-stat-copy">
                <div class="up-stat-label">Total Earnings</div>
                <div class="up-stat-value"><?= currency((float) $user['total_earnings']) ?></div>
                <div class="up-stat-foot"><span>+ income</span> Lifetime total</div>
            </div>
        </div>
    </article>
    <article class="up-stat g-orange">
        <div class="up-stat-bg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v4M12 11L5 17M12 11l7 6"/></svg>
        </div>
        <div class="up-stat-inner">
            <span class="up-stat-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v4M12 11L5 17M12 11l7 6"/></svg>
            </span>
            <div class="up-stat-copy">
                <div class="up-stat-label">Left / Right</div>
                <div class="up-stat-value"><?= (int) $user['left_count'] ?> / <?= (int) $user['right_count'] ?></div>
                <div class="up-stat-foot"><span>team</span> Binary count</div>
            </div>
        </div>
    </article>
    <article class="up-stat g-purple">
        <div class="up-stat-bg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <div class="up-stat-inner">
            <span class="up-stat-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </span>
            <div class="up-stat-copy">
                <div class="up-stat-label">Package</div>
                <div class="up-stat-value is-sm"><?= e($user['package_name'] ?? ($needsActivation ? 'Not activated' : '—')) ?></div>
                <div class="up-stat-foot">
                    <?php if ($needsActivation): ?>
                        <a href="activate.php" style="color:inherit;font-weight:800;text-decoration:underline">Activate →</a>
                    <?php else: ?>
                        <span>plan</span> Active package
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>
</div>

<div class="up-grid-2">
    <section class="up-card up-panel-card up-quick-panel">
        <div class="up-panel-head is-blue">
            <div class="up-panel-head-main">
                <span class="up-panel-head-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </span>
                <div>
                    <span class="up-panel-kicker">Shortcuts</span>
                    <h2>Quick Navigation</h2>
                    <p>Jump to common account actions instantly.</p>
                </div>
            </div>
        </div>
        <div class="up-quick">
            <a href="profile.php" class="up-q-tile c1">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <strong>Profile</strong>
                <small>View account</small>
            </a>
            <a href="edit-profile.php" class="up-q-tile c2">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></span>
                <strong>Edit</strong>
                <small>Update details</small>
            </a>
            <a href="change-password.php" class="up-q-tile c3">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
                <strong>Password</strong>
                <small>Secure login</small>
            </a>
            <a href="profile.php" class="up-q-tile c4">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span>
                <strong>Settings</strong>
                <small>Preferences</small>
            </a>
            <a href="income-summary.php" class="up-q-tile c5">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3"/><path d="M9 13c6.667 0 6.667-10 0-10"/></svg></span>
                <strong>Earnings</strong>
                <small>Income view</small>
            </a>
            <a href="index.php" class="up-q-tile c6">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v4M12 11L5 17M12 11l7 6"/></svg></span>
                <strong>Team</strong>
                <small>Binary tree</small>
            </a>
            <a href="edit-profile.php" class="up-q-tile c7">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4z"/><path d="M8 10h8M8 14h5"/></svg></span>
                <strong>Details</strong>
                <small>Your info</small>
            </a>
            <a href="logout.php" class="up-q-tile c8">
                <span class="up-quick-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <strong>Logout</strong>
                <small>Sign out</small>
            </a>
        </div>
    </section>

    <section class="up-card up-panel-card up-snap-panel">
        <div class="up-panel-head is-green">
            <div class="up-panel-head-main">
                <span class="up-panel-head-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
                <div>
                    <span class="up-panel-kicker">Live console</span>
                    <h2>Account Snapshot</h2>
                    <p>Live summary of your membership status.</p>
                </div>
            </div>
        </div>
        <div class="up-snap-body">
            <div class="up-snap-list">
                <div class="up-snap-item">
                    <span class="dot a" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <div class="up-snap-meta">
                        <strong>Direct sponsors</strong>
                        <small>Members under your ID</small>
                    </div>
                    <span class="badge"><?= $directCount ?></span>
                </div>
                <div class="up-snap-item">
                    <span class="dot b" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                    <div class="up-snap-meta">
                        <strong>Account status</strong>
                        <small>Current membership state</small>
                    </div>
                    <span class="badge is-status<?= member_is_active($user) ? '' : ' is-inactive' ?>"><?= e(ucfirst(member_effective_status($user))) ?></span>
                </div>
                <div class="up-snap-item">
                    <span class="dot c" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
                    <div class="up-snap-meta">
                        <strong>Joined</strong>
                        <small>Registration date</small>
                    </div>
                    <span class="badge is-date"><?= $user['join_date'] ? date('d M Y', strtotime($user['join_date'])) : '—' ?></span>
                </div>
            </div>
            <?php if ($needsActivation): ?>
                <a href="activate.php" class="up-btn up-btn-primary up-btn-block">Activate Account →</a>
            <?php else: ?>
                <a href="profile.php" class="up-btn up-btn-soft up-btn-block">Open profile →</a>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="up-card up-panel-card up-referral" aria-labelledby="referralTitle">
    <div class="up-panel-head is-coral">
        <div>
            <span class="up-panel-kicker">Grow your team</span>
            <h2 id="referralTitle">Referral Links</h2>
            <p>Share Left / Right placement links under your ID <strong><?= e($memberCode) ?></strong>.</p>
        </div>
        <div class="up-referral-id">
            <span>Your ID</span>
            <strong><?= e($memberCode) ?></strong>
        </div>
    </div>

    <div class="up-referral-body">
        <div class="up-referral-grid">
            <article class="up-ref-card up-ref-left">
                <div class="up-ref-card-top">
                    <div class="up-ref-title">
                        <span class="up-ref-side-ico left" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        </span>
                        <span class="up-ref-badge">Left Leg</span>
                    </div>
                    <span class="up-ref-count">L <?= (int) $user['left_count'] ?></span>
                </div>
                <p class="up-ref-desc">Place new members on your <strong>left</strong> side of the binary tree.</p>
                <div class="up-ref-field">
                    <input type="text" readonly value="<?= e($referralLeft) ?>" id="refLinkLeft" aria-label="Left referral link">
                    <button type="button" class="up-ref-copy" data-copy="#refLinkLeft">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        <span>Copy</span>
                    </button>
                    <a href="<?= e($waLeftUrl) ?>" class="up-ref-wa" target="_blank" rel="noopener noreferrer" title="Share on WhatsApp">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                </div>
            </article>

            <article class="up-ref-card up-ref-right">
                <div class="up-ref-card-top">
                    <div class="up-ref-title">
                        <span class="up-ref-side-ico right" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </span>
                        <span class="up-ref-badge">Right Leg</span>
                    </div>
                    <span class="up-ref-count">R <?= (int) $user['right_count'] ?></span>
                </div>
                <p class="up-ref-desc">Place new members on your <strong>right</strong> side of the binary tree.</p>
                <div class="up-ref-field">
                    <input type="text" readonly value="<?= e($referralRight) ?>" id="refLinkRight" aria-label="Right referral link">
                    <button type="button" class="up-ref-copy" data-copy="#refLinkRight">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        <span>Copy</span>
                    </button>
                    <a href="<?= e($waRightUrl) ?>" class="up-ref-wa" target="_blank" rel="noopener noreferrer" title="Share on WhatsApp">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                </div>
            </article>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

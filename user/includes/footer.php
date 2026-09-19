</main>
        <nav class="up-bottom-nav" aria-label="Quick navigation">
            <?php
            $bnDashActive = $isDash;
            $bnRefActive = $teamOpen || $currentPage === 'my-direct';
            $bnWdHref = $featWithdrawals ? 'withdrawal-fund.php' : 'wallet.php';
            $bnWdActive = $wdOpen || (!$featWithdrawals && $walletOpen);
            $bnKycHref = $featKyc ? 'kyc-pan.php' : 'edit-profile.php';
            $bnKycActive = $featKyc ? $kycOpen : ($currentPage === 'edit-profile');
            $bnProfileActive = $profileOpen;
            ?>
            <a href="index.php" class="up-bn-item <?= $bnDashActive ? 'active' : '' ?>">
                <span class="up-bn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="12" width="4" height="8" rx="1" fill="#22c55e"/><rect x="10" y="7" width="4" height="13" rx="1" fill="#ef4444"/><rect x="17" y="3" width="4" height="17" rx="1" fill="#3b82f6"/></svg>
                </span>
                <span class="up-bn-label">Dashboard</span>
            </a>
            <a href="my-direct.php" class="up-bn-item <?= $bnRefActive ? 'active' : '' ?>">
                <span class="up-bn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </span>
                <span class="up-bn-label">Referrals</span>
            </a>
            <a href="<?= e($bnWdHref) ?>" class="up-bn-item <?= $bnWdActive ? 'active' : '' ?>">
                <span class="up-bn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                </span>
                <span class="up-bn-label">Withdraw</span>
            </a>
            <a href="<?= e($bnKycHref) ?>" class="up-bn-item <?= $bnKycActive ? 'active' : '' ?>">
                <span class="up-bn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="#ef4444" stroke="none"/></svg>
                </span>
                <span class="up-bn-label">KYC</span>
            </a>
            <a href="profile.php" class="up-bn-item <?= $bnProfileActive ? 'active' : '' ?>">
                <span class="up-bn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <span class="up-bn-label">Profile</span>
            </a>
        </nav>
        <footer class="up-footer">
            <div class="up-footer-inner">
                <div class="up-footer-brand">
                    <div>
                        <strong><?= e($company) ?></strong>
                        <!-- <small>Member User Panel</small> -->
                    </div>
                </div>

               

                <div class="up-footer-meta">
                    <span class="up-footer-copy">&copy; <?= date('Y') ?> <?= e($company) ?>. All rights reserved.</span>
                    <span class="up-footer-pill">Secure Session</span>
                </div>
            </div>
        </footer>
    </div>
</div>
<div class="up-overlay" id="upOverlay"></div>
<script src="assets/js/user.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/user.js') ?>"></script>
</body>
</html>

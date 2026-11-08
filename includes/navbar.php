<?php
if (isLoggedIn()) {
    $nav_pending_offers = getPendingOfferCount($conn, $_SESSION['user_id']);
    $nav_unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
    $nav_unread_notifs = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<nav class="navbar" data-base-url="<?= e(BASE_URL) ?>" data-logged-in="<?= isLoggedIn() ? '1' : '0' ?>">
    <div class="container">
        <div class="navbar-brand">
            <a href="<?= BASE_URL ?>index.php">
                <i class="fas fa-exchange-alt"></i>
                <span>SWAP-IT</span>
            </a>
        </div>

        <div class="navbar-menu">
            <a href="<?= BASE_URL ?>index.php" class="nav-link">
                <i class="fas fa-home"></i> Beranda
            </a>

            <?php if (isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>pages/my-items.php" class="nav-link">
                    <i class="fas fa-box"></i> Barang Saya
                </a>

                <a href="<?= BASE_URL ?>pages/my-offers.php" class="nav-link" data-badge="offers">
                    <i class="fas fa-handshake"></i> Penawaran
                    <span class="badge" <?= $nav_pending_offers > 0 ? '' : 'style="display:none"' ?>><?= $nav_pending_offers ?></span>
                </a>

                <a href="<?= BASE_URL ?>pages/messages.php" class="nav-link" data-badge="messages">
                    <i class="fas fa-comments"></i> Chat
                    <span class="badge" <?= $nav_unread_messages > 0 ? '' : 'style="display:none"' ?>><?= $nav_unread_messages ?></span>
                </a>

                <a href="<?= BASE_URL ?>pages/notifications.php" class="nav-link" data-badge="notifications" title="Notifikasi">
                    <i class="fas fa-bell"></i> <span class="nav-label-mobile">Notifikasi</span>
                    <span class="badge" <?= $nav_unread_notifs > 0 ? '' : 'style="display:none"' ?>><?= $nav_unread_notifs ?></span>
                </a>

                <div class="navbar-user">
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <span><?= e($_SESSION['username']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?= BASE_URL ?>pages/profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <a href="<?= BASE_URL ?>pages/post-item.php" class="dropdown-item">
                                <i class="fas fa-plus"></i> Posting Barang
                            </a>
                            <a href="<?= BASE_URL ?>pages/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Pengaturan
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?= BASE_URL ?>auth/logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus"></i> Daftar
                </a>
            <?php endif; ?>
        </div>

        <button class="navbar-toggle" id="navbarToggle" type="button" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<?php $flashes = getFlashes(); if (!empty($flashes)): ?>
    <div class="container flash-container">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

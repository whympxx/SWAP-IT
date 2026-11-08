<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$uid = (int)$_SESSION['user_id'];

// Petakan link tersimpan ke URL aman di dalam aplikasi
function notificationTarget($link) {
    $link = (string)$link;
    if ($link === '' || preg_match('#^(https?:)?//#i', $link) || strpos($link, '..') !== false) {
        return BASE_URL . 'pages/notifications.php';
    }
    return BASE_URL . ltrim($link, '/');
}

// Buka satu notifikasi: tandai dibaca lalu redirect
if (isset($_GET['open'])) {
    $id = (int)$_GET['open'];
    $stmt = $conn->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $notif = $stmt->get_result()->fetch_assoc();
    if ($notif) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header('Location: ' . notificationTarget($notif['link']));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    setFlash('success', 'Semua notifikasi ditandai sudah dibaca.');
    header('Location: notifications.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$stmt->bind_param("i", $uid);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$has_unread = (bool)array_filter($notifications, fn($n) => !$n['is_read']);

function notificationIcon($title) {
    if (stripos($title, 'Diterima') !== false) return 'check-circle';
    if (stripos($title, 'Selesai') !== false) return 'trophy';
    if (stripos($title, 'Ditolak') !== false || stripos($title, 'Dibatalkan') !== false) return 'times-circle';
    if (stripos($title, 'Baru') !== false) return 'handshake';
    return 'bell';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Notifikasi</h1>
            <?php if ($has_unread): ?>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Tandai semua dibaca</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notification-list">
            <?php if ($notifications): ?>
                <?php foreach ($notifications as $n): ?>
                    <a href="?open=<?= (int)$n['id'] ?>" class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="notification-icon"><i class="fas fa-<?= notificationIcon($n['title']) ?>"></i></div>
                        <div class="notification-body">
                            <strong><?= e($n['title']) ?></strong>
                            <p><?= e($n['message']) ?></p>
                            <span class="notification-time"><?= timeAgo($n['created_at']) ?></span>
                        </div>
                        <?php if (!$n['is_read']): ?><span class="notification-dot"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>Belum ada notifikasi</h3>
                    <p>Notifikasi penawaran dan barter akan muncul di sini</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

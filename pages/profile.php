<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// profile.php?id=X -> profil publik; tanpa id -> profil sendiri (wajib login)
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$profile_id) {
    requireLogin();
    $profile_id = (int)$_SESSION['user_id'];
}
$is_me = isLoggedIn() && $profile_id === (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    setFlash('error', 'Pengguna tidak ditemukan.');
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$stmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM items WHERE user_id = ?) AS items,
    (SELECT COUNT(*) FROM offers WHERE sender_id = ? OR receiver_id = ?) AS offers,
    (SELECT COUNT(*) FROM offers WHERE (sender_id = ? OR receiver_id = ?) AND status = 'completed') AS completed");
$stmt->bind_param("iiiii", $profile_id, $profile_id, $profile_id, $profile_id, $profile_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT id, title, photo, category, condition_item FROM items WHERE user_id = ? AND status = 'available' ORDER BY created_at DESC");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_me ? 'Profil Saya' : 'Profil ' . e($user['username']) ?> - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> <?= $is_me ? 'Profil Saya' : 'Profil Pengguna' ?></h1>
        </div>

        <div class="profile-container">
            <div class="profile-card">
                <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>

                <div class="profile-info">
                    <h2><?= e($user['full_name']) ?></h2>
                    <p class="username">@<?= e($user['username']) ?></p>

                    <div class="profile-details">
                        <?php if ($is_me): ?>
                            <div class="detail-item"><i class="fas fa-envelope"></i><span><?= e($user['email']) ?></span></div>
                            <?php if (!empty($user['phone'])): ?>
                                <div class="detail-item"><i class="fas fa-phone"></i><span><?= e($user['phone']) ?></span></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($user['university'])): ?>
                            <div class="detail-item"><i class="fas fa-university"></i><span><?= e($user['university']) ?></span></div>
                        <?php endif; ?>
                        <div class="detail-item"><i class="fas fa-calendar"></i><span>Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?></span></div>
                    </div>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <i class="fas fa-box"></i>
                            <div class="stat-value"><?= (int)$stats['items'] ?></div>
                            <div class="stat-label">Barang</div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-handshake"></i>
                            <div class="stat-value"><?= (int)$stats['offers'] ?></div>
                            <div class="stat-label">Penawaran</div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-check-circle"></i>
                            <div class="stat-value"><?= (int)$stats['completed'] ?></div>
                            <div class="stat-label">Barter Selesai</div>
                        </div>
                    </div>

                    <?php if ($is_me): ?>
                        <div class="profile-actions">
                            <a href="settings.php" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profil</a>
                            <a href="my-items.php" class="btn btn-secondary"><i class="fas fa-box"></i> Kelola Barang</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="related-section">
            <h2>Barang Tersedia (<?= count($items) ?>)</h2>
            <?php if (!$items): ?>
                <p class="muted-text"><?= $is_me ? 'Anda belum punya barang tersedia.' : 'Pengguna ini belum punya barang tersedia.' ?></p>
            <?php else: ?>
                <div class="related-grid">
                    <?php foreach ($items as $item): ?>
                        <a class="related-card" href="item-detail.php?id=<?= (int)$item['id'] ?>">
                            <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                            <div>
                                <strong><?= e($item['title']) ?></strong>
                                <span><?= e($item['category']) ?> · <?= e($item['condition_item']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

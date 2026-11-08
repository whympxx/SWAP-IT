<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT i.*, u.username, u.full_name, u.university, u.created_at AS member_since
                        FROM items i
                        JOIN users u ON i.user_id = u.id
                        WHERE i.id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    setFlash('error', 'Barang tidak ditemukan atau sudah dihapus.');
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$is_owner = isLoggedIn() && $item['user_id'] == $_SESSION['user_id'];

// Penawaran user yang masih menunggu untuk barang ini
$my_pending_offer = null;
if (isLoggedIn() && !$is_owner) {
    $stmt = $conn->prepare("SELECT id FROM offers WHERE sender_id = ? AND item_wanted_id = ? AND status = 'pending' LIMIT 1");
    $stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
    $stmt->execute();
    $my_pending_offer = $stmt->get_result()->fetch_assoc();
}

// Jumlah penawaran masuk (untuk pemilik)
$pending_offer_count = 0;
if ($is_owner) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM offers WHERE item_wanted_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $pending_offer_count = (int)$stmt->get_result()->fetch_assoc()['c'];
}

// Statistik pemilik
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM offers WHERE (sender_id = ? OR receiver_id = ?) AND status = 'completed'");
$stmt->bind_param("ii", $item['user_id'], $item['user_id']);
$stmt->execute();
$owner_trades = (int)$stmt->get_result()->fetch_assoc()['c'];

// Barang lain dari pemilik yang sama
$stmt = $conn->prepare("SELECT id, title, photo, category FROM items
                        WHERE user_id = ? AND id != ? AND status = 'available'
                        ORDER BY created_at DESC LIMIT 4");
$stmt->bind_param("ii", $item['user_id'], $item_id);
$stmt->execute();
$other_items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($item['title']) ?> - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <a href="<?= BASE_URL ?>index.php#items" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke daftar barang</a>

        <div class="item-detail-container">
            <div class="item-detail-image">
                <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>"
                     alt="<?= e($item['title']) ?>">
            </div>

            <div class="item-detail-info">
                <div class="item-detail-header">
                    <h1><?= e($item['title']) ?></h1>
                    <span class="item-status-badge status-<?= e($item['status']) ?>">
                        <?= e(itemStatusLabel($item['status'])) ?>
                    </span>
                </div>

                <div class="item-detail-meta">
                    <div class="meta-item">
                        <i class="fas fa-th-large"></i>
                        <strong>Kategori:</strong> <?= e($item['category']) ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-star"></i>
                        <strong>Kondisi:</strong> <?= e($item['condition_item']) ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <strong>Diposting:</strong> <?= timeAgo($item['created_at']) ?>
                    </div>
                </div>

                <div class="item-detail-description">
                    <h3><i class="fas fa-align-left"></i> Deskripsi</h3>
                    <p><?= nl2br(e($item['description'])) ?></p>
                </div>

                <div class="item-detail-owner">
                    <h3><i class="fas fa-user"></i> Pemilik Barang</h3>
                    <a class="owner-info" href="profile.php?id=<?= (int)$item['user_id'] ?>">
                        <i class="fas fa-user-circle"></i>
                        <div>
                            <strong><?= e($item['full_name']) ?></strong>
                            <p>@<?= e($item['username']) ?></p>
                            <?php if (!empty($item['university'])): ?>
                                <p><i class="fas fa-university"></i> <?= e($item['university']) ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-check-circle"></i> <?= $owner_trades ?> barter selesai · bergabung <?= date('M Y', strtotime($item['member_since'])) ?></p>
                        </div>
                    </a>
                </div>

                <div class="item-detail-actions">
                    <?php if ($is_owner): ?>
                        <?php if ($pending_offer_count > 0): ?>
                            <a href="my-offers.php" class="btn btn-success btn-lg">
                                <i class="fas fa-handshake"></i> <?= $pending_offer_count ?> Penawaran Masuk
                            </a>
                        <?php endif; ?>
                        <?php if ($item['status'] !== 'traded'): ?>
                            <a href="edit-item.php?id=<?= (int)$item['id'] ?>" class="btn btn-secondary btn-lg">
                                <i class="fas fa-edit"></i> Edit Barang
                            </a>
                        <?php endif; ?>
                        <?php if ($item['status'] === 'available'): ?>
                            <form method="POST" action="delete-item.php" class="inline-form"
                                  onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-lg">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (isLoggedIn() && $my_pending_offer): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Anda sudah mengirim penawaran untuk barang ini. Tunggu respon pemilik.
                        </div>
                        <a href="my-offers.php?tab=sent" class="btn btn-secondary btn-lg btn-block">
                            <i class="fas fa-paper-plane"></i> Lihat Penawaran Saya
                        </a>
                    <?php elseif (isLoggedIn() && $item['status'] == 'available'): ?>
                        <a href="make-offer.php?item_id=<?= (int)$item['id'] ?>" class="btn btn-success btn-lg btn-block">
                            <i class="fas fa-exchange-alt"></i> Tawarkan Barang untuk Ditukar
                        </a>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-sign-in-alt"></i> Login untuk Menawarkan
                        </a>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i>
                            Barang ini sedang tidak menerima penawaran.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($other_items->num_rows > 0): ?>
            <div class="related-section">
                <h2>Barang lain dari @<?= e($item['username']) ?></h2>
                <div class="related-grid">
                    <?php while ($other = $other_items->fetch_assoc()): ?>
                        <a class="related-card" href="item-detail.php?id=<?= (int)$other['id'] ?>">
                            <img src="<?= UPLOAD_URL ?>items/<?= e($other['photo']) ?>" alt="<?= e($other['title']) ?>" loading="lazy">
                            <div>
                                <strong><?= e($other['title']) ?></strong>
                                <span><?= e($other['category']) ?></span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

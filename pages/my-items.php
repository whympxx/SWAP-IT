<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$status_filter = $_GET['status'] ?? 'all';
if (!is_string($status_filter) || ($status_filter !== 'all' && !isset(ITEM_STATUS_LABELS[$status_filter]))) {
    $status_filter = 'all';
}

// Jumlah per status untuk tab filter
$counts = ['all' => 0, 'available' => 0, 'pending' => 0, 'traded' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) AS c FROM items WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

// Barang user + jumlah penawaran masuk yang menunggu
$sql = "SELECT i.*,
        (SELECT COUNT(*) FROM offers o WHERE o.item_wanted_id = i.id AND o.status = 'pending') AS pending_offers
        FROM items i WHERE i.user_id = ?";
if ($status_filter !== 'all') {
    $sql .= " AND i.status = ?";
}
$sql .= " ORDER BY i.created_at DESC";
$stmt = $conn->prepare($sql);
if ($status_filter !== 'all') {
    $stmt->bind_param("is", $_SESSION['user_id'], $status_filter);
} else {
    $stmt->bind_param("i", $_SESSION['user_id']);
}
$stmt->execute();
$items = $stmt->get_result();

$filter_tabs = ['all' => 'Semua'] + ITEM_STATUS_LABELS;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Saya - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-box"></i> Barang Saya</h1>
            <a href="post-item.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Barang
            </a>
        </div>

        <?php if (isset($_GET['posted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Barang berhasil diposting!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Barang berhasil dihapus!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Barang berhasil diupdate!
            </div>
        <?php endif; ?>

        <div class="filter-chips">
            <?php foreach ($filter_tabs as $key => $label): ?>
                <a href="?status=<?= $key ?>" class="chip <?= $status_filter === $key ? 'active' : '' ?>">
                    <?= e($label) ?> <span><?= $counts[$key] ?? 0 ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="items-grid">
            <?php if ($items && $items->num_rows > 0): ?>
                <?php while ($item = $items->fetch_assoc()): ?>
                    <div class="item-card">
                        <a class="item-image" href="item-detail.php?id=<?= (int)$item['id'] ?>">
                            <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>"
                                 alt="<?= e($item['title']) ?>" loading="lazy">
                            <span class="item-category"><?= e($item['category']) ?></span>
                            <span class="item-status status-<?= e($item['status']) ?>">
                                <?= e(itemStatusLabel($item['status'])) ?>
                            </span>
                        </a>
                        <div class="item-content">
                            <h3 class="item-title"><?= e($item['title']) ?></h3>
                            <p class="item-condition">
                                <i class="fas fa-star"></i> <?= e($item['condition_item']) ?>
                            </p>
                            <p class="item-description"><?= e(excerpt($item['description'], 100)) ?></p>
                            <div class="item-meta">
                                <div class="item-date">
                                    <i class="fas fa-clock"></i>
                                    <?= timeAgo($item['created_at']) ?>
                                </div>
                                <?php if ($item['pending_offers'] > 0): ?>
                                    <a class="offer-count-link" href="my-offers.php">
                                        <i class="fas fa-handshake"></i> <?= (int)$item['pending_offers'] ?> penawaran baru
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="item-actions">
                                <a href="item-detail.php?id=<?= (int)$item['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Lihat
                                </a>
                                <?php if ($item['status'] !== 'traded'): ?>
                                    <a href="edit-item.php?id=<?= (int)$item['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                                <?php if ($item['status'] === 'available'): ?>
                                    <form method="POST" action="delete-item.php" class="inline-form"
                                          onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3><?= $status_filter === 'all' ? 'Belum ada barang' : 'Tidak ada barang dengan status ini' ?></h3>
                    <p>Mulai posting barang yang ingin Anda tukarkan</p>
                    <a href="post-item.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Posting Barang
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

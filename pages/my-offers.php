<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$active_tab = ($_GET['tab'] ?? '') === 'sent' ? 'sent' : 'received';

$base_sql = "SELECT o.*,
             i_wanted.title AS wanted_title, i_wanted.photo AS wanted_photo,
             i_offered.title AS offered_title, i_offered.photo AS offered_photo,
             u.username AS other_username, u.id AS other_id
             FROM offers o
             JOIN items i_wanted ON o.item_wanted_id = i_wanted.id
             JOIN items i_offered ON o.item_offered_id = i_offered.id";

// Urutan: yang perlu tindakan (pending/accepted) di atas
$order = " ORDER BY FIELD(o.status, 'pending', 'accepted', 'completed', 'rejected', 'cancelled'), o.updated_at DESC";

$stmt = $conn->prepare($base_sql . " JOIN users u ON o.receiver_id = u.id WHERE o.sender_id = ?" . $order);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$sent_offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare($base_sql . " JOIN users u ON o.sender_id = u.id WHERE o.receiver_id = ?" . $order);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$received_offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$received_pending = count(array_filter($received_offers, fn($o) => $o['status'] === 'pending'));
$sent_active = count(array_filter($sent_offers, fn($o) => in_array($o['status'], ['pending', 'accepted'], true)));

function offerActionButton($offer_id, $action, $label, $icon, $class, $confirm) {
    return '<form method="POST" action="offer-action.php" class="inline-form" onsubmit="return confirm(\'' . e($confirm) . '\')">'
        . csrfField()
        . '<input type="hidden" name="id" value="' . (int)$offer_id . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button type="submit" class="btn ' . e($class) . ' btn-sm"><i class="fas fa-' . e($icon) . '"></i> ' . e($label) . '</button>'
        . '</form>';
}

function renderOfferCard($offer, $type) {
    $mine_title  = $type === 'received' ? $offer['wanted_title']  : $offer['offered_title'];
    $mine_photo  = $type === 'received' ? $offer['wanted_photo']  : $offer['offered_photo'];
    $their_title = $type === 'received' ? $offer['offered_title'] : $offer['wanted_title'];
    $their_photo = $type === 'received' ? $offer['offered_photo'] : $offer['wanted_photo'];
    $mine_id     = $type === 'received' ? $offer['item_wanted_id'] : $offer['item_offered_id'];
    $their_id    = $type === 'received' ? $offer['item_offered_id'] : $offer['item_wanted_id'];
    ?>
    <div class="offer-card">
        <div class="offer-header">
            <a class="offer-user" href="profile.php?id=<?= (int)$offer['other_id'] ?>">
                <i class="fas fa-user-circle"></i>
                <strong><?= $type === 'sent' ? 'Kepada: ' : '' ?><?= e($offer['other_username']) ?></strong>
            </a>
            <span class="offer-status status-<?= e($offer['status']) ?>">
                <?= e(offerStatusLabel($offer['status'])) ?>
            </span>
        </div>

        <div class="offer-items">
            <a class="offer-item" href="item-detail.php?id=<?= (int)$mine_id ?>">
                <img src="<?= UPLOAD_URL ?>items/<?= e($mine_photo) ?>" alt="<?= e($mine_title) ?>" loading="lazy">
                <p>Barang Anda: <strong><?= e($mine_title) ?></strong></p>
            </a>
            <div class="offer-arrow">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <a class="offer-item" href="item-detail.php?id=<?= (int)$their_id ?>">
                <img src="<?= UPLOAD_URL ?>items/<?= e($their_photo) ?>" alt="<?= e($their_title) ?>" loading="lazy">
                <p><?= $type === 'received' ? 'Ditawarkan' : 'Diinginkan' ?>: <strong><?= e($their_title) ?></strong></p>
            </a>
        </div>

        <?php if (!empty($offer['message'])): ?>
            <div class="offer-message">
                <i class="fas fa-comment"></i>
                <p><?= nl2br(e($offer['message'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="offer-footer">
            <span class="offer-date">
                <i class="fas fa-clock"></i> <?= timeAgo($offer['created_at']) ?>
            </span>

            <div class="offer-actions">
                <?php if ($offer['status'] === 'pending' && $type === 'received'): ?>
                    <?= offerActionButton($offer['id'], 'accept', 'Terima', 'check', 'btn-success', 'Terima penawaran ini? Penawaran lain untuk barang yang sama akan dibatalkan otomatis.') ?>
                    <?= offerActionButton($offer['id'], 'reject', 'Tolak', 'times', 'btn-danger', 'Tolak penawaran ini?') ?>
                <?php elseif ($offer['status'] === 'pending' && $type === 'sent'): ?>
                    <?= offerActionButton($offer['id'], 'cancel', 'Tarik Penawaran', 'undo', 'btn-secondary', 'Tarik kembali penawaran ini?') ?>
                <?php elseif ($offer['status'] === 'accepted'): ?>
                    <a href="chat.php?offer_id=<?= (int)$offer['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                    <?= offerActionButton($offer['id'], 'complete', 'Barter Selesai', 'check-double', 'btn-success', 'Konfirmasi barang sudah saling ditukar?') ?>
                <?php elseif ($offer['status'] === 'completed'): ?>
                    <a href="chat.php?offer_id=<?= (int)$offer['id'] ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-history"></i> Riwayat Chat
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penawaran Saya - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-handshake"></i> Penawaran Saya</h1>
        </div>

        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Penawaran berhasil dikirim!
            </div>
        <?php endif; ?>

        <div class="offers-tabs">
            <button class="tab-btn <?= $active_tab === 'received' ? 'active' : '' ?>" data-tab="received" type="button">
                Penawaran Masuk
                <?php if ($received_pending > 0): ?>
                    <span class="badge"><?= $received_pending ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn <?= $active_tab === 'sent' ? 'active' : '' ?>" data-tab="sent" type="button">
                Penawaran Terkirim
                <?php if ($sent_active > 0): ?>
                    <span class="badge"><?= $sent_active ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Received Offers Tab -->
        <div class="tab-content <?= $active_tab === 'received' ? 'active' : '' ?>" id="received-tab">
            <?php if ($received_offers): ?>
                <?php foreach ($received_offers as $offer) renderOfferCard($offer, 'received'); ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada penawaran masuk</h3>
                    <p>Posting lebih banyak barang agar peluang barter makin besar</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sent Offers Tab -->
        <div class="tab-content <?= $active_tab === 'sent' ? 'active' : '' ?>" id="sent-tab">
            <?php if ($sent_offers): ?>
                <?php foreach ($sent_offers as $offer) renderOfferCard($offer, 'sent'); ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Belum ada penawaran terkirim</h3>
                    <p>Mulai tawarkan barang Anda untuk barter</p>
                    <a href="<?= BASE_URL ?>index.php#items" class="btn btn-primary">
                        <i class="fas fa-search"></i> Cari Barang
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

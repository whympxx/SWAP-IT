<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Get all offers with accepted status (where chat is available)
$stmt = $conn->prepare("SELECT o.*, 
                        u1.username as sender_username, u1.full_name as sender_name,
                        u2.username as receiver_username, u2.full_name as receiver_name,
                        i_wanted.title as wanted_title, i_wanted.photo as wanted_photo,
                        i_offered.title as offered_title, i_offered.photo as offered_photo,
                        (SELECT COUNT(*) FROM messages m 
                         WHERE m.offer_id = o.id 
                         AND m.receiver_id = ? 
                         AND m.is_read = 0) as unread_count,
                        (SELECT MAX(created_at) FROM messages m 
                         WHERE m.offer_id = o.id) as last_message_time,
                        (SELECT message FROM messages m 
                         WHERE m.offer_id = o.id 
                         ORDER BY m.id DESC LIMIT 1) as last_message,
                        (SELECT sender_id FROM messages m 
                         WHERE m.offer_id = o.id 
                         ORDER BY m.id DESC LIMIT 1) as last_sender_id
                        FROM offers o
                        JOIN users u1 ON o.sender_id = u1.id
                        JOIN users u2 ON o.receiver_id = u2.id
                        JOIN items i_wanted ON o.item_wanted_id = i_wanted.id
                        JOIN items i_offered ON o.item_offered_id = i_offered.id
                        WHERE (o.status IN ('accepted', 'completed')
                               OR (o.status = 'cancelled' AND EXISTS (SELECT 1 FROM messages mx WHERE mx.offer_id = o.id)))
                        AND (o.sender_id = ? OR o.receiver_id = ?)
                        ORDER BY (o.status = 'accepted') DESC, COALESCE(last_message_time, o.updated_at) DESC");
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$conversations = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-comments"></i> Pesan</h1>
        </div>
        
        <div class="conversations-container">
            <?php if ($conversations && $conversations->num_rows > 0): ?>
                <?php while ($conv = $conversations->fetch_assoc()): ?>
                    <?php
                        // Determine other user
                        $other_user_name = ($conv['sender_id'] == $_SESSION['user_id']) ? $conv['receiver_name'] : $conv['sender_name'];
                        $other_user_username = ($conv['sender_id'] == $_SESSION['user_id']) ? $conv['receiver_username'] : $conv['sender_username'];
                    ?>
                    <a href="chat.php?offer_id=<?= $conv['id'] ?>" class="conversation-card <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">
                        <div class="conversation-avatar">
                            <i class="fas fa-user-circle"></i>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="conversation-content">
                            <div class="conversation-header">
                                <strong>
                                    <?= e($other_user_name) ?>
                                    <?php if ($conv['status'] !== 'accepted'): ?>
                                        <span class="offer-status status-<?= e($conv['status']) ?> status-mini"><?= e(offerStatusLabel($conv['status'])) ?></span>
                                    <?php endif; ?>
                                </strong>
                                <span class="conversation-time">
                                    <?= $conv['last_message_time'] ? timeAgo($conv['last_message_time']) : 'Belum ada pesan' ?>
                                </span>
                            </div>
                            
                            <div class="conversation-deal">
                                <i class="fas fa-exchange-alt"></i>
                                <?= e($conv['offered_title']) ?> ⇄ <?= e($conv['wanted_title']) ?>
                            </div>
                            
                            <?php if ($conv['last_message']): ?>
                                <div class="conversation-preview">
                                    <?= $conv['last_sender_id'] == $_SESSION['user_id'] ? '<i class="fas fa-reply"></i> Anda: ' : '' ?><?= e(excerpt($conv['last_message'], 80)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="conversation-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Belum ada percakapan</h3>
                    <p>Percakapan akan muncul setelah penawaran diterima</p>
                    <a href="my-offers.php" class="btn btn-primary">
                        <i class="fas fa-handshake"></i> Lihat Penawaran
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

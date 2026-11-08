<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$error = '';
$success = '';

// Get the item details
$stmt = $conn->prepare("SELECT i.*, u.username FROM items i JOIN users u ON i.user_id = u.id WHERE i.id = ? AND i.status = 'available' AND i.user_id != ?");
$stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlash('error', 'Barang tidak tersedia untuk ditawar.');
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$wanted_item = $result->fetch_assoc();

// Cegah penawaran ganda untuk barang yang sama
$stmt = $conn->prepare("SELECT id FROM offers WHERE sender_id = ? AND item_wanted_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    setFlash('info', 'Anda sudah mengirim penawaran untuk barang ini. Tunggu respon pemilik.');
    header('Location: my-offers.php?tab=sent');
    exit();
}

$message = '';
$selected_item_id = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $offered_item_id = (int)($_POST['offered_item_id'] ?? 0);
    $selected_item_id = $offered_item_id;
    $message = cleanInput($_POST['message'] ?? '');

    if (!verifyCsrf()) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif (empty($offered_item_id)) {
        $error = 'Pilih barang yang ingin Anda tawarkan!';
    } elseif (textLength($message) > 1000) {
        $error = 'Pesan maksimal 1000 karakter.';
    } else {
        // Verify ownership of offered item
        $stmt = $conn->prepare("SELECT id, title FROM items WHERE id = ? AND user_id = ? AND status = 'available'");
        $stmt->bind_param("ii", $offered_item_id, $_SESSION['user_id']);
        $stmt->execute();
        $offered_item = $stmt->get_result()->fetch_assoc();

        if (!$offered_item) {
            $error = 'Barang tidak valid!';
        } else {
            $stmt = $conn->prepare("INSERT INTO offers (sender_id, receiver_id, item_wanted_id, item_offered_id, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $_SESSION['user_id'], $wanted_item['user_id'], $item_id, $offered_item_id, $message);

            if ($stmt->execute()) {
                notify($conn, $wanted_item['user_id'], 'Penawaran Barter Baru',
                    $current_user['username'] . ' menawarkan "' . $offered_item['title'] . '" untuk ditukar dengan "' . $wanted_item['title'] . '"',
                    'pages/my-offers.php');

                header('Location: my-offers.php?sent=1&tab=sent');
                exit();
            }
            $error = 'Terjadi kesalahan saat mengirim penawaran!';
        }
    }
}

// Get user's available items
$stmt = $conn->prepare("SELECT * FROM items WHERE user_id = ? AND status = 'available' ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Penawaran - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> Buat Penawaran Barter</h1>
            <p>Pilih barang Anda untuk ditukar</p>
        </div>
        
        <div class="offer-container">
            <div class="wanted-item-section">
                <h3>Barang yang Diinginkan</h3>
                <div class="item-card-horizontal">
                    <img src="<?= UPLOAD_URL ?>items/<?= e($wanted_item['photo']) ?>" 
                         alt="<?= e($wanted_item['title']) ?>">
                    <div class="item-info">
                        <h4><?= e($wanted_item['title']) ?></h4>
                        <p><i class="fas fa-user"></i> <?= e($wanted_item['username']) ?></p>
                        <p><i class="fas fa-th-large"></i> <?= e($wanted_item['category']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="offer-form-section">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($my_items->num_rows == 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Anda belum memiliki barang yang tersedia untuk ditukar.
                        <a href="post-item.php">Posting barang sekarang</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <h3>Pilih Barang Anda</h3>
                        
                        <div class="my-items-selection">
                            <?php while ($item = $my_items->fetch_assoc()): ?>
                                <label class="item-selection-card">
                                    <input type="radio" name="offered_item_id" value="<?= (int)$item['id'] ?>" required <?= $selected_item_id === (int)$item['id'] ? 'checked' : '' ?>>
                                    <div class="selection-content">
                                        <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>" 
                                             alt="<?= e($item['title']) ?>">
                                        <div class="selection-info">
                                            <h4><?= e($item['title']) ?></h4>
                                            <p><i class="fas fa-th-large"></i> <?= e($item['category']) ?></p>
                                            <p><i class="fas fa-star"></i> <?= e($item['condition_item']) ?></p>
                                        </div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">
                                <i class="fas fa-comment"></i> Pesan (Opsional)
                            </label>
                            <textarea id="message" name="message" rows="4" maxlength="1000" data-counter="msgCounter"
                                      placeholder="Tulis pesan untuk pemilik barang..."><?= e($message) ?></textarea>
                            <small id="msgCounter" class="char-counter"></small>
                        </div>
                        
                        <div class="form-actions">
                            <a href="item-detail.php?id=<?= $item_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Kirim Penawaran
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

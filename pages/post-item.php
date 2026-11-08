<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$error = '';

$categories = ITEM_CATEGORIES;
$conditions = ITEM_CONDITIONS;
$old = ['title' => '', 'description' => '', 'category' => '', 'condition' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = cleanInput($_POST['title'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $category = cleanInput($_POST['category'] ?? '');
    $condition = cleanInput($_POST['condition'] ?? '');
    $old = compact('title', 'description', 'category', 'condition');

    if (!verifyCsrf()) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif ($title === '' || $description === '' || $category === '' || $condition === '') {
        $error = 'Semua field wajib diisi!';
    } elseif (textLength($title) > 200) {
        $error = 'Judul maksimal 200 karakter.';
    } elseif (textLength($description) > 5000) {
        $error = 'Deskripsi maksimal 5000 karakter.';
    } elseif (!in_array($category, $categories, true)) {
        $error = 'Kategori tidak valid!';
    } elseif (!in_array($condition, $conditions, true)) {
        $error = 'Kondisi tidak valid!';
    } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] == UPLOAD_ERR_NO_FILE) {
        $error = 'Foto barang wajib diunggah!';
    } else {
        $upload_result = uploadImage($_FILES['photo'], 'items');

        if ($upload_result['success']) {
            $photo = $upload_result['filename'];

            $stmt = $conn->prepare("INSERT INTO items (user_id, title, description, category, condition_item, photo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $_SESSION['user_id'], $title, $description, $category, $condition, $photo);

            if ($stmt->execute()) {
                header('Location: my-items.php?posted=1');
                exit();
            }
            $error = 'Terjadi kesalahan saat memposting barang!';
        } else {
            $error = $upload_result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posting Barang - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-plus-circle"></i> Posting Barang Baru</h1>
            <p>Unggah barang yang ingin Anda tukarkan</p>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="post-item-form">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="title">
                        <i class="fas fa-heading"></i> Judul Barang *
                    </label>
                    <input type="text" id="title" name="title" required maxlength="200"
                           value="<?= e($old['title']) ?>"
                           placeholder="Contoh: Buku Kalkulus Edisi 10">
                </div>

                <div class="form-group">
                    <label for="category">
                        <i class="fas fa-th-large"></i> Kategori *
                    </label>
                    <select id="category" name="category" required>
                        <option value="">Pilih kategori...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $old['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="condition">
                        <i class="fas fa-star"></i> Kondisi *
                    </label>
                    <select id="condition" name="condition" required>
                        <option value="">Pilih kondisi...</option>
                        <?php foreach ($conditions as $cond): ?>
                            <option value="<?= e($cond) ?>" <?= $old['condition'] === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Deskripsi *
                    </label>
                    <textarea id="description" name="description" rows="6" required maxlength="5000" data-counter="descCounter"
                              placeholder="Jelaskan kondisi barang, kelebihan, kekurangan, barang apa yang Anda cari, dll."><?= e($old['description']) ?></textarea>
                    <small id="descCounter" class="char-counter"></small>
                </div>

                <div class="form-group">
                    <label for="photo">
                        <i class="fas fa-camera"></i> Foto Barang *
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <small>Format: JPG, PNG, GIF, WEBP. Maksimal 5MB</small>
                    <div id="imagePreview" class="image-preview"></div>
                </div>

                <div class="form-actions">
                    <a href="my-items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Posting Barang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>

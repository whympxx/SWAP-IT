<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Get item data
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: my-items.php');
    exit();
}

$item = $result->fetch_assoc();

if ($item['status'] === 'traded') {
    setFlash('error', 'Barang yang sudah ditukar tidak bisa diedit.');
    header('Location: my-items.php');
    exit();
}

$categories = ITEM_CATEGORIES;
$conditions = ITEM_CONDITIONS;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = cleanInput($_POST['title'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $category = cleanInput($_POST['category'] ?? '');
    $condition = cleanInput($_POST['condition'] ?? '');

    // Simpan isian supaya tidak hilang jika ada error
    $item['title'] = $title;
    $item['description'] = $description;
    $item['category'] = $category;
    $item['condition_item'] = $condition;

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
    } else {
        $photo = $item['photo']; // Keep old photo by default
        $old_photo = null;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] != UPLOAD_ERR_NO_FILE) {
            $upload_result = uploadImage($_FILES['photo'], 'items');

            if ($upload_result['success']) {
                $old_photo = $item['photo'];
                $photo = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, condition_item = ?, photo = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssssii", $title, $description, $category, $condition, $photo, $item_id, $_SESSION['user_id']);

            if ($stmt->execute()) {
                // Foto lama baru dihapus setelah database berhasil diupdate
                if ($old_photo) {
                    $old_photo_path = UPLOAD_PATH . 'items/' . basename($old_photo);
                    if (is_file($old_photo_path)) {
                        @unlink($old_photo_path);
                    }
                }
                header('Location: my-items.php?updated=1');
                exit();
            }
            $error = 'Terjadi kesalahan saat update barang!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Barang - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Edit Barang</h1>
            <p>Update informasi barang Anda</p>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="title">
                        <i class="fas fa-heading"></i> Judul Barang *
                    </label>
                    <input type="text" id="title" name="title" required maxlength="200"
                           value="<?= e($item['title']) ?>">
                </div>

                <div class="form-group">
                    <label for="category">
                        <i class="fas fa-th-large"></i> Kategori *
                    </label>
                    <select id="category" name="category" required>
                        <option value="">Pilih kategori...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
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
                            <option value="<?= e($cond) ?>" <?= $item['condition_item'] === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Deskripsi *
                    </label>
                    <textarea id="description" name="description" rows="6" required maxlength="5000" data-counter="descCounter"><?= e($item['description']) ?></textarea>
                    <small id="descCounter" class="char-counter"></small>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-camera"></i> Foto Saat Ini
                    </label>
                    <div class="current-photo">
                        <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>"
                             alt="Foto saat ini"
                             style="max-width: 300px; border-radius: 12px; box-shadow: var(--shadow);">
                    </div>
                </div>

                <div class="form-group">
                    <label for="photo">
                        <i class="fas fa-camera"></i> Ganti Foto (Opsional)
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>Kosongkan jika tidak ingin mengganti foto. Format: JPG, PNG, GIF, WEBP. Max 5MB</small>
                    <div id="imagePreview" class="image-preview"></div>
                </div>

                <div class="form-actions">
                    <a href="my-items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
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

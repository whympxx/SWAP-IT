<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = cleanInput($_POST['full_name'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $university = cleanInput($_POST['university'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (!verifyCsrf()) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif (empty($full_name)) {
        $error = 'Nama lengkap harus diisi!';
    } elseif (textLength($full_name) > 100 || textLength($university) > 100) {
        $error = 'Nama dan universitas maksimal 100 karakter.';
    } elseif ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{8,20}$/', $phone)) {
        $error = 'Nomor HP tidak valid.';
    } else {
        // If changing password
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error = 'Password lama harus diisi!';
            } elseif (!password_verify($current_password, $current_user['password'])) {
                $error = 'Password lama salah!';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Password baru tidak cocok!';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password minimal 6 karakter!';
            } else {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, university = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $full_name, $phone, $university, $hashed_password, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $success = 'Profil dan password berhasil diupdate!';
                    $current_user = getCurrentUser($conn);
                } else {
                    $error = 'Gagal update profil!';
                }
            }
        } else {
            // Update without password change
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, university = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $phone, $university, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $success = 'Profil berhasil diupdate!';
                $current_user = getCurrentUser($conn);
            } else {
                $error = 'Gagal update profil!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container main-content">
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Pengaturan Akun</h1>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= csrfField() ?>
                <h3>Informasi Profil</h3>
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user-circle"></i> Username
                    </label>
                    <input type="text" id="username" value="<?= e($current_user['username']) ?>" disabled>
                    <small>Username tidak bisa diubah</small>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="email" value="<?= e($current_user['email']) ?>" disabled>
                    <small>Email tidak bisa diubah</small>
                </div>
                
                <div class="form-group">
                    <label for="full_name">
                        <i class="fas fa-user"></i> Nama Lengkap *
                    </label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?= e($current_user['full_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i> Nomor HP
                    </label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= e($current_user['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="university">
                        <i class="fas fa-university"></i> Universitas
                    </label>
                    <input type="text" id="university" name="university"
                           value="<?= e($current_user['university'] ?? '') ?>">
                </div>
                
                <hr style="margin: 2rem 0;">
                
                <h3>Ubah Password (Opsional)</h3>
                <small style="color: var(--gray-600); margin-bottom: 1rem; display: block;">
                    Kosongkan jika tidak ingin mengubah password
                </small>
                
                <div class="form-group">
                    <label for="current_password">
                        <i class="fas fa-lock"></i> Password Lama
                    </label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">
                        <i class="fas fa-key"></i> Password Baru
                    </label>
                    <input type="password" id="new_password" name="new_password">
                    <small>Minimal 6 karakter</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-key"></i> Konfirmasi Password Baru
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                
                <div class="form-actions">
                    <a href="profile.php" class="btn btn-secondary">
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

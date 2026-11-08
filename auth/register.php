<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';
$success = '';
$old = ['username' => '', 'email' => '', 'full_name' => '', 'phone' => '', 'university' => ''];

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    $username = cleanInput($_POST['username'] ?? '');
    $email = strtolower(cleanInput($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = cleanInput($_POST['full_name'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $university = cleanInput($_POST['university'] ?? '');
    $old = compact('username', 'email', 'full_name', 'phone', 'university');
    
    // Validation
    if (!verifyCsrf()) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Semua field wajib diisi!';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter dan hanya boleh huruf, angka, titik, atau underscore.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif (textLength($full_name) > 100) {
        $error = 'Nama lengkap maksimal 100 karakter.';
    } elseif ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{8,20}$/', $phone)) {
        $error = 'Nomor HP tidak valid.';
    } elseif (textLength($university) > 100) {
        $error = 'Nama universitas maksimal 100 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok!';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username atau email sudah terdaftar!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, university) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $email, $hashed_password, $full_name, $phone, $university);
            
            if ($stmt->execute()) {
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = 'Terjadi kesalahan saat registrasi!';
            }
        }
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-exchange-alt"></i>
                    <h1>SWAP-IT</h1>
                </div>
                <p class="tagline">Platform Barter Barang Bekas Antar Mahasiswa</p>
            </div>
            
            <div class="auth-body">
                <h2>Buat Akun Baru</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-user"></i> Nama Lengkap *
                        </label>
                        <input type="text" id="full_name" name="full_name" required value="<?= e($old['full_name']) ?>"
                               placeholder="Masukkan nama lengkap">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user-circle"></i> Username *
                        </label>
                        <input type="text" id="username" name="username" required value="<?= e($old['username']) ?>"
                               placeholder="Huruf, angka, titik, underscore" pattern="[A-Za-z0-9_.]{3,30}">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" id="email" name="email" required value="<?= e($old['email']) ?>"
                               placeholder="Masukkan email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Nomor HP
                        </label>
                        <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>"
                               placeholder="Masukkan nomor HP">
                    </div>
                    
                    <div class="form-group">
                        <label for="university">
                            <i class="fas fa-university"></i> Universitas
                        </label>
                        <input type="text" id="university" name="university" value="<?= e($old['university']) ?>"
                               placeholder="Masukkan nama universitas">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Minimal 6 karakter" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Konfirmasi Password *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Ulangi password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Daftar
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>

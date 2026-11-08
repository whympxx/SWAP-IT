<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';
$success = '';
$email = '';
$redirect = safeRedirectPath($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . ($redirect ?: BASE_URL . 'index.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!verifyCsrf()) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Cegah session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                
                header('Location: ' . ($redirect ?: BASE_URL . 'index.php'));
                exit();
            } else {
                $error = 'Email atau password salah!';
            }
        } else {
            $error = 'Email atau password salah!';
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
    <title>Login - SWAP-IT</title>
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
                <h2>Masuk ke Akun</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Password berhasil direset. Silakan login dengan password baru.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Registrasi berhasil! Silakan login.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <?php if ($redirect): ?>
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= e($email) ?>" placeholder="Masukkan email Anda">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                   placeholder="Masukkan password Anda">
                            <button type="button" class="password-toggle" data-target="password" aria-label="Tampilkan password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small style="margin-top: 0.5rem; display: block;">
                            <a href="forgot-password.php" style="color: var(--primary-color); text-decoration: none;">
                                <i class="fas fa-question-circle"></i> Lupa password?
                            </a>
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>

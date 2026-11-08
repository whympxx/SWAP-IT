<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';
$success = '';
$valid_token = false;
$user_id = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Token reset password tidak valid.';
} else {
    try {
        $token = (string)$_GET['token'];
        $token_hash = hash('sha256', $token);
        
        $conn = getDBConnection();
        
        // Check if token exists and is not expired
        $stmt = $conn->prepare("SELECT pr.user_id, pr.expires_at, u.email, u.username 
                                FROM password_resets pr 
                                JOIN users u ON pr.user_id = u.id 
                                WHERE pr.token = ? AND pr.expires_at > NOW()");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $valid_token = true;
            $reset_data = $result->fetch_assoc();
            $user_id = $reset_data['user_id'];
        } else {
            // Check if token exists but expired
            $stmt = $conn->prepare("SELECT user_id, expires_at, TIMESTAMPDIFF(MINUTE, expires_at, NOW()) as expired_minutes FROM password_resets WHERE token = ?");
            if ($stmt) {
                $stmt->bind_param("s", $token_hash);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $token_data = $result->fetch_assoc();
                    $expired_minutes = $token_data['expired_minutes'];
                    
                    // Delete expired token
                    $cleanup_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    if ($cleanup_stmt) {
                        $cleanup_stmt->bind_param("s", $token_hash);
                        $cleanup_stmt->execute();
                    }
                    
                    if ($expired_minutes > 60) {
                        $hours = floor($expired_minutes / 60);
                        $error = "Token reset password telah kadaluarsa sejak $hours jam yang lalu. Link reset hanya berlaku selama 2 jam. Silakan request reset password baru.";
                    } else {
                        $error = "Token reset password telah kadaluarsa sejak $expired_minutes menit yang lalu. Link reset hanya berlaku selama 2 jam. Silakan request reset password baru.";
                    }
                } else {
                    $error = 'Token reset password tidak valid atau sudah digunakan. Pastikan Anda menggunakan link terbaru.';
                }
            } else {
                $error = 'Token reset password tidak valid.';
            }
        }
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        error_log('Reset password validation error: ' . $e->getMessage());
    }
}

// Process password reset
$form_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    try {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        if (!verifyCsrf()) {
            $form_error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
        } elseif (empty($password) || empty($confirm_password)) {
            $form_error = 'Password dan konfirmasi password harus diisi!';
        } elseif (strlen($password) < 6) {
            $form_error = 'Password minimal 6 karakter!';
        } elseif (strlen($password) > 255) {
            $form_error = 'Password terlalu panjang! Maksimal 255 karakter.';
        } elseif ($password !== $confirm_password) {
            $form_error = 'Password dan konfirmasi password tidak cocok!';
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if (!$hashed_password) {
                throw new Exception('Failed to hash password');
            }
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // Delete used token
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }
                
                $success = true;
            } else {
                $form_error = 'Terjadi kesalahan saat mereset password. Silakan coba lagi.';
            }
        }
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        error_log('Reset password update error: ' . $e->getMessage());
    }
}

if (isset($conn)) {
    $conn->close();
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SWAP-IT</title>
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
                <?php if ($success): ?>
                    <h2><i class="fas fa-check-circle" style="color: var(--success-color);"></i> Password Berhasil Direset!</h2>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Password Anda telah berhasil direset. Silakan login dengan password baru Anda.
                    </div>
                    <a href="login.php" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login Sekarang
                    </a>
                    
                <?php elseif ($error): ?>
                    <h2><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Error</h2>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                    <div class="auth-footer">
                        <p><a href="forgot-password.php"><i class="fas fa-redo"></i> Request Reset Baru</a></p>
                        <p><a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a></p>
                    </div>
                    
                <?php elseif ($valid_token): ?>
                    <h2><i class="fas fa-lock"></i> Buat Password Baru</h2>
                    <p style="color: var(--gray-600); margin-bottom: 1.5rem;">
                        Masukkan password baru Anda di bawah ini.
                    </p>
                    
                    <?php if ($form_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= e($form_error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="resetForm">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-key"></i> Password Baru
                            </label>
                            <input type="password" id="password" name="password" required 
                                   placeholder="Masukkan password baru (minimal 6 karakter)"
                                   minlength="6">
                            <small id="password-strength" style="margin-top: 0.5rem; display: block;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check-double"></i> Konfirmasi Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   placeholder="Masukkan ulang password baru">
                            <small id="password-match" style="margin-top: 0.5rem; display: block;"></small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Reset Password
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p><a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthText = document.getElementById('password-strength');
        const matchText = document.getElementById('password-match');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let text = '';
                let color = '';
                
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                if (password.length === 0) {
                    text = '';
                } else if (strength <= 1) {
                    text = '🔴 Lemah';
                    color = 'var(--danger-color)';
                } else if (strength <= 3) {
                    text = '🟡 Sedang';
                    color = 'var(--warning-color)';
                } else {
                    text = '🟢 Kuat';
                    color = 'var(--success-color)';
                }
                
                strengthText.textContent = text;
                strengthText.style.color = color;
                strengthText.style.fontWeight = '600';
            });
        }
        
        // Password match indicator
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirm = this.value;
                
                if (confirm.length === 0) {
                    matchText.textContent = '';
                } else if (password === confirm) {
                    matchText.textContent = '✓ Password cocok';
                    matchText.style.color = 'var(--success-color)';
                    matchText.style.fontWeight = '600';
                } else {
                    matchText.textContent = '✗ Password tidak cocok';
                    matchText.style.color = 'var(--danger-color)';
                    matchText.style.fontWeight = '600';
                }
            });
        }
        
        // Form validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Password dan konfirmasi password tidak cocok!');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password minimal 6 karakter!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>

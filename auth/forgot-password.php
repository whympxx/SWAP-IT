<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDBConnection();
        
        $email = isset($_POST['email']) ? cleanInput($_POST['email']) : '';
        
        if (!verifyCsrf()) {
            $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
        } elseif (empty($email)) {
            $error = 'Email harus diisi!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid!';
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                
                // Clean up expired tokens globally (housekeeping)
                $conn->query("DELETE FROM password_resets WHERE expires_at < NOW()");
                
                // Delete old tokens for this user
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                }
                
                // Store new token (waktu kedaluwarsa dihitung oleh MySQL agar
                // konsisten dengan NOW() saat validasi)
                $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))");
                if (!$stmt) {
                    throw new Exception('Database error: ' . $conn->error);
                }
                
                $stmt->bind_param("is", $user['id'], $token_hash);
                
                if ($stmt->execute()) {
                    // Create reset link
                    $reset_link = BASE_URL . "auth/reset-password.php?token=" . urlencode($token);
                    
                    // In production, send this via email
                    // For now, we'll display it (for development)
                    $safe_link = e($reset_link);
                    $success = "Link reset password telah dibuat. Silakan klik link berikut untuk mereset password Anda: <br><br>
                               <a href='$safe_link' style='color: var(--primary-color); font-weight: 600; word-break: break-all;'>$safe_link</a><br><br>
                               <small><i class='fas fa-clock'></i> Link ini berlaku selama <strong>2 jam</strong>.</small>";
                    
                    // TODO: Send email with reset link
                    // Example email content:
                    // $to = $user['email'];
                    // $subject = "Reset Password - SWAP-IT";
                    // $message = "Halo " . $user['username'] . ",\n\n";
                    // $message .= "Anda meminta reset password. Klik link berikut untuk mereset password Anda:\n\n";
                    // $message .= $reset_link . "\n\n";
                    // $message .= "Link ini berlaku selama 1 jam.\n\n";
                    // $message .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
                    // $message .= "Terima kasih,\nTim SWAP-IT";
                    // mail($to, $subject, $message);
                    
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan token. Silakan coba lagi.';
                }
            } else {
                // Don't reveal that email doesn't exist (security best practice)
                $success = "Jika email terdaftar, link reset password telah dikirim ke email Anda.";
            }
        }
        
        if (isset($conn)) {
            $conn->close();
        }
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        error_log('Forgot password error: ' . $e->getMessage());
        if (isset($conn)) {
            $conn->close();
        }
    }
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
    <title>Lupa Password - SWAP-IT</title>
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
                <h2><i class="fas fa-key"></i> Lupa Password</h2>
                <p style="color: var(--gray-600); margin-bottom: 1.5rem;">
                    Masukkan email Anda dan kami akan mengirimkan link untuk mereset password.
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" id="email" name="email" required 
                               placeholder="Masukkan email yang terdaftar">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Kirim Link Reset
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="auth-footer">
                    <p><a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

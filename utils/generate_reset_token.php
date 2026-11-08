<?php
/**
 * Generate Reset Password Token
 * 
 * Quick script to generate a reset password token for testing
 */

// Change to SWAP-IT directory
chdir(dirname(__DIR__));
require_once 'config/database.php';
require_once 'config/functions.php';

echo "========================================\n";
echo "Generate Password Reset Token\n";
echo "========================================\n\n";

try {
    $conn = getDBConnection();
    
    // Get user email from command line or use default
    $email = $argv[1] ?? null;
    
    if (!$email) {
        // Show available users
        echo "Available users:\n";
        echo str_repeat("-", 50) . "\n";
        
        $result = $conn->query("SELECT id, email, username FROM users ORDER BY id LIMIT 10");
        while ($row = $result->fetch_assoc()) {
            printf("%3d | %-30s | %s\n", $row['id'], $row['email'], $row['username']);
        }
        echo str_repeat("-", 50) . "\n\n";
        
        echo "Usage: php generate_reset_token.php [email]\n";
        echo "Example: php generate_reset_token.php john@example.com\n\n";
        
        // Use first user as default
        $result = $conn->query("SELECT email FROM users LIMIT 1");
        if ($result->num_rows > 0) {
            $email = $result->fetch_assoc()['email'];
            echo "Using default email: $email\n\n";
        } else {
            echo "✗ No users found in database!\n";
            exit(1);
        }
    }
    
    // Get user
    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo "✗ User with email '$email' not found!\n";
        exit(1);
    }
    
    $user = $result->fetch_assoc();
    echo "User found:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Username: {$user['username']}\n\n";
    
    // Clean up expired tokens globally
    $conn->query("DELETE FROM password_resets WHERE expires_at < NOW()");
    
    // Delete old tokens for this user
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    echo "✓ Old tokens cleaned up\n\n";
    
    // Generate new token
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    // Insert token
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user['id'], $token_hash, $expires);
    
    if ($stmt->execute()) {
        echo "✓ Token generated successfully!\n\n";
        
        // Build reset link
        $base_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/SWAP-IT/';
        $reset_link = $base_url . "auth/reset-password.php?token=" . urlencode($token);
        
        echo "========================================\n";
        echo "Reset Password Link\n";
        echo "========================================\n\n";
        echo $reset_link . "\n\n";
        
        echo "Token Details:\n";
        echo "  Created: " . date('Y-m-d H:i:s') . "\n";
        echo "  Expires: $expires\n";
        echo "  Valid for: 2 hours\n\n";
        
        echo "========================================\n";
        echo "Instructions:\n";
        echo "========================================\n";
        echo "1. Copy the link above\n";
        echo "2. Paste in your browser\n";
        echo "3. Enter new password (min 6 characters)\n";
        echo "4. Confirm password\n";
        echo "5. Submit\n\n";
        
        // Save to file for easy access
        $link_file = dirname(__DIR__) . '/reset_link.txt';
        file_put_contents($link_file, $reset_link);
        echo "✓ Link also saved to: reset_link.txt\n";
        
    } else {
        echo "✗ Failed to generate token: " . $stmt->error . "\n";
        exit(1);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

<?php
/**
 * Cleanup Expired Password Reset Tokens
 * 
 * This script removes expired password reset tokens from the database.
 * Can be run manually or set up as a cron job.
 */

// Change to SWAP-IT directory
chdir(dirname(__DIR__));
require_once 'config/database.php';

echo "========================================\n";
echo "Cleanup Expired Password Reset Tokens\n";
echo "========================================\n\n";

try {
    $conn = getDBConnection();
    
    // Count expired tokens before cleanup
    $result = $conn->query("SELECT COUNT(*) as count FROM password_resets WHERE expires_at < NOW()");
    $before_count = $result->fetch_assoc()['count'];
    
    echo "Found $before_count expired token(s)\n\n";
    
    if ($before_count > 0) {
        // Delete expired tokens
        $result = $conn->query("DELETE FROM password_resets WHERE expires_at < NOW()");
        
        if ($result) {
            $deleted = $conn->affected_rows;
            echo "✓ Successfully deleted $deleted expired token(s)\n";
        } else {
            echo "✗ Error deleting tokens: " . $conn->error . "\n";
        }
    } else {
        echo "✓ No expired tokens to clean up\n";
    }
    
    // Count remaining active tokens
    $result = $conn->query("SELECT COUNT(*) as count FROM password_resets WHERE expires_at > NOW()");
    $active_count = $result->fetch_assoc()['count'];
    
    echo "\nActive token(s) remaining: $active_count\n";
    
    // Show details of active tokens (without revealing the actual token)
    if ($active_count > 0) {
        echo "\nActive Tokens:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-10s | %-25s | %-30s | %-10s\n", "User ID", "Email", "Expires At", "Remaining");
        echo str_repeat("-", 80) . "\n";
        
        $result = $conn->query("SELECT pr.user_id, u.email, pr.expires_at, 
                                TIMESTAMPDIFF(MINUTE, NOW(), pr.expires_at) as minutes_remaining
                                FROM password_resets pr
                                JOIN users u ON pr.user_id = u.id
                                WHERE pr.expires_at > NOW()
                                ORDER BY pr.expires_at ASC");
        
        while ($row = $result->fetch_assoc()) {
            $minutes = $row['minutes_remaining'];
            $time_str = $minutes > 60 
                ? floor($minutes / 60) . "h " . ($minutes % 60) . "m" 
                : $minutes . " min";
            
            printf("%-10s | %-25s | %-30s | %-10s\n", 
                $row['user_id'], 
                $row['email'], 
                $row['expires_at'], 
                $time_str
            );
        }
        echo str_repeat("-", 80) . "\n";
    }
    
    $conn->close();
    
    echo "\n========================================\n";
    echo "Cleanup Complete\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

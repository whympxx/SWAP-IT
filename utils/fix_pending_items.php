<?php
/**
 * Fix Pending Items - Update to Available
 */

chdir(dirname(__DIR__));
require_once 'config/database.php';

echo "========================================\n";
echo "Fix Pending Items\n";
echo "========================================\n\n";

try {
    $conn = getDBConnection();
    
    // Count pending items
    $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'pending'");
    $pending_count = $result->fetch_assoc()['count'];
    
    echo "Found $pending_count pending item(s)\n\n";
    
    if ($pending_count > 0) {
        // Show pending items
        echo "Pending items:\n";
        echo str_repeat("-", 60) . "\n";
        
        $result = $conn->query("SELECT id, title, category, status FROM items WHERE status = 'pending'");
        while ($item = $result->fetch_assoc()) {
            printf("ID: %d | %s | %s\n", $item['id'], $item['title'], $item['category']);
        }
        echo str_repeat("-", 60) . "\n\n";
        
        // Ask for confirmation
        echo "Update all pending items to 'available'? (y/n): ";
        
        // Auto yes for script
        $confirm = 'y';
        
        if ($confirm === 'y') {
            // Update all pending to available
            $result = $conn->query("UPDATE items SET status = 'available' WHERE status = 'pending'");
            
            if ($result) {
                $updated = $conn->affected_rows;
                echo "\n✓ Successfully updated $updated item(s) to 'available'\n";
            } else {
                echo "\n✗ Error updating items: " . $conn->error . "\n";
            }
        } else {
            echo "\nOperation cancelled.\n";
        }
    } else {
        echo "✓ No pending items to fix.\n";
    }
    
    // Show final count
    echo "\n";
    $result = $conn->query("SELECT status, COUNT(*) as count FROM items GROUP BY status");
    echo "Items by status:\n";
    echo str_repeat("-", 30) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("  %-15s: %d\n", $row['status'], $row['count']);
    }
    echo str_repeat("-", 30) . "\n";
    
    $conn->close();
    
    echo "\n========================================\n";
    echo "Complete\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

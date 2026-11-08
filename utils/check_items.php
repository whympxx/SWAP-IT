<?php
/**
 * Check Items in Database
 */

chdir(dirname(__DIR__));
require_once 'config/database.php';

echo "========================================\n";
echo "Check Items in Database\n";
echo "========================================\n\n";

try {
    $conn = getDBConnection();
    
    // Count all items
    $result = $conn->query("SELECT COUNT(*) as count FROM items");
    $total = $result->fetch_assoc()['count'];
    echo "Total items: $total\n";
    
    // Count available items
    $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'available'");
    $available = $result->fetch_assoc()['count'];
    echo "Available items: $available\n\n";
    
    // Count by status
    echo "Items by status:\n";
    echo str_repeat("-", 40) . "\n";
    $result = $conn->query("SELECT status, COUNT(*) as count FROM items GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        printf("  %-15s: %d\n", $row['status'], $row['count']);
    }
    echo "\n";
    
    if ($available > 0) {
        echo "Sample available items:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-5s | %-30s | %-15s | %-20s\n", "ID", "Title", "Category", "Owner");
        echo str_repeat("-", 80) . "\n";
        
        $result = $conn->query("SELECT i.*, u.username 
                                FROM items i 
                                JOIN users u ON i.user_id = u.id 
                                WHERE i.status = 'available' 
                                LIMIT 10");
        
        while ($item = $result->fetch_assoc()) {
            printf("%-5d | %-30s | %-15s | %-20s\n", 
                $item['id'], 
                substr($item['title'], 0, 30),
                $item['category'],
                $item['username']
            );
        }
        echo str_repeat("-", 80) . "\n";
    } else {
        echo "⚠️  No available items found!\n\n";
        echo "Creating sample items...\n\n";
        
        // Get first user
        $result = $conn->query("SELECT id FROM users LIMIT 1");
        if ($result->num_rows > 0) {
            $user_id = $result->fetch_assoc()['id'];
            
            // Sample items
            $sample_items = [
                [
                    'title' => 'Buku Pemrograman PHP',
                    'description' => 'Buku pemrograman PHP untuk pemula. Kondisi masih bagus, jarang dipakai.',
                    'category' => 'Buku',
                    'condition_item' => 'Baik',
                    'photo' => 'default.jpg'
                ],
                [
                    'title' => 'Laptop Asus X441U',
                    'description' => 'Laptop bekas pakai kuliah. RAM 4GB, HDD 500GB. Normal semua.',
                    'category' => 'Elektronik',
                    'condition_item' => 'Baik',
                    'photo' => 'default.jpg'
                ],
                [
                    'title' => 'Jaket Denim',
                    'description' => 'Jaket denim biru ukuran L. Masih layak pakai, bersih.',
                    'category' => 'Pakaian',
                    'condition_item' => 'Baik',
                    'photo' => 'default.jpg'
                ]
            ];
            
            foreach ($sample_items as $item) {
                $stmt = $conn->prepare("INSERT INTO items (user_id, title, description, category, condition_item, photo, status) VALUES (?, ?, ?, ?, ?, ?, 'available')");
                $stmt->bind_param("isssss", 
                    $user_id, 
                    $item['title'], 
                    $item['description'], 
                    $item['category'], 
                    $item['condition_item'], 
                    $item['photo']
                );
                
                if ($stmt->execute()) {
                    echo "✓ Created: {$item['title']}\n";
                }
            }
            
            echo "\n✓ Sample items created successfully!\n";
        } else {
            echo "✗ No users found. Please register a user first.\n";
        }
    }
    
    $conn->close();
    
    echo "\n========================================\n";
    echo "Check Complete\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

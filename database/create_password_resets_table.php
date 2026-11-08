<?php
// Change to SWAP-IT directory first
chdir(dirname(__DIR__));
require_once 'config/database.php';

echo "Creating password_resets table...\n\n";

$conn = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'password_resets' created successfully!\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

$conn->close();

echo "\nDone!\n";
?>

<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'messages_count' => 0, 'offers_count' => 0, 'notifications_count' => 0]);
    exit();
}

$conn = getDBConnection();
$user_id = (int)$_SESSION['user_id'];

echo json_encode([
    'success' => true,
    'messages_count' => getUnreadMessageCount($conn, $user_id),
    'offers_count' => getPendingOfferCount($conn, $user_id),
    'notifications_count' => getUnreadNotificationCount($conn, $user_id),
]);

$conn->close();

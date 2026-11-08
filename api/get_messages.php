<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();
$offer_id = isset($_GET['offer_id']) ? (int)$_GET['offer_id'] : 0;
$last_message_id = isset($_GET['last_id']) ? max(0, (int)$_GET['last_id']) : 0;

// Verify user has access to this offer
$stmt = $conn->prepare("SELECT status FROM offers WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
$stmt->bind_param("iii", $offer_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$offer = $stmt->get_result()->fetch_assoc();

if (!$offer) {
    echo json_encode(['success' => false, 'message' => 'Invalid offer']);
    exit();
}

// Mark messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE offer_id = ? AND receiver_id = ? AND is_read = 0");
$stmt->bind_param("ii", $offer_id, $_SESSION['user_id']);
$stmt->execute();

// Ambil pesan baru saja (berdasarkan id, bukan waktu, agar urutan pasti benar)
$stmt = $conn->prepare("SELECT m.*, u.full_name
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.offer_id = ? AND m.id > ?
                        ORDER BY m.id ASC");
$stmt->bind_param("ii", $offer_id, $last_message_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'message' => $row['message'],
        'full_name' => $row['full_name'],
        'created_at' => $row['created_at'],
        'time_ago' => timeAgo($row['created_at']),
        'is_mine' => $row['sender_id'] == $_SESSION['user_id']
    ];
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'offer_status' => $offer['status'],
]);

$conn->close();

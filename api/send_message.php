<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login kembali']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

if (!verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi kedaluwarsa, muat ulang halaman']);
    exit();
}

$offer_id = isset($_POST['offer_id']) ? (int)$_POST['offer_id'] : 0;
$message = cleanInput($_POST['message'] ?? '');

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong']);
    exit();
}
if (textLength($message) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Pesan maksimal 2000 karakter']);
    exit();
}

$conn = getDBConnection();

// Hanya peserta penawaran yang sudah diterima yang boleh mengirim pesan
$stmt = $conn->prepare("SELECT sender_id, receiver_id, status FROM offers WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
$stmt->bind_param("iii", $offer_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$offer = $stmt->get_result()->fetch_assoc();

if (!$offer) {
    echo json_encode(['success' => false, 'message' => 'Percakapan tidak ditemukan']);
    exit();
}
if ($offer['status'] !== 'accepted') {
    echo json_encode(['success' => false, 'message' => 'Barter ini sudah tidak aktif, pesan tidak bisa dikirim']);
    exit();
}

$other_user_id = ($offer['sender_id'] == $_SESSION['user_id']) ? $offer['receiver_id'] : $offer['sender_id'];

$stmt = $conn->prepare("INSERT INTO messages (offer_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $offer_id, $_SESSION['user_id'], $other_user_id, $message);

if ($stmt->execute()) {
    $message_id = $stmt->insert_id;

    $stmt = $conn->prepare("SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $msg = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => [
            'id' => (int)$msg['id'],
            'sender_id' => (int)$msg['sender_id'],
            'message' => $msg['message'],
            'full_name' => $msg['full_name'],
            'created_at' => $msg['created_at'],
            'time_ago' => timeAgo($msg['created_at']),
            'is_mine' => true
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan']);
}

$conn->close();

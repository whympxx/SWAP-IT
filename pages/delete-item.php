<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

// Hapus hanya lewat POST + token CSRF (sebelumnya cukup membuka link GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    setFlash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    header('Location: my-items.php');
    exit();
}

$conn = getDBConnection();
$item_id = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT photo, status, title FROM items WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    setFlash('error', 'Barang tidak ditemukan.');
} elseif ($item['status'] !== 'available') {
    // Barang dalam proses barter / sudah ditukar menyimpan riwayat chat & transaksi
    // pihak lain, jadi tidak boleh dihapus.
    setFlash('error', $item['status'] === 'pending'
        ? 'Barang sedang dalam proses barter. Selesaikan atau batalkan barter terlebih dahulu.'
        : 'Barang yang sudah ditukar disimpan sebagai riwayat dan tidak bisa dihapus.');
} else {
    // Kabari pengirim penawaran yang masih menunggu untuk barang ini
    $stmt = $conn->prepare("SELECT id, sender_id, receiver_id FROM offers
                            WHERE status = 'pending' AND (item_wanted_id = ? OR item_offered_id = ?)");
    $stmt->bind_param("ii", $item_id, $item_id);
    $stmt->execute();
    $affected = $stmt->get_result();
    while ($o = $affected->fetch_assoc()) {
        $other = ($o['sender_id'] == $_SESSION['user_id']) ? $o['receiver_id'] : $o['sender_id'];
        notify($conn, $other, 'Penawaran Dibatalkan',
            'Barang "' . $item['title'] . '" telah dihapus oleh pemiliknya, sehingga penawaran terkait dibatalkan.',
            'pages/my-offers.php');
    }

    $stmt = $conn->prepare("DELETE FROM items WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $file_path = UPLOAD_PATH . 'items/' . basename($item['photo']);
        if (is_file($file_path)) {
            @unlink($file_path);
        }
        $conn->close();
        header('Location: my-items.php?deleted=1');
        exit();
    }
    setFlash('error', 'Gagal menghapus barang.');
}

$conn->close();
header('Location: my-items.php');
exit();
?>

<?php
/**
 * Semua aksi penawaran barter dalam satu tempat (hanya POST + CSRF):
 *  - accept      : penerima menerima penawaran (barang jadi "Dalam Proses")
 *  - reject      : penerima menolak penawaran
 *  - cancel      : pengirim menarik penawaran yang masih menunggu
 *  - complete    : salah satu pihak menandai barter selesai (barang jadi "Sudah Ditukar")
 *  - cancel_deal : salah satu pihak membatalkan barter yang sudah diterima (barang kembali "Tersedia")
 */
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$action = $action ?? ($_POST['action'] ?? '');
$offer_id = (int)($_POST['id'] ?? 0);
$back = ($_POST['redirect'] ?? '') === 'chat' ? 'chat.php?offer_id=' . $offer_id : 'my-offers.php';

function finish($type, $message, $back) {
    setFlash($type, $message);
    header('Location: ' . $back);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    finish('error', 'Permintaan tidak valid. Silakan coba lagi.', 'my-offers.php');
}

$conn = getDBConnection();
$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT o.*, iw.title AS wanted_title, io.title AS offered_title,
                               iw.status AS wanted_status, io.status AS offered_status
                        FROM offers o
                        JOIN items iw ON o.item_wanted_id = iw.id
                        JOIN items io ON o.item_offered_id = io.id
                        WHERE o.id = ? AND (o.sender_id = ? OR o.receiver_id = ?)");
$stmt->bind_param("iii", $offer_id, $uid, $uid);
$stmt->execute();
$offer = $stmt->get_result()->fetch_assoc();

if (!$offer) {
    finish('error', 'Penawaran tidak ditemukan.', 'my-offers.php');
}

$is_sender = (int)$offer['sender_id'] === $uid;
$is_receiver = (int)$offer['receiver_id'] === $uid;
$other_id = $is_sender ? (int)$offer['receiver_id'] : (int)$offer['sender_id'];
$deal = '"' . $offer['offered_title'] . '" ⇄ "' . $offer['wanted_title'] . '"';
$username = $_SESSION['username'];

try {
    switch ($action) {
        case 'accept':
            if (!$is_receiver || $offer['status'] !== 'pending') {
                finish('error', 'Penawaran ini tidak bisa diterima.', $back);
            }

            $conn->begin_transaction();

            // Kunci kedua barang & pastikan masih tersedia
            $stmt = $conn->prepare("SELECT id, status FROM items WHERE id IN (?, ?) FOR UPDATE");
            $stmt->bind_param("ii", $offer['item_wanted_id'], $offer['item_offered_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $all_available = $res->num_rows === 2;
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] !== 'available') {
                    $all_available = false;
                }
            }
            if (!$all_available) {
                $conn->rollback();
                $stmt = $conn->prepare("UPDATE offers SET status = 'cancelled' WHERE id = ?");
                $stmt->bind_param("i", $offer_id);
                $stmt->execute();
                finish('error', 'Salah satu barang sudah tidak tersedia, penawaran otomatis dibatalkan.', $back);
            }

            $stmt = $conn->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE items SET status = 'pending' WHERE id IN (?, ?)");
            $stmt->bind_param("ii", $offer['item_wanted_id'], $offer['item_offered_id']);
            $stmt->execute();

            // Penawaran lain yang melibatkan salah satu barang ini otomatis dibatalkan,
            // supaya satu barang tidak bisa "diterima" di dua barter sekaligus.
            $stmt = $conn->prepare("SELECT id, sender_id, receiver_id FROM offers
                                    WHERE status = 'pending' AND id != ?
                                    AND (item_wanted_id IN (?, ?) OR item_offered_id IN (?, ?))");
            $stmt->bind_param("iiiii", $offer_id,
                $offer['item_wanted_id'], $offer['item_offered_id'],
                $offer['item_wanted_id'], $offer['item_offered_id']);
            $stmt->execute();
            $others = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if ($others) {
                $ids = array_column($others, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("UPDATE offers SET status = 'cancelled' WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();

                foreach ($others as $o) {
                    foreach ([(int)$o['sender_id'], (int)$o['receiver_id']] as $party) {
                        if ($party !== $uid) {
                            notify($conn, $party, 'Penawaran Dibatalkan Otomatis',
                                'Salah satu barang dalam penawaran Anda sudah masuk proses barter lain.',
                                'pages/my-offers.php');
                        }
                    }
                }
            }

            notify($conn, $offer['sender_id'], 'Penawaran Diterima',
                $username . ' menerima penawaran ' . $deal . '. Silakan chat untuk mengatur pertemuan.',
                'pages/chat.php?offer_id=' . $offer_id);

            $conn->commit();
            finish('success', 'Penawaran diterima! Silakan chat untuk mengatur pertemuan.', 'chat.php?offer_id=' . $offer_id);

        case 'reject':
            if (!$is_receiver || $offer['status'] !== 'pending') {
                finish('error', 'Penawaran ini tidak bisa ditolak.', $back);
            }
            $stmt = $conn->prepare("UPDATE offers SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            notify($conn, $offer['sender_id'], 'Penawaran Ditolak',
                $username . ' menolak penawaran ' . $deal . '. Coba tawarkan barang lain!',
                'pages/my-offers.php?tab=sent');
            finish('success', 'Penawaran ditolak.', $back);

        case 'cancel':
            if (!$is_sender || $offer['status'] !== 'pending') {
                finish('error', 'Penawaran ini tidak bisa dibatalkan.', $back);
            }
            $stmt = $conn->prepare("UPDATE offers SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            notify($conn, $offer['receiver_id'], 'Penawaran Ditarik',
                $username . ' menarik penawaran ' . $deal . '.', 'pages/my-offers.php');
            finish('success', 'Penawaran berhasil dibatalkan.', 'my-offers.php?tab=sent');

        case 'complete':
            if ($offer['status'] !== 'accepted') {
                finish('error', 'Hanya barter yang sedang berjalan yang bisa diselesaikan.', $back);
            }
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE offers SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            $stmt = $conn->prepare("UPDATE items SET status = 'traded' WHERE id IN (?, ?)");
            $stmt->bind_param("ii", $offer['item_wanted_id'], $offer['item_offered_id']);
            $stmt->execute();
            notify($conn, $other_id, 'Barter Selesai 🎉',
                $username . ' menandai barter ' . $deal . ' sebagai selesai. Terima kasih sudah berbagi!',
                'pages/chat.php?offer_id=' . $offer_id);
            $conn->commit();
            finish('success', 'Barter ditandai selesai. Terima kasih sudah ikut mengurangi sampah! 🌱', $back);

        case 'cancel_deal':
            if ($offer['status'] !== 'accepted') {
                finish('error', 'Barter ini tidak bisa dibatalkan.', $back);
            }
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE offers SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            $stmt = $conn->prepare("UPDATE items SET status = 'available' WHERE id IN (?, ?) AND status = 'pending'");
            $stmt->bind_param("ii", $offer['item_wanted_id'], $offer['item_offered_id']);
            $stmt->execute();
            notify($conn, $other_id, 'Barter Dibatalkan',
                $username . ' membatalkan barter ' . $deal . '. Barang Anda kembali tersedia.',
                'pages/my-offers.php');
            $conn->commit();
            finish('success', 'Barter dibatalkan. Kedua barang kembali tersedia.', $back);

        default:
            finish('error', 'Aksi tidak dikenal.', $back);
    }
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Offer action error: ' . $e->getMessage());
    finish('error', 'Terjadi kesalahan sistem. Silakan coba lagi.', $back);
}

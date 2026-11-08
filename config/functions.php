<?php
// ===== Konstanta aplikasi =====
const ITEM_CATEGORIES = ['Buku', 'Elektronik', 'Pakaian', 'Alat Olahraga', 'Peralatan Kampus', 'Lainnya'];
const ITEM_CONDITIONS = ['Baru', 'Seperti Baru', 'Baik', 'Layak Pakai'];

const ITEM_STATUS_LABELS = [
    'available' => 'Tersedia',
    'pending'   => 'Dalam Proses',
    'traded'    => 'Sudah Ditukar',
];

const OFFER_STATUS_LABELS = [
    'pending'   => 'Menunggu',
    'accepted'  => 'Diterima',
    'rejected'  => 'Ditolak',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

const SCHEMA_VERSION = 2;

// ===== Migrasi skema otomatis (sekali jalan) =====
// Menambah status 'completed' & 'cancelled' pada offers dan memastikan
// tabel password_resets ada. Penanda versi disimpan di config/.schema_version.
function ensureSchema($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $flag = __DIR__ . '/.schema_version';
    if (is_file($flag) && (int)trim(@file_get_contents($flag)) >= SCHEMA_VERSION) {
        return;
    }

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("ALTER TABLE offers MODIFY status
            ENUM('pending','accepted','rejected','completed','cancelled') DEFAULT 'pending'");

        @file_put_contents($flag, (string)SCHEMA_VERSION);
    } catch (Throwable $e) {
        error_log('Schema migration failed: ' . $e->getMessage());
    }
}

// ===== Auth =====
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . 'auth/login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit();
    }
}

function getCurrentUser($conn) {
    if (!isLoggedIn()) {
        return null;
    }

    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Akun sudah dihapus tapi session masih ada
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    return $user;
}

// Hanya izinkan redirect ke path lokal (cegah open redirect)
function safeRedirectPath($path) {
    if (!is_string($path) || $path === '' || $path[0] !== '/' || strpos($path, '//') === 0 || strpos($path, '\\') !== false) {
        return null;
    }
    return $path;
}

// ===== Input & output =====
// Data disimpan apa adanya (hanya di-trim); escaping dilakukan saat ditampilkan
// dengan e(). Dulu htmlspecialchars dipakai saat menyimpan sehingga teks seperti
// "Buku & Pena" tampil sebagai "Buku &amp; Pena" dan rusak lagi tiap kali diedit.
function cleanInput($data) {
    return trim((string)($data ?? ''));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function excerpt($text, $length = 100) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . '…';
}

function textLength($text) {
    return mb_strlen((string)$text);
}

// ===== CSRF =====
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== Flash message =====
function setFlash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ===== Notifikasi =====
function notify($conn, $user_id, $title, $message, $link = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $link);
    $stmt->execute();
}

// ===== Label status =====
function itemStatusLabel($status) {
    return ITEM_STATUS_LABELS[$status] ?? ucfirst((string)$status);
}

function offerStatusLabel($status) {
    return OFFER_STATUS_LABELS[$status] ?? ucfirst((string)$status);
}

// Ubah nomor HP Indonesia (08xx / +628xx) jadi format wa.me
function whatsappNumber($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return null;
    }
    if (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    }
    return strlen($digits) >= 9 ? $digits : null;
}

// ===== Upload =====
function uploadImage($file, $folder = 'items') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return ['success' => false, 'message' => 'Ukuran file terlalu besar. Max 5MB.'];
        }
        return ['success' => false, 'message' => 'Upload gagal. Silakan coba lagi.'];
    }

    // Check file size (max 5MB)
    if ($file["size"] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Max 5MB.'];
    }

    // Validasi berdasarkan isi file, bukan hanya ekstensi
    $check = @getimagesize($file["tmp_name"]);
    $allowed_types = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if ($check === false || !isset($allowed_types[$check[2]])) {
        return ['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG, GIF & WEBP yang diperbolehkan.'];
    }

    $target_dir = UPLOAD_PATH . $folder . '/';
    $new_filename = uniqid() . '_' . time() . '.' . $allowed_types[$check[2]];

    if (move_uploaded_file($file["tmp_name"], $target_dir . $new_filename)) {
        return ['success' => true, 'filename' => $new_filename];
    }
    return ['success' => false, 'message' => 'Terjadi kesalahan saat upload file.'];
}

// ===== Waktu =====
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = max(0, time() - $timestamp);

    if ($difference < 60) {
        return 'baru saja';
    } elseif ($difference < 3600) {
        return floor($difference / 60) . ' menit yang lalu';
    } elseif ($difference < 86400) {
        return floor($difference / 3600) . ' jam yang lalu';
    } elseif ($difference < 2592000) {
        return floor($difference / 86400) . ' hari yang lalu';
    }
    return date('d M Y', $timestamp);
}

// ===== Hitungan badge =====
function getUnreadMessageCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['count'];
}

function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['count'];
}

function getPendingOfferCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM offers WHERE receiver_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['count'];
}
?>

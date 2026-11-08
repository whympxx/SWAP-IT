<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'swapit_db');

// Zona waktu disamakan antara PHP dan MySQL (WIB).
// Sebelumnya PHP memakai Europe/Berlin sedangkan MySQL memakai waktu sistem,
// sehingga link reset password langsung kedaluwarsa dan "x menit yang lalu" salah.
define('APP_TIMEZONE', 'Asia/Jakarta');
define('DB_TIMEZONE_OFFSET', '+07:00');
date_default_timezone_set(APP_TIMEZONE);

// Create connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (mysqli_sql_exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Koneksi database gagal. Pastikan MySQL berjalan dan database "' . DB_NAME . '" sudah dibuat.');
    }

    if ($conn->connect_error) {
        http_response_code(500);
        die('Koneksi database gagal. Pastikan MySQL berjalan dan database "' . DB_NAME . '" sudah dibuat.');
    }

    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");

    if (function_exists('ensureSchema')) {
        ensureSchema($conn);
    }

    return $conn;
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Base URL: dideteksi otomatis supaya aplikasi tetap jalan walau nama folder
// di htdocs berbeda (misalnya mengandung spasi). Fallback ke default lama.
function detectBaseUrl() {
    $default = 'http://localhost/SWAP-IT/';
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST']) || empty($_SERVER['DOCUMENT_ROOT'])) {
        return $default;
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $appRoot = realpath(__DIR__ . '/..');
    if ($docRoot === false || $appRoot === false || stripos($appRoot, $docRoot) !== 0) {
        return $default;
    }

    $relative = trim(str_replace('\\', '/', substr($appRoot, strlen($docRoot))), '/');
    $path = $relative === '' ? '/' : '/' . implode('/', array_map('rawurlencode', explode('/', $relative))) . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $path;
}

define('BASE_URL', detectBaseUrl());
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Create uploads directories if not exists
foreach (['', 'items/', 'profiles/'] as $dir) {
    if (!file_exists(UPLOAD_PATH . $dir)) {
        mkdir(UPLOAD_PATH . $dir, 0777, true);
    }
}
?>

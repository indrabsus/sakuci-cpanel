<?php
// Kredensial dibaca dari config/env.php yang TIDAK masuk git, supaya file yang
// sama bisa dipakai di laptop maupun server tanpa mengedit kode.
// Salin env.example.php menjadi env.php lalu sesuaikan isinya.
$env = file_exists(__DIR__ . '/env.php') ? require __DIR__ . '/env.php' : [];

define('DB_HOST', $env['db_host'] ?? 'localhost');
define('DB_USER', $env['db_user'] ?? 'root');
define('DB_PASS', $env['db_pass'] ?? '');
define('DB_NAME', $env['db_name'] ?? 'sakuci_cpanel');

// Default relatif terhadap folder aplikasi, jadi tidak terikat Windows/Linux.
define('PROJECTS_PATH', $env['projects_path'] ?? dirname(__DIR__) . '/projects');

// Di server, galat tidak boleh tampil ke pengunjung: isinya memuat path,
// nama user database, dan stack trace. Aktifkan hanya lewat 'debug' di env.php.
// Catatan: php_flag di .htaccess tidak berlaku bila PHP dijalankan sebagai
// PHP-FPM (seperti di aaPanel), jadi pengaturannya dilakukan di sini.
ini_set('display_errors', !empty($env['debug']) ? '1' : '0');
ini_set('log_errors', '1');

// mysqli melempar exception sejak PHP 8.1, jadi cek $conn->connect_error
// tidak pernah tercapai -- koneksi gagal harus ditangkap dengan try/catch.
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database tidak dapat dihubungi. Periksa config/env.php.');
}

if (session_status() === PHP_SESSION_NONE) {
    // The legacy user/ app runs on this same origin against a different
    // database. Sharing the default PHPSESSID let its user_id leak into this
    // app, where the id belongs to a different (or no) user.
    session_name('SAKUCI_CPANEL');

    // secure=true hanya saat HTTPS, supaya login lokal via http tetap jalan.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

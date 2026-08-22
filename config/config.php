<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'admin123');
define('DB_NAME', 'sakuci_cpanel');
define('PROJECTS_PATH', 'C:/hosting/projects');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database error: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    // The legacy user/ app runs on this same origin against a different
    // database. Sharing the default PHPSESSID let its user_id leak into this
    // app, where the id belongs to a different (or no) user.
    session_name('SAKUCI_CPANEL');
    session_start();
}

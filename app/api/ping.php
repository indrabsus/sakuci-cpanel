<?php
// Endpoint heartbeat periodik dari browser cPanel.
// Menjaga status pengguna tetap "Online" selama tab browser masih aktif dibuka.

include '../../config/config.php';
include '../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = require_api_login($conn);

echo json_encode([
    'ok'       => true,
    'online'   => true,
    'user_id'  => (int) $user['id'],
    'username' => $user['username'],
]);

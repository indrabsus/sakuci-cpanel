<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';

header('Content-Type: application/json');

$user_id = require_api_login($conn)['id'];
$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'job_id required']);
    exit;
}

// user_id ikut disaring supaya pekerjaan milik orang lain tidak terbaca.
$stmt = $conn->prepare("SELECT * FROM job_queue WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}

echo json_encode(job_payload($job));

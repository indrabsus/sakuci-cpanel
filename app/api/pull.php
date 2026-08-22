<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';

header('Content-Type: application/json');

$user_id = require_api_login($conn)['id'];
$project_id = intval($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id);
if (!$project) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

if (!is_dir($project['local_path'])) {
    http_response_code(404);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Folder project belum ada. Jalankan Clone lebih dulu.',
    ]);
    exit;
}

// Sama seperti clone: hanya dititipkan, worker cron yang menjalankan git.
$result = queue_job($conn, $project_id, $user_id, 'pull');

echo json_encode(job_payload($result['job']));

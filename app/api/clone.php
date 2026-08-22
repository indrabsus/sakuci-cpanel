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

if (is_dir($project['local_path'])) {
    echo json_encode([
        'status'  => 'already_exists',
        'message' => 'Project sudah ter-clone.',
        'path'    => $project['local_path'],
    ]);
    exit;
}

// Tidak menjalankan git di sini: exec() dimatikan pada PHP web. Pekerjaan
// dititipkan ke antrean, worker cron yang mengerjakannya.
$result = queue_job($conn, $project_id, $user_id, 'clone');

echo json_encode(job_payload($result['job']));

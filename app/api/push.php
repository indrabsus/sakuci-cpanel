<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';

header('Content-Type: application/json');

$me = require_api_login($conn);
$user_id = $me['id'];
$project_id = intval($_REQUEST['project_id'] ?? 0);
$commit_msg = trim($_REQUEST['commit_message'] ?? '');

if (empty($commit_msg)) {
    $commit_msg = 'Update berkas via Sakuci cPanel';
}

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id, is_admin($me));
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

if (empty($project['github_token'])) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'GitHub Personal Access Token (PAT) belum diatur untuk project ini. Masukkan token di pengaturan project untuk melakukan Git Push.',
    ]);
    exit;
}

$result = queue_job($conn, $project_id, $user_id, 'push', $commit_msg);

echo json_encode(job_payload($result['job']));

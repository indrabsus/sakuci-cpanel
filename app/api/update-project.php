<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';

header('Content-Type: application/json');

$me = require_api_login($conn);
$user_id = $me['id'];
$isAdmin = is_admin($me);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$project_id = intval($_POST['project_id'] ?? 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id, $isAdmin);
if (!$project) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$action = $_POST['action'] ?? 'update_settings';

if ($action === 'regenerate_secret') {
    $newSecret = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("UPDATE projects SET webhook_secret = ? WHERE id = ?");
    $stmt->bind_param("si", $newSecret, $project_id);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook secret berhasil diperbarui.',
        'webhook_secret' => $newSecret,
    ]);
    exit;
}

$github_token = isset($_POST['github_token']) ? trim($_POST['github_token']) : null;
$git_branch = isset($_POST['git_branch']) ? trim($_POST['git_branch']) : null;

$updates = [];
$params = [];
$types = '';

if ($github_token !== null) {
    $updates[] = "github_token = ?";
    $val = $github_token !== '' ? $github_token : null;
    $params[] = $val;
    $types .= 's';
}

if (!empty($git_branch)) {
    $updates[] = "git_branch = ?";
    $params[] = $git_branch;
    $types .= 's';
}

if (empty($updates)) {
    echo json_encode(['status' => 'success', 'message' => 'Tidak ada perubahan.']);
    exit;
}

$params[] = $project_id;
$types .= 'i';

$sql = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

echo json_encode([
    'status' => 'success',
    'message' => 'Pengaturan project berhasil disimpan.',
    'has_token' => !empty($github_token),
]);

<?php
include '../../config/config.php';
include '../../config/auth.php';

header('Content-Type: application/json');

$user_id = require_api_login($conn)['id'];
$project_id = intval($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

// Verify project belongs to user
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$project = $result->fetch_assoc();
$local_path = $project['local_path'];
$git_url = $project['git_url'];
$git_branch = $project['git_branch'];

// Create projects directory if not exists
$projects_base = dirname($local_path);
if (!is_dir($projects_base)) {
    mkdir($projects_base, 0755, true);
}

// Check if already cloned
if (is_dir($local_path)) {
    echo json_encode([
        'status' => 'already_exists',
        'message' => 'Project already cloned',
        'path' => $local_path
    ]);
    exit;
}

// Clone repository
// Each command redirects its own stderr; a trailing 2>&1 would only cover the last one.
$cmd = "cd " . escapeshellarg($projects_base) . " 2>&1"
    . " && git clone --branch " . escapeshellarg($git_branch)
    . " " . escapeshellarg($git_url)
    . " " . escapeshellarg(basename($local_path)) . " 2>&1";
$output = [];
$return_var = 0;
exec($cmd, $output, $return_var);

if ($return_var === 0) {
    // Update project status
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'active', last_pull = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $project_id);
    $update_stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Repository cloned successfully',
        'path' => $local_path,
        'output' => implode("\n", $output)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to clone repository',
        'error' => implode("\n", $output)
    ]);
}
?>

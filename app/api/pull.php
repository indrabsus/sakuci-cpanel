<?php
include '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
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
$git_branch = $project['git_branch'];

// Check if directory exists
if (!is_dir($local_path)) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Project directory not found. Clone first.'
    ]);
    exit;
}

// Pull from git
// Each command redirects its own stderr; a trailing 2>&1 would only cover the last one.
$cmd = "cd " . escapeshellarg($local_path)
    . " && git fetch origin 2>&1"
    . " && git reset --hard origin/" . escapeshellarg($git_branch) . " 2>&1";
$output = [];
$return_var = 0;
exec($cmd, $output, $return_var);

if ($return_var === 0) {
    // Update project last_pull timestamp
    $update_stmt = $conn->prepare("UPDATE projects SET last_pull = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $project_id);
    $update_stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Repository updated successfully',
        'output' => implode("\n", $output)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to pull repository',
        'error' => implode("\n", $output)
    ]);
}
?>

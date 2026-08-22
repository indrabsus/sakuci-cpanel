<?php
include '../../config/config.php';
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$project_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$result = $conn->query("SELECT * FROM projects WHERE id = $project_id AND user_id = $user_id");
if ($result->num_rows === 0) {
    die('Project not found');
}

$project = $result->fetch_assoc();
$path = $project['local_path'];
$branch = $project['git_branch'];

// Git pull
$output = [];
exec("cd " . escapeshellarg($path) . " && git pull origin " . escapeshellarg($branch) . " 2>&1", $output, $return);

if ($return === 0) {
    // Update last_pull timestamp
    $conn->query("UPDATE projects SET last_pull = NOW() WHERE id = $project_id");
    echo "✅ Git pull successful!";
} else {
    echo "❌ Git pull failed: " . implode("\n", $output);
}

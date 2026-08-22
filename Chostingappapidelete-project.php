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

// Delete from database
$conn->query("DELETE FROM projects WHERE id = $project_id");

// Delete local folder (if exists)
shell_exec("rmdir /s /q " . escapeshellarg($path) . " 2>nul");

header("Location: ../dashboard.php");

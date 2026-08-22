<?php
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $git_url = $_POST['git_url'] ?? '';
    $git_branch = $_POST['git_branch'] ?? 'main';
    $domain = $_POST['domain'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($name) || empty($git_url)) {
        $message = '❌ Name dan Git URL required';
    } else {
        $project_path = PROJECTS_PATH . '/' . preg_replace("/[^a-zA-Z0-9_-]/", "", $name);
        
        // Git clone
        $output = [];
        $return = 0;
        exec("cd " . escapeshellarg(PROJECTS_PATH) . " && git clone " . escapeshellarg($git_url) . " " . escapeshellarg($name) . " 2>&1", $output, $return);
        
        if ($return === 0) {
            // Save to database
            $stmt = $conn->prepare("INSERT INTO projects (user_id, name, domain, git_url, git_branch, local_path, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("issssss", $user_id, $name, $domain, $git_url, $git_branch, $project_path, $description);
            
            if ($stmt->execute()) {
                $message = '✅ Project added! Cloning completed.';
                sleep(2);
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $message = '❌ Git clone failed. Check URL?';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add Project - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        .container { max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 8px; }
        h1 { margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input, textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; }
        button { background: #667eea; color: white; padding: 0.75rem 2rem; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #5568d3; }
        .back { display: inline-block; margin-bottom: 1rem; color: #667eea; text-decoration: none; }
        .message { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .success { background: #c6f6d5; color: #22543d; }
        .error { background: #fed7d7; color: #742a2a; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Add Project</h1>
    </header>

    <div class="container">
        <a href="dashboard.php" class="back">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') === 0 ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Project Name</label>
                <input type="text" name="name" placeholder="my-sakuci-app" required>
            </div>

            <div class="form-group">
                <label>Git Repository URL</label>
                <input type="text" name="git_url" placeholder="https://github.com/user/repo.git" required>
            </div>

            <div class="form-group">
                <label>Branch (default: main)</label>
                <input type="text" name="git_branch" placeholder="main" value="main">
            </div>

            <div class="form-group">
                <label>Domain (optional)</label>
                <input type="text" name="domain" placeholder="myapp.local">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Project description..." rows="3"></textarea>
            </div>

            <button type="submit">Add Project</button>
        </form>
    </div>
</body>
</html>

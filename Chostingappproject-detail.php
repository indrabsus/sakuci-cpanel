<?php
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$result = $conn->query("SELECT * FROM projects WHERE id = $project_id AND user_id = $user_id");
if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit;
}

$project = $result->fetch_assoc();
$databases = $conn->query("SELECT * FROM db_list WHERE project_id = $project_id")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($project['name']); ?> - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; }
        .nav a { color: #667eea; text-decoration: none; margin-right: 1rem; }
        .section { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        h2 { margin-bottom: 1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .info-item { background: #f7fafc; padding: 1rem; border-radius: 5px; }
        .info-label { color: #666; font-size: 0.9rem; }
        .info-value { font-weight: 600; color: #333; margin-top: 0.5rem; word-break: break-all; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-right: 0.5rem; }
        .btn-danger { background: #e53e3e; }
        .btn:hover { opacity: 0.9; }
        code { background: #e2e8f0; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($project['name']); ?></h1>
    </header>

    <div class="container">
        <div class="nav">
            <a href="dashboard.php">← Dashboard</a>
        </div>

        <div class="section">
            <h2>Project Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Domain</div>
                    <div class="info-value"><?php echo htmlspecialchars($project['domain'] ?? 'No domain'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Git Repository</div>
                    <div class="info-value"><?php echo htmlspecialchars($project['git_url']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value"><?php echo htmlspecialchars($project['git_branch']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Local Path</div>
                    <div class="info-value"><code><?php echo htmlspecialchars($project['local_path']); ?></code></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Pull</div>
                    <div class="info-value"><?php echo $project['last_pull'] ? date('d M Y H:i', strtotime($project['last_pull'])) : 'Never'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><?php echo ucfirst($project['status']); ?></div>
                </div>
            </div>

            <?php if (!empty($project['description'])): ?>
            <div style="background: #f7fafc; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                <strong>Description:</strong><br>
                <?php echo htmlspecialchars($project['description']); ?>
            </div>
            <?php endif; ?>

            <div>
                <a href="api/pull.php?id=<?php echo $project['id']; ?>" class="btn">🔄 Git Pull</a>
                <a href="api/delete-project.php?id=<?php echo $project['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete project?')">🗑️ Delete</a>
            </div>
        </div>

        <?php if (count($databases) > 0): ?>
        <div class="section">
            <h2>Linked Databases (<?php echo count($databases); ?>)</h2>
            <?php foreach ($databases as $db): ?>
            <div style="background: #f7fafc; padding: 1rem; border-radius: 5px; margin-bottom: 0.5rem;">
                <code><?php echo htmlspecialchars($db['db_name']); ?></code>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

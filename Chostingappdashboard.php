<?php
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get projects
$projects = [];
$result = $conn->query("SELECT * FROM projects WHERE user_id = $user_id ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

// Get databases count
$db_count = $conn->query("SELECT COUNT(*) as total FROM db_list WHERE user_id = $user_id")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        header h1 { font-size: 1.5rem; }
        header p { font-size: 0.9rem; opacity: 0.9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav a { margin-right: 1.5rem; text-decoration: none; color: #667eea; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #667eea; }
        .stat-label { color: #666; margin-top: 0.5rem; }
        .section { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .section h2 { margin-bottom: 1rem; color: #333; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #e2e8f0; color: #333; }
        .project-card { background: #f7fafc; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
        .project-name { font-weight: 600; color: #333; font-size: 1.1rem; }
        .project-info { color: #666; font-size: 0.9rem; margin-top: 0.5rem; }
        .project-actions { margin-top: 1rem; }
        .project-actions a { display: inline-block; margin-right: 1rem; padding: 0.5rem 1rem; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
        .project-actions a:hover { background: #5568d3; }
        .logout { color: #e53e3e; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Sakuci cPanel</h1>
        <p>Welcome, <?php echo htmlspecialchars($username); ?></p>
    </header>

    <div class="container">
        <div class="nav">
            <div>
                <a href="#projects">Projects</a>
                <a href="databases.php">Databases</a>
                <a href="phpmyadmin.php">PhpMyAdmin</a>
            </div>
            <a href="../index.php?logout=1" class="logout">Logout</a>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-number"><?php echo count($projects); ?></div>
                <div class="stat-label">Projects</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $db_count; ?></div>
                <div class="stat-label">Databases</div>
            </div>
        </div>

        <div class="section">
            <h2 id="projects">📁 Projects</h2>
            <a href="add-project.php" class="btn">+ Add Project</a>

            <?php if (count($projects) > 0): ?>
                <div style="margin-top: 1.5rem;">
                    <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-name"><?php echo htmlspecialchars($project['name']); ?></div>
                        <div class="project-info">
                            🌐 <?php echo htmlspecialchars($project['domain'] ?? 'No domain'); ?><br>
                            📦 <?php echo htmlspecialchars($project['git_url']); ?><br>
                            📅 <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                        </div>
                        <div class="project-actions">
                            <a href="project-detail.php?id=<?php echo $project['id']; ?>">View</a>
                            <a href="api/pull.php?id=<?php echo $project['id']; ?>">Pull</a>
                            <a href="api/delete-project.php?id=<?php echo $project['id']; ?>" onclick="return confirm('Delete?')">Delete</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; margin-top: 1rem;">No projects yet. <a href="add-project.php">Add one</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

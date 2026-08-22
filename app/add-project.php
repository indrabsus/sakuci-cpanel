<?php
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $git_url = trim($_POST['git_url'] ?? '');
    $git_branch = trim($_POST['git_branch'] ?? 'main');

    if (empty($name) || empty($domain) || empty($git_url)) {
        $error = '❌ Semua field harus diisi';
    } else {
        $local_path = '../projects/' . preg_replace('/[^a-zA-Z0-9-_]/', '', $domain);

        $stmt = $conn->prepare("INSERT INTO projects (user_id, name, domain, git_url, git_branch, local_path, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("isssss", $user_id, $name, $domain, $git_url, $git_branch, $local_path);

        if ($stmt->execute()) {
            $project_id = $conn->insert_id;
            $success = "✅ Project berhasil ditambahkan! ID: $project_id";
        } else {
            $error = "❌ Error: " . $conn->error;
        }
    }
}

// Get projects
$projects = [];
$result = $conn->query("SELECT * FROM projects WHERE user_id = $user_id ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        header h1 { font-size: 1.5rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav a { margin-right: 1.5rem; text-decoration: none; color: #667eea; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .section { background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; }
        .section h2 { margin-bottom: 1.5rem; color: #333; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input, textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #718096; }
        .btn-secondary:hover { background: #4a5568; }
        .error { color: #e53e3e; background: #fed7d7; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .success { color: #22863a; background: #f6f8fa; border: 1px solid #28a745; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .project-item { background: #f7fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .project-item h3 { color: #333; margin-bottom: 0.5rem; }
        .project-meta { color: #666; font-size: 0.9rem; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 3px; font-size: 0.85rem; font-weight: 500; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Sakuci cPanel</h1>
        <p>Add Project from GitHub</p>
    </header>

    <div class="container">
        <div class="nav">
            <div>
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="add-project.php">➕ Add Project</a>
                <a href="databases.php">🗄️ Databases</a>
                <a href="phpmyadmin.php">📊 PhpMyAdmin</a>
            </div>
            <div>
                <span>👤 <?php echo htmlspecialchars($username); ?></span>
                <a href="../index.php?logout=1" class="btn btn-secondary" style="margin-left: 1rem; display: inline-block;">Logout</a>
            </div>
        </div>

        <div class="section">
            <h2>➕ Tambah Project Baru</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Nama Project</label>
                    <input type="text" name="name" placeholder="Contoh: My Awesome App" required>
                </div>

                <div class="form-group">
                    <label>Domain/Subdomain</label>
                    <input type="text" name="domain" placeholder="Contoh: myapp" required>
                    <small style="color: #666; display: block; margin-top: 0.5rem;">Digunakan untuk folder lokal dan URL</small>
                </div>

                <div class="form-group">
                    <label>GitHub Repository URL</label>
                    <input type="url" name="git_url" placeholder="Contoh: https://github.com/username/repo.git" required>
                </div>

                <div class="form-group">
                    <label>Branch (Default: main)</label>
                    <input type="text" name="git_branch" placeholder="main" value="main">
                </div>

                <button type="submit" class="btn">➕ Tambah Project</button>
            </form>
        </div>

        <div class="section">
            <h2>📋 Project Anda</h2>

            <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $proj): ?>
                    <div class="project-item">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3><?php echo htmlspecialchars($proj['name']); ?></h3>
                                <div class="project-meta">
                                    Domain: <strong><?php echo htmlspecialchars($proj['domain']); ?></strong><br>
                                    URL: <code><?php echo htmlspecialchars($proj['git_url']); ?></code><br>
                                    Branch: <strong><?php echo htmlspecialchars($proj['git_branch']); ?></strong><br>
                                    Created: <?php echo date('d M Y H:i', strtotime($proj['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $proj['status']; ?>">
                                <?php echo ucfirst($proj['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Anda belum menambahkan project apapun.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

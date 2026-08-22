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
    $db_name = 'db_' . preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'] ?? '');
    $project_id = intval($_POST['project_id'] ?? 0);

    if (empty($db_name) || $project_id <= 0) {
        $error = '❌ Database name dan project harus diisi';
    } else {
        // Generate random username dan password
        $db_user = substr($db_name, 0, 10) . '_' . substr(md5(uniqid()), 0, 5);
        $db_pass = bin2hex(random_bytes(8));

        $stmt = $conn->prepare("INSERT INTO db_list (project_id, user_id, db_name, db_host, db_port) VALUES (?, ?, ?, 'localhost', 3306)");
        $stmt->bind_param("iis", $project_id, $user_id, $db_name);

        if ($stmt->execute()) {
            $db_id = $conn->insert_id;

            // Insert database user
            $stmt2 = $conn->prepare("INSERT INTO db_users (db_id, username, password, privileges) VALUES (?, ?, ?, 'ALL')");
            $stmt2->bind_param("iss", $db_id, $db_user, $db_pass);
            $stmt2->execute();

            $success = "✅ Database berhasil dibuat!<br>DB: <code>$db_name</code><br>User: <code>$db_user</code><br>Pass: <code>$db_pass</code>";
        } else {
            $error = "❌ Error: " . $conn->error;
        }
    }
}

// Get projects
$projects = [];
$result = $conn->query("SELECT * FROM projects WHERE user_id = $user_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Get databases
$databases = [];
$result = $conn->query("SELECT db.*, pr.name as project_name FROM db_list db LEFT JOIN projects pr ON db.project_id = pr.id WHERE db.user_id = $user_id ORDER BY db.created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Databases - Sakuci cPanel</title>
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
        input, select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #718096; }
        .error { color: #e53e3e; background: #fed7d7; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .success { color: #22863a; background: #f6f8fa; border: 1px solid #28a745; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .db-item { background: #f7fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .db-item h3 { color: #333; margin-bottom: 0.5rem; }
        .db-meta { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        code { background: #edf2f7; padding: 0.2rem 0.5rem; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Sakuci cPanel</h1>
        <p>Database Management</p>
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
            <h2>🗄️ Buat Database Baru</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Pilih Project</label>
                    <select name="project_id" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Database</label>
                    <input type="text" name="db_name" placeholder="Contoh: myapp_db" required>
                    <small style="color: #666; display: block; margin-top: 0.5rem;">Prefix 'db_' akan ditambahkan otomatis</small>
                </div>

                <button type="submit" class="btn">🗄️ Buat Database</button>
            </form>
        </div>

        <div class="section">
            <h2>📋 Database Anda</h2>

            <?php if (!empty($databases)): ?>
                <?php foreach ($databases as $db): ?>
                    <div class="db-item">
                        <h3><?php echo htmlspecialchars($db['db_name']); ?></h3>
                        <div class="db-meta">
                            Project: <strong><?php echo htmlspecialchars($db['project_name'] ?? '-'); ?></strong><br>
                            Host: <code><?php echo htmlspecialchars($db['db_host']); ?></code>:<code><?php echo $db['db_port']; ?></code><br>
                            Created: <?php echo date('d M Y H:i', strtotime($db['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Anda belum membuat database apapun.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

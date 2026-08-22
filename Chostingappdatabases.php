<?php
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Create database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $db_name = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST['db_name'] ?? '');
        $db_user = $_POST['db_user'] ?? substr($db_name, 0, 10) . '_usr';
        $db_pass = $_POST['db_pass'] ?? bin2hex(random_bytes(8));

        // Create database in MySQL
        if ($conn->query("CREATE DATABASE " . $conn->real_escape_string($db_name))) {
            // Create user
            $conn->query("CREATE USER '" . $conn->real_escape_string($db_user) . "'@'localhost' IDENTIFIED BY '" . $conn->real_escape_string($db_pass) . "'");
            $conn->query("GRANT ALL PRIVILEGES ON " . $conn->real_escape_string($db_name) . ".* TO '" . $conn->real_escape_string($db_user) . "'@'localhost'");
            $conn->query("FLUSH PRIVILEGES");

            // Save to our database
            $stmt = $conn->prepare("INSERT INTO db_list (user_id, db_name, db_host, db_port) VALUES (?, ?, 'localhost', 3306)");
            $stmt->bind_param("iss", $user_id, $db_name);
            if ($stmt->execute()) {
                $db_id = $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO db_users (db_id, username, password) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $db_id, $db_user, $db_pass);
                $stmt->execute();
                $message = '✅ Database created!';
            }
        } else {
            $message = '❌ Database creation failed';
        }
    }
}

// Get user databases
$databases = [];
$result = $conn->query("SELECT * FROM db_list WHERE user_id = $user_id ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $users = $conn->query("SELECT * FROM db_users WHERE db_id = " . $row['id']);
    $row['users'] = $users->fetch_all(MYSQLI_ASSOC);
    $databases[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Databases - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; gap: 1rem; }
        .nav a { text-decoration: none; color: #667eea; font-weight: 500; }
        .section { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #5568d3; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: 500; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .db-card { background: #f7fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .db-name { font-weight: 600; color: #333; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .db-info { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        code { background: #e2e8f0; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: monospace; }
        .message { padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; }
        .success { background: #c6f6d5; color: #22543d; }
    </style>
</head>
<body>
    <header>
        <h1>🗄️ Databases</h1>
    </header>

    <div class="container">
        <div class="nav">
            <a href="dashboard.php">← Dashboard</a>
            <a href="phpmyadmin.php">PhpMyAdmin</a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>Create New Database</h2>
            <form method="POST" style="display: grid; gap: 1rem; max-width: 500px; margin-top: 1rem;">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" placeholder="my_database" required>
                </div>

                <div class="form-group">
                    <label>Username (auto-generated if empty)</label>
                    <input type="text" name="db_user" placeholder="optional">
                </div>

                <div class="form-group">
                    <label>Password (auto-generated if empty)</label>
                    <input type="text" name="db_pass" placeholder="optional">
                </div>

                <button class="btn" type="submit">Create Database</button>
            </form>
        </div>

        <div class="section">
            <h2>Your Databases (<?php echo count($databases); ?>)</h2>
            
            <?php if (count($databases) > 0): ?>
                <?php foreach ($databases as $db): ?>
                <div class="db-card">
                    <div class="db-name">📦 <?php echo htmlspecialchars($db['db_name']); ?></div>
                    <div class="db-info">
                        Host: <code><?php echo $db['db_host']; ?>:<?php echo $db['db_port']; ?></code><br>
                        Created: <?php echo date('d M Y H:i', strtotime($db['created_at'])); ?>
                    </div>
                    
                    <?php if (!empty($db['users'])): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                        <strong>Database Users:</strong>
                        <?php foreach ($db['users'] as $user): ?>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                            User: <code><?php echo htmlspecialchars($user['username']); ?></code><br>
                            Pass: <code><?php echo htmlspecialchars($user['password']); ?></code>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666;">No databases yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

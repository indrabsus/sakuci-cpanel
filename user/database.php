<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle database creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_db') {
    $db_name = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST['db_name'] ?? '');

    if (empty($db_name)) {
        $error = "Nama database tidak valid";
    } else {
        // Generate credentials
        $db_user = substr($db_name, 0, 10) . '_user';
        $db_password = bin2hex(random_bytes(8));

        if ($DB_CONNECTED && $conn) {
            $stmt = $conn->prepare("INSERT INTO hosting_databases (hosting_account_id, db_name, db_user, db_password, db_type) VALUES (?, ?, ?, ?, 'mysql')");
            $account_id = 1; // Default account
            $stmt->bind_param("isss", $account_id, $db_name, $db_user, $db_password);

            if ($stmt->execute()) {
                $success = "✅ Database berhasil dibuat!<br><strong>Database:</strong> $db_name<br><strong>User:</strong> $db_user<br><strong>Password:</strong> $db_password";
            } else {
                $error = "Gagal membuat database: " . $stmt->error;
            }
        }
    }
}

// Get user databases
$databases = [];
if ($DB_CONNECTED && $conn) {
    $result = $conn->query("SELECT * FROM hosting_databases WHERE hosting_account_id = 1");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $databases[] = $row;
        }
    }
}

$user = $conn->prepare("SELECT username FROM users WHERE id = ?");
$user->bind_param("i", $user_id);
$user->execute();
$user_data = $user->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Manager - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .sidebar a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; color: #2d3748; text-decoration: none; border-radius: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: #edf2f7; color: #667eea; }
        .db-card { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .db-name { font-weight: 600; font-size: 1.1rem; color: #2d3748; margin-bottom: 0.5rem; }
        .db-info { color: #718096; font-size: 0.9rem; margin-bottom: 0.25rem; }
        code { background: #edf2f7; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="file-manager.php">File Manager</a></li>
                <li><a href="database.php">Database</a></li>
            </ul>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: #2d3748;">👤 <?php echo htmlspecialchars($user_data['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul style="list-style: none;">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="file-manager.php">📁 File Manager</a></li>
                <li><a href="database.php" class="active">🗄️ Database</a></li>
                <li><a href="backup.php">💾 Backup</a></li>
                <li><a href="email.php">📧 Email</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="support.php">🎫 Support</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>🗄️ Database Manager</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Kelola database MySQL untuk framework Anda</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Create Database Form -->
            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <h2 style="margin-top: 0;">Buat Database Baru</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_db">
                    <div style="display: grid; gap: 1rem;">
                        <div class="form-group">
                            <label>Nama Database</label>
                            <input type="text" name="db_name" placeholder="e.g., sakuci_app" required>
                            <small style="color: #718096;">Gunakan huruf, angka, dan underscore saja</small>
                        </div>
                        <button type="submit" class="btn btn-primary">✨ Buat Database</button>
                    </div>
                </form>
            </div>

            <!-- Database List -->
            <h2>📋 Database Anda</h2>
            <?php if (!empty($databases)): ?>
                <?php foreach ($databases as $db): ?>
                <div class="db-card">
                    <div class="db-name">📦 <?php echo htmlspecialchars($db['db_name']); ?></div>
                    <div class="db-info">Type: <code><?php echo strtoupper($db['db_type']); ?></code></div>
                    <div class="db-info">User: <code><?php echo htmlspecialchars($db['db_user']); ?></code></div>
                    <div class="db-info">Password: <code><?php echo htmlspecialchars($db['db_password']); ?></code></div>
                    <div class="db-info">Created: <?php echo date('d M Y H:i', strtotime($db['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #718096; text-align: center; padding: 2rem;">Belum ada database. Buat database baru untuk framework Anda!</p>
            <?php endif; ?>

            <!-- Info Box -->
            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea; margin-bottom: 1rem;">💡 Informasi Database</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ Buat database MySQL untuk setiap aplikasi</li>
                    <li>✅ User dan password otomatis di-generate</li>
                    <li>✅ Simpan credential dengan aman</li>
                    <li>✅ Host: localhost:3306</li>
                    <li>✅ Akses via PhpMyAdmin jika diperlukan</li>
                </ul>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>

    <script src="../public/js/script.js"></script>
</body>
</html>

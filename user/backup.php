<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';

// Simulate backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    $backup_date = date('Y-m-d H:i:s');
    $success = "✅ Backup dibuat: " . date('d M Y H:i', strtotime($backup_date));
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
    <title>Backup - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .sidebar a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; color: #2d3748; text-decoration: none; border-radius: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: #edf2f7; color: #667eea; }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: #2d3748;">👤 <?php echo htmlspecialchars($user_data['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul style="list-style: none;">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="file-manager.php">📁 File Manager</a></li>
                <li><a href="database.php">🗄️ Database</a></li>
                <li><a href="backup.php" class="active">💾 Backup</a></li>
                <li><a href="email.php">📧 Email</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="support.php">🎫 Support</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>💾 Backup Management</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Backup otomatis file dan database Anda</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <h2 style="color: white; margin-top: 0;">🔄 Backup Otomatis</h2>
                <p>Backup harian otomatis dijalankan setiap pukul 02:00 AM</p>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn" style="background: white; color: #667eea;">Buat Backup Sekarang</button>
                </form>
            </div>

            <h2>📋 Backup History</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Size</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>22 Agus 2024 14:30</td>
                        <td>245 MB</td>
                        <td>Full Backup</td>
                        <td><span class="status-badge status-active">Completed</span></td>
                        <td><a href="#" style="color: #667eea;">Download</a></td>
                    </tr>
                    <tr>
                        <td>21 Agus 2024 02:00</td>
                        <td>240 MB</td>
                        <td>Full Backup</td>
                        <td><span class="status-badge status-active">Completed</span></td>
                        <td><a href="#" style="color: #667eea;">Download</a></td>
                    </tr>
                </tbody>
            </table>

            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea;">💡 Backup Information</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ Backup otomatis setiap hari</li>
                    <li>✅ Backup mencakup semua file dan database</li>
                    <li>✅ Tersimpan di multiple location untuk keamanan</li>
                    <li>✅ Restore tersedia kapan saja</li>
                    <li>✅ Retensi: 30 hari</li>
                </ul>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

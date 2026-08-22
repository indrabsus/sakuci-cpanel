<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
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
    <title>Email - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; }
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
                <li><a href="backup.php">💾 Backup</a></li>
                <li><a href="email.php" class="active">📧 Email</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="support.php">🎫 Support</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>📧 Email Management</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Kelola akun email profesional</p>

            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <h2 style="margin-top: 0;">Buat Email Baru</h2>
                <div style="display: grid; gap: 1rem;">
                    <div class="form-group">
                        <label>Nama Email</label>
                        <input type="text" placeholder="e.g., info" required>
                    </div>
                    <div class="form-group">
                        <label>Domain</label>
                        <select required>
                            <option>example.com</option>
                            <option>mybusiness.id</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" required>
                    </div>
                    <button class="btn btn-primary">Buat Email</button>
                </div>
            </div>

            <h2>📋 Email Accounts</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Quota</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>info@example.com</strong></td>
                        <td>5 GB</td>
                        <td>15 Agus 2024</td>
                        <td><a href="#" style="color: #667eea;">Edit</a></td>
                    </tr>
                </tbody>
            </table>

            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea;">💡 Pengaturan Email</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ Create unlimited email accounts</li>
                    <li>✅ Forwarding dan autoresponder</li>
                    <li>✅ Spam filtering</li>
                    <li>✅ Webmail access</li>
                    <li>✅ IMAP/SMTP configuration</li>
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

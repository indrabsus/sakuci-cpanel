<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user->bind_param("i", $user_id);
$user->execute();
$user_data = $user->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Sakuci Hosting</title>
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
                <li><a href="email.php">📧 Email</a></li>
                <li><a href="settings.php" class="active">⚙️ Settings</a></li>
                <li><a href="support.php">🎫 Support</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>⚙️ Settings & Preferences</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Kelola pengaturan akun Anda</p>

            <h2>👤 Profile Information</h2>
            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" placeholder="Your phone number">
                </div>
            </div>

            <h2>🔐 Security</h2>
            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label>Change Password</label>
                    <input type="password" placeholder="Current password">
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" placeholder="New password">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" placeholder="Confirm new password">
                </div>
                <button class="btn btn-primary">Update Password</button>
            </div>

            <h2>🔔 Notifications</h2>
            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem;">
                <div style="display: grid; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked>
                        <span>Email notifications for billing</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked>
                        <span>Backup completed notifications</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked>
                        <span>Support ticket updates</span>
                    </label>
                </div>
                <button class="btn btn-primary" style="margin-top: 1rem;">Save Preferences</button>
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

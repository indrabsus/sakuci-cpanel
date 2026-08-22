<?php
include '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

// Get hosting accounts
if ($DB_CONNECTED && $conn) {
    $accounts_query = $conn->prepare("
        SELECT ha.*, p.name as package_name, p.disk_space, p.bandwidth
        FROM hosting_accounts ha
        LEFT JOIN packages p ON ha.package_id = p.id
        WHERE ha.user_id = ?
        ORDER BY ha.registration_date DESC
    ");
    $accounts_query->bind_param("i", $user_id);
    $accounts_query->execute();
    $accounts = $accounts_query->get_result();
} else {
    // Demo data if not connected
    $accounts = null;
}

// Get statistics
$stats_query = $conn->prepare("
    SELECT COUNT(*) as total_accounts FROM hosting_accounts WHERE user_id = ?
");
$stats_query->bind_param("i", $user_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#">Billing</a></li>
                <li><a href="#">Support</a></li>
            </ul>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: var(--dark);">Halo, <?php echo htmlspecialchars($user['username']); ?>!</span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul>
                <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="#">🌐 Manage Hosting</a></li>
                <li><a href="#">📁 File Manager</a></li>
                <li><a href="#">📊 Database</a></li>
                <li><a href="#">💾 Backup</a></li>
                <li><a href="#">⚙️ Settings</a></li>
                <li><a href="#">🎫 Support Ticket</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>Dashboard</h1>
            <p style="color: var(--gray); margin-bottom: 2rem;">Selamat datang kembali, <?php echo htmlspecialchars($user['full_name']); ?>!</p>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_accounts']; ?></div>
                    <div>Hosting Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">99.9%</div>
                    <div>Uptime</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">0</div>
                    <div>Invoices Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">1</div>
                    <div>Support Tickets</div>
                </div>
            </div>

            <!-- Hosting Accounts -->
            <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h2>Hosting Accounts Anda</h2>
                <a href="new-hosting.php" class="btn btn-primary" style="margin-bottom: 1rem;">+ Beli Hosting Baru</a>

                <?php if ($accounts->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Paket</th>
                                <th>Status</th>
                                <th>Tanggal Registrasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($account = $accounts->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($account['domain']); ?></strong></td>
                                <td><?php echo htmlspecialchars($account['package_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($account['status']); ?>">
                                        <?php echo ucfirst($account['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($account['registration_date'])); ?></td>
                                <td>
                                    <a href="manage-hosting.php?id=<?php echo $account['id']; ?>" style="color: var(--primary); text-decoration: none;">Kelola →</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        Anda belum memiliki hosting. <a href="new-hosting.php">Beli hosting sekarang</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div style="background: var(--light); padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3>Link Cepat & Menu</h3>
                <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <a href="file-manager.php" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        📁 File Manager<br><small>Upload Framework</small>
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        🗄️ Database<br><small>Kelola DB</small>
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        💾 Backup<br><small>Backup Data</small>
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        📧 Email<br><small>Kelola Email</small>
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        ⚙️ Settings<br><small>Pengaturan</small>
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border); transition: all 0.3s;">
                        🎫 Support<br><small>Hubungi Support</small>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Dukungan</h4>
                <ul>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Knowledge Base</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Kontak</h4>
                <p>Email: support@sakuci-hosting.com</p>
                <p>Phone: +62-XXX-XXX-XXXX</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>

    <script src="../public/js/script.js"></script>
</body>
</html>

<?php
include '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if ($user['role'] !== 'admin') {
    header("Location: ../user/dashboard.php");
    exit;
}

// Get statistics
$stats = [];

// Total Users
$users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $users_result->fetch_assoc()['count'];

// Total Hosting Accounts
$accounts_result = $conn->query("SELECT COUNT(*) as count FROM hosting_accounts");
$stats['total_accounts'] = $accounts_result->fetch_assoc()['count'];

// Total Revenue
$revenue_result = $conn->query("SELECT SUM(amount) as total FROM orders WHERE status = 'completed'");
$revenue_data = $revenue_result->fetch_assoc();
$stats['total_revenue'] = $revenue_data['total'] ?? 0;

// Active Hosting
$active_result = $conn->query("SELECT COUNT(*) as count FROM hosting_accounts WHERE status = 'active'");
$stats['active_hosting'] = $active_result->fetch_assoc()['count'];

// Recent Orders
$orders_query = $conn->query("
    SELECT o.*, u.username
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .admin-nav {
            background: var(--dark);
            color: white;
            padding: 1rem 2rem;
        }
        .admin-nav a {
            color: white;
            text-decoration: none;
            margin-right: 2rem;
            transition: color 0.3s;
        }
        .admin-nav a:hover {
            color: var(--primary);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .admin-dashboard {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting Admin</a>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: var(--dark);">Admin</span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="admin-nav">
        <a href="index.php">📊 Dashboard</a>
        <a href="customers.php">👥 Customers</a>
        <a href="packages.php">📦 Packages</a>
        <a href="servers.php">🖥️ Servers</a>
        <a href="phpmyadmin-setup.php">🔧 PhpMyAdmin</a>
    </div>

    <div class="admin-dashboard">
        <h1>Admin Dashboard</h1>
        <p style="color: var(--gray); margin-bottom: 2rem;">Selamat datang di panel administrasi Sakuci Hosting</p>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div style="color: var(--gray);">Total Users</div>
            </div>
            <div class="stat-card" style="border-left-color: #48bb78;">
                <div class="stat-number" style="color: #48bb78;"><?php echo $stats['total_accounts']; ?></div>
                <div style="color: var(--gray);">Hosting Accounts</div>
            </div>
            <div class="stat-card" style="border-left-color: #ed8936;">
                <div class="stat-number" style="color: #ed8936;"><?php echo $stats['active_hosting']; ?></div>
                <div style="color: var(--gray);">Active Hosting</div>
            </div>
            <div class="stat-card" style="border-left-color: #764ba2;">
                <div class="stat-number" style="color: #764ba2;">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                <div style="color: var(--gray);">Total Revenue</div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
            <h2>Recent Orders</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Username</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders_query->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['invoice_number'] ?? $order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td>Rp <?php echo number_format($order['amount'], 0, ',', '.'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Actions -->
        <div style="background: var(--light); padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
            <h3>Quick Actions</h3>
            <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <a href="customers.php" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                    👥 Manage Customers
                </a>
                <a href="packages.php" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                    📦 Manage Packages
                </a>
                <a href="servers.php" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                    🖥️ Manage Servers
                </a>
                <a href="phpmyadmin-setup.php" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                    🔧 PhpMyAdmin
                </a>
            </div>
        </div>
    </div>

    <script src="../public/js/script.js"></script>
</body>
</html>

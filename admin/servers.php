<?php
include '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

// Get all servers
$servers_query = $conn->query("
    SELECT
        s.*,
        COUNT(ha.id) as current_accounts
    FROM servers s
    LEFT JOIN hosting_accounts ha ON s.id = ha.server_id
    GROUP BY s.id
    ORDER BY s.id
");

$error = '';
$success = '';

// Handle server operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_server') {
            $name = sanitize($_POST['name']);
            $ip_address = sanitize($_POST['ip_address']);
            $hostname = sanitize($_POST['hostname']);
            $os = sanitize($_POST['os']);
            $disk_capacity = (int)$_POST['disk_capacity'];
            $bandwidth_capacity = (int)$_POST['bandwidth_capacity'];

            $insert = $conn->prepare("
                INSERT INTO servers (name, ip_address, hostname, os, disk_capacity, bandwidth_capacity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("sssiii", $name, $ip_address, $hostname, $os, $disk_capacity, $bandwidth_capacity);

            if ($insert->execute()) {
                $success = "Server berhasil ditambahkan";
                header("Refresh: 1");
            } else {
                $error = "Gagal menambah server: " . $conn->error;
            }
        }
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Servers - Sakuci Hosting Admin</title>
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
        }
        .admin-content {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting Admin</a>
            <div class="nav-buttons">
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

    <div class="admin-content">
        <h1>🖥️ Manage Servers</h1>
        <p style="color: var(--gray); margin-bottom: 2rem;">Kelola server hosting dan resource</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Server Form -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-bottom: 2rem;">
            <h2>Add New Server</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_server">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Server Name</label>
                        <input type="text" name="name" placeholder="e.g., Server 1 (SG)" required>
                    </div>
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" name="ip_address" placeholder="e.g., 185.199.100.1" required>
                    </div>
                    <div class="form-group">
                        <label>Hostname</label>
                        <input type="text" name="hostname" placeholder="e.g., server1.sakuci-hosting.com" required>
                    </div>
                    <div class="form-group">
                        <label>OS</label>
                        <input type="text" name="os" placeholder="e.g., Ubuntu 22.04 LTS" required>
                    </div>
                    <div class="form-group">
                        <label>Disk Capacity (GB)</label>
                        <input type="number" name="disk_capacity" required>
                    </div>
                    <div class="form-group">
                        <label>Bandwidth Capacity (Gbps)</label>
                        <input type="number" name="bandwidth_capacity" required>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Add Server</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Servers Table -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; overflow-x: auto;">
            <h2>Server List</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Server Name</th>
                        <th>IP Address</th>
                        <th>OS</th>
                        <th>Disk (GB)</th>
                        <th>Bandwidth (Gbps)</th>
                        <th>Accounts</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($server = $servers_query->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($server['name']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($server['ip_address']); ?></code></td>
                        <td><?php echo htmlspecialchars($server['os']); ?></td>
                        <td><?php echo $server['disk_capacity']; ?></td>
                        <td><?php echo $server['bandwidth_capacity']; ?></td>
                        <td><?php echo $server['current_accounts']; ?>/<?php echo $server['max_accounts'] ?? 'Unlimited'; ?></td>
                        <td>
                            <span class="status-badge" style="background: #c6f6d5; color: #22543d;">
                                <?php echo ucfirst($server['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="#" style="color: var(--primary); text-decoration: none;">Edit →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

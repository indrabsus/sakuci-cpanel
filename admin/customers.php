<?php
include '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

// Get all customers with their hosting accounts
$customers_query = $conn->query("
    SELECT
        u.*,
        COUNT(ha.id) as total_hosting,
        SUM(CASE WHEN ha.status = 'active' THEN 1 ELSE 0 END) as active_hosting
    FROM users u
    LEFT JOIN hosting_accounts ha ON u.id = ha.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

$error = '';
$success = '';

// Handle customer status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $status = sanitize($_POST['status']);

    if (in_array($status, ['active', 'suspended', 'inactive'])) {
        $update = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $update->bind_param("si", $status, $user_id);

        if ($update->execute()) {
            $success = "Status customer berhasil diubah";
            // Refresh page
            header("Refresh: 1");
        } else {
            $error = "Gagal mengubah status: " . $conn->error;
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
    <title>Manage Customers - Sakuci Hosting Admin</title>
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
        <h1>👥 Manage Customers</h1>
        <p style="color: var(--gray); margin-bottom: 2rem;">Kelola semua customer dan hosting account mereka</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Search -->
        <div style="margin-bottom: 2rem;">
            <input type="text" id="searchInput" placeholder="Search customers..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem;">
        </div>

        <!-- Customers Table -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Nama Lengkap</th>
                        <th>Total Hosting</th>
                        <th>Active</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="customersTable">
                    <?php while ($customer = $customers_query->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($customer['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['full_name'] ?? '-'); ?></td>
                        <td><?php echo $customer['total_hosting'] ?? 0; ?></td>
                        <td><?php echo $customer['active_hosting'] ?? 0; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="border: 1px solid var(--border); padding: 0.25rem; border-radius: 0.25rem;">
                                    <option value="active" <?php echo $customer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $customer['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="inactive" <?php echo $customer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <input type="hidden" name="action" value="update_status">
                            </form>
                        </td>
                        <td><?php echo date('d M Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <a href="#" style="color: var(--primary); text-decoration: none;">View Details →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.getElementById('customersTable').getElementsByTagName('tr');

            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>

<?php
include '../config/database.php';

// Check if user is admin
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

// Get all databases
$databases_query = $conn->query("
    SELECT d.*, ha.domain, u.username
    FROM databases d
    JOIN hosting_accounts ha ON d.hosting_account_id = ha.id
    JOIN users u ON ha.user_id = u.id
    ORDER BY d.created_at DESC
");

$error = '';
$success = '';

// Handle database creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_db') {
    $hosting_account_id = (int)$_POST['hosting_account_id'];
    $db_name = sanitize($_POST['db_name']);
    $db_type = sanitize($_POST['db_type']);

    if (empty($db_name) || empty($hosting_account_id)) {
        $error = 'Semua field harus diisi';
    } else {
        // Generate database user and password
        $db_user = substr($db_name, 0, 10) . '_user';
        $db_password = bin2hex(random_bytes(8));

        $insert = $conn->prepare("
            INSERT INTO databases (hosting_account_id, db_name, db_user, db_password, db_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->bind_param("issss", $hosting_account_id, $db_name, $db_user, $db_password, $db_type);

        if ($insert->execute()) {
            $success = "Database berhasil dibuat. Username: $db_user | Password: $db_password";
        } else {
            $error = 'Gagal membuat database: ' . $conn->error;
        }
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input));
}

// Get hosting accounts
$accounts_query = $conn->query("
    SELECT ha.id, ha.domain FROM hosting_accounts ha
    ORDER BY ha.domain
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhpMyAdmin Manager - Sakuci Hosting Admin</title>
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
        <h1>🔧 PhpMyAdmin - Database Manager</h1>
        <p style="color: var(--gray); margin-bottom: 2rem;">Kelola semua database MySQL untuk hosting customers</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Create Database Form -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-bottom: 2rem;">
            <h2>Create New Database</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_db">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="hosting_account_id">Hosting Account</label>
                        <select name="hosting_account_id" id="hosting_account_id" required>
                            <option value="">-- Pilih Hosting --</option>
                            <?php while ($account = $accounts_query->fetch_assoc()): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['domain']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" name="db_name" id="db_name" placeholder="e.g., myapp_db" required>
                    </div>
                    <div class="form-group">
                        <label for="db_type">Database Type</label>
                        <select name="db_type" id="db_type" required>
                            <option value="mysql">MySQL</option>
                            <option value="postgresql">PostgreSQL</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Create Database</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Databases List -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem;">
            <h2>Database List</h2>
            <input type="text" id="searchInput" placeholder="Search databases..." style="width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid var(--border); border-radius: 0.5rem;">

            <table class="table">
                <thead>
                    <tr>
                        <th>Database Name</th>
                        <th>DB User</th>
                        <th>Type</th>
                        <th>Hosting Account</th>
                        <th>Customer</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="databasesTable">
                    <?php while ($db = $databases_query->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($db['db_name']); ?></strong></td>
                        <td>
                            <code style="background: var(--light); padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                <?php echo htmlspecialchars($db['db_user']); ?>
                            </code>
                        </td>
                        <td>
                            <span class="status-badge" style="background: #bee3f8; color: #2c5282;">
                                <?php echo strtoupper($db['db_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($db['domain']); ?></td>
                        <td><?php echo htmlspecialchars($db['username']); ?></td>
                        <td><?php echo date('d M Y', strtotime($db['created_at'])); ?></td>
                        <td>
                            <button class="btn" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer;">
                                Manage
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- PhpMyAdmin Access Info -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
            <h3>🌐 PhpMyAdmin Access</h3>
            <p>PhpMyAdmin tersedia untuk mengelola database MySQL Anda secara langsung:</p>
            <ul style="margin-top: 1rem; padding-left: 2rem;">
                <li><strong>URL:</strong> <code style="background: rgba(0,0,0,0.2); padding: 0.25rem 0.5rem;">http://phpmyadmin.sakuci-hosting.com</code></li>
                <li><strong>Username:</strong> root (atau database user Anda)</li>
                <li><strong>Password:</strong> <code style="background: rgba(0,0,0,0.2); padding: 0.25rem 0.5rem;">[password database Anda]</code></li>
                <li>Akses melalui control panel hosting atau koneksi langsung MySQL</li>
            </ul>
        </div>

        <!-- MySQL Information -->
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
            <h3>MySQL Server Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Host:</strong> localhost
                </div>
                <div>
                    <strong>Port:</strong> 3306
                </div>
                <div>
                    <strong>Max Connections:</strong> 1000
                </div>
                <div>
                    <strong>Version:</strong> MySQL 8.0+
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.getElementById('databasesTable').getElementsByTagName('tr');

            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>

<?php
include '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

// Get all packages
$packages_query = $conn->query("
    SELECT * FROM packages ORDER BY id
");

$error = '';
$success = '';

// Handle package update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_package') {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $disk_space = (int)$_POST['disk_space'];
        $bandwidth = (int)$_POST['bandwidth'];
        $databases = (int)$_POST['databases'];
        $price_monthly = (float)$_POST['price_monthly'];
        $price_yearly = (float)$_POST['price_yearly'];

        $update = $conn->prepare("
            UPDATE packages
            SET name = ?, disk_space = ?, bandwidth = ?, databases = ?,
                price_monthly = ?, price_yearly = ?
            WHERE id = ?
        ");
        $update->bind_param("siiiddi", $name, $disk_space, $bandwidth, $databases, $price_monthly, $price_yearly, $id);

        if ($update->execute()) {
            $success = "Package berhasil diperbarui";
            header("Refresh: 1");
        } else {
            $error = "Gagal memperbarui package: " . $conn->error;
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
    <title>Manage Packages - Sakuci Hosting Admin</title>
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
        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .package-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.5rem;
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
        <h1>📦 Manage Packages</h1>
        <p style="color: var(--gray); margin-bottom: 2rem;">Edit paket hosting dan harga</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="package-grid">
            <?php while ($package = $packages_query->fetch_assoc()): ?>
            <div class="package-card">
                <form method="POST">
                    <input type="hidden" name="action" value="update_package">
                    <input type="hidden" name="id" value="<?php echo $package['id']; ?>">

                    <h3>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($package['name']); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                    </h3>

                    <div class="form-group" style="margin-top: 1rem;">
                        <label>Storage (MB)</label>
                        <input type="number" name="disk_space" value="<?php echo $package['disk_space']; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                    </div>

                    <div class="form-group">
                        <label>Bandwidth (GB)</label>
                        <input type="number" name="bandwidth" value="<?php echo $package['bandwidth']; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                    </div>

                    <div class="form-group">
                        <label>Databases</label>
                        <input type="number" name="databases" value="<?php echo $package['databases']; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Price/Month (Rp)</label>
                            <input type="number" step="0.01" name="price_monthly" value="<?php echo $package['price_monthly']; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                        </div>
                        <div class="form-group">
                            <label>Price/Year (Rp)</label>
                            <input type="number" step="0.01" name="price_yearly" value="<?php echo $package['price_yearly']; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.25rem;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Save Changes</button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>

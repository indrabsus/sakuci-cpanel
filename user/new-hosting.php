<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 3; // Default to pengguna for testing
$success = '';
$error = '';

// Handle new hosting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_hosting') {
    $domain = preg_replace("/[^a-zA-Z0-9.-]/", "", $_POST['domain'] ?? '');
    $package_id = intval($_POST['package_id'] ?? 1);

    if (empty($domain)) {
        $error = "Domain tidak valid";
    } else if (strlen($domain) < 3) {
        $error = "Domain minimal 3 karakter";
    } else if ($DB_CONNECTED && $conn) {
        $hosting_username = substr($domain, 0, 10) . '_' . random_int(1000, 9999);
        $query = "INSERT INTO hosting_accounts (user_id, domain, package_id, username, account_status, registration_date) VALUES ($user_id, '$domain', $package_id, '$hosting_username', 'active', NOW())";

        if ($conn->query($query)) {
            $success = "✅ Hosting berhasil ditambahkan!<br><strong>Domain:</strong> $domain<br><strong>Username:</strong> $hosting_username<br><strong>Status:</strong> Aktif";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Get user info
$user_data = ['username' => 'User'];
if ($DB_CONNECTED && $conn) {
    $user = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user->bind_param("i", $user_id);
    $user->execute();
    $result = $user->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Hosting - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; }
        .sidebar a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; color: #2d3748; text-decoration: none; border-radius: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: #edf2f7; color: #667eea; }
        .package-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .package-card { border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .package-card:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1); }
        .package-card.selected { border-color: #667eea; background: #edf2f7; }
        .package-name { font-size: 1.3rem; font-weight: 600; color: #2d3748; margin-bottom: 0.5rem; }
        .price { font-size: 2rem; font-weight: 700; color: #667eea; margin-bottom: 1rem; }
        .price-small { color: #718096; font-size: 0.9rem; }
        .features-list { text-align: left; margin: 1rem 0; color: #2d3748; }
        .features-list li { margin-bottom: 0.5rem; }
        .free-badge { display: inline-block; background: #48bb78; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; }
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
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="support.php">🎫 Support</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>🌐 Tambah Hosting Gratis</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Semua paket hosting kami GRATIS! Pilih paket yang sesuai kebutuhan Anda.</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Package Selection -->
            <h2 style="margin-bottom: 1.5rem;">📦 Pilih Paket Hosting</h2>
            <div class="package-grid">
                <!-- Free Starter -->
                <div class="package-card selected" onclick="selectPackage(this, 1)">
                    <div class="free-badge">✨ GRATIS</div>
                    <div class="package-name">Starter</div>
                    <div class="price">Rp 0<span class="price-small">/bulan</span></div>
                    <ul class="features-list">
                        <li>✅ 500 MB Storage</li>
                        <li>✅ 5 GB Bandwidth</li>
                        <li>✅ 1 Database MySQL</li>
                        <li>✅ 5 Email Accounts</li>
                        <li>✅ SSL Certificate</li>
                        <li>✅ Daily Backup</li>
                        <li>✅ 24/7 Support</li>
                    </ul>
                    <input type="hidden" class="package-id" value="1">
                </div>

                <!-- Free Pro -->
                <div class="package-card" onclick="selectPackage(this, 2)">
                    <div class="free-badge">✨ GRATIS</div>
                    <div class="package-name">Professional</div>
                    <div class="price">Rp 0<span class="price-small">/bulan</span></div>
                    <ul class="features-list">
                        <li>✅ 2 GB Storage</li>
                        <li>✅ 20 GB Bandwidth</li>
                        <li>✅ 5 Database MySQL</li>
                        <li>✅ 50 Email Accounts</li>
                        <li>✅ SSL Certificate</li>
                        <li>✅ Daily Backup</li>
                        <li>✅ Priority Support</li>
                    </ul>
                    <input type="hidden" class="package-id" value="2">
                </div>

                <!-- Free Business -->
                <div class="package-card" onclick="selectPackage(this, 3)">
                    <div class="free-badge">✨ GRATIS</div>
                    <div class="package-name">Business</div>
                    <div class="price">Rp 0<span class="price-small">/bulan</span></div>
                    <ul class="features-list">
                        <li>✅ 5 GB Storage</li>
                        <li>✅ 50 GB Bandwidth</li>
                        <li>✅ Unlimited Database</li>
                        <li>✅ Unlimited Email</li>
                        <li>✅ SSL Certificate</li>
                        <li>✅ Hourly Backup</li>
                        <li>✅ Premium Support</li>
                    </ul>
                    <input type="hidden" class="package-id" value="3">
                </div>
            </div>

            <!-- Domain Registration Form -->
            <div style="background: #f7fafc; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h2 style="margin-top: 0;">🌍 Daftarkan Domain</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_hosting">
                    <input type="hidden" name="package_id" id="selected-package-id" value="1">

                    <div class="form-group">
                        <label>Nama Domain</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="domain" id="domain-input" placeholder="example" required>
                            <span style="padding-top: 0.75rem; color: #718096;">.sakuci-hosting.id</span>
                        </div>
                        <small style="color: #718096; display: block; margin-top: 0.5rem;">
                            Hanya gunakan huruf, angka, dan tanda (-). Minimal 3 karakter.
                        </small>
                    </div>

                    <div style="background: #edf2f7; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #2d3748;">
                            <strong>📌 Paket:</strong> <span id="selected-package-name">Starter</span><br>
                            <strong>💾 Storage:</strong> <span id="selected-package-storage">500 MB</span><br>
                            <strong>📊 Bandwidth:</strong> <span id="selected-package-bandwidth">5 GB</span><br>
                            <strong>💰 Harga:</strong> <span style="color: #48bb78; font-weight: 600;">GRATIS</span>
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">✨ Daftarkan Domain Gratis</button>
                </form>
            </div>

            <!-- Info Box -->
            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea; margin-top: 0;">💡 Informasi Penting</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ <strong>100% GRATIS</strong> - Semua paket tanpa biaya tersembunyi</li>
                    <li>✅ <strong>No Credit Card Needed</strong> - Tidak perlu kartu kredit</li>
                    <li>✅ <strong>Unlimited Domains</strong> - Bisa hosting unlimited domain gratis</li>
                    <li>✅ <strong>Full Features</strong> - Semua fitur tersedia di semua paket</li>
                    <li>✅ <strong>24/7 Support</strong> - Tim support kami siap membantu</li>
                    <li>✅ <strong>Perfect for Sakuci Framework</strong> - Optimized untuk Sakuci Framework</li>
                </ul>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="dashboard.php" class="btn btn-secondary">← Kembali ke Dashboard</a>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function selectPackage(element, packageId) {
            // Remove selected class from all cards
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            // Add selected class to clicked card
            element.classList.add('selected');

            // Update hidden input
            document.getElementById('selected-package-id').value = packageId;

            // Update package display
            const packages = {
                1: { name: 'Starter', storage: '500 MB', bandwidth: '5 GB' },
                2: { name: 'Professional', storage: '2 GB', bandwidth: '20 GB' },
                3: { name: 'Business', storage: '5 GB', bandwidth: '50 GB' }
            };

            const pkg = packages[packageId];
            document.getElementById('selected-package-name').textContent = pkg.name;
            document.getElementById('selected-package-storage').textContent = pkg.storage;
            document.getElementById('selected-package-bandwidth').textContent = pkg.bandwidth;
        }

        // Format domain input
        document.getElementById('domain-input').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        });
    </script>
</body>
</html>

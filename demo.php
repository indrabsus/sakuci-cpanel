<?php
/**
 * Sakuci Hosting - Demo Mode
 * Showcase aplikasi tanpa database
 */

session_start();

// Mock packages
$packages = [
    [
        'id' => 1,
        'name' => 'Starter',
        'slug' => 'starter',
        'description' => 'Perfect untuk memulai',
        'disk_space' => 1024,
        'bandwidth' => 50,
        'databases' => 1,
        'email_accounts' => 5,
        'ftp_accounts' => 2,
        'addon_domains' => 0,
        'price_yearly' => 299.99,
        'is_popular' => false
    ],
    [
        'id' => 2,
        'name' => 'Professional',
        'slug' => 'professional',
        'description' => 'Untuk bisnis berkembang',
        'disk_space' => 5120,
        'bandwidth' => 200,
        'databases' => 5,
        'email_accounts' => 20,
        'ftp_accounts' => 10,
        'addon_domains' => 5,
        'price_yearly' => 799.99,
        'is_popular' => true
    ],
    [
        'id' => 3,
        'name' => 'Business',
        'slug' => 'business',
        'description' => 'Solusi bisnis lengkap',
        'disk_space' => 20480,
        'bandwidth' => 1000,
        'databases' => 25,
        'email_accounts' => 100,
        'ftp_accounts' => 50,
        'addon_domains' => 50,
        'price_yearly' => 1999.99,
        'is_popular' => false
    ],
    [
        'id' => 4,
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'description' => 'Untuk enterprise besar',
        'disk_space' => 102400,
        'bandwidth' => 5000,
        'databases' => 100,
        'email_accounts' => 500,
        'ftp_accounts' => 200,
        'addon_domains' => 200,
        'price_yearly' => 4999.99,
        'is_popular' => false
    ]
];

// Handle demo mode
if ($_GET['mode'] === 'admin_demo' && !isset($_SESSION['admin_demo'])) {
    $_SESSION['admin_demo'] = true;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    header("Location: demo.php?mode=admin_demo&page=dashboard");
    exit;
}

if ($_GET['mode'] === 'user_demo' && !isset($_SESSION['user_demo'])) {
    $_SESSION['user_demo'] = true;
    $_SESSION['username'] = 'johndoe';
    $_SESSION['user_id'] = 123;
    $_SESSION['role'] = 'user';
    header("Location: demo.php?mode=user_demo&page=dashboard");
    exit;
}

$page = $_GET['page'] ?? 'home';
$mode = $_GET['mode'] ?? '';
$mode = $mode === '' ? 'home' : $mode;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page === 'home' ? 'Sakuci Hosting - Web Hosting' : 'Dashboard'; ?></title>
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        .demo-banner {
            background: linear-gradient(90deg, #f39c12, #e74c3c);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .demo-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        .demo-btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .demo-btn-admin {
            background: #667eea;
            color: white;
        }
        .demo-btn-admin:hover {
            background: #5568d3;
        }
        .demo-btn-user {
            background: #48bb78;
            color: white;
        }
        .demo-btn-user:hover {
            background: #38a169;
        }
        .demo-btn-home {
            background: #718096;
            color: white;
        }
        .demo-btn-home:hover {
            background: #4a5568;
        }
    </style>
</head>
<body>
    <!-- Demo Banner -->
    <div class="demo-banner">
        🎬 DEMO MODE - This is a live preview. No database required!
        <a href="demo.php" style="color: white; margin-left: 10px;">[ Reset ]</a>
    </div>

    <!-- Navigation -->
    <header>
        <nav class="container">
            <a href="demo.php" class="logo">🚀 Sakuci Hosting</a>
            <ul class="nav-links">
                <li><a href="#features">Fitur</a></li>
                <li><a href="#packages">Paket</a></li>
                <li><a href="#why">Mengapa Kami</a></li>
                <li><a href="#support">Support</a></li>
            </ul>
            <div class="nav-buttons">
                <?php if ($mode === 'admin_demo'): ?>
                    <span style="margin-right: 1rem; color: var(--dark);">👤 Admin Demo</span>
                    <a href="demo.php?mode=admin_demo&page=dashboard" class="btn btn-primary">Dashboard</a>
                <?php elseif ($mode === 'user_demo'): ?>
                    <span style="margin-right: 1rem; color: var(--dark);">👤 User Demo</span>
                    <a href="demo.php?mode=user_demo&page=dashboard" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="demo.php?mode=admin_demo" class="btn btn-secondary">Admin Demo</a>
                    <a href="demo.php?mode=user_demo" class="btn btn-primary">User Demo</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <?php if ($mode === 'admin_demo' && $page === 'dashboard'): ?>
        <!-- Admin Dashboard Demo -->
        <div style="padding: 2rem;">
            <div class="container">
                <h1>Admin Dashboard</h1>
                <p style="color: var(--gray); margin-bottom: 2rem;">Welcome to Sakuci Hosting Admin Panel</p>

                <!-- Statistics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #667eea;">
                        <div style="font-size: 2rem; font-weight: 700; color: #667eea;">1,234</div>
                        <div style="color: var(--gray);">Total Users</div>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #48bb78;">
                        <div style="font-size: 2rem; font-weight: 700; color: #48bb78;">567</div>
                        <div style="color: var(--gray);">Hosting Accounts</div>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #ed8936;">
                        <div style="font-size: 2rem; font-weight: 700; color: #ed8936;">450</div>
                        <div style="color: var(--gray);">Active Hosting</div>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #764ba2;">
                        <div style="font-size: 2rem; font-weight: 700; color: #764ba2;">Rp 45.6 Jt</div>
                        <div style="color: var(--gray);">Total Revenue</div>
                    </div>
                </div>

                <!-- Menu Items -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; background: var(--light); padding: 2rem; border-radius: 0.75rem;">
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                        👥 Manage Customers
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                        📦 Manage Packages
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                        🖥️ Manage Servers
                    </a>
                    <a href="#" style="padding: 1rem; background: white; border-radius: 0.5rem; text-decoration: none; text-align: center; color: var(--dark); border: 1px solid var(--border);">
                        🔧 Database Manager
                    </a>
                </div>
            </div>
        </div>

    <?php elseif ($mode === 'user_demo' && $page === 'dashboard'): ?>
        <!-- User Dashboard Demo -->
        <div style="padding: 2rem;">
            <div class="container">
                <h1>My Hosting Dashboard</h1>
                <p style="color: var(--gray); margin-bottom: 2rem;">Welcome back, johndoe!</p>

                <!-- Statistics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">2</div>
                        <div style="color: var(--gray); font-size: 0.9rem;">Hosting Active</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #48bb78;">99.9%</div>
                        <div style="color: var(--gray); font-size: 0.9rem;">Uptime</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #ed8936;">0</div>
                        <div style="color: var(--gray); font-size: 0.9rem;">Invoices Pending</div>
                    </div>
                </div>

                <!-- My Hosting Accounts -->
                <div style="background: white; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                    <h2>My Hosting Accounts</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Package</th>
                                <th>Status</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>example.com</strong></td>
                                <td>Professional</td>
                                <td><span class="status-badge status-active">Active</span></td>
                                <td>15 Jan 2024</td>
                            </tr>
                            <tr>
                                <td><strong>mybusiness.id</strong></td>
                                <td>Starter</td>
                                <td><span class="status-badge status-active">Active</span></td>
                                <td>10 Feb 2024</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Homepage -->
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1>Hosting Cepat & Aman untuk Sakuci Framework</h1>
                <p>Mulai bisnis online Anda dengan web hosting berkualitas tinggi dan dukungan 24/7</p>
                <div class="hero-buttons">
                    <a href="demo.php?mode=user_demo" class="btn btn-primary">Lihat User Demo</a>
                    <a href="#packages" class="btn btn-secondary">Lihat Paket</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features" id="features">
            <div class="container">
                <h2>Fitur Unggulan</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <h3>SSD Storage</h3>
                        <p>Penyimpanan ultra-cepat dengan teknologi SSD terbaru</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>SSL Gratis</h3>
                        <p>Sertifikat SSL gratis seumur hidup</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💾</div>
                        <h3>Backup Otomatis</h3>
                        <p>Backup harian otomatis untuk keamanan data</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>Uptime Guarantee</h3>
                        <p>Jaminan uptime 99.9% dengan SLA komprehensif</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Packages Section -->
        <section class="packages" id="packages">
            <div class="container">
                <h2>Pilih Paket Terbaik Anda</h2>
                <div class="packages-grid">
                    <?php foreach ($packages as $package): ?>
                    <div class="package-card <?php echo $package['is_popular'] ? 'popular' : ''; ?>">
                        <?php if ($package['is_popular']): ?>
                            <div style="position: absolute; top: -10px; right: 20px; background: var(--primary); color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.85rem;">⭐ Popular</div>
                        <?php endif; ?>
                        <h3><?php echo $package['name']; ?></h3>
                        <p><?php echo $package['description']; ?></p>
                        <div class="package-price">
                            Rp <?php echo number_format($package['price_yearly'] / 12, 0, ',', '.'); ?>
                            <small>/bulan</small>
                        </div>
                        <ul class="package-features">
                            <li><?php echo number_format($package['disk_space'] / 1024, 1); ?> GB Storage</li>
                            <li><?php echo $package['bandwidth']; ?> GB Bandwidth</li>
                            <li><?php echo $package['databases']; ?> Database MySQL</li>
                            <li><?php echo $package['email_accounts']; ?> Email Account</li>
                            <li><?php echo $package['addon_domains']; ?> Add-on Domain</li>
                            <li>SSL Gratis</li>
                            <li>Backup Harian</li>
                        </ul>
                        <a href="demo.php?mode=user_demo" class="btn btn-primary" style="width: 100%; text-align: center;">Pilih Paket</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Admin Demo CTA -->
        <section class="hero">
            <div class="container" style="text-align: center;">
                <h2 style="color: white;">Ingin Lihat Panel Admin?</h2>
                <p style="color: rgba(255,255,255,0.9);">Explore fitur admin management untuk hosting platform</p>
                <a href="demo.php?mode=admin_demo" class="btn btn-secondary" style="color: var(--primary); margin-top: 1rem;">Lihat Admin Demo</a>
            </div>
        </section>

        <!-- Footer -->
        <footer id="support">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Tentang Kami</h4>
                    <p>Sakuci Hosting adalah penyedia layanan web hosting terpercaya dengan fokus pada kualitas dan pelayanan terbaik.</p>
                </div>
                <div class="footer-section">
                    <h4>Produk</h4>
                    <ul>
                        <li><a href="#packages">Paket Hosting</a></li>
                        <li><a href="#">Domain</a></li>
                        <li><a href="#">Email Pro</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Dukungan</h4>
                    <ul>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Knowledge Base</a></li>
                        <li><a href="#">Status Page</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
            </div>
        </footer>
    <?php endif; ?>

    <!-- Demo Buttons -->
    <div class="demo-buttons">
        <a href="demo.php" class="demo-btn demo-btn-home">🏠 Home</a>
        <a href="demo.php?mode=user_demo" class="demo-btn demo-btn-user">👤 User</a>
        <a href="demo.php?mode=admin_demo" class="demo-btn demo-btn-admin">⚙️ Admin</a>
    </div>

    <script src="public/js/script.js"></script>
</body>
</html>

<?php
include '../config/config.php';
include '../config/auth.php';

$user = require_login($conn);
$user_id = $user['id'];
$username = $user['username'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhpMyAdmin - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        header h1 { font-size: 1.5rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav a { margin-right: 1.5rem; text-decoration: none; color: #667eea; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .section { background: white; padding: 2rem; border-radius: 8px; }
        .section h2 { margin-bottom: 1rem; color: #333; }
        .info { background: #edf2f7; padding: 1.5rem; border-radius: 5px; margin-bottom: 1rem; }
        .info p { margin-bottom: 0.5rem; color: #2d3748; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #718096; }
        code { background: #edf2f7; padding: 0.2rem 0.5rem; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Sakuci cPanel</h1>
        <p>PhpMyAdmin Access</p>
    </header>

    <div class="container">
        <div class="nav">
            <div>
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="add-project.php">➕ Add Project</a>
                <a href="databases.php">🗄️ Databases</a>
                <a href="phpmyadmin.php">📊 PhpMyAdmin</a>
            </div>
            <div>
                <span>👤 <?php echo htmlspecialchars($username); ?></span>
                <a href="../index.php?logout=1" class="btn btn-secondary" style="margin-left: 1rem; display: inline-block;">Logout</a>
            </div>
        </div>

        <div class="section">
            <h2>📊 PhpMyAdmin Access</h2>

            <?php if (PHPMYADMIN_URL === ''): ?>
                <div class="info">
                    <strong>Belum dikonfigurasi.</strong><br>
                    Isi <code>phpmyadmin_url</code> di <code>config/env.php</code>.
                    Alamatnya bisa dilihat di aaPanel &rarr; Database &rarr; phpMyAdmin.
                </div>
            <?php else: ?>
                <div class="info">
                    <p><strong>Alamat:</strong></p>
                    <p><code><?php echo htmlspecialchars(PHPMYADMIN_URL); ?></code></p>
                    <p style="margin-top:.75rem"><strong>Login:</strong> pakai username dan
                    password database yang Anda buat di menu <a href="databases.php">Databases</a>.</p>
                </div>
            <?php endif; ?>

            <?php if (PHPMYADMIN_URL !== ''): ?>
                <a class="btn" href="<?php echo htmlspecialchars(PHPMYADMIN_URL); ?>"
                   target="_blank" rel="noopener noreferrer">🔗 Buka phpMyAdmin</a>
            <?php endif; ?>

            <div style="margin-top: 2rem; padding: 1.5rem; background: #fef3c7; border-radius: 5px; border-left: 4px solid #f59e0b;">
                <p style="color: #92400e; font-weight: 500; margin-bottom: 0.5rem;">⚠️ Catatan Penting</p>
                <ul style="color: #92400e; margin-left: 1.5rem; line-height: 1.6;">
                    <li>Hanya gunakan untuk database Anda sendiri</li>
                    <li>Jangan ubah struktur database sistem</li>
                    <li>Selalu buat backup sebelum melakukan perubahan besar</li>
                    <li>Gunakan password yang kuat untuk database Anda</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Optionally disable the button if phpmyadmin is not accessible
        // You could check with a ping request here
    </script>
</body>
</html>

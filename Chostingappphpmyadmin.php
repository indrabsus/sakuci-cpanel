<?php
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PhpMyAdmin - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        .container { max-width: 1000px; margin: 3rem auto; padding: 2rem; }
        .info-box { background: white; padding: 2rem; border-radius: 8px; text-align: center; }
        .btn { display: inline-block; padding: 1rem 2rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-size: 1.1rem; margin-top: 1rem; }
        .btn:hover { background: #5568d3; }
        h2 { margin-bottom: 1rem; color: #333; }
        p { color: #666; margin-bottom: 1rem; }
        code { background: #e2e8f0; padding: 0.25rem 0.5rem; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <h1>🔧 PhpMyAdmin</h1>
    </header>

    <div class="container">
        <div class="info-box">
            <h2>PhpMyAdmin Access</h2>
            <p>Open PhpMyAdmin to manage your databases:</p>
            <a href="http://localhost/phpmyadmin" class="btn" target="_blank">Open PhpMyAdmin</a>
            
            <p style="margin-top: 2rem; font-size: 0.9rem;">
                <strong>Default credentials:</strong><br>
                Username: <code>root</code><br>
                Password: <code>admin123</code>
            </p>
        </div>
    </div>
</body>
</html>

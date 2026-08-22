<?php
/**
 * Sakuci Hosting - Setup Wizard
 * Initialize database dan konfigurasi awal
 */

$setup_complete = file_exists('config/database.php') &&
                  file_get_contents('config/database.php');

$error = '';
$success = '';
$step = $_GET['step'] ?? 1;

// Database info
$db_host = 'localhost';
$db_user = 'root';
$db_pass = $_POST['db_pass'] ?? '';
$db_name = 'sakuci_hosting';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'test_db') {
        // Test database connection
        $conn = @mysqli_connect($db_host, $db_user, $db_pass);

        if ($conn) {
            $success = "✅ Database connection successful!";
            $step = 2;

            // Create database
            if (!mysqli_select_db($conn, $db_name)) {
                mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                mysqli_select_db($conn, $db_name);
                $success .= " Database created successfully!";
            }

            // Import schema
            $schema = file_get_contents('database/schema.sql');
            $queries = array_filter(array_map('trim', explode(';', $schema)));

            $imported = 0;
            foreach ($queries as $query) {
                if (!empty($query)) {
                    if (@mysqli_query($conn, $query)) {
                        $imported++;
                    }
                }
            }

            $success .= " Imported $imported database objects.";

            // Create config file
            $config_content = "<?php\n// Database Configuration\ndefine('DB_HOST', '$db_host');\ndefine('DB_USER', '$db_user');\ndefine('DB_PASS', '$db_pass');\ndefine('DB_NAME', '$db_name');\ndefine('DB_PORT', 3306);\n\n\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);\n\nif (\$conn->connect_error) {\n    die(\"Connection failed: \" . \$conn->connect_error);\n}\n\n\$conn->set_charset(\"utf8mb4\");\n\nif (session_status() === PHP_SESSION_NONE) {\n    session_start();\n}\n?>";

            file_put_contents('config/database.php', $config_content);
            $success .= " Configuration saved!";

            $step = 3;
        } else {
            $error = "❌ Connection failed: " . mysqli_connect_error();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Sakuci Hosting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #718096;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .progress {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        .progress-step {
            flex: 1;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-step.active {
            background: #667eea;
        }
        .progress-step.complete {
            background: #48bb78;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        .success-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .links {
            margin-top: 20px;
            text-align: center;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .links a {
            display: inline-block;
            padding: 8px 16px;
            background: #edf2f7;
            color: #667eea;
            border-radius: 4px;
            font-size: 14px;
        }
        .links a:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🚀 Sakuci Hosting Setup</h1>
        <p class="subtitle">Initialize your hosting platform</p>

        <div class="progress">
            <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'complete' : ''; ?>"></div>
            <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'complete' : ''; ?>"></div>
            <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>"></div>
        </div>

        <!-- Step 1: Database Connection -->
        <div class="step-content <?php echo $step === 1 ? 'active' : ''; ?>">
            <h2 style="color: #2d3748; margin-bottom: 20px;">Step 1: Database Connection</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" value="localhost" disabled>
                    <small style="color: #718096;">Default: localhost</small>
                </div>

                <div class="form-group">
                    <label>Database User</label>
                    <input type="text" value="root" disabled>
                    <small style="color: #718096;">Default: root</small>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" placeholder="Enter MySQL root password (leave empty if none)">
                    <small style="color: #718096;">If your MySQL has no password, leave this empty</small>
                </div>

                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" value="sakuci_hosting" disabled>
                    <small style="color: #718096;">Default: sakuci_hosting</small>
                </div>

                <input type="hidden" name="action" value="test_db">
                <button type="submit">Test Connection & Setup Database</button>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 6px; font-size: 14px; color: #4a5568;">
                <strong>Need help?</strong><br>
                MySQL usually runs on localhost:3306. Default root user has no password on Windows.
                <br><br>
                If you get an error, make sure MySQL is running:<br>
                <code style="background: #edf2f7; padding: 4px 8px; border-radius: 3px;">net start MySQL80</code>
            </div>
        </div>

        <!-- Step 2: Database Setup in Progress -->
        <div class="step-content <?php echo $step === 2 ? 'active' : ''; ?>">
            <div class="success-icon">✅</div>
            <h2 style="color: #2d3748; text-align: center; margin-bottom: 10px;">Database Ready!</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo nl2br($success); ?></div>
            <?php endif; ?>

            <p style="color: #718096; text-align: center; margin: 20px 0;">
                Your database has been created and initialized with sample data.
            </p>

            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="step" value="3">
                <button type="button" onclick="window.location.href='?step=3'" style="background: #667eea;">
                    Continue to Next Step
                </button>
            </form>
        </div>

        <!-- Step 3: Setup Complete -->
        <div class="step-content <?php echo $step === 3 ? 'active' : ''; ?>">
            <div class="success-icon">🎉</div>
            <h2 style="color: #2d3748; text-align: center; margin-bottom: 20px;">Setup Complete!</h2>

            <div class="alert alert-success">
                ✅ Sakuci Hosting Platform is ready to use!<br>
                <br>
                <strong>Default Credentials:</strong><br>
                • Admin Username: <code>admin</code><br>
                • Admin Password: <code>admin123</code><br>
                <br>
                ⚠️ Change password after first login!
            </div>

            <p style="color: #718096; margin: 20px 0;">
                Your hosting platform is now fully configured and ready for use. Click below to access the application.
            </p>

            <div class="links" style="flex-direction: column; gap: 10px;">
                <a href="/" style="background: #667eea; color: white; padding: 12px; border-radius: 6px; text-align: center;">🏠 Go to Homepage</a>
                <a href="/admin/" style="background: #f7fafc; color: #667eea; padding: 12px; border-radius: 6px; text-align: center;">🔐 Admin Panel</a>
                <a href="/user/login.php" style="background: #f7fafc; color: #667eea; padding: 12px; border-radius: 6px; text-align: center;">👤 User Login</a>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #edf2f7; border-radius: 6px; font-size: 13px; color: #4a5568;">
                <strong>📚 Documentation:</strong><br>
                • README.md - Full documentation<br>
                • QUICK_START.md - 5-minute guide<br>
                • FEATURES.md - Feature list<br>
                • API.md - API documentation
            </div>
        </div>
    </div>
</body>
</html>

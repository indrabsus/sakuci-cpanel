<?php
include 'config/config.php';
include 'config/auth.php';

if (isset($_GET['logout'])) {
    clear_session();
    header("Location: index.php");
    exit;
}

// Validate rather than just checking the key, so a stale session shows the
// login form here instead of bouncing off the dashboard and back.
if (current_user($conn)) {
    header("Location: app/dashboard.php");
    exit;
}

$error = isset($_GET['expired']) ? '❌ Sesi Anda sudah tidak berlaku. Silakan login kembali.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    header("Location: app/dashboard.php");
                    exit;
                } else {
                    $error = "❌ Password salah";
                }
            } else {
                $error = "❌ Username tidak ditemukan";
            }
        } else {
            $error = "❌ Database error";
        }
    } else {
        $error = "❌ Username dan password harus diisi";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sakuci cPanel - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        h1 { color: #333; margin-bottom: 0.5rem; font-size: 1.8rem; }
        .subtitle { color: #666; margin-bottom: 2rem; font-size: 0.9rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        button { width: 100%; padding: 0.75rem; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #5568d3; }
        .error { color: #e53e3e; margin-bottom: 1rem; padding: 0.75rem; background: #fed7d7; border-radius: 5px; }
        .info { color: #744210; margin-bottom: 1rem; padding: 0.75rem; background: #feebc8; border-radius: 5px; font-size: 0.9rem; }
        .info code { background: white; padding: 0.1rem 0.3rem; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🚀 Sakuci cPanel</h1>
        <p class="subtitle">Development Control Panel</p>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="info">
            <strong>Test Account:</strong><br>
            User: <code>admin</code><br>
            Pass: <code>password123</code>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>

<?php
include '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        // Query user
        if ($DB_CONNECTED && $conn) {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE (username = ? OR email = ?) AND user_status = 'active'");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    // Update last login
                    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();

                    // Redirect
                    if ($user['role'] === 'admin') {
                        header("Location: ../admin/");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                } else {
                    $error = 'Password salah';
                }
            } else {
                $error = 'Username atau email tidak ditemukan';
            }
        } else {
            $error = 'Database tidak terkoneksi';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 4rem auto;
            padding: 2rem;
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .auth-container h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1rem;
            color: var(--gray);
        }
        .auth-footer a {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <div class="nav-buttons">
                <a href="register.php" class="btn btn-primary">Daftar</a>
            </div>
        </nav>
    </header>

    <div class="auth-container">
        <h1>Login</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Error!</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username atau Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>

        <div class="auth-footer">
            <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </div>
    </div>

    <script src="../public/js/script.js"></script>
</body>
</html>

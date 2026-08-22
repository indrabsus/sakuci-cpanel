<?php
include '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $full_name = sanitize($_POST['full_name'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field harus diisi';
    } elseif (strlen($password) < 8) {
        $error = 'Password harus minimal 8 karakter';
    } elseif ($password !== $password_confirm) {
        $error = 'Password tidak cocok';
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username atau email sudah terdaftar';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Insert user
            $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, status) VALUES (?, ?, ?, ?, 'active')");
            $insert->bind_param("ssss", $username, $email, $hashed_password, $full_name);

            if ($insert->execute()) {
                $success = 'Registrasi berhasil! Silahkan login.';
                // Redirect after 2 seconds
                header("Refresh: 2; url=login.php");
            } else {
                $error = 'Terjadi kesalahan: ' . $conn->error;
            }
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
    <title>Daftar - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .auth-container {
            max-width: 450px;
            margin: 3rem auto;
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
        .auth-container a {
            color: var(--primary);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1rem;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-secondary">Login</a>
            </div>
        </nav>
    </header>

    <div class="auth-container">
        <h1>Daftar Akun Baru</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Error!</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Sukses!</strong> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="full_name">Nama Lengkap</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <small style="color: var(--gray);">Minimal 8 karakter, kombinasi angka, huruf besar dan kecil</small>
            </div>

            <div class="form-group">
                <label for="password_confirm">Konfirmasi Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Daftar Sekarang</button>
        </form>

        <div class="auth-footer">
            <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
        </div>
    </div>

    <script src="../public/js/script.js"></script>
</body>
</html>

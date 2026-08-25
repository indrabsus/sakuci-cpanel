<?php
include 'config/config.php';
include 'config/auth.php';
include 'config/login-limit.php';

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

$error = isset($_GET['expired']) ? 'Sesi Anda sudah tidak berlaku. Silakan login kembali.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = ip_pemanggil();

    // Diperiksa SEBELUM password dicocokkan, supaya percobaan yang diblokir
    // tidak ikut membebani pencocokan bcrypt.
    $sisa = sisa_blokir($conn, $ip);

    if ($sisa > 0) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam '
               . sebut_durasi($sisa) . '.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($user && password_verify($password, $user['password'])) {
            bersihkan_gagal($conn, $ip);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header("Location: app/dashboard.php");
            exit;
        }

        catat_gagal($conn, $ip, $username);

        // Pesan sengaja sama untuk username salah maupun password salah:
        // membedakannya memberi tahu penebak akun mana yang benar-benar ada.
        $tersisa = LOGIN_MAKS_GAGAL - jumlah_gagal($conn, $ip);

        $error = 'Username atau password salah.'
               . ($tersisa > 0 && $tersisa <= 2 ? ' Sisa ' . $tersisa . ' percobaan.' : '');
    }
}

$v = fn(string $f) => $f . '?v=' . @filemtime(__DIR__ . '/app/assets/' . $f);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk &middot; Sakuci cPanel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;550;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="app/assets/<?php echo $v('panel.css'); ?>">
<link rel="stylesheet" href="app/assets/<?php echo $v('login.css'); ?>">
</head>
<body class="masuk">

<div class="masuk-kotak">
    <div class="masuk-kepala">
        <h1>Sakuci cPanel</h1>
        <p>Panel hosting untuk project siswa</p>
    </div>

    <div class="masuk-isi">
        <?php if ($error): ?>
            <div class="note note-err"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="m-user">Nama Pengguna</label>
                <input id="m-user" type="text" name="username" required autofocus
                       autocomplete="username" autocapitalize="none" spellcheck="false">
            </div>

            <div class="field">
                <label for="m-pin">PIN</label>
                <input id="m-pin" class="pin" type="password" name="password" required
                       inputmode="numeric" autocomplete="current-password"
                       maxlength="32" placeholder="······">
            </div>

            <button type="submit" class="btn">Masuk</button>
        </form>
    </div>
</div>

<footer class="masuk-kaki">
    Sakuci cPanel &mdash; Created by Indra Batara, S.Pd., Gr.
</footer>

</body>
</html>

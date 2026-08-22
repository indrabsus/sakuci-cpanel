<?php
/*
| Mengganti password user cPanel.
|
|   php tools/set-password.php admin
|
| Password diketik langsung di terminal (disembunyikan bila didukung) dan
| hanya hash bcrypt-nya yang disimpan -- password aslinya tidak pernah
| ditulis ke file, log, maupun riwayat perintah.
*/

if (PHP_SAPI !== 'cli') {
    exit("Jalankan lewat terminal, bukan browser.\n");
}

$username = $argv[1] ?? null;
if (!$username) {
    exit("Cara pakai: php tools/set-password.php <username>\n");
}

/** Membaca input tanpa menampilkannya bila terminal mendukung. */
function readSecret(string $prompt): string
{
    echo $prompt;

    $hidden = false;
    if (DIRECTORY_SEPARATOR !== '\\' && shell_exec('command -v stty 2>/dev/null')) {
        shell_exec('stty -echo 2>/dev/null');
        $hidden = true;
    }

    $value = trim((string) fgets(STDIN));

    if ($hidden) {
        shell_exec('stty echo 2>/dev/null');
        echo "\n";
    } else {
        echo "  (catatan: terminal ini menampilkan ketikan Anda)\n";
    }

    return $value;
}

require __DIR__ . '/../config/config.php';

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    exit("User '$username' tidak ditemukan di database " . DB_NAME . ".\n");
}

$password = readSecret("Password baru untuk '$username': ");
$confirm  = readSecret("Ulangi password                : ");

if ($password === '') {
    exit("Password kosong, dibatalkan.\n");
}
if ($password !== $confirm) {
    exit("Password tidak sama, dibatalkan.\n");
}
if (strlen($password) < 12) {
    exit("Password minimal 12 karakter, dibatalkan.\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$update = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$update->bind_param("ss", $hash, $username);
$update->execute();

echo $update->affected_rows === 1
    ? "OK. Password '$username' berhasil diganti.\n"
    : "Tidak ada baris yang berubah -- password mungkin sama dengan sebelumnya.\n";

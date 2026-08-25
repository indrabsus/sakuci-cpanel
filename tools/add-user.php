<?php
/*
| Membuat akun siswa.
|
|   php tools/add-user.php budi                 satu akun, password diketik
|   php tools/add-user.php --acak budi siti eka beberapa akun sekaligus,
|                                              password diacak dan dicetak
|
| Mode --acak memudahkan pendaftaran satu kelas: password ditampilkan sekali
| di layar untuk dibagikan, dan hanya hash bcrypt-nya yang tersimpan.
*/

if (PHP_SAPI !== 'cli') {
    exit("Jalankan lewat terminal, bukan browser.\n");
}

require __DIR__ . '/../config/config.php';

$args = array_slice($argv, 1);
$acak = in_array('--acak', $args, true);
$names = array_values(array_filter($args, fn($a) => $a !== '--acak'));

if (!$names) {
    exit("Cara pakai:\n"
        . "  php tools/add-user.php <username>\n"
        . "  php tools/add-user.php --acak <username> [username ...]\n");
}

function readSecret(string $prompt): string
{
    echo $prompt;

    // shell_exec kerap dimatikan lewat disable_functions; fungsi yang dimatikan
    // menjadi undefined di PHP 8 sehingga harus diperiksa dulu.
    $hide = DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')
        && @shell_exec('command -v stty 2>/dev/null');

    if ($hide) {
        @shell_exec('stty -echo 2>/dev/null');
    }

    $value = trim((string) fgets(STDIN));

    if ($hide) {
        @shell_exec('stty echo 2>/dev/null');
        echo "\n";
    }

    return $value;
}

$dibuat = [];
$dilewati = [];

foreach ($names as $username) {
    $username = strtolower(trim($username));

    if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $username)) {
        $dilewati[] = "$username (nama tidak valid: huruf kecil, angka, garis bawah, 3-32 karakter)";
        continue;
    }

    $cek = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $cek->bind_param("s", $username);
    $cek->execute();

    if ($cek->get_result()->fetch_assoc()) {
        $dilewati[] = "$username (sudah ada)";
        continue;
    }

    if ($acak) {
        // PIN enam angka: mudah dibacakan dan diketik siswa. Ruang tebakannya
        // kecil, jadi hanya layak bersama pembatasan percobaan login.
        $password = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } else {
        $password = readSecret("Password untuk '$username': ");
        $ulang    = readSecret("Ulangi                  : ");

        if ($password !== $ulang) {
            $dilewati[] = "$username (password tidak sama)";
            continue;
        }
        if (strlen($password) < 6) {
            $dilewati[] = "$username (password minimal 6 karakter)";
            continue;
        }
    }

    $hash  = password_hash($password, PASSWORD_DEFAULT);
    $email = $username . '@siswa.local';

    try {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hash);
        $stmt->execute();

        $dibuat[$username] = $acak ? $password : '(diketik sendiri)';
    } catch (mysqli_sql_exception $e) {
        $dilewati[] = "$username (gagal disimpan)";
    }
}

echo "\n";

if ($dibuat) {
    echo "Akun dibuat:\n";
    printf("  %-20s %s\n", 'USERNAME', 'PASSWORD');
    foreach ($dibuat as $u => $p) {
        printf("  %-20s %s\n", $u, $p);
    }
    if ($acak) {
        echo "\n  Catat sekarang -- password tidak bisa ditampilkan lagi.\n";
    }
}

if ($dilewati) {
    echo "\nDilewati:\n";
    foreach ($dilewati as $d) {
        echo "  $d\n";
    }
}

echo "\n";

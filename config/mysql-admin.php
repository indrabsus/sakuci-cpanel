<?php
// Pembuatan database MySQL yang sesungguhnya.
//
// Sebelumnya panel hanya mencatat nama database di tabel db_list tanpa pernah
// menjalankan CREATE DATABASE, sehingga kredensial yang ditampilkan tidak bisa
// dipakai sama sekali.
//
// CREATE DATABASE / CREATE USER / GRANT adalah perintah SQL biasa, jadi tetap
// bisa dijalankan meski exec() dimatikan. Yang dibutuhkan hanya user MySQL
// dengan wewenang tersebut -- lihat 'db_admin_*' di config/env.php.

/**
 * Koneksi sebagai user yang berwenang membuat database.
 * Mengembalikan null bila belum dikonfigurasi.
 */
function mysql_admin_connect(array $env): ?mysqli
{
    if (empty($env['db_admin_user'])) {
        return null;
    }

    try {
        $conn = new mysqli(
            $env['db_host'] ?? 'localhost',
            $env['db_admin_user'],
            $env['db_admin_pass'] ?? '',
            '',
            (int) ($env['db_port'] ?? 3306)
        );
        $conn->set_charset('utf8mb4');

        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log('Koneksi admin MySQL gagal: ' . $e->getMessage());

        return null;
    }
}

/**
 * Nama database dan user MySQL hanya boleh berisi karakter aman.
 *
 * Identifier tidak bisa di-bind sebagai parameter prepared statement, jadi
 * satu-satunya pengaman adalah membatasi karakter yang diterima. Pola ketat
 * ini yang mencegah injeksi lewat nama database.
 */
function valid_mysql_identifier(string $name): bool
{
    return (bool) preg_match('/^[a-z][a-z0-9_]{2,47}$/', $name);
}

/**
 * Membuat database beserta user yang hanya berwenang atas database itu.
 *
 * @return array{ok:bool, error?:string}
 */
function create_mysql_database(mysqli $admin, string $dbName, string $dbUser, string $dbPass): array
{
    if (!valid_mysql_identifier($dbName) || !valid_mysql_identifier($dbUser)) {
        return ['ok' => false, 'error' => 'Nama database atau user tidak valid.'];
    }

    try {
        $admin->query(
            "CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        // MySQL tidak menerima placeholder pada CREATE USER: nama user adalah
        // identifier, bukan nilai. Keamanannya bertumpu pada dua hal --
        // valid_mysql_identifier() sudah membatasi $dbUser hanya huruf kecil,
        // angka, dan garis bawah, sedangkan password di-escape di sini.
        $escapedPass = $admin->real_escape_string($dbPass);
        $admin->query("CREATE USER '$dbUser'@'localhost' IDENTIFIED BY '$escapedPass'");

        // Wewenang dibatasi pada satu database saja: siswa tidak bisa menyentuh
        // database milik siswa lain maupun database panel.
        $admin->query("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'localhost'");
        $admin->query("FLUSH PRIVILEGES");

        return ['ok' => true];
    } catch (mysqli_sql_exception $e) {
        error_log("Gagal membuat database $dbName: " . $e->getMessage());

        // Bila user gagal dibuat, database yang telanjur ada ikut dibersihkan
        // agar tidak meninggalkan sisa yang membingungkan.
        try {
            $admin->query("DROP DATABASE IF EXISTS `$dbName`");
        } catch (mysqli_sql_exception $ignored) {
        }

        $msg = str_contains($e->getMessage(), 'exists')
            ? 'Nama database atau user sudah dipakai.'
            : 'Gagal membuat database di MySQL.';

        return ['ok' => false, 'error' => $msg];
    }
}

/** Menghapus database beserta usernya. */
function drop_mysql_database(mysqli $admin, string $dbName, ?string $dbUser): array
{
    if (!valid_mysql_identifier($dbName)) {
        return ['ok' => false, 'error' => 'Nama database tidak valid.'];
    }

    try {
        $admin->query("DROP DATABASE IF EXISTS `$dbName`");

        if ($dbUser !== null && valid_mysql_identifier($dbUser)) {
            $admin->query("DROP USER IF EXISTS '$dbUser'@'localhost'");
            $admin->query("FLUSH PRIVILEGES");
        }

        return ['ok' => true];
    } catch (mysqli_sql_exception $e) {
        error_log("Gagal menghapus database $dbName: " . $e->getMessage());

        return ['ok' => false, 'error' => 'Gagal menghapus database.'];
    }
}

<?php
// Membatasi penebakan password.
//
// Password akun berupa PIN enam angka -- hanya sejuta kemungkinan. Tanpa
// pembatasan ini, seluruh ruang tebakan bisa ditelusuri mesin dalam hitungan
// menit. Dengan pembatasan, laju tebakan turun drastis sehingga menebak satu
// PIN memakan waktu yang tidak masuk akal.

const LOGIN_MAKS_GAGAL = 5;      // percobaan sebelum diblokir
const LOGIN_JENDELA    = 900;    // detik: rentang penghitungan dan lama blokir

/**
 * Alamat pemanggil. Sengaja hanya REMOTE_ADDR -- X-Forwarded-For dikirim klien
 * dan bisa dipalsukan, sehingga penebak tinggal mengganti header itu tiap
 * percobaan untuk menghindari blokir sepenuhnya.
 */
function ip_pemanggil(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'tidak-diketahui';
}

/**
 * Sisa detik blokir untuk IP ini, atau 0 bila tidak diblokir.
 */
function sisa_blokir($conn, string $ip): int
{
    // Pembatasan ini pelengkap, bukan syarat masuk. Bila tabelnya belum ada --
    // misalnya migrasi belum dijalankan setelah pembaruan -- login harus tetap
    // berfungsi, bukan ikut jatuh. Kegagalannya dicatat agar tetap ketahuan.
    if (!tabel_batas_ada($conn)) {
        return 0;
    }

    // Sisa waktu dihitung di dalam SQL, bukan dengan membandingkan MAX(waktu)
    // terhadap time() milik PHP. Zona waktu MySQL dan PHP bisa berbeda, dan
    // selisihnya langsung muncul sebagai lama blokir yang keliru -- pada
    // pengujian sempat terbaca 435 menit, bukan 15.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS jumlah,
                GREATEST(0, ? - TIMESTAMPDIFF(SECOND, MAX(waktu), NOW())) AS sisa
           FROM login_gagal
          WHERE ip = ? AND waktu > (NOW() - INTERVAL ? SECOND)"
    );
    $jendela = LOGIN_JENDELA;
    $stmt->bind_param("isi", $jendela, $ip, $jendela);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();

    if ((int) $r['jumlah'] < LOGIN_MAKS_GAGAL) {
        return 0;
    }

    // Dihitung dari percobaan gagal terakhir, sehingga mencoba lagi saat
    // diblokir memperpanjang blokirnya.
    return (int) $r['sisa'];
}

/** Sekali periksa per permintaan; hasilnya diingat agar tidak berulang. */
function tabel_batas_ada($conn): bool
{
    static $ada = null;

    if ($ada === null) {
        try {
            $r = $conn->query("SHOW TABLES LIKE 'login_gagal'");
            $ada = $r && $r->num_rows > 0;
        } catch (mysqli_sql_exception $e) {
            $ada = false;
        }

        if (!$ada) {
            error_log('Tabel login_gagal belum ada; pembatasan percobaan login tidak aktif. '
                    . 'Jalankan database/migrations/005-batas-login.sql.');
        }
    }

    return $ada;
}

function catat_gagal($conn, string $ip, string $username): void
{
    if (!tabel_batas_ada($conn)) {
        return;
    }

    $stmt = $conn->prepare("INSERT INTO login_gagal (ip, username) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $username);
    $stmt->execute();

    // Bersihkan catatan lama sesekali agar tabel tidak menumpuk selamanya.
    if (random_int(1, 20) === 1) {
        $conn->query("DELETE FROM login_gagal WHERE waktu < (NOW() - INTERVAL 1 DAY)");
    }
}

function bersihkan_gagal($conn, string $ip): void
{
    if (!tabel_batas_ada($conn)) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM login_gagal WHERE ip = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
}

/** Jumlah percobaan gagal dari IP ini dalam jendela waktu berjalan. */
function jumlah_gagal($conn, string $ip): int
{
    if (!tabel_batas_ada($conn)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS n FROM login_gagal
          WHERE ip = ? AND waktu > (NOW() - INTERVAL ? SECOND)"
    );
    $jendela = LOGIN_JENDELA;
    $stmt->bind_param("si", $ip, $jendela);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['n'];
}

/** "3 menit" atau "45 detik" -- lebih mudah dibaca daripada detik mentah. */
function sebut_durasi(int $detik): string
{
    return $detik >= 60
        ? ceil($detik / 60) . ' menit'
        : $detik . ' detik';
}

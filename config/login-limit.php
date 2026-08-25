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

function catat_gagal($conn, string $ip, string $username): void
{
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
    $stmt = $conn->prepare("DELETE FROM login_gagal WHERE ip = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
}

/** "3 menit" atau "45 detik" -- lebih mudah dibaca daripada detik mentah. */
function sebut_durasi(int $detik): string
{
    return $detik >= 60
        ? ceil($detik / 60) . ' menit'
        : $detik . ' detik';
}

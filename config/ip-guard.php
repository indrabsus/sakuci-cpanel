<?php
// Pembatasan akses berdasarkan alamat IP.
//
// Idealnya penyaringan dilakukan web server sebelum PHP dijalankan. Ini
// lapisan aplikasi: dipakai bila pengaturan di panel hosting tidak tersedia
// atau tidak berfungsi. Halaman login tidak akan pernah tampil bagi IP yang
// tidak terdaftar.

/**
 * Mencocokkan satu IP dengan satu aturan: alamat persis ("1.2.3.4") atau
 * rentang CIDR ("1.2.3.0/24"). Mendukung IPv4 maupun IPv6.
 */
function ip_matches(string $ip, string $rule): bool
{
    $rule = trim($rule);
    if ($rule === '') {
        return false;
    }

    $bits = null;
    if (str_contains($rule, '/')) {
        [$rule, $bitsRaw] = explode('/', $rule, 2);
        if (!ctype_digit(trim($bitsRaw))) {
            return false;
        }
        $bits = (int) trim($bitsRaw);
    }

    // inet_pton menormalkan bentuk penulisan, sehingga ::1 dan 0:0:...:1
    // dianggap sama.
    $ipBin   = @inet_pton($ip);
    $ruleBin = @inet_pton(trim($rule));

    if ($ipBin === false || $ruleBin === false) {
        return false;
    }
    // Panjang berbeda berarti beda keluarga (IPv4 vs IPv6): bukan kecocokan.
    if (strlen($ipBin) !== strlen($ruleBin)) {
        return false;
    }

    if ($bits === null) {
        return $ipBin === $ruleBin;
    }

    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $wholeBytes = intdiv($bits, 8);
    $extraBits  = $bits % 8;

    if ($wholeBytes > 0
        && substr($ipBin, 0, $wholeBytes) !== substr($ruleBin, 0, $wholeBytes)) {
        return false;
    }
    if ($extraBits === 0) {
        return true;
    }

    $mask = chr((0xFF << (8 - $extraBits)) & 0xFF);

    return ($ipBin[$wholeBytes] & $mask) === ($ruleBin[$wholeBytes] & $mask);
}

function ip_allowed(string $ip, array $rules): bool
{
    foreach ($rules as $rule) {
        if (ip_matches($ip, (string) $rule)) {
            return true;
        }
    }

    return false;
}

/**
 * Menghentikan permintaan dari IP yang tidak terdaftar.
 *
 * Daftar kosong berarti fitur mati -- supaya pengembangan lokal dan instalasi
 * baru tidak terkunci tanpa sengaja.
 */
function enforce_ip_allowlist(array $allowed): void
{
    // Worker cron dan perkakas terminal tidak punya alamat IP dan tidak boleh
    // ikut diblokir; tanpa ini worker berhenti bekerja.
    if (PHP_SAPI === 'cli') {
        return;
    }

    if ($allowed === []) {
        return;
    }

    // Sengaja hanya REMOTE_ADDR. Header X-Forwarded-For dikirim oleh klien dan
    // bisa dipalsukan siapa saja, jadi mempercayainya justru meniadakan
    // pembatasan ini. Bila kelak dipasang CDN/proxy di depan, bagian ini harus
    // ditinjau ulang karena REMOTE_ADDR akan berisi alamat proxy.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($ip !== '' && ip_allowed($ip, $allowed)) {
        return;
    }

    error_log('cPanel: akses ditolak dari IP ' . ($ip ?: 'tidak diketahui'));

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 Akses ditolak.\n\nAlamat IP Anda: " . ($ip ?: 'tidak diketahui') . "\n");
}

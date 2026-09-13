<?php
// Memastikan repo yang di-clone benar-benar project Sakuci Framework.
//
// Sengaja TIDAK memakai kunci rahasia di dalam framework. Repo framework
// bersifat publik, jadi kunci apa pun di dalamnya bisa disalin siapa saja ke
// project mana pun -- ia hanya membuktikan "berkas itu tersalin", bukan
// "ini project Sakuci".
//
// Yang diperiksa adalah strukturnya. Untuk memalsukannya, seseorang harus
// menyusun ulang seluruh kerangka framework -- dan bila itu dilakukan, project
// tersebut memang sudah menjadi project Sakuci.
//
// Ini penegakan aturan ujian, bukan pembatas keamanan. Tujuannya mencegah
// project yang keliru masuk, bukan menahan orang yang sengaja mengakalinya.

/**
 * @return array{ok:bool, kurang:string[]}
 */
function periksa_sakuci(string $path): array
{
    $kurang = [];

    // Berkas dan folder yang selalu ada pada project Sakuci.
    $wajib = [
        'sakuci'             => 'berkas CLI "sakuci"',
        'core/bootstrap.php' => 'core/bootstrap.php',
        'core/Application.php' => 'core/Application.php',
        'public/index.php'   => 'public/index.php',
        'routes'             => 'folder routes/',
    ];

    foreach ($wajib as $rel => $label) {
        if (!file_exists($path . '/' . $rel)) {
            $kurang[] = $label;
        }
    }

    // Titik masuk harus benar-benar membangkitkan aplikasi Sakuci, bukan
    // sekadar berkas kosong bernama sama.
    $index = $path . '/public/index.php';
    if (is_file($index)) {
        $isi = (string) file_get_contents($index, false, null, 0, 4000);
        if (!str_contains($isi, 'SAKUCI_START') && !str_contains($isi, 'Sakuci\\Application')) {
            $kurang[] = 'public/index.php tidak memuat penanda Sakuci';
        }
    }

    return ['ok' => $kurang === [], 'kurang' => $kurang];
}

/**
 * Pesan yang ditampilkan ke siswa saat repo ditolak.
 *
 * Sengaja TIDAK merinci apa yang kurang. Daftar itu sama saja dengan memberi
 * petunjuk berkas apa yang perlu dipalsukan agar lolos. Rinciannya tetap
 * dicatat ke log server lewat catat_penolakan() supaya pengelola bisa
 * membantu siswa yang gagal karena alasan jujur, misalnya salah tempel URL.
 */
function pesan_bukan_sakuci(): string
{
    return "Repo ini bukan project Sakuci Framework, jadi tidak bisa di-hosting di sini.\n\n"
        . "Pastikan URL repo benar dan project dibuat dari Sakuci Framework.";
}

/** Mencatat alasan sebenarnya ke log server, bukan ke layar siswa. */
function catat_penolakan(string $gitUrl, array $kurang): void
{
    error_log(
        'Clone ditolak, bukan project Sakuci: ' . $gitUrl
        . ' -- tidak ditemukan: ' . implode('; ', $kurang)
    );
}

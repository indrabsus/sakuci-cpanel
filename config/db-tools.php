<?php
// Impor berkas SQL dan pengosongan tabel.
//
// Keduanya dijalankan memakai kredensial database SISWA, bukan kredensial
// admin MySQL. Wewenang user siswa sudah dibatasi GRANT hanya pada databasenya
// sendiri, sehingga berkas SQL yang berisi perintah nakal -- USE database
// lain, DROP DATABASE, membaca tabel milik panel -- akan ditolak MySQL sendiri.
// Itulah pembatas sebenarnya di sini, bukan pemeriksaan isi berkas.

const IMPOR_MAKS_BYTE = 8 * 1024 * 1024;   // 8 MB

/**
 * Menyambung sebagai pemilik database.
 *
 * @return array{ok:bool, conn?:mysqli, pesan?:string}
 */
function sambung_sebagai_siswa(array $d): array
{
    try {
        $c = new mysqli($d['db_host'], $d['username'], $d['password'], $d['db_name'], (int) $d['db_port']);
        $c->set_charset('utf8mb4');

        return ['ok' => true, 'conn' => $c];
    } catch (mysqli_sql_exception $e) {
        error_log('Sambungan database siswa gagal: ' . $e->getMessage());

        return ['ok' => false, 'pesan' => 'Tidak bisa menyambung ke database ini.'];
    }
}

/**
 * Menjalankan isi berkas SQL.
 *
 * Dipakai multi_query supaya MySQL sendiri yang memilah pernyataan. Memilahnya
 * di PHP dengan memecah pada tanda titik koma tampak sederhana tetapi salah:
 * titik koma juga muncul di dalam teks, komentar, dan definisi trigger.
 *
 * @return array{ok:bool, pesan:string, jumlah:int}
 */
function impor_sql(mysqli $c, string $sql): array
{
    $jumlah = 0;

    try {
        if (!$c->multi_query($sql)) {
            return ['ok' => false, 'pesan' => ringkas_galat($c->error), 'jumlah' => 0];
        }

        // Hasil tiap pernyataan harus dihabiskan; bila tidak, pernyataan
        // berikutnya tidak pernah dijalankan dan galatnya tidak terlihat.
        do {
            $jumlah++;
            if ($r = $c->store_result()) {
                $r->free();
            }
            if ($c->errno) {
                return ['ok' => false, 'pesan' => ringkas_galat($c->error), 'jumlah' => $jumlah];
            }
        } while ($c->more_results() && $c->next_result());

        // next_result() terakhir bisa membawa galat yang belum terbaca.
        if ($c->errno) {
            return ['ok' => false, 'pesan' => ringkas_galat($c->error), 'jumlah' => $jumlah];
        }
    } catch (mysqli_sql_exception $e) {
        return ['ok' => false, 'pesan' => ringkas_galat($e->getMessage()), 'jumlah' => $jumlah];
    }

    return ['ok' => true, 'pesan' => $jumlah . ' pernyataan dijalankan.', 'jumlah' => $jumlah];
}

/**
 * Mengosongkan seluruh tabel dan view di database.
 *
 * @return array{ok:bool, pesan:string, jumlah:int}
 */
function kosongkan_database(mysqli $c): array
{
    try {
        $tabel = [];
        $view  = [];

        $r = $c->query('SHOW FULL TABLES');
        while ($x = $r->fetch_row()) {
            // Kolom kedua berisi 'BASE TABLE' atau 'VIEW'.
            if ($x[1] === 'VIEW') {
                $view[] = $x[0];
            } else {
                $tabel[] = $x[0];
            }
        }

        if (!$tabel && !$view) {
            return ['ok' => true, 'pesan' => 'Database memang sudah kosong.', 'jumlah' => 0];
        }

        // Foreign key dimatikan sementara: tanpa itu, urutan penghapusan harus
        // mengikuti ketergantungan antar tabel dan bisa buntu pada rujukan
        // melingkar.
        $c->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($view as $v) {
            $c->query('DROP VIEW IF EXISTS `' . str_replace('`', '``', $v) . '`');
        }
        foreach ($tabel as $t) {
            $c->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
        }

        $c->query('SET FOREIGN_KEY_CHECKS = 1');

        $total = count($tabel) + count($view);

        return ['ok' => true, 'pesan' => $total . ' tabel dihapus.', 'jumlah' => $total];
    } catch (mysqli_sql_exception $e) {
        error_log('Gagal mengosongkan database: ' . $e->getMessage());

        return ['ok' => false, 'pesan' => ringkas_galat($e->getMessage()), 'jumlah' => 0];
    }
}

/**
 * Memendekkan pesan galat MySQL agar layak ditampilkan.
 *
 * Pesan aslinya kerap memuat potongan SQL yang panjang; yang dibutuhkan siswa
 * hanya inti masalahnya.
 */
function ringkas_galat(string $pesan): string
{
    $pesan = trim(preg_replace('/\s+/', ' ', $pesan));

    return strlen($pesan) > 200 ? substr($pesan, 0, 200) . '…' : $pesan;
}

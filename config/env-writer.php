<?php
// Menuliskan kredensial database ke berkas .env milik project.
//
// Menyambungkan project ke database adalah langkah yang paling sering salah
// ketik. Panel sudah tahu semua nilainya, jadi lebih baik ditulis langsung.

/**
 * Mengganti nilai beberapa kunci di berkas bergaya .env, mempertahankan baris
 * lain apa adanya.
 *
 * Kunci yang belum ada ditambahkan di akhir. Komentar, baris kosong, dan
 * pengaturan lain milik siswa tidak disentuh -- hanya kunci yang disebut yang
 * diubah.
 */
function set_env_values(string $isi, array $pasangan): string
{
    // Samakan akhir baris dulu agar pencocokan tidak meleset pada berkas CRLF.
    $isi = str_replace("\r\n", "\n", $isi);
    $baris = $isi === '' ? [] : explode("\n", $isi);

    foreach ($pasangan as $kunci => $nilai) {
        $ketemu = false;

        foreach ($baris as $i => $b) {
            // Cocokkan "KUNCI=" di awal baris, boleh berspasi, abaikan komentar.
            if (preg_match('/^\s*' . preg_quote($kunci, '/') . '\s*=/', $b)) {
                $baris[$i] = $kunci . '=' . $nilai;
                $ketemu = true;
                break;
            }
        }

        if (!$ketemu) {
            $baris[] = $kunci . '=' . $nilai;
        }
    }

    return rtrim(implode("\n", $baris), "\n") . "\n";
}

/**
 * Menulis kredensial database ke .env di dalam folder project.
 *
 * @return array{ok:bool, pesan:string}
 */
function write_db_env(string $projectPath, array $db): array
{
    $akar = realpath($projectPath);

    if ($akar === false || !is_dir($akar)) {
        return ['ok' => false, 'pesan' => 'Project belum di-clone, .env belum bisa diisi.'];
    }

    $env = $akar . DIRECTORY_SEPARATOR . '.env';
    $contoh = $akar . DIRECTORY_SEPARATOR . '.env.example';

    // Sakuci hanya menyertakan .env.example; .env dibuat dari situ agar
    // pengaturan lain seperti APP_NAME dan APP_TIMEZONE ikut terbawa.
    if (!is_file($env)) {
        $isi = is_file($contoh) ? (string) file_get_contents($contoh) : '';
    } else {
        $isi = (string) file_get_contents($env);
    }

    if (!is_writable(is_file($env) ? $env : $akar)) {
        return ['ok' => false, 'pesan' => 'Berkas .env tidak bisa ditulis. Periksa izin di server.'];
    }

    $baru = set_env_values($isi, [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST'       => $db['host'],
        'DB_PORT'       => $db['port'],
        'DB_DATABASE'   => $db['nama'],
        'DB_USERNAME'   => $db['user'],
        'DB_PASSWORD'   => $db['pass'],
    ]);

    if (file_put_contents($env, $baru) === false) {
        return ['ok' => false, 'pesan' => 'Gagal menulis .env.'];
    }

    // Berisi kredensial, jadi jangan terbaca pengguna lain di server.
    @chmod($env, 0640);

    return ['ok' => true, 'pesan' => '.env project sudah diisi, aplikasi langsung tersambung.'];
}

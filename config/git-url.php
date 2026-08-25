<?php
// Penormalan URL repo, dipakai untuk memastikan satu repo hanya dipakai satu
// project di seluruh panel.
//
// Membandingkan teks mentah tidak cukup: satu repo yang sama bisa ditulis
// dalam banyak bentuk, dan tiap bentuk akan lolos sebagai "repo berbeda".
// Yang disimpan untuk pembanding adalah bentuk bakunya, sementara URL asli
// tetap disimpan apa adanya untuk ditampilkan.

/**
 * Membakukan URL repo menjadi "host/pemilik/nama".
 *
 * Semua bentuk berikut menghasilkan nilai yang sama:
 *   https://github.com/Budi/Toko.git
 *   http://www.github.com/budi/toko/
 *   git@github.com:budi/toko.git
 *   https://github.com/BUDI/TOKO
 */
function normalize_git_url(string $url): string
{
    $u = strtolower(trim($url));

    // Buang skema: https://, http://, ssh://, git://
    $u = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $u);

    // Buang kredensial atau user SSH di depan host (git@, user:pass@).
    $potong = strpos($u, '/');
    $depan = $potong === false ? $u : substr($u, 0, $potong);
    if (str_contains($depan, '@')) {
        $u = substr($u, strrpos($depan, '@') + 1);
    }

    // Bentuk SSH "host:pemilik/nama" disamakan dengan "host/pemilik/nama".
    // Titik dua yang diikuti angka adalah nomor port, jadi tidak diubah.
    $u = preg_replace('#^([^/:]+):(?!\d)#', '$1/', $u);

    // www. tidak membedakan repo.
    $u = preg_replace('#^www\.#', '', $u);

    // Akhiran .git dan garis miring penutup bersifat opsional pada git.
    $u = rtrim($u, '/');
    $u = preg_replace('#\.git$#', '', $u);

    return rtrim($u, '/');
}

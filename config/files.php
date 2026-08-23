<?php
// Penjelajah berkas untuk satu project.
//
// Seluruh keamanan bertumpu pada satu hal: setiap path yang datang dari
// browser harus terbukti berada di dalam folder project, sesudah symlink dan
// ".." diselesaikan. Itulah tugas project_path().

const FILE_EDIT_MAX = 512 * 1024;   // 512 KB, batas berkas yang boleh disunting

/**
 * Mengubah path relatif dari browser menjadi path absolut di dalam project.
 *
 * Mengembalikan null bila hasilnya berada di luar folder project. realpath()
 * dipakai karena ia menyelesaikan "..", "." dan symlink sekaligus -- memeriksa
 * string mentah saja bisa ditembus dengan "a/../../etc/passwd" atau symlink
 * yang menunjuk keluar.
 */
function project_path(string $root, string $relative): ?string
{
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return null;
    }

    $relative = str_replace('\\', '/', trim($relative));
    $relative = ltrim($relative, '/');

    $target = $relative === '' ? $rootReal : $rootReal . DIRECTORY_SEPARATOR . $relative;
    $real   = realpath($target);

    if ($real === false) {
        return null;   // tidak ada
    }

    // Perbandingan memakai pemisah yang seragam agar konsisten di Windows.
    $rootCmp = rtrim(str_replace('\\', '/', $rootReal), '/');
    $realCmp = str_replace('\\', '/', $real);

    if ($realCmp !== $rootCmp && !str_starts_with($realCmp, $rootCmp . '/')) {
        return null;   // di luar project
    }

    return $real;
}

/** Path relatif terhadap akar project, untuk ditampilkan dan ditautkan. */
function relative_to_root(string $root, string $path): string
{
    $rootReal = str_replace('\\', '/', realpath($root) ?: $root);
    $pathCmp  = str_replace('\\', '/', $path);

    return ltrim(substr($pathCmp, strlen($rootReal)), '/');
}

/** Daftar isi direktori: folder dulu, lalu berkas, masing-masing urut nama. */
function list_directory(string $dir): array
{
    $items = [];

    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $items[] = [
            'name'  => $name,
            'dir'   => is_dir($full),
            'size'  => is_dir($full) ? 0 : (filesize($full) ?: 0),
            'mtime' => filemtime($full) ?: 0,
        ];
    }

    usort($items, function ($a, $b) {
        if ($a['dir'] !== $b['dir']) {
            return $a['dir'] ? -1 : 1;
        }

        return strcasecmp($a['name'], $b['name']);
    });

    return $items;
}

/** Menebak apakah berkas layak ditampilkan di editor teks. */
function is_editable(string $path): bool
{
    if (!is_file($path) || filesize($path) > FILE_EDIT_MAX) {
        return false;
    }

    $chunk = (string) file_get_contents($path, false, null, 0, 8000);
    if ($chunk === '') {
        return true;   // berkas kosong boleh disunting
    }

    // Byte NUL praktis hanya muncul pada berkas biner.
    return !str_contains($chunk, "\0");
}

/**
 * Nama berkas atau folder baru yang aman.
 *
 * Pemisah path ditolak supaya nama tidak bisa dipakai berpindah folder --
 * "a/../../x" hanya boleh terbentuk lewat navigasi, bukan lewat kotak nama.
 * Titik satu dan dua ditolak karena keduanya merujuk direktori, bukan nama.
 * Nama berawalan titik tetap diizinkan: siswa memang perlu membuat .env.
 */
function valid_filename(string $name): bool
{
    $name = trim($name);

    if ($name === '' || $name === '.' || $name === '..' || strlen($name) > 255) {
        return false;
    }

    return !preg_match('#[/\\\\\0]#', $name);
}

/**
 * Menghapus berkas atau folder beserta isinya.
 *
 * Symlink diperiksa lebih dulu dan dihapus sebagai berkas, bukan ditelusuri --
 * tanpa itu, symlink yang menunjuk ke luar project bisa membuat penghapusan
 * merambat ke folder lain di server.
 */
function delete_recursive(string $path): bool
{
    if (is_link($path) || is_file($path)) {
        return @unlink($path);
    }

    if (!is_dir($path)) {
        return false;
    }

    foreach (scandir($path) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (!delete_recursive($path . DIRECTORY_SEPARATOR . $name)) {
            return false;
        }
    }

    return @rmdir($path);
}

function human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1024 / 1024, 1) . ' MB';
}

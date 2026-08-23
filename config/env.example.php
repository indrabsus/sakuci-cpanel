<?php
// Salin file ini menjadi config/env.php lalu sesuaikan isinya.
// config/env.php sengaja diabaikan git -- jangan pernah di-commit.
return [
    'db_host' => 'localhost',
    'db_name' => 'sakuci_cpanel',
    'db_user' => 'sakuci_cpanel',
    'db_pass' => 'ISI_PASSWORD_DATABASE_DI_SINI',

    // Folder tempat project di-clone.
    //   Windows : 'C:/hosting/projects'
    //   Server  : '/www/wwwroot/cpanel.sakuci.id/projects'
    // Catatan: di aaPanel, path ini harus berada di dalam open_basedir situs.
    'projects_path' => __DIR__ . '/../projects',

    // true hanya untuk pengembangan lokal. Di server WAJIB false, kalau tidak
    // galat beserta path dan nama user database akan tampil ke pengunjung.
    'debug' => false,

    // Daftar IP yang boleh membuka panel. Kosongkan untuk mematikan
    // pembatasan (dipakai saat pengembangan lokal).
    //
    // Menerima alamat persis maupun rentang CIDR, IPv4 dan IPv6:
    //     '103.158.96.27'        satu alamat
    //     '103.158.96.0/24'      satu rentang
    //     '2404:c0:ab00::/48'    rentang IPv6
    //
    // Cek alamat Anda sekarang di https://ifconfig.me
    //
    // PERHATIAN: salah isi akan mengunci Anda sendiri dari panel. Pemulihannya
    // lewat SSH -- kosongkan kembali larik ini, tidak perlu restart apa pun.
    //
    // Kalau memakai IPv6, daftarkan alamat IPv4 DAN IPv6 Anda: browser bisa
    // memilih salah satunya, dan yang tidak terdaftar akan tertolak.
    'allowed_ips' => [],
];

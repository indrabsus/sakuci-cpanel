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
];

<?php
// Kerangka halaman panel.
//
// Sebelumnya tiap halaman menulis <head>, navigasi, dan gayanya sendiri.
// Akibatnya menu bisa berbeda antar halaman dan perubahan tampilan harus
// disalin ke banyak tempat. Semuanya kini satu sumber.

/** Ikon garis sederhana; emoji terbaca berbeda-beda antar sistem. */
function ikon(string $nama): string
{
    $d = [
        'grid'   => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'db'     => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
         . 'stroke-linecap="round" stroke-linejoin="round">' . ($d[$nama] ?? '') . '</svg>';
}

/**
 * Membuka halaman: <head>, sidebar, dan bilah judul.
 *
 * @param string $aktif  kunci menu yang sedang dibuka
 * @param array  $aksi   tombol di kanan judul: ['teks' => ..., 'href' => ...]
 */
function layout_start(string $judul, string $subjudul, string $aktif, array $user, array $aksi = []): void
{
    $menu = [
        'dashboard' => ['Dashboard',   'dashboard.php',   'grid'],
        'add'       => ['Tambah Project', 'add-project.php', 'plus'],
        'db'        => ['Database',    'databases.php',   'db'],
    ];
    if (is_admin($user)) {
        $menu['users'] = ['Pengguna', 'users.php', 'users'];
    }

    $v = fn(string $f) => htmlspecialchars($f) . '?v=' . @filemtime(__DIR__ . '/../assets/' . $f);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($judul); ?> &middot; Sakuci cPanel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;550;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/<?php echo $v('panel.css'); ?>">
<link rel="stylesheet" href="assets/<?php echo $v('git-actions.css'); ?>">
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="side-brand">
            <strong>Sakuci cPanel</strong>
            <span>Panel Hosting Siswa</span>
        </div>

        <nav class="side-nav">
            <?php foreach ($menu as $kunci => [$teks, $href, $ic]): ?>
                <a href="<?php echo $href; ?>" class="<?php echo $kunci === $aktif ? 'on' : ''; ?>">
                    <?php echo ikon($ic); ?><?php echo $teks; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="side-foot">
            <div class="side-user">
                <b><?php echo htmlspecialchars($user['username']); ?></b>
                <span><?php echo is_admin($user) ? 'Administrator' : 'Siswa'; ?></span>
            </div>
            <a href="../index.php?logout=1" title="Keluar"><?php echo ikon('out'); ?></a>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div>
                <h1><?php echo htmlspecialchars($judul); ?></h1>
                <?php if ($subjudul !== ''): ?><p><?php echo htmlspecialchars($subjudul); ?></p><?php endif; ?>
            </div>
            <?php if ($aksi): ?>
                <a class="btn" href="<?php echo htmlspecialchars($aksi['href']); ?>">
                    <?php echo ikon('plus'); ?><?php echo htmlspecialchars($aksi['teks']); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="wrap">
<?php
}

function layout_end(bool $pakaiSkrip = false): void
{
    ?>
        </div>

        <footer class="kaki">
            Sakuci cPanel &mdash; Created by Indra Batara, S.Pd., Gr.
        </footer>
    </div>
</div>
<?php if ($pakaiSkrip): ?>
<script src="assets/git-actions.js?v=<?php echo @filemtime(__DIR__ . '/../assets/git-actions.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
<?php
}

<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/jobs.php';
include '../config/files.php';

$user = require_login($conn);
$user_id = $user['id'];
$username = $user['username'];

$project_id = intval($_GET['project'] ?? 0);
$project = $project_id > 0 ? find_project($conn, $project_id, $user_id, is_admin($user)) : null;

if (!$project) {
    http_response_code(404);
    exit('Project tidak ditemukan.');
}

$root = $project['local_path'];
if (!is_dir($root)) {
    http_response_code(404);
    exit('Folder project belum ada. Jalankan Clone lebih dulu.');
}

$error = '';
$success = '';
$relative = (string) ($_GET['path'] ?? '');

// Semua aksi tulis memakai POST lalu dialihkan (POST-redirect-GET), supaya
// menekan refresh tidak mengulang penyimpanan, penghapusan, atau pembuatan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['action'] ?? 'simpan';
    $relative = (string) ($_POST['path'] ?? '');
    $kembaliKe = $relative;
    $pesan = '';

    if ($aksi === 'simpan') {
        $target = project_path($root, $relative);

        if ($target === null || !is_file($target)) {
            $pesan = 'err|Berkas tidak ditemukan.';
        } elseif (!is_editable($target)) {
            $pesan = 'err|Berkas ini tidak bisa disunting.';
        } elseif (!is_writable($target)) {
            $pesan = 'err|Berkas tidak bisa ditulis. Periksa kepemilikan berkas di server.';
        } else {
            // Browser mengirim akhir baris sebagai CRLF; disamakan ke LF supaya
            // berkas tidak jadi campur aduk saat dibuka di server Linux.
            $isi = str_replace("\r\n", "\n", (string) ($_POST['content'] ?? ''));
            $pesan = file_put_contents($target, $isi) === false
                ? 'err|Gagal menyimpan.'
                : 'ok|Tersimpan.';
        }
    }

    if ($aksi === 'hapus') {
        $target = project_path($root, $relative);
        $akar = realpath($root);

        if ($target === null) {
            $pesan = 'err|Berkas tidak ditemukan.';
        } elseif ($target === $akar) {
            // Tanpa penjagaan ini, folder project bisa terhapus seluruhnya
            // dari dalam file manager.
            $pesan = 'err|Folder utama project tidak bisa dihapus dari sini.';
        } else {
            $nama = basename($target);
            $kembaliKe = dirname($relative) === '.' ? '' : dirname($relative);

            $pesan = delete_recursive($target)
                ? 'ok|' . $nama . ' dihapus.'
                : 'err|Gagal menghapus ' . $nama . '. Periksa izin berkas di server.';
        }
    }

    if ($aksi === 'ganti_nama') {
        $target = project_path($root, $relative);
        $akar = realpath($root);
        $namaBaru = trim((string) ($_POST['nama'] ?? ''));

        if ($target === null) {
            $pesan = 'err|Berkas tidak ditemukan.';
        } elseif ($target === $akar) {
            $pesan = 'err|Folder utama project tidak bisa diganti nama.';
        } elseif (!valid_filename($namaBaru)) {
            $pesan = 'err|Nama tidak valid. Tidak boleh mengandung / atau \\, dan tidak boleh . atau ..';
        } else {
            // Nama baru selalu digabung ke folder induk yang sama. Karena
            // valid_filename() menolak pemisah path, hasilnya mustahil keluar
            // dari folder tempat berkas itu berada.
            $tujuan = dirname($target) . DIRECTORY_SEPARATOR . $namaBaru;
            $kembaliKe = dirname($relative) === '.' ? '' : dirname($relative);

            if (file_exists($tujuan)) {
                $pesan = 'err|' . $namaBaru . ' sudah ada.';
            } else {
                $pesan = @rename($target, $tujuan)
                    ? 'ok|Diganti nama menjadi ' . $namaBaru . '.'
                    : 'err|Gagal mengganti nama. Periksa izin berkas di server.';
            }
        }
    }

    if ($aksi === 'buat_berkas' || $aksi === 'buat_folder') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $indukPath = project_path($root, $relative);

        if ($indukPath === null || !is_dir($indukPath)) {
            $pesan = 'err|Folder tujuan tidak ditemukan.';
        } elseif (!valid_filename($nama)) {
            $pesan = 'err|Nama tidak valid. Tidak boleh mengandung garis miring, dan tidak boleh . atau ..';
        } else {
            $tujuan = $indukPath . DIRECTORY_SEPARATOR . $nama;

            if (file_exists($tujuan)) {
                $pesan = 'err|' . $nama . ' sudah ada.';
            } elseif (!is_writable($indukPath)) {
                $pesan = 'err|Folder ini tidak bisa ditulis. Periksa izin di server.';
            } elseif ($aksi === 'buat_folder') {
                $pesan = @mkdir($tujuan, 0755)
                    ? 'ok|Folder ' . $nama . ' dibuat.'
                    : 'err|Gagal membuat folder.';
            } else {
                $pesan = @file_put_contents($tujuan, '') !== false
                    ? 'ok|Berkas ' . $nama . ' dibuat.'
                    : 'err|Gagal membuat berkas.';
            }
        }
    }

    header('Location: files.php?project=' . $project_id
        . '&path=' . urlencode($kembaliKe)
        . '&pesan=' . urlencode($pesan));
    exit;
}

if (isset($_GET['pesan']) && str_contains((string) $_GET['pesan'], '|')) {
    [$jenis, $teks] = explode('|', (string) $_GET['pesan'], 2);
    $teks = htmlspecialchars($teks);
    if ($jenis === 'ok') {
        $success = '✅ ' . $teks;
    } else {
        $error = '❌ ' . $teks;
    }
}

$target = project_path($root, $relative);
if ($target === null) {
    $error = $error ?: '❌ Path tidak valid.';
    $target = realpath($root);
    $relative = '';
}

$isDir = is_dir($target);
$relative = relative_to_root($root, $target);

// Remah jejak untuk navigasi ke atas
$crumbs = [];
$walk = '';
foreach (array_filter(explode('/', $relative)) as $bagian) {
    $walk = $walk === '' ? $bagian : $walk . '/' . $bagian;
    $crumbs[] = ['name' => $bagian, 'path' => $walk];
}

$items = $isDir ? list_directory($target) : [];
$editable = !$isDir && is_editable($target);
$content = $editable ? (string) file_get_contents($target) : '';
$webUrl = SITE_DOMAIN !== '' ? 'http://' . basename($root) . '.' . SITE_DOMAIN : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - <?php echo htmlspecialchars($project['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; color: #2d3748; }
        header { background: #667eea; color: white; padding: 1.25rem 1.5rem; }
        header h1 { font-size: 1.25rem; }
        header p { font-size: .85rem; opacity: .9; }
        .container { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        .bar { background: white; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .bar a { color: #667eea; text-decoration: none; font-weight: 500; }
        .bar a:hover { text-decoration: underline; }
        .crumbs { font-size: .9rem; color: #718096; }
        .crumbs a { color: #667eea; text-decoration: none; }
        .panel { background: white; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .6rem 1rem; border-bottom: 1px solid #edf2f7; font-size: .92rem; }
        th { background: #f7fafc; color: #4a5568; font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        tr:last-child td { border-bottom: none; }
        td a { color: #2d3748; text-decoration: none; }
        td a:hover { color: #667eea; }
        .muted { color: #a0aec0; }
        .num { text-align: right; white-space: nowrap; }
        textarea { width: 100%; min-height: 62vh; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 6px; font-family: ui-monospace, Consolas, monospace; font-size: .88rem; line-height: 1.5; resize: vertical; }
        .btn { display: inline-block; padding: .6rem 1.2rem; background: #667eea; color: white; border: none; border-radius: 5px; font-size: .95rem; font-weight: 500; cursor: pointer; text-decoration: none; font-family: inherit; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #718096; }
        .alert { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: .92rem; }
        .alert-ok { background: #f0fff4; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-err { background: #fff5f5; color: #822727; border: 1px solid #feb2b2; }
        .editor-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: .75rem; flex-wrap: wrap; }
        .fname { font-family: ui-monospace, Consolas, monospace; font-weight: 600; }
        .buat-bar { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .inline-form { display: flex; gap: .4rem; background: white; padding: .6rem; border-radius: 8px; }
        .inline-form input[type=text] { padding: .45rem .6rem; border: 1px solid #e2e8f0; border-radius: 5px; font-size: .88rem; font-family: inherit; width: 11rem; }
        .btn-kecil { padding: .45rem .9rem; font-size: .85rem; }
        form.inline { display: inline; }
        .btn-ikon { background: none; border: none; cursor: pointer; font-size: .95rem; opacity: .35; padding: .2rem .35rem; border-radius: 4px; }
        .btn-ikon:hover { opacity: 1; background: #edf2f7; }
        .btn-hapus:hover { background: #fed7d7; }
    </style>
</head>
<body>
<header>
    <h1>📁 <?php echo htmlspecialchars($project['name']); ?></h1>
    <p>File Manager</p>
</header>

<div class="container">
    <div class="bar">
        <a href="dashboard.php">← Dashboard</a>
        <?php if ($webUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($webUrl); ?>" target="_blank" rel="noopener noreferrer">🌐 Buka Web</a>
        <?php endif; ?>
        <span class="crumbs">
            <a href="?project=<?php echo $project_id; ?>">root</a>
            <?php foreach ($crumbs as $i => $c): ?>
                / <?php if ($i === count($crumbs) - 1 && !$isDir): ?>
                        <span class="fname"><?php echo htmlspecialchars($c['name']); ?></span>
                   <?php else: ?>
                        <a href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode($c['path']); ?>"><?php echo htmlspecialchars($c['name']); ?></a>
                   <?php endif; ?>
            <?php endforeach; ?>
        </span>
    </div>

    <?php if ($error): ?><div class="alert alert-err"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-ok"><?php echo $success; ?></div><?php endif; ?>

    <?php if ($isDir): ?>
        <div class="buat-bar">
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="buat_berkas">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
                <input type="text" name="nama" placeholder="nama-berkas.php" required>
                <button type="submit" class="btn btn-kecil">📄 Buat Berkas</button>
            </form>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="buat_folder">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
                <input type="text" name="nama" placeholder="nama-folder" required>
                <button type="submit" class="btn btn-kecil btn-secondary">📁 Buat Folder</button>
            </form>
        </div>

        <div class="panel">
            <table>
                <thead><tr><th>Nama</th><th class="num">Ukuran</th><th class="num">Diubah</th><th></th></tr></thead>
                <tbody>
                <?php if ($relative !== ''): ?>
                    <tr><td colspan="4">
                        <a href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">📂 ..</a>
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($items as $it): ?>
                    <?php $p = $relative === '' ? $it['name'] : $relative . '/' . $it['name']; ?>
                    <tr>
                        <td>
                            <a href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode($p); ?>">
                                <?php echo $it['dir'] ? '📁' : '📄'; ?>
                                <?php echo htmlspecialchars($it['name']); ?>
                            </a>
                        </td>
                        <td class="num muted"><?php echo $it['dir'] ? '—' : human_size($it['size']); ?></td>
                        <td class="num muted"><?php echo $it['mtime'] ? date('d/m/y H:i', $it['mtime']) : '—'; ?></td>
                        <td class="num">
                            <form method="POST" class="inline" onsubmit="
                                    var b = prompt('Nama baru:', <?php echo htmlspecialchars(json_encode($it['name']), ENT_QUOTES); ?>);
                                    if (b === null || b.trim() === '') return false;
                                    this.nama.value = b.trim();
                                    return true;">
                                <input type="hidden" name="action" value="ganti_nama">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>">
                                <input type="hidden" name="nama" value="">
                                <button type="submit" class="btn-ikon" title="Ganti nama">✏️</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm(<?php
                                echo htmlspecialchars(json_encode(
                                    ($it['dir']
                                        ? "Hapus folder \"{$it['name']}\" BESERTA SELURUH ISINYA?"
                                        : "Hapus berkas \"{$it['name']}\"?")
                                    . "

Tidak bisa dikembalikan."
                                ), ENT_QUOTES);
                            ?>);">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>">
                                <button type="submit" class="btn-ikon btn-hapus" title="Hapus">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$items): ?>
                    <tr><td colspan="4" class="muted">Folder kosong.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($editable): ?>
        <form method="POST">
            <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
            <div class="editor-head">
                <span class="fname"><?php echo htmlspecialchars(basename($target)); ?></span>
                <span>
                    <a class="btn btn-secondary" href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">Kembali</a>
                    <button type="submit" class="btn">💾 Simpan</button>
                </span>
            </div>
            <textarea name="content" spellcheck="false"><?php echo htmlspecialchars($content); ?></textarea>
        </form>

    <?php else: ?>
        <div class="panel" style="padding:2rem">
            <p class="fname"><?php echo htmlspecialchars(basename($target)); ?></p>
            <p class="muted" style="margin-top:.5rem">
                Berkas ini tidak bisa disunting di sini — kemungkinan berupa gambar,
                arsip, atau berukuran lebih dari <?php echo human_size(FILE_EDIT_MAX); ?>.
            </p>
            <p style="margin-top:1rem">
                <a class="btn btn-secondary" href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">Kembali</a>
            </p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

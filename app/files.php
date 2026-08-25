<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/jobs.php';
include '../config/files.php';
include 'partials/layout.php';

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
        $success = $teks;
    } else {
        $error = $teks;
    }
}

$target = project_path($root, $relative);
if ($target === null) {
    $error = $error ?: 'Path tidak valid.';
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

$jejak = 'root' . ($relative !== '' ? ' / ' . $relative : '');

layout_start(
    $project['name'],
    'Berkas project' . ($webUrl !== '' ? '' : ''),
    'dashboard',
    $user
);
?>

<div class="card">
    <div class="card-h">
        <div>
            <h2 class="mono" style="font-weight:550"><?php echo htmlspecialchars($jejak); ?></h2>
            <p>
                <a href="dashboard.php">Kembali ke Dashboard</a>
                <?php if ($webUrl !== ''): ?>
                    &middot; <a href="<?php echo htmlspecialchars($webUrl); ?>" target="_blank" rel="noopener noreferrer">Buka Web</a>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($crumbs): ?>
            <a class="btn btn-2 btn-sm"
               href="?project=<?php echo $project_id; ?>">Ke akar project</a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?><div style="padding:1rem 1.25rem 0"><div class="note note-err" style="margin:0"><?php echo $error; ?></div></div><?php endif; ?>
    <?php if ($success): ?><div style="padding:1rem 1.25rem 0"><div class="note note-ok" style="margin:0"><?php echo $success; ?></div></div><?php endif; ?>

    <?php if ($isDir): ?>
        <div class="card-b" style="border-bottom:1px solid var(--line-soft); display:flex; gap:1rem; flex-wrap:wrap">
            <form method="POST" style="display:flex; gap:.45rem">
                <input type="hidden" name="action" value="buat_berkas">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
                <input type="text" name="nama" placeholder="berkas.php" required style="width:12rem">
                <button type="submit" class="btn btn-2 btn-sm">Buat Berkas</button>
            </form>
            <form method="POST" style="display:flex; gap:.45rem">
                <input type="hidden" name="action" value="buat_folder">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
                <input type="text" name="nama" placeholder="folder" required style="width:12rem">
                <button type="submit" class="btn btn-2 btn-sm">Buat Folder</button>
            </form>
        </div>

        <div class="card-b flush">
            <table>
                <thead><tr><th>Nama</th><th class="num">Ukuran</th><th class="num">Diubah</th><th></th></tr></thead>
                <tbody>
                <?php if ($relative !== ''): ?>
                    <tr><td colspan="4">
                        <a href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">..</a>
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($items as $it): ?>
                    <?php $p = $relative === '' ? $it['name'] : $relative . '/' . $it['name']; ?>
                    <tr>
                        <td>
                            <a href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode($p); ?>">
                                <?php if ($it['dir']): ?><strong><?php endif; ?>
                                <?php echo htmlspecialchars($it['name']); ?><?php echo $it['dir'] ? '/' : ''; ?>
                                <?php if ($it['dir']): ?></strong><?php endif; ?>
                            </a>
                        </td>
                        <td class="num dim"><?php echo $it['dir'] ? '—' : human_size($it['size']); ?></td>
                        <td class="num dim"><?php echo $it['mtime'] ? date('d/m/y H:i', $it['mtime']) : '—'; ?></td>
                        <td class="num">
                            <form method="POST" style="display:inline" onsubmit="
                                    var b = prompt('Nama baru:', <?php echo htmlspecialchars(json_encode($it['name']), ENT_QUOTES); ?>);
                                    if (b === null || b.trim() === '') return false;
                                    this.nama.value = b.trim();
                                    return true;">
                                <input type="hidden" name="action" value="ganti_nama">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>">
                                <input type="hidden" name="nama" value="">
                                <button type="submit" class="btn btn-2 btn-sm">Ganti nama</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm(<?php
                                echo htmlspecialchars(json_encode(
                                    ($it['dir']
                                        ? 'Hapus folder "' . $it['name'] . '" BESERTA SELURUH ISINYA?'
                                        : 'Hapus berkas "' . $it['name'] . '"?')
                                    . ' Tidak bisa dikembalikan.'
                                ), ENT_QUOTES);
                            ?>);">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$items): ?>
                    <tr><td colspan="4" class="dim" style="padding:1.6rem 1.25rem; text-align:center">Folder kosong.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($editable): ?>
        <form method="POST" class="card-b">
            <input type="hidden" name="path" value="<?php echo htmlspecialchars($relative, ENT_QUOTES); ?>">
            <textarea name="content" spellcheck="false"
                      style="width:100%; min-height:58vh; padding:.9rem; border:1px solid var(--line); border-radius:var(--r); font-family:'SFMono-Regular',ui-monospace,Consolas,monospace; font-size:.82rem; line-height:1.6; resize:vertical"><?php echo htmlspecialchars($content); ?></textarea>
            <div style="margin-top:.8rem; display:flex; gap:.45rem">
                <button type="submit" class="btn">Simpan</button>
                <a class="btn btn-2" href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">Kembali</a>
            </div>
        </form>

    <?php else: ?>
        <div class="empty">
            <p class="mono"><?php echo htmlspecialchars(basename($target)); ?></p>
            <p style="margin-top:.4rem">Berkas ini tidak bisa disunting di sini &mdash; kemungkinan berupa
               gambar, arsip, atau berukuran lebih dari <?php echo human_size(FILE_EDIT_MAX); ?>.</p>
            <a class="btn btn-2" href="?project=<?php echo $project_id; ?>&path=<?php echo urlencode(dirname($relative) === '.' ? '' : dirname($relative)); ?>">Kembali</a>
        </div>
    <?php endif; ?>
</div>

<?php layout_end(); ?>

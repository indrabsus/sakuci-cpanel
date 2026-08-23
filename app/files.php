<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/jobs.php';
include '../config/files.php';

$user = require_login($conn);
$user_id = $user['id'];
$username = $user['username'];

$project_id = intval($_GET['project'] ?? 0);
$project = $project_id > 0 ? find_project($conn, $project_id, $user_id) : null;

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

// Simpan suntingan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relative = (string) ($_POST['path'] ?? '');
    $target = project_path($root, $relative);

    if ($target === null || !is_file($target)) {
        $error = '❌ Berkas tidak ditemukan.';
    } elseif (!is_editable($target)) {
        $error = '❌ Berkas ini tidak bisa disunting.';
    } elseif (!is_writable($target)) {
        $error = '❌ Berkas tidak bisa ditulis. Periksa kepemilikan berkas di server.';
    } else {
        // Normalkan CRLF supaya berkas tetap rapi di server Linux.
        $isi = str_replace("\r\n", "\n", (string) ($_POST['content'] ?? ''));

        if (file_put_contents($target, $isi) === false) {
            $error = '❌ Gagal menyimpan.';
        } else {
            $success = '✅ Tersimpan ' . date('H:i:s');
        }
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
        <div class="panel">
            <table>
                <thead><tr><th>Nama</th><th class="num">Ukuran</th><th class="num">Diubah</th></tr></thead>
                <tbody>
                <?php if ($relative !== ''): ?>
                    <tr><td colspan="3">
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
                    </tr>
                <?php endforeach; ?>

                <?php if (!$items): ?>
                    <tr><td colspan="3" class="muted">Folder kosong.</td></tr>
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

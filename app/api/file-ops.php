<?php
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/auth.php';
include __DIR__ . '/../../config/jobs.php';
include __DIR__ . '/../../config/files.php';

header('Content-Type: application/json');

$me = require_api_login($conn);
$user_id = $me['id'];
$isAdmin = is_admin($me);

$project_id = intval($_REQUEST['project_id'] ?? 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id, $isAdmin);
if (!$project) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$root = realpath($project['local_path']);
if (!$root || !is_dir($root)) {
    http_response_code(404);
    echo json_encode(['error' => 'Folder project belum ada. Jalankan Clone lebih dulu.']);
    exit;
}

$hasToken = !empty($project['github_token']);
$action = $_REQUEST['action'] ?? 'tree';

/** Membaca pohon direktori secara rekursif (mengabaikan .git) */
function get_dir_tree(string $dir, string $rootDir, int $maxDepth = 7, int $currentDepth = 0): array
{
    if ($currentDepth >= $maxDepth) {
        return [];
    }

    $entries = @scandir($dir);
    if ($entries === false) {
        return [];
    }

    $folders = [];
    $files = [];

    foreach ($entries as $item) {
        if ($item === '.' || $item === '..' || $item === '.git') {
            continue;
        }

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $relPath = ltrim(substr($fullPath, strlen($rootDir)), '/\\');
        $relPath = str_replace('\\', '/', $relPath);
        $isDir = is_dir($fullPath);

        $node = [
            'name'  => $item,
            'path'  => $relPath,
            'isDir' => $isDir,
        ];

        if ($isDir) {
            $node['children'] = get_dir_tree($fullPath, $rootDir, $maxDepth, $currentDepth + 1);
            $folders[] = $node;
        } else {
            $node['size'] = @filesize($fullPath) ?: 0;
            $node['mtime'] = @filemtime($fullPath) ?: 0;
            $node['isEnv'] = is_env_file($item);
            $files[] = $node;
        }
    }

    // Urutkan alfabetis: folder dulu, baru file
    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return array_merge($folders, $files);
}

// ---------------- 1. Mengambil Pohon Direktori ---------------- //
if ($action === 'tree') {
    $tree = get_dir_tree($root, $root);
    echo json_encode([
        'status' => 'ok',
        'project' => [
            'id' => (int) $project['id'],
            'name' => $project['name'],
            'branch' => $project['git_branch'] ?: 'main',
            'hasToken' => $hasToken,
        ],
        'tree' => $tree,
    ]);
    exit;
}

// ---------------- 2. Membaca Isi Berkas ---------------- //
if ($action === 'read') {
    $relPath = trim((string) ($_REQUEST['path'] ?? ''));
    $target = project_path($root, $relPath);

    if (!$target || !is_file($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'Berkas tidak ditemukan: ' . $relPath]);
        exit;
    }

    $size = filesize($target);
    if ($size > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Berkas terlalu besar untuk dibuka di editor (' . human_size($size) . ').']);
        exit;
    }

    $isEnv = is_env_file(basename($target));
    $canEdit = $isAdmin || $hasToken || $isEnv;
    $content = (string) file_get_contents($target);

    echo json_encode([
        'status'  => 'ok',
        'path'    => $relPath,
        'name'    => basename($target),
        'content' => $content,
        'size'    => $size,
        'isEnv'   => $isEnv,
        'canEdit' => $canEdit,
    ]);
    exit;
}

// ---------------- 3. Menyimpan Isi Berkas ---------------- //
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $relPath = trim((string) ($_REQUEST['path'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');
    $target = project_path($root, $relPath);

    if (!$target || !is_file($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'Berkas tidak ditemukan.']);
        exit;
    }

    $isEnv = is_env_file(basename($target));
    $canEdit = $isAdmin || $hasToken || $isEnv;

    if (!$canEdit) {
        http_response_code(403);
        echo json_encode(['error' => 'Berkas bersifat Read-Only. Hubungkan GitHub PAT di Dashboard untuk mengaktifkan pengeditan & push.']);
        exit;
    }

    if (!is_writable($target)) {
        http_response_code(500);
        echo json_encode(['error' => 'Berkas tidak dapat ditulis di server. Periksa izin kepemilikan.']);
        exit;
    }

    $content = str_replace("\r\n", "\n", $content);
    if (file_put_contents($target, $content) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menulis berkas ke disk.']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Berkas tersimpan.',
        'size' => filesize($target),
        'mtime' => filemtime($target),
    ]);
    exit;
}

// ---------------- 4. Membuat Berkas Baru ---------------- //
if ($action === 'create_file') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $parentPath = trim((string) ($_POST['parent_path'] ?? ''));
    $fileName = trim((string) ($_POST['name'] ?? ''));

    if (!valid_filename($fileName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama berkas tidak valid.']);
        exit;
    }

    $parentTarget = project_path($root, $parentPath);
    if (!$parentTarget || !is_dir($parentTarget)) {
        http_response_code(400);
        echo json_encode(['error' => 'Folder tujuan tidak ditemukan.']);
        exit;
    }

    $newFile = $parentTarget . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($newFile)) {
        http_response_code(400);
        echo json_encode(['error' => 'Berkas dengan nama tersebut sudah ada.']);
        exit;
    }

    if (file_put_contents($newFile, '') === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal membuat berkas di server.']);
        exit;
    }

    $rel = ltrim(substr($newFile, strlen($root)), '/\\');
    echo json_encode(['status' => 'ok', 'path' => str_replace('\\', '/', $rel)]);
    exit;
}

// ---------------- 5. Membuat Folder Baru ---------------- //
if ($action === 'create_folder') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $parentPath = trim((string) ($_POST['parent_path'] ?? ''));
    $folderName = trim((string) ($_POST['name'] ?? ''));

    if (!valid_filename($folderName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama folder tidak valid.']);
        exit;
    }

    $parentTarget = project_path($root, $parentPath);
    if (!$parentTarget || !is_dir($parentTarget)) {
        http_response_code(400);
        echo json_encode(['error' => 'Folder tujuan tidak ditemukan.']);
        exit;
    }

    $newDir = $parentTarget . DIRECTORY_SEPARATOR . $folderName;
    if (file_exists($newDir)) {
        http_response_code(400);
        echo json_encode(['error' => 'Folder dengan nama tersebut sudah ada.']);
        exit;
    }

    if (!@mkdir($newDir, 0755)) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal membuat folder di server.']);
        exit;
    }

    $rel = ltrim(substr($newDir, strlen($root)), '/\\');
    echo json_encode(['status' => 'ok', 'path' => str_replace('\\', '/', $rel)]);
    exit;
}

// ---------------- 6. Mengganti Nama (Rename) ---------------- //
if ($action === 'rename') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $relPath = trim((string) ($_REQUEST['path'] ?? ''));
    $newName = trim((string) ($_POST['new_name'] ?? ''));

    if (!valid_filename($newName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama baru tidak valid.']);
        exit;
    }

    $target = project_path($root, $relPath);
    if (!$target || !file_exists($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'Berkas/folder tidak ditemukan.']);
        exit;
    }

    if ($target === $root) {
        http_response_code(400);
        echo json_encode(['error' => 'Folder utama project tidak bisa diganti nama.']);
        exit;
    }

    $newTarget = dirname($target) . DIRECTORY_SEPARATOR . $newName;
    if (file_exists($newTarget)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama tersebut sudah dipakai.']);
        exit;
    }

    if (!@rename($target, $newTarget)) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal mengganti nama di server.']);
        exit;
    }

    $rel = ltrim(substr($newTarget, strlen($root)), '/\\');
    echo json_encode(['status' => 'ok', 'newPath' => str_replace('\\', '/', $rel)]);
    exit;
}

// ---------------- 7. Menghapus Berkas atau Folder ---------------- //
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $relPath = trim((string) ($_REQUEST['path'] ?? ''));
    $target = project_path($root, $relPath);

    if (!$target || !file_exists($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'Berkas/folder tidak ditemukan.']);
        exit;
    }

    if ($target === $root) {
        http_response_code(400);
        echo json_encode(['error' => 'Folder utama project tidak bisa dihapus dari sini.']);
        exit;
    }

    if (!delete_recursive($target)) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menghapus berkas/folder. Periksa izin di server.']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'message' => 'Berhasil dihapus.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Aksi tidak dikenal: ' . $action]);

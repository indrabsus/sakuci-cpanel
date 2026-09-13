<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';
include '../../config/files.php';

header('Content-Type: application/json');

// Hanya POST: penghapusan tidak boleh terjadi karena tautan yang tidak sengaja
// diklik, di-prefetch browser, atau dimuat sebagai gambar.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$me = require_api_login($conn);
$user_id = $me['id'];
$project_id = intval($_POST['project_id'] ?? 0);

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id, is_admin($me));
if (!$project) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

if (active_job($conn, $project_id)) {
    http_response_code(409);
    echo json_encode([
        'error' => 'Masih ada pekerjaan git berjalan untuk project ini. Tunggu sampai selesai.',
    ]);
    exit;
}

// Memakai user_id dari baris project, bukan dari penghapus: admin boleh
// menghapus milik siswa mana pun.
$owner = (int) $project['user_id'];
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $owner);
$stmt->execute();

// Folder hasil clone ikut dibuang, tetapi hanya setelah terbukti berada di
// dalam PROJECTS_PATH. Tanpa pembuktian itu, satu baris local_path yang keliru
// bisa membuat folder lain di server ikut terhapus.
//
// Baris database sengaja dihapus lebih dulu: bila penghapusan folder gagal,
// project tetap hilang dari panel dan siswa tidak terjebak pada baris yang
// tidak bisa dibuang.
$path = $project['local_path'];
$folderTerhapus = false;
$catatan = 'Folder tidak ditemukan di server.';

if (is_dir($path)) {
    if (!path_inside(PROJECTS_PATH, $path)) {
        error_log('Menolak menghapus folder di luar PROJECTS_PATH: ' . $path);
        $catatan = 'Folder tidak dihapus karena berada di luar folder project.';
    } elseif (delete_recursive($path)) {
        $folderTerhapus = true;
        $catatan = 'Folder beserta isinya ikut dihapus.';
    } else {
        error_log('Gagal menghapus folder project: ' . $path);
        $catatan = 'Folder gagal dihapus. Periksa izin berkas di server.';
    }
}

echo json_encode([
    'status'          => 'deleted',
    'message'         => 'Project dihapus.',
    'folder_terhapus' => $folderTerhapus,
    'note'            => $catatan,
]);

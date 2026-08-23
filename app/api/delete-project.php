<?php
include '../../config/config.php';
include '../../config/auth.php';
include '../../config/jobs.php';

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

// Folder hasil clone sengaja TIDAK dihapus. Menghapus direktori berdasarkan
// nilai dari database terlalu berisiko bila local_path pernah salah isi, dan
// project lain bisa saja menunjuk folder yang sama. Berkasnya dibuang manual.
// Memakai user_id dari baris project, bukan dari penghapus: admin boleh
// menghapus milik siswa mana pun.
$owner = (int) $project['user_id'];
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $owner);
$stmt->execute();

echo json_encode([
    'status'     => 'deleted',
    'message'    => 'Project dihapus dari daftar.',
    'local_path' => $project['local_path'],
    'note'       => 'Folder di server tidak ikut dihapus.',
]);

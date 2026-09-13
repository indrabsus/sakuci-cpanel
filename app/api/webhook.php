<?php
// Endpoint Webhook Auto-Deploy ala Vercel untuk GitHub.
// Dipanggil otomatis oleh GitHub saat ada event 'push'.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/jobs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Hanya menerima request POST']);
    exit;
}

$project_id = intval($_GET['id'] ?? $_GET['project_id'] ?? 0);
$secret = trim((string) ($_GET['secret'] ?? ''));

if ($project_id <= 0 || $secret === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter id dan secret wajib diisi']);
    exit;
}

// Validasi secret project
$stmt = $conn->prepare(
    "SELECT id, user_id, name, git_branch, webhook_secret, local_path
       FROM projects
      WHERE id = ? AND status = 'active'"
);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project || empty($project['webhook_secret']) || !hash_equals($project['webhook_secret'], $secret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak: secret webhook tidak valid']);
    exit;
}

// Cek event dari GitHub
$event = strtolower($_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'push');

if ($event === 'ping') {
    echo json_encode([
        'status'  => 'ok',
        'message' => 'Pong! Webhook Sakuci cPanel terhubung dengan repositori GitHub.',
        'project' => $project['name'],
    ]);
    exit;
}

// Baca payload GitHub
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) && isset($_POST['payload'])) {
    $payload = json_decode((string) $_POST['payload'], true);
}

// Jika ada info ref (branch), periksa apakah cocok dengan branch project
if (is_array($payload) && !empty($payload['ref'])) {
    $pushedBranch = str_replace('refs/heads/', '', (string) $payload['ref']);
    $targetBranch = $project['git_branch'] ?: 'main';

    if ($pushedBranch !== $targetBranch) {
        echo json_encode([
            'status'  => 'ignored',
            'message' => "Push ke branch '$pushedBranch' diabaikan karena project memantau branch '$targetBranch'.",
        ]);
        exit;
    }
}

// Masukkan job pull ke antrean
$result = queue_job($conn, $project_id, (int) $project['user_id'], 'pull');

http_response_code(200);
echo json_encode([
    'status'  => 'queued',
    'message' => 'Commit GitHub terdeteksi. Auto-deploy (Pull) telah dimasukkan ke antrean.',
    'job'     => job_payload($result['job']),
]);

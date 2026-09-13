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
    echo json_encode(['status' => 'error', 'error' => 'project_id required']);
    exit;
}

$project = find_project($conn, $project_id, $user_id, $isAdmin);
if (!$project) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Project tidak ditemukan atau Anda tidak memiliki akses']);
    exit;
}

$root = realpath($project['local_path']);
if (!$root || !is_dir($root)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Folder project belum ada. Jalankan Clone lebih dulu.']);
    exit;
}

$cliPath = $root . '/sakuci';
if (!file_exists($cliPath)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Berkas CLI "sakuci" tidak ditemukan pada project ini.']);
    exit;
}

// Temukan binary PHP yang aktif
$phpBin = defined('PHP_BINARY') && is_executable(PHP_BINARY) ? PHP_BINARY : '/usr/bin/php';

/**
 * Konversi kode warna ANSI terminal (\033[...m) menjadi HTML berwarna
 */
function ansi_to_html(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $replacements = [
        '/\033\[0m/'  => '</span>',
        '/\033\[1m/'  => '<span style="font-weight:bold;color:#f3f4f6">',
        '/\033\[31m/' => '<span style="color:#ef4444;font-weight:600">',
        '/\033\[32m/' => '<span style="color:#10b981;font-weight:600">',
        '/\033\[33m/' => '<span style="color:#f59e0b;font-weight:550">',
        '/\033\[36m/' => '<span style="color:#06b6d4;font-weight:550">',
        '/\033\[90m/' => '<span style="color:#9ca3af">',
        '/\033\[[0-9;]*m/' => '', // Hapus kode ANSI lainnya
    ];

    $html = preg_replace(array_keys($replacements), array_values($replacements), $html);
    return $html;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$output = [];
$returnCode = 0;
$createdFile = null;

switch ($action) {
    case 'make_controller':
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Nama Controller wajib diisi. Contoh: UserController']);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_\/]+$/', $name)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Nama Controller hanya boleh memuat huruf, angka, dan garis miring.']);
            exit;
        }

        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci make:controller " . escapeshellarg($name) . " 2>&1";
        exec($cmd, $output, $returnCode);

        // Tebak lokasi berkas yang dibuat
        $ctrlName = basename($name);
        if (!str_ends_with($ctrlName, 'Controller')) {
            $ctrlName .= 'Controller';
        }
        $createdFile = 'app/Controllers/' . $ctrlName . '.php';
        break;

    case 'make_model':
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Nama Model wajib diisi. Contoh: User atau Product']);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Nama Model hanya boleh memuat huruf, angka, dan garis bawah.']);
            exit;
        }

        $withMigration = !empty($_POST['with_migration']);
        $opt = $withMigration ? ' -m' : '';

        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci make:model " . escapeshellarg($name) . $opt . " 2>&1";
        exec($cmd, $output, $returnCode);

        $createdFile = 'app/Models/' . basename($name) . '.php';
        break;

    case 'migrate':
        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci migrate 2>&1";
        exec($cmd, $output, $returnCode);
        break;

    case 'migrate_fresh':
        // Wajib verifikasi PIN akun cPanel siswa
        $pin = trim($_POST['pin'] ?? '');
        if ($pin === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error'  => 'PIN keamanan wajib diisi untuk menjalankan migrate:fresh (Hapus Semua Data).'
            ]);
            exit;
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();

        if (!$u || !password_verify($pin, $u['password'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error'  => 'PIN keamanan salah! Aksi migrate:fresh dibatalkan demi keamanan data Anda.'
            ]);
            exit;
        }

        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci migrate:fresh 2>&1";
        exec($cmd, $output, $returnCode);
        break;

    case 'db_check':
        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci db:check 2>&1";
        exec($cmd, $output, $returnCode);
        break;

    case 'route_list':
        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci route:list 2>&1";
        exec($cmd, $output, $returnCode);
        break;

    case 'view_clear':
        $cmd = "cd " . escapeshellarg($root) . " && " . escapeshellarg($phpBin) . " sakuci view:clear 2>&1";
        exec($cmd, $output, $returnCode);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Aksi tidak dikenali: ' . htmlspecialchars($action)]);
        exit;
}

$rawText = implode("\n", $output);
$htmlText = ansi_to_html($rawText);

echo json_encode([
    'status'       => $returnCode === 0 ? 'success' : 'failed',
    'return_code'  => $returnCode,
    'output_raw'   => $rawText,
    'output_html'  => $htmlText,
    'created_file' => $createdFile,
    'action'       => $action
]);

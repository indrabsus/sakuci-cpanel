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

if (!is_dir($root . '/.git')) {
    http_response_code(400);
    echo json_encode(['error' => 'Bukan repositori Git']);
    exit;
}

$action = $_REQUEST['action'] ?? 'status';

function run_git(string $repoPath, string $cmd): array
{
    $gitBin = '/usr/bin/git';
    $fullCmd = "cd " . escapeshellarg($repoPath) . " && $gitBin -c safe.directory=* $cmd 2>&1";
    $output = [];
    $retCode = 0;
    exec($fullCmd, $output, $retCode);
    return [implode("\n", $output), $retCode];
}

// 1. Status Perubahan Git
if ($action === 'status') {
    [$statusOut, $code] = run_git($root, 'status --porcelain -uall');
    [$branchOut, $bCode] = run_git($root, 'branch --show-current');

    $branch = trim($branchOut) ?: ($project['git_branch'] ?: 'main');
    $files = [];

    if ($code === 0 && trim($statusOut) !== '') {
        $lines = explode("\n", trim($statusOut));
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 3) continue;

            $statusCode = substr($line, 0, 2);
            $filePath = trim(substr($line, 2));
            $filePath = trim($filePath, '"\'');

            $type = 'M';
            $label = 'Modified';
            if (str_contains($statusCode, '?')) {
                $type = 'U';
                $label = 'Untracked';
            } elseif (str_contains($statusCode, 'A')) {
                $type = 'A';
                $label = 'Added';
            } elseif (str_contains($statusCode, 'D')) {
                $type = 'D';
                $label = 'Deleted';
            } elseif (str_contains($statusCode, 'R')) {
                $type = 'R';
                $label = 'Renamed';
            }

            $files[] = [
                'path' => $filePath,
                'name' => basename($filePath),
                'type' => $type,
                'label' => $label,
                'raw' => $statusCode,
            ];
        }
    }

    echo json_encode([
        'status' => 'ok',
        'branch' => $branch,
        'hasToken' => !empty($project['github_token']),
        'total_changes' => count($files),
        'files' => $files,
    ]);
    exit;
}

// 2. Riwayat Commit (Commit History)
if ($action === 'history') {
    $limit = min(50, max(5, intval($_GET['limit'] ?? 15)));
    [$logOut, $code] = run_git($root, "log -n $limit --pretty=format:\"%h|%an|%ad|%s\" --date=relative");

    $commits = [];
    if ($code === 0 && trim($logOut) !== '') {
        $lines = explode("\n", trim($logOut));
        foreach ($lines as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4) {
                $commits[] = [
                    'hash' => $parts[0],
                    'author' => $parts[1],
                    'date' => $parts[2],
                    'message' => $parts[3],
                ];
            }
        }
    }

    echo json_encode([
        'status' => 'ok',
        'commits' => $commits,
    ]);
    exit;
}

// 3. Batalkan Perubahan Satu Berkas (Discard File)
if ($action === 'discard_file') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $relPath = trim($_POST['path'] ?? '');
    if ($relPath === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Path berkas required']);
        exit;
    }

    [$statusOut, $code] = run_git($root, 'status --porcelain ' . escapeshellarg($relPath));
    $isUntracked = str_contains($statusOut, '?');

    if ($isUntracked) {
        $target = $root . DIRECTORY_SEPARATOR . $relPath;
        if (file_exists($target)) {
            @unlink($target);
        }
    } else {
        run_git($root, 'checkout HEAD -- ' . escapeshellarg($relPath));
    }

    echo json_encode(['status' => 'ok', 'message' => "Perubahan pada '$relPath' berhasil dibatalkan."]);
    exit;
}

// 4. Batalkan Seluruh Perubahan (Discard All)
if ($action === 'discard_all') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    run_git($root, 'reset --hard HEAD');
    run_git($root, 'clean -fd');

    echo json_encode(['status' => 'ok', 'message' => 'Semua perubahan berhasil dibatalkan ke kondisi commit terakhir.']);
    exit;
}

// 5. Kembalikan ke Commit Sebelumnya (Rollback to Commit)
if ($action === 'rollback_commit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $hash = trim($_POST['commit_hash'] ?? '');
    if (!preg_match('/^[a-f0-9]{7,40}$/i', $hash)) {
        http_response_code(400);
        echo json_encode(['error' => 'Hash commit tidak valid.']);
        exit;
    }

    [$resetOut, $code] = run_git($root, 'reset --hard ' . escapeshellarg($hash));
    if ($code !== 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal melakukan rollback: ' . $resetOut]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => "Project berhasil dikembalikan ke commit $hash.",
        'output' => $resetOut,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Aksi tidak dikenal: ' . $action]);

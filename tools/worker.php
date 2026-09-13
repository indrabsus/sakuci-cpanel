<?php
/*
| Mengerjakan antrean git. Dipanggil cron:
|
|   * * * * * /usr/bin/php /www/wwwroot/cpanel.sakuci.id/tools/worker.php
|
| Dijalankan lewat PHP CLI, yang memakai php-cli.ini terpisah sehingga exec()
| masih tersedia. PHP web tetap dikeraskan tanpa exec, dan itu memang tujuannya:
| permintaan dari browser hanya menitipkan baris di job_queue, tidak pernah
| menyentuh shell.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/framework-check.php';

const JOB_TIMEOUT = 600;   // detik, batas satu perintah git
const MAX_PER_RUN = 5;     // agar satu putaran cron tidak berjalan terlalu lama

// Menolak repo yang bukan project Sakuci. Diatur lewat 'wajib_sakuci' di
// config/env.php; kosongkan menjadi false bila panel dipakai untuk framework lain.
define('WAJIB_SAKUCI', $env['wajib_sakuci'] ?? true);

/** Membuang folder sementara beserta isinya. */
function delete_recursive_worker(string $path): bool
{
    if (is_link($path) || is_file($path)) {
        // git menandai berkas objeknya read-only. Di Windows, unlink() menolak
        // berkas read-only, sehingga folder sementara tertinggal saat clone
        // ditolak. chmod dulu agar pembersihan tuntas di kedua platform.
        @chmod($path, 0666);

        return @unlink($path);
    }
    if (!is_dir($path)) {
        return false;
    }
    foreach (scandir($path) ?: [] as $n) {
        if ($n === '.' || $n === '..') {
            continue;
        }
        if (!delete_recursive_worker($path . DIRECTORY_SEPARATOR . $n)) {
            return false;
        }
    }
    @chmod($path, 0777);

    return @rmdir($path);
}

/** Mencegah dua cron tumpang tindih saat satu pekerjaan berjalan lama. */
$lock = fopen(sys_get_temp_dir() . '/sakuci-cpanel-worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit;   // putaran sebelumnya masih jalan
}

if (!function_exists('exec')) {
    fwrite(STDERR, "exec() tidak tersedia di PHP CLI ini; worker tidak bisa bekerja.\n");
    exit(1);
}

/** Menjalankan perintah dan mengembalikan [keluaran, kode keluar]. */
function run(string $command): array
{
    $output = [];
    $code = 0;
    exec($command, $output, $code);

    return [trim(implode("\n", $output)), $code];
}

$isWindows = DIRECTORY_SEPARATOR === '\\';

// git tidak boleh menunggu prompt kredensial di skrip non-interaktif: repo
// privat harus gagal cepat, bukan menggantung sampai batas waktu. Diatur lewat
// putenv, bukan awalan "VAR=nilai" yang hanya dikenal shell POSIX.
putenv('GIT_TERMINAL_PROMPT=0');
putenv('GIT_ASKPASS=' . ($isWindows ? 'cmd /c exit' : '/bin/true'));

function get_auth_git_url(string $url, ?string $token): string
{
    if (!$token) {
        return $url;
    }
    // Sisipkan token ke URL GitHub: https://oauth2:TOKEN@github.com/user/repo
    if (preg_match('#^https?://([^@]+@)?github\.com/(.+)$#i', $url, $m)) {
        return 'https://oauth2:' . urlencode($token) . '@github.com/' . $m[2];
    }
    return $url;
}

function git(string $args, ?string $cwd = null): array
{
    global $isWindows;

    $cmd = '';
    if ($cwd !== null) {
        $cmd .= 'cd ' . escapeshellarg($cwd) . ' && ';
    }

    // timeout(1) mencegah satu repo bermasalah menahan antrean selamanya.
    // Perintah itu milik coreutils dan tidak ada di Windows, jadi dilewati
    // saat pengembangan lokal.
    $cmd .= $isWindows ? '' : 'timeout ' . JOB_TIMEOUT . ' ';

    return run($cmd . 'git -c safe.directory=* ' . $args . ' 2>&1');
}

function finish($conn, int $id, string $status, string $output): void
{
    $stmt = $conn->prepare(
        "UPDATE job_queue SET status = ?, output = ?, finished_at = NOW() WHERE id = ?"
    );
    // Output git bisa sangat panjang; simpan ekornya saja.
    $trimmed = strlen($output) > 5000 ? '…' . substr($output, -5000) : $output;
    $stmt->bind_param("ssi", $status, $trimmed, $id);
    $stmt->execute();
}

$processed = 0;

while ($processed < MAX_PER_RUN) {
    $row = $conn->query(
        "SELECT id FROM job_queue WHERE status = 'pending' ORDER BY id LIMIT 1"
    )->fetch_assoc();

    if (!$row) {
        break;
    }

    // Klaim atomik: hanya satu proses yang bisa mengubah pending -> running.
    $claim = $conn->prepare(
        "UPDATE job_queue SET status = 'running', started_at = NOW()
          WHERE id = ? AND status = 'pending'"
    );
    $claim->bind_param("i", $row['id']);
    $claim->execute();

    if ($claim->affected_rows !== 1) {
        continue;   // sudah diambil proses lain
    }

    $id = (int) $row['id'];
    $processed++;

    $stmt = $conn->prepare(
        "SELECT j.action, j.commit_message, p.git_url, p.git_branch, p.local_path, p.github_token, u.username
           FROM job_queue j
           JOIN projects p ON p.id = j.project_id
           JOIN users u ON u.id = j.user_id
          WHERE j.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();

    if (!$job) {
        finish($conn, $id, 'failed', 'Project sudah tidak ada.');
        continue;
    }

    $path   = $job['local_path'];
    $branch = $job['git_branch'] ?: 'main';

    if ($job['action'] === 'clone') {
        $parent = dirname($path);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            finish($conn, $id, 'failed', "Tidak bisa membuat folder induk: $parent");
            continue;
        }

        // Di-clone ke folder sementara lebih dulu. Isinya baru bisa diperiksa
        // setelah terunduh, dan bila ternyata bukan project Sakuci, folder
        // tujuan tidak pernah sempat terisi -- tidak ada sisa yang harus
        // dibereskan siswa.
        $sementara = $path . '.tmp-' . bin2hex(random_bytes(4));

        $authCloneUrl = get_auth_git_url($job['git_url'], $job['github_token']);
        [$out, $code] = git(
            'clone --branch ' . escapeshellarg($branch)
            . ' ' . escapeshellarg($authCloneUrl)
            . ' ' . escapeshellarg($sementara),
            $parent
        );

        if ($code === 0 && WAJIB_SAKUCI) {
            $cek = periksa_sakuci($sementara);

            if (!$cek['ok']) {
                delete_recursive_worker($sementara);
                catat_penolakan($job['git_url'], $cek['kurang']);
                finish($conn, $id, 'failed', pesan_bukan_sakuci());
                continue;
            }
        }

        if ($code === 0 && !@rename($sementara, $path)) {
            delete_recursive_worker($sementara);
            finish($conn, $id, 'failed', "Gagal memindahkan hasil clone ke $path");
            continue;
        }

        if ($code !== 0) {
            delete_recursive_worker($sementara);
        }
    } elseif ($job['action'] === 'pull') {
        if (!is_dir($path . '/.git')) {
            finish($conn, $id, 'failed', "Bukan repo git: $path");
            continue;
        }

        if (!empty($job['github_token'])) {
            git('remote set-url origin ' . escapeshellarg(get_auth_git_url($job['git_url'], $job['github_token'])), $path);
        }

        [$fetchOut, $code] = git('fetch origin', $path);
        $out = $fetchOut;

        if ($code === 0) {
            [$resetOut, $code] = git(
                'reset --hard ' . escapeshellarg('origin/' . $branch),
                $path
            );
            $out = trim($fetchOut . "\n" . $resetOut);
        }

        if (!empty($job['github_token'])) {
            git('remote set-url origin ' . escapeshellarg($job['git_url']), $path);
        }
    } elseif ($job['action'] === 'push') {
        if (!is_dir($path . '/.git')) {
            finish($conn, $id, 'failed', "Bukan repo git: $path");
            continue;
        }

        if (empty($job['github_token'])) {
            finish($conn, $id, 'failed', "Fitur Push membutuhkan GitHub Personal Access Token (PAT). Silakan masukkan token di form project.");
            continue;
        }

        $authUrl = get_auth_git_url($job['git_url'], $job['github_token']);
        $authorName = $job['username'] ?: 'Sakuci User';
        $authorEmail = ($job['username'] ?: 'user') . '@sakuci.id';
        $commitMsg = trim($job['commit_message'] ?: 'Update kodingan via Sakuci cPanel');

        // Gunakan remote dengan otentikasi token
        git('remote set-url origin ' . escapeshellarg($authUrl), $path);
        git('config user.name ' . escapeshellarg($authorName), $path);
        git('config user.email ' . escapeshellarg($authorEmail), $path);

        // Tambahkan semua file yang berubah
        git('add -A', $path);

        // Periksa apakah ada perubahan
        [$statusOut, $statusCode] = git('status --porcelain', $path);
        if ($statusCode === 0 && empty(trim($statusOut))) {
            git('remote set-url origin ' . escapeshellarg($job['git_url']), $path);
            finish($conn, $id, 'success', 'Tidak ada perubahan file untuk di-commit.');
            continue;
        }

        // Commit perubahan
        [$commitOut, $code] = git('commit -m ' . escapeshellarg($commitMsg), $path);

        if ($code === 0) {
            // Push ke remote GitHub
            [$pushOut, $code] = git('push origin ' . escapeshellarg($branch), $path);
            $out = trim($commitOut . "\n" . $pushOut);
        } else {
            $out = $commitOut;
        }

        // Reset remote URL agar token tidak tersimpan di .git/config
        git('remote set-url origin ' . escapeshellarg($job['git_url']), $path);
    }

    if ($code === 0) {
        // Berkas hasil git harus dimiliki user web agar PHP bisa membacanya.
        // Hanya mungkin bila worker berjalan sebagai root.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            run('chown -R www:www ' . escapeshellarg($path) . ' 2>&1');
        }

        $upd = $conn->prepare("UPDATE projects SET last_pull = NOW() WHERE id = (SELECT project_id FROM job_queue WHERE id = ?)");
        $upd->bind_param("i", $id);
        $upd->execute();

        finish($conn, $id, 'success', $out ?: 'Selesai tanpa keluaran.');
    } else {
        $reason = $code === 124 ? "Melewati batas waktu " . JOB_TIMEOUT . " detik.\n" : '';
        finish($conn, $id, 'failed', $reason . ($out ?: "git keluar dengan kode $code"));
    }
}

flock($lock, LOCK_UN);

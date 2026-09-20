<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/git-url.php';
include 'partials/layout.php';

$user = require_login($conn);
$user_id = $user['id'];
$error = '';
$success = '';

$isAdmin = is_admin($user);
$isBarcode = is_barcode($user);

// Hitung jumlah project yang sudah dimiliki
$stmtCek = $conn->prepare("SELECT COUNT(*) AS total FROM projects WHERE user_id = ?");
$stmtCek->bind_param("i", $user_id);
$stmtCek->execute();
$userProjectCount = (int) $stmtCek->get_result()->fetch_assoc()['total'];

$maxProjects = $isBarcode ? 10 : 1;

// Pola POST-redirect-GET: tanpa ini, menekan refresh setelah submit akan
// mengirim ulang form dan membuat project ganda.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin) {
        $pesan = 'err|Administrator hanya bertugas memantau dan tidak diperkenankan membuat project.';
        header('Location: add-project.php?pesan=' . urlencode($pesan));
        exit;
    }

    if ($userProjectCount >= $maxProjects) {
        $pesan = $isBarcode
            ? 'err|Batas kuota tercapai: Anda sudah memiliki ' . $maxProjects . ' project.'
            : 'err|Batas kuota tercapai: Setiap siswa hanya diperbolehkan memiliki maksimal 1 project. Hapus project Anda yang ada di Dashboard jika ingin membuat baru.';
        header('Location: add-project.php?pesan=' . urlencode($pesan));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $project_type = trim($_POST['project_type'] ?? ($isBarcode ? 'dari_nol' : 'framework'));

    // Alamat dinormalkan lebih dulu
    $domain = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $domain));

    if (empty($name) || empty($domain)) {
        $pesan = 'err|Nama project dan alamat subdomain wajib diisi.';
    } elseif (!preg_match('/^[a-z][a-z0-9-]{2,29}$/', $domain)) {
        $pesan = 'err|Alamat harus 3-30 karakter, diawali huruf, hanya huruf kecil, angka, dan tanda hubung.';
    } else {
        $local_path = PROJECTS_PATH . '/' . $domain;
        $webhook_secret = bin2hex(random_bytes(16));

        if ($isBarcode && $project_type === 'dari_nol') {
            $git_url = 'local://' . $domain;
            $git_key = 'local/' . $domain;
            $git_branch = 'main';
            $token_val = null;

            try {
                $stmt = $conn->prepare(
                    "INSERT INTO projects (user_id, name, domain, git_url, git_key, git_branch, local_path, webhook_secret, github_token, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
                );
                $stmt->bind_param("issssssss", $user_id, $name, $domain, $git_url, $git_key, $git_branch, $local_path, $webhook_secret, $token_val);
                $stmt->execute();
                $newProjId = $conn->insert_id;

                // Buat folder proyek dan starter index.php
                if (!is_dir($local_path)) {
                    @mkdir($local_path, 0777, true);
                }

                $starterIndex = <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Barcode - Ngoding dari 0</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 620px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
        }
        h1 { font-size: 1.75rem; margin-bottom: 0.75rem; color: #ffffff; }
        p { color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .code-box {
            background: #090d16;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 1rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            color: #38bdf8;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .info {
            background: rgba(14, 165, 233, 0.1);
            border-left: 3px solid #0284c7;
            padding: 0.8rem 1rem;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
            color: #7dd3fc;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #4f46e5;
            color: #ffffff;
            padding: 0.65rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: background 0.2s;
        }
        .btn:hover { background: #4338ca; }
        .btn-sec {
            background: #334155;
            color: #e2e8f0;
        }
        .btn-sec:hover { background: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">🚀 Barcode Custom Project (Dari 0)</div>
        <h1>Halo Dunia dari Barcode!</h1>
        <p>Project ini dibuat mulai dari 0. Anda dapat menyunting berkas ini atau menambahkan berkas PHP, HTML, CSS, dan JS baru langsung melalui Web Editor cPanel.</p>
        <div class="code-box">
            PHP Version: <?php echo PHP_VERSION; ?><br>
            Waktu Server: <?php echo date('Y-m-d H:i:s'); ?><br>
            Host: <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost'); ?>
        </div>
        <div class="info">
            💡 <strong>Tips:</strong> Buka menu <strong>Editor</strong> di cPanel untuk mulai ngoding. Buat database di menu <strong>Database</strong> untuk menghubungkan aplikasi Anda ke MySQL via Table Designer.
        </div>
        <div class="links">
            <a class="btn" href="https://cpanel.sakuci.id/app/dashboard.php">&larr; Kembali ke cPanel</a>
        </div>
    </div>
</body>
</html>
HTML;
                if (!file_exists($local_path . '/index.php')) {
                    @file_put_contents($local_path . '/index.php', $starterIndex);
                    @chmod($local_path . '/index.php', 0666);
                }

                // Inisialisasi repo git lokal agar tracking & commit editor berfungsi
                @shell_exec(sprintf(
                    "cd %s && git init 2>/dev/null && git config user.name 'Barcode' && git config user.email 'barcode@barcode.sakuci.id' && git add -A && git commit -m 'Initial project setup' 2>/dev/null",
                    escapeshellarg($local_path)
                ));

                header('Location: files.php?project=' . $newProjId . '&pesan=' . urlencode('ok|Project "' . $name . '" berhasil dibuat dari 0! Buka Web Editor untuk mulai ngoding.'));
                exit;
            } catch (mysqli_sql_exception $e) {
                error_log('add-project insert failed: ' . $e->getMessage());
                if (str_contains($e->getMessage(), 'unik_git_key') || str_contains($e->getMessage(), 'Duplicate')) {
                    $pesan = 'err|Alamat subdomain "' . $domain . '" sudah dipakai. Pilih nama lain.';
                } else {
                    $pesan = 'err|Gagal menyimpan project: ' . $e->getMessage();
                }
            }
        } else {
            // Mode Sakuci Framework
            $git_url = trim($_POST['git_url'] ?? '');
            $git_branch = trim($_POST['git_branch'] ?? 'main');
            if (empty($git_branch)) {
                $git_branch = 'main';
            }
            $github_token = trim($_POST['github_token'] ?? '');
            $token_val = !empty($github_token) ? $github_token : null;

            if (empty($git_url)) {
                $pesan = 'err|URL repositori Git wajib diisi untuk mode Sakuci Framework.';
            } else {
                $git_key = normalize_git_url($git_url);
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO projects (user_id, name, domain, git_url, git_key, git_branch, local_path, webhook_secret, github_token, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
                    );
                    $stmt->bind_param("issssssss", $user_id, $name, $domain, $git_url, $git_key, $git_branch, $local_path, $webhook_secret, $token_val);
                    $stmt->execute();

                    header('Location: dashboard.php?pesan=' . urlencode(
                        'ok|Project "' . $name . '" berhasil ditambahkan! Klik Clone untuk mengambil kode dan aktifkan Auto-Deploy Webhook.'
                    ));
                    exit;
                } catch (mysqli_sql_exception $e) {
                    error_log('add-project insert failed: ' . $e->getMessage());
                    if (str_contains($e->getMessage(), 'unik_git_key')) {
                        $pesan = 'err|Repo ini sudah dipakai project lain. Satu repo hanya boleh dipakai satu project.';
                    } elseif (str_contains($e->getMessage(), 'Duplicate')) {
                        $pesan = 'err|Alamat subdomain "' . $domain . '" sudah dipakai. Pilih nama lain.';
                    } else {
                        $pesan = 'err|Gagal menyimpan project. Coba lagi.';
                    }
                }
            }
        }
    }

    header('Location: add-project.php?pesan=' . urlencode($pesan));
    exit;
}

if (isset($_GET['pesan']) && str_contains((string) $_GET['pesan'], '|')) {
    [$jenis, $teks] = explode('|', (string) $_GET['pesan'], 2);
    if ($jenis === 'ok') {
        $success = htmlspecialchars($teks);
    } else {
        $error = htmlspecialchars($teks);
    }
}

$domainSuffix = get_domain_suffix($user);
$headerSubtitle = $isBarcode
    ? 'Buat project baru (Ngoding dari 0 atau gunakan Sakuci Framework)'
    : 'Ambil project Sakuci Framework dari repositori Git';

layout_start('Tambah Project', $headerSubtitle, 'add', $user);
?>

<?php if ($error): ?><div class="note note-err"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="note note-ok"><?php echo $success; ?></div><?php endif; ?>

<?php if ($isAdmin): ?>
    <div class="card" style="max-width:680px">
        <div class="card-h">
            <div>
                <h2>Mode Pemantauan Administrator</h2>
                <p>Akses Terbatas &mdash; Admin tidak diperkenankan membuat project</p>
            </div>
        </div>
        <div class="card-b">
            <p style="color:var(--ink-2); line-height:1.6; font-size:14px">
                Akun Administrator bertugas untuk memantau pengguna, seluruh project siswa, dan seluruh database siswa di panel hosting ini. Administrator tidak diperkenankan membuat project secara mandiri.
            </p>
            <div style="margin-top:1.2rem">
                <a class="btn" href="dashboard.php">&larr; Kembali ke Dashboard Pemantauan</a>
            </div>
        </div>
    </div>
<?php elseif ($userProjectCount >= $maxProjects): ?>
    <div class="card" style="max-width:680px">
        <div class="card-h">
            <div>
                <h2>Batas Kuota Project Tercapai</h2>
                <p><span class="pill pill-warn" style="background:#fef3c7; color:#92400e; font-weight:600"><?php echo $userProjectCount; ?> / <?php echo $maxProjects; ?> Project Digunakan (Kuota Penuh)</span></p>
            </div>
        </div>
        <div class="card-b">
            <p style="color:var(--ink-2); line-height:1.6; font-size:14px">
                Akun Anda telah mencapai batas maksimal <strong><?php echo $maxProjects; ?> project</strong>.
            </p>
            <p style="color:var(--ink-2); line-height:1.6; font-size:14px; margin-top:.5rem">
                Jika Anda ingin membuat project baru, silakan hapus project yang ada terlebih dahulu melalui halaman <strong>Dashboard</strong>.
            </p>
            <div style="margin-top:1.2rem; display:flex; gap:.6rem">
                <a class="btn" href="dashboard.php">&larr; Kelola Project di Dashboard</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="max-width:700px">
        <div class="card-h">
            <div>
                <h2>Project Baru</h2>
                <p>
                    <?php if ($isBarcode): ?>
                        Akun Barcode &mdash; Domain: <code>*.<?php echo htmlspecialchars($domainSuffix); ?></code> (Penggunaan: <?php echo $userProjectCount; ?> / <?php echo $maxProjects; ?>)
                    <?php else: ?>
                        Deploy repositori Sakuci Framework dengan fitur Auto-Deploy &amp; Git Push (Kuota: <?php echo $userProjectCount; ?> / <?php echo $maxProjects; ?>)
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="card-b">
            <form method="POST" id="form-tambah-project">
                <?php if ($isBarcode): ?>
                    <!-- Pilihan Mode Khusus Akun Barcode: Dari 0 vs Sakuci Framework -->
                    <div class="field" style="margin-bottom:1.5rem">
                        <label style="font-weight:600; font-size:.92rem; margin-bottom:.6rem; display:block">Tipe Project:</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.85rem">
                            <label id="opt-dari-nol" style="border:2px solid var(--accent); background:rgba(99,102,241,0.06); padding:1.1rem; border-radius:10px; cursor:pointer; display:flex; flex-direction:column; gap:.4rem; transition:all 0.2s">
                                <div style="display:flex; align-items:center; gap:.5rem">
                                    <input type="radio" name="project_type" value="dari_nol" checked onchange="toggleProjectType(this.value)">
                                    <strong style="color:var(--accent); font-size:.95rem">🚀 Mulai dari 0</strong>
                                </div>
                                <span style="font-size:.8rem; color:var(--ink-2); line-height:1.45">Koding langsung dari awal di Web Editor cPanel. Tanpa perlu git clone.</span>
                            </label>
                            <label id="opt-framework" style="border:1px solid var(--line); background:var(--surface); padding:1.1rem; border-radius:10px; cursor:pointer; display:flex; flex-direction:column; gap:.4rem; transition:all 0.2s">
                                <div style="display:flex; align-items:center; gap:.5rem">
                                    <input type="radio" name="project_type" value="framework" onchange="toggleProjectType(this.value)">
                                    <strong style="font-size:.95rem">📦 Sakuci Framework</strong>
                                </div>
                                <span style="font-size:.8rem; color:var(--ink-2); line-height:1.45">Clone dari repositori GitHub Sakuci Framework dengan Auto-Deploy Webhook.</span>
                            </label>
                        </div>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="project_type" value="framework">
                <?php endif; ?>

                <div class="field">
                    <label for="f-nama">Nama Project</label>
                    <input id="f-nama" type="text" name="name" placeholder="Toko Online" required>
                    <div class="hint">Bebas, hanya untuk memudahkan Anda mengenalinya di dashboard.</div>
                </div>

                <div class="field">
                    <label for="f-domain">Alamat Subdomain</label>
                    <input id="f-domain" type="text" name="domain" placeholder="tokoonline" required
                           pattern="[A-Za-z][A-Za-z0-9-]{2,29}">
                    <div class="hint">
                        Web akan tampil di <code>alamat.<?php echo htmlspecialchars($domainSuffix); ?></code>.
                        Huruf kecil, angka, dan tanda hubung (3&ndash;30 karakter).
                    </div>
                </div>

                <div id="git-fields-wrapper" style="<?php echo ($isBarcode ? 'display:none;' : ''); ?>">
                    <div class="field">
                        <label for="f-url">URL Repositori Git</label>
                        <input id="f-url" type="url" name="git_url"
                               <?php echo (!$isBarcode ? 'required' : ''); ?>
                               placeholder="https://github.com/nama/repo.git">
                        <div class="hint">Mendukung repositori GitHub publik maupun privat.</div>
                    </div>

                    <div class="field">
                        <label for="f-branch">Branch</label>
                        <input id="f-branch" type="text" name="git_branch" value="main" placeholder="main">
                        <div class="hint">Branch utama yang akan di-deploy (default: <code>main</code>).</div>
                    </div>

                    <div class="field" style="margin-top:1.4rem; padding-top:1.2rem; border-top:1px dashed var(--line-soft)">
                        <label for="f-token" style="display:flex; align-items:center; gap:.5rem">
                            <span>GitHub Personal Access Token (PAT)</span>
                            <span class="dim" style="font-size:.78rem; font-weight:normal">(Opsional / Sangat Direkomendasikan)</span>
                        </label>
                        <input id="f-token" type="password" name="github_token" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" autocomplete="off">
                        <div class="hint" style="line-height:1.5; margin-top:.4rem">
                            Dibutuhkan jika repositori bersifat <strong>Private</strong> atau jika Anda ingin mengaktifkan fitur <strong>Commit &amp; Push</strong> dua arah langsung dari cPanel (seperti Vercel).<br>
                            Cara membuat token di GitHub: <em>Settings &rarr; Developer Settings &rarr; Personal Access Tokens (Classic) &rarr; Generate new token</em>, lalu centang izin <code>repo</code>.
                        </div>
                    </div>
                </div>

                <div style="margin-top:1.6rem; display:flex; gap:.6rem; align-items:center">
                    <button type="submit" class="btn" id="btn-submit-project"><?php echo ikon('plus'); ?><span id="btn-submit-label"><?php echo ($isBarcode ? 'Buat Project (Mulai dari 0)' : 'Tambah Project'); ?></span></button>
                    <a class="btn btn-2" href="dashboard.php">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($isBarcode): ?>
    <script>
    function toggleProjectType(val) {
        const gitBox = document.getElementById('git-fields-wrapper');
        const optNol = document.getElementById('opt-dari-nol');
        const optFw = document.getElementById('opt-framework');
        const gitInput = document.getElementById('f-url');
        const submitLabel = document.getElementById('btn-submit-label');

        if (val === 'dari_nol') {
            gitBox.style.display = 'none';
            gitInput.removeAttribute('required');
            optNol.style.border = '2px solid var(--accent)';
            optNol.style.background = 'rgba(99,102,241,0.06)';
            optFw.style.border = '1px solid var(--line)';
            optFw.style.background = 'var(--surface)';
            if (submitLabel) submitLabel.textContent = 'Buat Project (Mulai dari 0)';
        } else {
            gitBox.style.display = 'block';
            gitInput.setAttribute('required', 'required');
            optFw.style.border = '2px solid var(--accent)';
            optFw.style.background = 'rgba(99,102,241,0.06)';
            optNol.style.border = '1px solid var(--line)';
            optNol.style.background = 'var(--surface)';
            if (submitLabel) submitLabel.textContent = 'Tambah Project (Framework)';
        }
    }
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>

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

// Hitung jumlah project yang sudah dimiliki siswa
$stmtCek = $conn->prepare("SELECT COUNT(*) AS total FROM projects WHERE user_id = ?");
$stmtCek->bind_param("i", $user_id);
$stmtCek->execute();
$userProjectCount = (int) $stmtCek->get_result()->fetch_assoc()['total'];

// Pola POST-redirect-GET: tanpa ini, menekan refresh setelah submit akan
// mengirim ulang form dan membuat project ganda.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin) {
        $pesan = 'err|Administrator hanya bertugas memantau dan tidak diperkenankan membuat project.';
        header('Location: add-project.php?pesan=' . urlencode($pesan));
        exit;
    }

    if ($userProjectCount >= 1) {
        $pesan = 'err|Batas kuota tercapai: Setiap siswa hanya diperbolehkan memiliki maksimal 1 project. Hapus project Anda yang ada di Dashboard jika ingin membuat baru.';
        header('Location: add-project.php?pesan=' . urlencode($pesan));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $git_url = trim($_POST['git_url'] ?? '');
    $git_branch = trim($_POST['git_branch'] ?? 'main');
    $github_token = trim($_POST['github_token'] ?? '');

    if (empty($git_branch)) {
        $git_branch = 'main';
    }

    // Alamat dinormalkan lebih dulu, bukan sekadar dibersihkan saat membentuk
    // path. Kalau tidak, "My-App" dan "myapp" tampil berbeda di daftar padahal
    // menunjuk folder dan subdomain yang sama.
    $domain = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $domain));

    if (empty($name) || empty($domain) || empty($git_url)) {
        $pesan = 'err|Semua kolom wajib harus diisi.';
    } elseif (!preg_match('/^[a-z][a-z0-9-]{2,29}$/', $domain)) {
        $pesan = 'err|Alamat harus 3-30 karakter, diawali huruf, hanya huruf kecil, angka, dan tanda hubung.';
    } else {
        $local_path = PROJECTS_PATH . '/' . $domain;

        // Bentuk baku dipakai sebagai pembanding; URL asli tetap disimpan
        // untuk ditampilkan. Tanpa pembakuan, repo yang sama bisa masuk
        // berkali-kali hanya dengan mengubah .git atau huruf besar-kecil.
        $git_key = normalize_git_url($git_url);
        $webhook_secret = bin2hex(random_bytes(16));
        $token_val = !empty($github_token) ? $github_token : null;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO projects (user_id, name, domain, git_url, git_key, git_branch, local_path, webhook_secret, github_token, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->bind_param("issssssss", $user_id, $name, $domain, $git_url, $git_key, $git_branch, $local_path, $webhook_secret, $token_val);
            $stmt->execute();

            // Diarahkan ke dashboard: di sanalah tombol Clone, Buka Web, dan
            // Berkas berada, jadi siswa langsung bisa melanjutkan.
            header('Location: dashboard.php?pesan=' . urlencode(
                'ok|Project "' . $name . '" berhasil ditambahkan! Klik Clone untuk mengambil kode dan aktifkan Auto-Deploy Webhook.'
            ));
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log('add-project insert failed: ' . $e->getMessage());

            // Dua indeks unik yang bisa memicu: alamat dan repo.
            if (str_contains($e->getMessage(), 'unik_git_key')) {
                $pesan = 'err|Repo ini sudah dipakai project lain. '
                       . 'Satu repo hanya boleh dipakai satu project.';
            } elseif (str_contains($e->getMessage(), 'Duplicate')) {
                $pesan = 'err|Alamat "' . $domain . '" sudah dipakai. Pilih nama lain.';
            } else {
                $pesan = 'err|Gagal menyimpan project. Coba lagi.';
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

$contohDomain = SITE_DOMAIN !== '' ? SITE_DOMAIN : 'contoh.id';

layout_start('Tambah Project', 'Ambil project Sakuci Framework dari repositori Git', 'add', $user);
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
<?php elseif ($userProjectCount >= 1): ?>
    <div class="card" style="max-width:680px">
        <div class="card-h">
            <div>
                <h2>Batas Kuota Project Tercapai</h2>
                <p><span class="pill pill-warn" style="background:#fef3c7; color:#92400e; font-weight:600">1 / 1 Project Digunakan (Kuota Penuh)</span></p>
            </div>
        </div>
        <div class="card-b">
            <p style="color:var(--ink-2); line-height:1.6; font-size:14px">
                Setiap akun siswa dibatasi maksimal <strong>1 project</strong> dan <strong>1 database</strong>. Anda telah memiliki 1 project aktif di cPanel.
            </p>
            <p style="color:var(--ink-2); line-height:1.6; font-size:14px; margin-top:.5rem">
                Jika Anda ingin mendaftarkan repositori project yang baru, silakan hapus project yang ada terlebih dahulu melalui halaman <strong>Dashboard</strong>.
            </p>
            <div style="margin-top:1.2rem; display:flex; gap:.6rem">
                <a class="btn" href="dashboard.php">&larr; Kelola Project di Dashboard</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="max-width:680px">
        <div class="card-h">
            <div>
                <h2>Project Baru</h2>
                <p>Deploy repositori Sakuci Framework dengan fitur Auto-Deploy &amp; Git Push (Kuota Anda: 0 / 1 Project)</p>
            </div>
        </div>
        <div class="card-b">
            <form method="POST">
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
                        Web akan tampil di <code>alamat.<?php echo htmlspecialchars($contohDomain); ?></code>.
                        Huruf kecil, angka, dan tanda hubung (3&ndash;30 karakter).
                    </div>
                </div>

                <div class="field">
                    <label for="f-url">URL Repositori Git</label>
                    <input id="f-url" type="url" name="git_url" required
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

                <div style="margin-top:1.5rem; display:flex; gap:.6rem; align-items:center">
                    <button type="submit" class="btn"><?php echo ikon('plus'); ?>Tambah Project</button>
                    <a class="btn btn-2" href="dashboard.php">Batal</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php layout_end(); ?>

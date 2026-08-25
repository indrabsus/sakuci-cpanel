<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/git-url.php';
include 'partials/layout.php';

$user = require_login($conn);
$user_id = $user['id'];
$error = '';
$success = '';

// Pola POST-redirect-GET: tanpa ini, menekan refresh setelah submit akan
// mengirim ulang form dan membuat project ganda.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $git_url = trim($_POST['git_url'] ?? '');
    $git_branch = trim($_POST['git_branch'] ?? 'main');

    // Alamat dinormalkan lebih dulu, bukan sekadar dibersihkan saat membentuk
    // path. Kalau tidak, "My-App" dan "myapp" tampil berbeda di daftar padahal
    // menunjuk folder dan subdomain yang sama.
    $domain = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $domain));

    if (empty($name) || empty($domain) || empty($git_url)) {
        $pesan = 'err|Semua kolom harus diisi.';
    } elseif (!preg_match('/^[a-z][a-z0-9-]{2,29}$/', $domain)) {
        $pesan = 'err|Alamat harus 3-30 karakter, diawali huruf, hanya huruf kecil, angka, dan tanda hubung.';
    } else {
        $local_path = PROJECTS_PATH . '/' . $domain;

        // Bentuk baku dipakai sebagai pembanding; URL asli tetap disimpan
        // untuk ditampilkan. Tanpa pembakuan, repo yang sama bisa masuk
        // berkali-kali hanya dengan mengubah .git atau huruf besar-kecil.
        $git_key = normalize_git_url($git_url);

        try {
            $stmt = $conn->prepare(
                "INSERT INTO projects (user_id, name, domain, git_url, git_key, git_branch, local_path, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->bind_param("issssss", $user_id, $name, $domain, $git_url, $git_key, $git_branch, $local_path);
            $stmt->execute();

            // Diarahkan ke dashboard: di sanalah tombol Clone, Buka Web, dan
            // Berkas berada, jadi siswa langsung bisa melanjutkan.
            header('Location: dashboard.php?pesan=' . urlencode(
                'ok|Project "' . $name . '" ditambahkan. Klik Clone untuk mengambil kodenya.'
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

<div class="card" style="max-width:640px">
    <div class="card-h">
        <div>
            <h2>Project Baru</h2>
            <p>Hanya repositori Sakuci Framework yang bisa dihosting di sini</p>
        </div>
    </div>
    <div class="card-b">
        <form method="POST">
            <div class="field">
                <label for="f-nama">Nama Project</label>
                <input id="f-nama" type="text" name="name" placeholder="Toko Online" required>
                <div class="hint">Bebas, hanya untuk memudahkan Anda mengenalinya.</div>
            </div>

            <div class="field">
                <label for="f-domain">Alamat</label>
                <input id="f-domain" type="text" name="domain" placeholder="tokoonline" required
                       pattern="[A-Za-z][A-Za-z0-9-]{2,29}">
                <div class="hint">
                    Web akan tampil di <code>alamat.<?php echo htmlspecialchars($contohDomain); ?></code>.
                    Huruf, angka, dan tanda hubung. 3&ndash;30 karakter.
                </div>
            </div>

            <div class="field">
                <label for="f-url">URL Repositori</label>
                <input id="f-url" type="url" name="git_url" required
                       placeholder="https://github.com/nama/repo.git">
                <div class="hint">Repositori harus dapat diakses publik.</div>
            </div>

            <div class="field">
                <label for="f-branch">Branch</label>
                <input id="f-branch" type="text" name="git_branch" value="main" placeholder="main">
            </div>

            <button type="submit" class="btn"><?php echo ikon('plus'); ?>Tambah Project</button>
            <a class="btn btn-2" href="dashboard.php">Batal</a>
        </form>
    </div>
</div>

<?php layout_end(); ?>

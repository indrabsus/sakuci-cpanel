<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/mysql-admin.php';
include '../config/env-writer.php';
include '../config/files.php';
include '../config/db-tools.php';
include 'partials/layout.php';

$user = require_login($conn);
$user_id = $user['id'];
$username = $user['username'];
$error = '';
$success = '';

$admin = mysql_admin_connect($env);
$isAdmin = is_admin($user);

// Penghapusan ditangani lebih dulu lalu dialihkan (pola POST-redirect-GET),
// supaya menekan refresh tidak mengulang perintah DROP.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    $db_id = intval($_POST['db_id'] ?? 0);

    // Admin boleh menghapus milik siswa mana pun.
    $sqlCari = "SELECT d.db_name, d.user_id, u.username AS db_user
                  FROM db_list d
                  LEFT JOIN db_users u ON u.db_id = d.id
                 WHERE d.id = ?" . ($isAdmin ? "" : " AND d.user_id = ?");
    $stmt = $conn->prepare($sqlCari);
    if ($isAdmin) {
        $stmt->bind_param("i", $db_id);
    } else {
        $stmt->bind_param("ii", $db_id, $user_id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $pesan = 'err|Database tidak ditemukan.';
    } elseif ($admin === null) {
        $pesan = 'err|Penghapusan butuh kredensial admin MySQL di config/env.php.';
    } else {
        $hapus = drop_mysql_database($admin, $row['db_name'], $row['db_user']);

        if (!$hapus['ok']) {
            $pesan = 'err|' . $hapus['error'];
        } else {
            // Baris db_users ikut terhapus lewat foreign key.
            $pemilik = (int) $row['user_id'];
            $del = $conn->prepare("DELETE FROM db_list WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $db_id, $pemilik);
            $del->execute();

            $pesan = 'ok|Database ' . $row['db_name'] . ' dihapus beserta seluruh isinya.';
        }
    }

    header('Location: databases.php?pesan=' . urlencode($pesan));
    exit;
}

/** Mengambil database beserta kredensialnya, disaring kepemilikan. */
function ambil_database($conn, int $db_id, int $user_id, bool $isAdmin): ?array
{
    $sql = "SELECT d.id, d.db_name, d.db_host, d.db_port, du.username, du.password
              FROM db_list d
              LEFT JOIN db_users du ON du.db_id = d.id
             WHERE d.id = ?" . ($isAdmin ? "" : " AND d.user_id = ?");
    $q = $conn->prepare($sql);
    if ($isAdmin) {
        $q->bind_param("i", $db_id);
    } else {
        $q->bind_param("ii", $db_id, $user_id);
    }
    $q->execute();

    return $q->get_result()->fetch_assoc() ?: null;
}

// Impor berkas SQL.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'impor') {
    $db_id = intval($_POST['db_id'] ?? 0);
    $d = ambil_database($conn, $db_id, $user_id, $isAdmin);
    $f = $_FILES['berkas'] ?? null;

    if (!$d || !$d['username']) {
        $pesan = 'err|Database tidak ditemukan.';
    } elseif (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        $pesan = 'err|Pilih berkas .sql lebih dulu.';
    } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $pesan = 'err|Berkas terlalu besar untuk diunggah server.';
    } elseif ($f['error'] !== UPLOAD_ERR_OK) {
        $pesan = 'err|Gagal mengunggah berkas.';
    } elseif ($f['size'] > IMPOR_MAKS_BYTE) {
        $pesan = 'err|Berkas melebihi ' . (IMPOR_MAKS_BYTE / 1024 / 1024) . ' MB.';
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $pesan = 'err|Hanya berkas berakhiran .sql yang bisa diimpor.';
    } else {
        // is_uploaded_file memastikan berkasnya memang hasil unggahan, bukan
        // path lain yang disisipkan lewat isian form.
        $isi = is_uploaded_file($f['tmp_name']) ? file_get_contents($f['tmp_name']) : false;

        if ($isi === false || trim($isi) === '') {
            $pesan = 'err|Berkas kosong atau tidak terbaca.';
        } else {
            $sambung = sambung_sebagai_siswa($d);

            if (!$sambung['ok']) {
                $pesan = 'err|' . $sambung['pesan'];
            } else {
                $hasil = impor_sql($sambung['conn'], $isi);
                $sambung['conn']->close();

                $pesan = $hasil['ok']
                    ? 'ok|Impor selesai ke ' . $d['db_name'] . '. ' . $hasil['pesan']
                    : 'err|Impor gagal pada pernyataan ke-' . $hasil['jumlah'] . ': ' . $hasil['pesan'];
            }
        }
    }

    header('Location: databases.php?pesan=' . urlencode($pesan));
    exit;
}

// Menghapus seluruh tabel.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kosongkan') {
    $db_id = intval($_POST['db_id'] ?? 0);
    $d = ambil_database($conn, $db_id, $user_id, $isAdmin);

    if (!$d || !$d['username']) {
        $pesan = 'err|Database tidak ditemukan.';
    } else {
        $sambung = sambung_sebagai_siswa($d);

        if (!$sambung['ok']) {
            $pesan = 'err|' . $sambung['pesan'];
        } else {
            $hasil = kosongkan_database($sambung['conn']);
            $sambung['conn']->close();

            $pesan = ($hasil['ok'] ? 'ok|' : 'err|') . $d['db_name'] . ': ' . $hasil['pesan'];
        }
    }

    header('Location: databases.php?pesan=' . urlencode($pesan));
    exit;
}

// Menuliskan ulang kredensial ke .env. Berguna bila database dibuat sebelum
// project di-clone, sehingga penulisan otomatis saat itu terlewat.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tulis_env') {
    $db_id = intval($_POST['db_id'] ?? 0);

    $sqlT = "SELECT d.db_name, d.db_host, d.db_port, du.username, du.password, p.local_path
               FROM db_list d
               LEFT JOIN db_users du ON du.db_id = d.id
               LEFT JOIN projects p ON p.id = d.project_id
              WHERE d.id = ?" . ($isAdmin ? "" : " AND d.user_id = ?");
    $q = $conn->prepare($sqlT);
    if ($isAdmin) {
        $q->bind_param("i", $db_id);
    } else {
        $q->bind_param("ii", $db_id, $user_id);
    }
    $q->execute();
    $d = $q->get_result()->fetch_assoc();

    if (!$d || !$d['username']) {
        $pesan = 'err|Kredensial database tidak ditemukan.';
    } elseif (!$d['local_path']) {
        $pesan = 'err|Database ini tidak terhubung ke project mana pun.';
    } else {
        $hasil = write_db_env($d['local_path'], [
            'host' => $d['db_host'],
            'port' => (string) $d['db_port'],
            'nama' => $d['db_name'],
            'user' => $d['username'],
            'pass' => $d['password'],
        ]);
        $pesan = ($hasil['ok'] ? 'ok|' : 'err|') . $hasil['pesan'];
    }

    header('Location: databases.php?pesan=' . urlencode($pesan));
    exit;
}

if (isset($_GET['pesan']) && str_contains((string) $_GET['pesan'], '|')) {
    [$jenis, $teks] = explode('|', (string) $_GET['pesan'], 2);
    $teks = htmlspecialchars($teks);
    if ($jenis === 'ok') {
        $success = $teks;
    } else {
        $error = $teks;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = strtolower(trim($_POST['db_name'] ?? ''));
    $db_name = 'db_' . preg_replace('/[^a-z0-9_]/', '', $raw);
    $project_id = intval($_POST['project_id'] ?? 0);

    if ($raw === '' || $project_id <= 0) {
        $error = 'Nama database dan project harus diisi';
    } elseif (!valid_mysql_identifier($db_name)) {
        $error = 'Nama hanya boleh huruf kecil, angka, dan garis bawah (minimal 3 karakter)';
    } elseif ($admin === null) {
        $error = 'Pembuatan database belum dikonfigurasi. Isi db_admin_user dan '
               . 'db_admin_pass di config/env.php.';
    } else {
        // Nama user MySQL dibatasi 32 karakter, jadi tidak bisa sekadar
        // memakai nama database apa adanya.
        $db_user = substr($db_name, 0, 20) . '_' . substr(bin2hex(random_bytes(4)), 0, 5);
        $db_pass = bin2hex(random_bytes(12));

        $made = create_mysql_database($admin, $db_name, $db_user, $db_pass);

        if (!$made['ok']) {
            $error = $made['error'];
        } else {
            try {
                // Kedua baris dicatat dalam satu transaksi. Tanpa ini, kegagalan
                // pada baris kedua meninggalkan baris pertama sebagai yatim, dan
                // karena db_name bersifat unik, nama itu terkunci selamanya --
                // percobaan membuat ulang selalu gagal.
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    "INSERT INTO db_list (project_id, user_id, db_name, db_host, db_port)
                     VALUES (?, ?, ?, 'localhost', 3306)"
                );
                $stmt->bind_param("iis", $project_id, $user_id, $db_name);
                $stmt->execute();

                // Dibaca SEGERA setelah execute: memanggil prepare() lebih dulu
                // mereset insert_id menjadi 0, sehingga foreign key db_users
                // gagal. Perilakunya berbeda antar versi MySQL.
                $db_id = $conn->insert_id;

                $stmt2 = $conn->prepare(
                    "INSERT INTO db_users (db_id, username, password, privileges)
                     VALUES (?, ?, ?, 'ALL')"
                );
                $stmt2->bind_param("iss", $db_id, $db_user, $db_pass);
                $stmt2->execute();

                $conn->commit();

                // Langsung tuliskan ke .env project yang dipilih supaya siswa
                // tidak perlu menyalin manual -- langkah yang paling sering
                // salah ketik. Kegagalannya tidak membatalkan pembuatan
                // database; kredensialnya tetap ditampilkan untuk disalin.
                // Diambil langsung dari database, bukan dari daftar $projects --
                // daftar itu baru diisi setelah blok ini. Kepemilikan tetap
                // disaring agar tidak bisa menulis ke .env project orang lain.
                $tulisEnv = ['ok' => false, 'pesan' => 'Project tidak ditemukan.'];

                $sqlPr = "SELECT local_path FROM projects WHERE id = ?"
                       . ($isAdmin ? "" : " AND user_id = ?");
                $qPr = $conn->prepare($sqlPr);
                if ($isAdmin) {
                    $qPr->bind_param("i", $project_id);
                } else {
                    $qPr->bind_param("ii", $project_id, $user_id);
                }
                $qPr->execute();

                if ($pr = $qPr->get_result()->fetch_assoc()) {
                    $tulisEnv = write_db_env($pr['local_path'], [
                        'host' => 'localhost',
                        'port' => '3306',
                        'nama' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass,
                    ]);
                }

                // Password ditampilkan sekali ini saja: yang tersimpan di
                // db_users dipakai untuk mengingatkan siswa, bukan rahasia
                // panel, tapi tetap tidak diulang di daftar.
                $success = ($tulisEnv['ok']
                        ? "Database dibuat dan " . htmlspecialchars($tulisEnv['pesan']) . "<br>"
                        : "Database dibuat. " . htmlspecialchars($tulisEnv['pesan']) . "<br>")
                    . ""
                    . "Database: <code>" . htmlspecialchars($db_name) . "</code><br>"
                    . "User: <code>" . htmlspecialchars($db_user) . "</code><br>"
                    . "Password: <code>" . htmlspecialchars($db_pass) . "</code><br>"
                    . "<strong>Catat sekarang</strong> -- password tidak ditampilkan lagi.";
            } catch (mysqli_sql_exception $e) {
                error_log('Gagal mencatat database: ' . $e->getMessage());

                // Batalkan kedua baris, lalu buang database yang telanjur
                // dibuat -- sehingga tidak ada sisa di sisi mana pun dan nama
                // itu bisa dipakai lagi.
                try {
                    $conn->rollback();
                } catch (mysqli_sql_exception $ignored) {
                }
                drop_mysql_database($admin, $db_name, $db_user);

                $error = 'Gagal menyimpan catatan database. Coba lagi.';
            }
        }
    }
}

// Get projects
$projects = [];
$result = $conn->query("SELECT * FROM projects" . ($isAdmin ? "" : " WHERE user_id = $user_id"));
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Get databases
$databases = [];
$sqlDb = "SELECT db.*, pr.name AS project_name, us.username AS owner,
                   du.username AS db_user, du.password AS db_pass
            FROM db_list db
            LEFT JOIN projects pr ON db.project_id = pr.id
            LEFT JOIN db_users du ON du.db_id = db.id
            JOIN users us ON us.id = db.user_id"
       . ($isAdmin ? "" : " WHERE db.user_id = $user_id")
       . " ORDER BY db.created_at DESC";
$result = $conn->query($sqlDb);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row;
    }
}

layout_start('Database', $isAdmin ? 'Seluruh database di panel ini' : 'Database milik Anda', 'db', $user);
?>

<?php if ($error): ?><div class="note note-err"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="note note-ok"><?php echo $success; ?></div><?php endif; ?>

<div class="card" style="max-width:640px">
    <div class="card-h">
        <div>
            <h2>Database Baru</h2>
            <p>Kredensial dibuat otomatis dan langsung ditulis ke .env project</p>
        </div>
    </div>
    <div class="card-b">
        <?php if (!$projects): ?>
            <p class="dim">Tambahkan project lebih dulu sebelum membuat database.</p>
        <?php else: ?>
        <form method="POST" class="row">
            <div>
                <label for="d-proyek">Project</label>
                <select id="d-proyek" name="project_id" required>
                    <option value="">Pilih project</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="d-nama">Nama Database</label>
                <input id="d-nama" type="text" name="db_name" placeholder="toko" required>
            </div>
            <div class="row-fix">
                <button type="submit" class="btn"><?php echo ikon('plus'); ?>Buat</button>
            </div>
        </form>
        <div class="hint" style="margin-top:.85rem">Awalan <code>db_</code> ditambahkan otomatis.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-h">
        <div>
            <h2>Daftar Database</h2>
            <p><?php echo count($databases); ?> database terdaftar</p>
        </div>
    </div>

    <?php if (!$databases): ?>
        <div class="empty"><p>Belum ada database.</p></div>
    <?php else: ?>
    <div class="card-b" style="display:grid; gap:.85rem">
        <?php foreach ($databases as $db): ?>
            <div class="proyek">
                <div class="proyek-h">
                    <div>
                        <div class="proyek-n">
                            <?php echo htmlspecialchars($db['db_name']); ?>
                            <?php if ($isAdmin): ?>
                                <span class="pill pill-mute"><?php echo htmlspecialchars($db['owner']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="proyek-m">
                            Project <?php echo htmlspecialchars($db['project_name'] ?? '—'); ?>
                            &middot; dibuat <?php echo date('d M Y', strtotime($db['created_at'])); ?>
                        </div>
                    </div>
                    <?php if (PHPMYADMIN_URL !== ''): ?>
                        <a class="git-btn" target="_blank" rel="noopener noreferrer"
                           href="<?php echo htmlspecialchars(rtrim(PHPMYADMIN_URL, '/') . '/index.php?db=' . urlencode($db['db_name'])); ?>">phpMyAdmin</a>
                    <?php endif; ?>
                </div>

                <?php if ($db['db_user']): ?>
                    <details class="akses">
                        <summary>Lihat kredensial</summary>
                        <table class="akses-tabel">
                            <tr><th>Host</th><td><code><?php echo htmlspecialchars($db['db_host']); ?></code></td></tr>
                            <tr><th>Port</th><td><code><?php echo (int) $db['db_port']; ?></code></td></tr>
                            <tr><th>Database</th><td><code><?php echo htmlspecialchars($db['db_name']); ?></code></td></tr>
                            <tr><th>Pengguna</th><td><code><?php echo htmlspecialchars($db['db_user']); ?></code></td></tr>
                            <tr><th>Sandi</th><td><code><?php echo htmlspecialchars($db['db_pass']); ?></code></td></tr>
                        </table>

                        <div class="hint">Salin ke berkas <code>.env</code> project:</div>
                        <pre class="git-output show" id="env-<?php echo (int) $db['id']; ?>">DB_CONNECTION=mysql
DB_HOST=<?php echo htmlspecialchars($db['db_host']); ?>

DB_PORT=<?php echo (int) $db['db_port']; ?>

DB_DATABASE=<?php echo htmlspecialchars($db['db_name']); ?>

DB_USERNAME=<?php echo htmlspecialchars($db['db_user']); ?>

DB_PASSWORD=<?php echo htmlspecialchars($db['db_pass']); ?></pre>
                        <div style="margin-top:.6rem; display:flex; gap:.45rem; flex-wrap:wrap">
                            <button type="button" class="git-btn btn-salin"
                                    data-target="env-<?php echo (int) $db['id']; ?>">Salin</button>
                            <?php if ($db['project_name']): ?>
                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Tulis kredensial ini ke berkas .env project? Pengaturan DB_ yang ada sekarang akan diganti.');">
                                    <input type="hidden" name="action" value="tulis_env">
                                    <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                                    <button type="submit" class="git-btn">Tulis ke .env</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="git-actions" style="margin-top:.85rem; padding-top:.85rem; border-top:1px solid var(--line-soft)">
                    <form method="POST" enctype="multipart/form-data" class="alat-impor"
                          onsubmit="return this.berkas.files.length > 0;">
                        <input type="hidden" name="action" value="impor">
                        <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                        <input type="file" name="berkas" accept=".sql" required>
                        <button type="submit" class="git-btn">Impor SQL</button>
                    </form>

                    <span class="git-spacer"></span>

                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Hapus SEMUA tabel di <?php echo htmlspecialchars($db['db_name'], ENT_QUOTES); ?>? Seluruh data di dalamnya hilang permanen. Databasenya sendiri tetap ada. Tidak bisa dikembalikan.');">
                        <input type="hidden" name="action" value="kosongkan">
                        <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                        <button type="submit" class="git-btn git-btn-danger">Kosongkan Tabel</button>
                    </form>

                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Hapus database <?php echo htmlspecialchars($db['db_name'], ENT_QUOTES); ?>? SELURUH TABEL DAN DATANYA hilang permanen. Tidak bisa dikembalikan.');">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                        <button type="submit" class="git-btn git-btn-danger">Hapus Database</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Menyalin blok .env ke papan klip. navigator.clipboard hanya tersedia pada
// HTTPS atau localhost, jadi disediakan cara cadangan agar tetap berfungsi
// saat panel diakses lewat http biasa.
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-salin');
    if (!btn) return;

    const teks = document.getElementById(btn.dataset.target).textContent;
    const selesai = () => {
        const semula = btn.textContent;
        btn.textContent = 'Tersalin';
        setTimeout(() => btn.textContent = semula, 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(teks).then(selesai).catch(() => salinCadangan(teks, selesai));
    } else {
        salinCadangan(teks, selesai);
    }
});

function salinCadangan(teks, selesai) {
    const ta = document.createElement('textarea');
    ta.value = teks;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); selesai(); } catch (err) { /* biarkan disalin manual */ }
    document.body.removeChild(ta);
}
</script>

<?php layout_end(); ?>

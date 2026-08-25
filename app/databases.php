<?php
include '../config/config.php';
include '../config/auth.php';
include '../config/mysql-admin.php';
include '../config/env-writer.php';
include '../config/files.php';

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
        $success = '✅ ' . $teks;
    } else {
        $error = '❌ ' . $teks;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = strtolower(trim($_POST['db_name'] ?? ''));
    $db_name = 'db_' . preg_replace('/[^a-z0-9_]/', '', $raw);
    $project_id = intval($_POST['project_id'] ?? 0);

    if ($raw === '' || $project_id <= 0) {
        $error = '❌ Nama database dan project harus diisi';
    } elseif (!valid_mysql_identifier($db_name)) {
        $error = '❌ Nama hanya boleh huruf kecil, angka, dan garis bawah (minimal 3 karakter)';
    } elseif ($admin === null) {
        $error = '❌ Pembuatan database belum dikonfigurasi. Isi db_admin_user dan '
               . 'db_admin_pass di config/env.php.';
    } else {
        // Nama user MySQL dibatasi 32 karakter, jadi tidak bisa sekadar
        // memakai nama database apa adanya.
        $db_user = substr($db_name, 0, 20) . '_' . substr(bin2hex(random_bytes(4)), 0, 5);
        $db_pass = bin2hex(random_bytes(12));

        $made = create_mysql_database($admin, $db_name, $db_user, $db_pass);

        if (!$made['ok']) {
            $error = '❌ ' . $made['error'];
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
                        ? "✅ Database dibuat dan " . htmlspecialchars($tulisEnv['pesan']) . "<br>"
                        : "✅ Database dibuat. ⚠️ " . htmlspecialchars($tulisEnv['pesan']) . "<br>")
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

                $error = '❌ Gagal menyimpan catatan database. Coba lagi.';
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Databases - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        header h1 { font-size: 1.5rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav a { margin-right: 1.5rem; text-decoration: none; color: #667eea; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .section { background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; }
        .section h2 { margin-bottom: 1.5rem; color: #333; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input, select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #718096; }
        .akses { margin-top: .75rem; }
        .akses summary { cursor: pointer; color: #667eea; font-weight: 500; font-size: .92rem; user-select: none; }
        .akses summary:hover { text-decoration: underline; }
        .akses[open] summary { margin-bottom: .75rem; }
        /* Lipatannya diatur sendiri, tidak menumpang gaya bawaan browser:
           kalau bawaan itu tidak diterapkan, password akan langsung terpampang
           tanpa diklik. Sudah terjadi saat pengujian. */
        .akses > *:not(summary) { display: none; }
        .akses[open] > *:not(summary) { display: block; }
        .akses-tabel { border-collapse: collapse; margin-bottom: .75rem; }
        .akses-tabel th { text-align: left; padding: .25rem 1rem .25rem 0; font-weight: 500; color: #718096; font-size: .85rem; }
        .akses-tabel td { padding: .25rem 0; }
        .akses-judul { font-size: .85rem; color: #718096; margin-bottom: .35rem; }
        .env-blok { background: #1a202c; color: #e2e8f0; padding: .85rem; border-radius: 5px; font-family: ui-monospace, Consolas, monospace; font-size: .82rem; line-height: 1.5; overflow-x: auto; white-space: pre; }
        .btn-salin { margin-top: .5rem; padding: .35rem .9rem; font-size: .85rem; background: #4a5568; }
        .btn-salin:hover { background: #2d3748; }
        .akses-kosong { margin-top: .5rem; font-size: .88rem; color: #a0aec0; }
        .btn-danger { background: #a0aec0; padding: .5rem 1rem; font-size: .9rem; }
        .btn-danger:hover { background: #c53030; }
        .error { color: #e53e3e; background: #fed7d7; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .success { color: #22863a; background: #f6f8fa; border: 1px solid #28a745; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .db-item { background: #f7fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .db-item h3 { color: #333; margin-bottom: 0.5rem; }
        .db-meta { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        code { background: #edf2f7; padding: 0.2rem 0.5rem; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <h1>🚀 Sakuci cPanel</h1>
        <p>Database Management</p>
    </header>

    <div class="container">
        <div class="nav">
            <div>
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="add-project.php">➕ Add Project</a>
                <a href="databases.php">🗄️ Databases</a>
                <a href="phpmyadmin.php">📊 PhpMyAdmin</a>
                <?php if ($isAdmin): ?><a href="users.php">👥 Users</a><?php endif; ?>
            </div>
            <div>
                <span>👤 <?php echo htmlspecialchars($username); ?></span>
                <a href="../index.php?logout=1" class="btn btn-secondary" style="margin-left: 1rem; display: inline-block;">Logout</a>
            </div>
        </div>

        <div class="section">
            <h2>🗄️ Buat Database Baru</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Pilih Project</label>
                    <select name="project_id" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Database</label>
                    <input type="text" name="db_name" placeholder="Contoh: myapp_db" required>
                    <small style="color: #666; display: block; margin-top: 0.5rem;">Prefix 'db_' akan ditambahkan otomatis</small>
                </div>

                <button type="submit" class="btn">🗄️ Buat Database</button>
            </form>
        </div>

        <div class="section">
            <h2>📋 Database Anda</h2>

            <?php if (!empty($databases)): ?>
                <?php foreach ($databases as $db): ?>
                    <div class="db-item">
                        <h3><?php echo htmlspecialchars($db['db_name']); ?></h3>
                        <div class="db-meta">
                            Project: <strong><?php echo htmlspecialchars($db['project_name'] ?? '-'); ?></strong><?php if ($isAdmin): ?> &middot; Pemilik: <strong><?php echo htmlspecialchars($db['owner']); ?></strong><?php endif; ?><br>
                            Host: <code><?php echo htmlspecialchars($db['db_host']); ?></code>:<code><?php echo $db['db_port']; ?></code><br>
                            Created: <?php echo date('d M Y H:i', strtotime($db['created_at'])); ?>
                        </div>
                        <?php if ($db['db_user']): ?>
                            <details class="akses">
                                <summary>🔑 Lihat Akses</summary>
                                <table class="akses-tabel">
                                    <tr><th>Host</th><td><code><?php echo htmlspecialchars($db['db_host']); ?></code></td></tr>
                                    <tr><th>Port</th><td><code><?php echo (int) $db['db_port']; ?></code></td></tr>
                                    <tr><th>Database</th><td><code><?php echo htmlspecialchars($db['db_name']); ?></code></td></tr>
                                    <tr><th>Username</th><td><code><?php echo htmlspecialchars($db['db_user']); ?></code></td></tr>
                                    <tr><th>Password</th><td><code><?php echo htmlspecialchars($db['db_pass']); ?></code></td></tr>
                                </table>

                                <p class="akses-judul">Salin ke <code>.env</code> project Anda:</p>
                                <pre class="env-blok" id="env-<?php echo (int) $db['id']; ?>">DB_CONNECTION=mysql
DB_HOST=<?php echo htmlspecialchars($db['db_host']); ?>

DB_PORT=<?php echo (int) $db['db_port']; ?>

DB_DATABASE=<?php echo htmlspecialchars($db['db_name']); ?>

DB_USERNAME=<?php echo htmlspecialchars($db['db_user']); ?>

DB_PASSWORD=<?php echo htmlspecialchars($db['db_pass']); ?></pre>
                                <button type="button" class="btn btn-salin"
                                        data-target="env-<?php echo (int) $db['id']; ?>">📋 Salin</button>
                                <?php if ($db['project_name']): ?>
                                    <form method="POST" style="display:inline"
                                          onsubmit="return confirm('Tulis kredensial ini ke berkas .env project?

Pengaturan DB_ yang ada sekarang akan diganti.');">
                                        <input type="hidden" name="action" value="tulis_env">
                                        <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                                        <button type="submit" class="btn btn-salin">📝 Tulis ke .env</button>
                                    </form>
                                <?php endif; ?>
                            </details>
                        <?php else: ?>
                            <p class="akses-kosong">Kredensial tidak tercatat untuk database ini.</p>
                        <?php endif; ?>

                        <form method="POST" style="margin-top:.75rem"
                              onsubmit="return confirm('Hapus database <?php echo htmlspecialchars($db['db_name'], ENT_QUOTES); ?>?

SELURUH TABEL DAN DATANYA HILANG PERMANEN dan tidak bisa dikembalikan.');">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="db_id" value="<?php echo (int) $db['id']; ?>">
                            <button type="submit" class="btn btn-danger">🗑 Hapus Database</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Anda belum membuat database apapun.</p>
            <?php endif; ?>
        </div>
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
        btn.textContent = '✅ Tersalin';
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
    try { document.execCommand('copy'); selesai(); } catch (err) { /* biarkan pengguna menyalin manual */ }
    document.body.removeChild(ta);
}
</script>
</body>
</html>

<?php
include '../config/config.php';
include '../config/auth.php';

/**
 * Membuat PIN enam angka.
 *
 * Dipilih angka saja agar mudah dibacakan dan diketik siswa. Ruang tebakannya
 * hanya sejuta kemungkinan, jadi ini hanya layak dipakai bersama pembatasan
 * percobaan login -- tanpa itu, PIN semacam ini bisa ditebak mesin dalam
 * hitungan menit.
 */
function buat_pin(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$me = require_admin($conn);
$username = $me['username'];

$error = '';
$success = '';

// Pola POST-redirect-GET: menekan refresh tidak boleh mengulang penghapusan
// maupun pembuatan akun.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['action'] ?? '';
    $pesan = '';

    if ($aksi === 'tambah') {
        $nama = strtolower(trim($_POST['username'] ?? ''));
        $peran = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $nama)) {
            $pesan = 'err|Username hanya boleh huruf kecil, angka, dan garis bawah (3-32 karakter).';
        } else {
            $pw = buat_pin();

            try {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $email = $nama . '@siswa.local';
                $stmt = $conn->prepare(
                    "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("ssss", $nama, $email, $hash, $peran);
                $stmt->execute();

                $pesan = 'baru|' . $nama . '|' . $pw;
            } catch (mysqli_sql_exception $e) {
                $pesan = str_contains($e->getMessage(), 'Duplicate')
                    ? 'err|Username sudah dipakai.'
                    : 'err|Gagal membuat akun.';
            }
        }
    }

    if ($aksi === 'reset') {
        $id = intval($_POST['user_id'] ?? 0);

        $cek = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $cek->bind_param("i", $id);
        $cek->execute();
        $target = $cek->get_result()->fetch_assoc();

        if (!$target) {
            $pesan = 'err|Akun tidak ditemukan.';
        } else {
            $pw = buat_pin();
            $hash = password_hash($pw, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hash, $id);
            $upd->execute();

            $pesan = 'baru|' . $target['username'] . '|' . $pw;
        }
    }

    if ($aksi === 'hapus') {
        $id = intval($_POST['user_id'] ?? 0);

        if ($id === (int) $me['id']) {
            // Tanpa penjagaan ini, admin bisa menghapus dirinya sendiri dan
            // panel kehilangan pengelola terakhirnya.
            $pesan = 'err|Anda tidak bisa menghapus akun sendiri.';
        } else {
            // Project, database, dan antrean milik akun itu ikut terhapus lewat
            // foreign key. Database MySQL-nya TIDAK ikut -- itu harus dibuang
            // lewat menu Databases supaya penghapusannya disadari.
            $del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del->bind_param("i", $id);
            $del->execute();

            $pesan = $del->affected_rows === 1
                ? 'ok|Akun dihapus beserta seluruh project dan catatan databasenya.'
                : 'err|Akun tidak ditemukan.';
        }
    }

    header('Location: users.php?pesan=' . urlencode($pesan));
    exit;
}

$akunBaru = null;
if (isset($_GET['pesan']) && str_contains((string) $_GET['pesan'], '|')) {
    $bagian = explode('|', (string) $_GET['pesan']);

    if ($bagian[0] === 'baru' && count($bagian) === 3) {
        $akunBaru = ['username' => $bagian[1], 'password' => $bagian[2]];
    } elseif ($bagian[0] === 'ok') {
        $success = '✅ ' . htmlspecialchars($bagian[1]);
    } else {
        $error = '❌ ' . htmlspecialchars($bagian[1]);
    }
}

// Daftar akun beserta jumlah project dan databasenya
$users = [];
$q = $conn->query(
    "SELECT u.id, u.username, u.role, u.created_at,
            (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id) AS n_project,
            (SELECT COUNT(*) FROM db_list d WHERE d.user_id = u.id) AS n_db
       FROM users u ORDER BY u.role DESC, u.username"
);
while ($row = $q->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Sakuci cPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f5f7fa; color: #2d3748; }
        header { background: #667eea; color: white; padding: 1.5rem; }
        header h1 { font-size: 1.5rem; }
        header p { font-size: .9rem; opacity: .9; }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav a { margin-right: 1.5rem; text-decoration: none; color: #667eea; font-weight: 500; }
        .section { background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; }
        .section h2 { margin-bottom: 1.25rem; font-size: 1.15rem; }
        label { display: block; margin-bottom: .4rem; font-weight: 500; font-size: .92rem; }
        input, select { padding: .65rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; }
        .row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .btn { padding: .65rem 1.3rem; background: #667eea; color: white; border: none; border-radius: 5px; font-size: .95rem; font-weight: 500; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #5568d3; }
        .btn-kecil { padding: .35rem .8rem; font-size: .85rem; }
        .btn-abu { background: #718096; }
        .btn-abu:hover { background: #4a5568; }
        .btn-merah { background: #a0aec0; }
        .btn-merah:hover { background: #c53030; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .7rem .75rem; border-bottom: 1px solid #edf2f7; font-size: .92rem; }
        th { background: #f7fafc; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #4a5568; }
        tr:last-child td { border-bottom: none; }
        .tag { font-size: .72rem; padding: .15rem .55rem; border-radius: 999px; font-weight: 600; }
        .tag-admin { background: #fef3c7; color: #92400e; }
        .tag-user { background: #e6fffa; color: #234e52; }
        .alert { padding: .8rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: .93rem; }
        .alert-ok { background: #f0fff4; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-err { background: #fff5f5; color: #822727; border: 1px solid #feb2b2; }
        .kredensial { background: #fffbeb; border: 1px solid #fbbf24; padding: 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .kredensial code { background: white; padding: .25rem .6rem; border-radius: 4px; font-size: 1.05rem; font-weight: 600; }
        .muted { color: #a0aec0; }
        form.inline { display: inline; }
    </style>
</head>
<body>
<header>
    <h1>👥 Manajemen User</h1>
    <p>Masuk sebagai <?php echo htmlspecialchars($username); ?> (admin)</p>
</header>

<div class="container">
    <div class="nav">
        <div>
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="add-project.php">➕ Add Project</a>
            <a href="databases.php">🗄️ Databases</a>
            <a href="phpmyadmin.php">📊 PhpMyAdmin</a>
            <a href="users.php">👥 Users</a>
        </div>
        <a href="../index.php?logout=1" style="color:#e53e3e">Logout</a>
    </div>

    <?php if ($error): ?><div class="alert alert-err"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-ok"><?php echo $success; ?></div><?php endif; ?>

    <?php if ($akunBaru): ?>
        <div class="kredensial">
            <strong>Password untuk <?php echo htmlspecialchars($akunBaru['username']); ?>:</strong><br>
            <p style="margin:.6rem 0">
                Username <code><?php echo htmlspecialchars($akunBaru['username']); ?></code>
                &nbsp; Password <code><?php echo htmlspecialchars($akunBaru['password']); ?></code>
            </p>
            <span class="muted" style="font-size:.88rem">
                Catat sekarang &mdash; hanya hash-nya yang tersimpan, jadi password ini
                tidak bisa ditampilkan lagi. Bila terlewat, pakai tombol Reset.
            </span>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>Tambah Akun</h2>
        <form method="POST" class="row">
            <input type="hidden" name="action" value="tambah">
            <div>
                <label>Username</label>
                <input type="text" name="username" placeholder="mis. budi" required
                       pattern="[a-z][a-z0-9_]{2,31}"
                       title="Huruf kecil, angka, garis bawah. 3-32 karakter.">
            </div>
            <div>
                <label>Peran</label>
                <select name="role">
                    <option value="user">Siswa</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn">+ Tambah</button>
        </form>
        <p class="muted" style="margin-top:.8rem; font-size:.88rem">
            Password dibuat acak dan ditampilkan sekali setelah akun dibuat.
            Untuk mendaftarkan satu kelas sekaligus, pakai
            <code>php tools/add-user.php --acak budi siti eka</code> di terminal.
        </p>
    </div>

    <div class="section">
        <h2>Daftar Akun (<?php echo count($users); ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th><th>Peran</th><th>Project</th>
                    <th>Database</th><th>Dibuat</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        <?php if ((int) $u['id'] === (int) $me['id']): ?>
                            <span class="muted">(Anda)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="tag tag-<?php echo $u['role']; ?>">
                            <?php echo $u['role'] === 'admin' ? 'ADMIN' : 'SISWA'; ?>
                        </span>
                    </td>
                    <td><?php echo (int) $u['n_project']; ?></td>
                    <td><?php echo (int) $u['n_db']; ?></td>
                    <td class="muted"><?php echo date('d/m/y', strtotime($u['created_at'])); ?></td>
                    <td style="text-align:right; white-space:nowrap">
                        <form method="POST" class="inline"
                              onsubmit="return confirm('Buatkan password baru untuk <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?\n\nPassword lamanya langsung tidak berlaku.');">
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" class="btn btn-kecil btn-abu">Reset</button>
                        </form>
                        <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                            <form method="POST" class="inline"
                                  onsubmit="return confirm('Hapus akun <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?\n\n<?php echo (int) $u['n_project']; ?> project dan <?php echo (int) $u['n_db']; ?> catatan database miliknya ikut terhapus.\n\nDatabase MySQL-nya TIDAK ikut terhapus -- buang lewat menu Databases lebih dulu bila perlu.');">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn btn-kecil btn-merah">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

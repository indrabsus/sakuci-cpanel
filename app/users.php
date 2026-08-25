<?php
include '../config/config.php';
include '../config/auth.php';
include 'partials/layout.php';

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
        $success = htmlspecialchars($bagian[1]);
    } else {
        $error = htmlspecialchars($bagian[1]);
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

layout_start('Pengguna', 'Kelola akun siswa dan administrator', 'users', $me);
?>

<?php if ($error): ?><div class="note note-err"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="note note-ok"><?php echo $success; ?></div><?php endif; ?>

<?php if ($akunBaru): ?>
    <div class="note note-warn">
        <strong>Kredensial untuk <?php echo htmlspecialchars($akunBaru['username']); ?></strong><br>
        Pengguna <code><?php echo htmlspecialchars($akunBaru['username']); ?></code>
        &nbsp;&middot;&nbsp; PIN <code><?php echo htmlspecialchars($akunBaru['password']); ?></code><br>
        <span class="dim">Catat sekarang &mdash; hanya sidik hashnya yang tersimpan, jadi PIN ini
        tidak dapat ditampilkan lagi. Bila terlewat, gunakan tombol Reset.</span>
    </div>
<?php endif; ?>

<div class="card" style="max-width:640px">
    <div class="card-h">
        <div>
            <h2>Tambah Akun</h2>
            <p>PIN enam angka dibuat otomatis dan ditampilkan sekali</p>
        </div>
    </div>
    <div class="card-b">
        <form method="POST" class="row">
            <input type="hidden" name="action" value="tambah">
            <div>
                <label for="u-nama">Nama Pengguna</label>
                <input id="u-nama" type="text" name="username" placeholder="budi" required
                       pattern="[a-z][a-z0-9_]{2,31}"
                       title="Huruf kecil, angka, garis bawah. 3-32 karakter.">
            </div>
            <div>
                <label for="u-peran">Peran</label>
                <select id="u-peran" name="role">
                    <option value="user">Siswa</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="row-fix">
                <button type="submit" class="btn"><?php echo ikon('plus'); ?>Tambah</button>
            </div>
        </form>
        <div class="hint" style="margin-top:.9rem">
            Untuk mendaftarkan satu kelas sekaligus, jalankan
            <code>php tools/add-user.php --acak budi siti eka</code> di terminal server.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-h">
        <div>
            <h2>Daftar Akun</h2>
            <p><?php echo count($users); ?> akun terdaftar</p>
        </div>
    </div>
    <div class="card-b flush">
        <table>
            <thead>
                <tr>
                    <th>Pengguna</th><th>Peran</th>
                    <th class="num">Project</th><th class="num">Database</th>
                    <th class="num">Dibuat</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        <?php if ((int) $u['id'] === (int) $me['id']): ?>
                            <span class="dim">&mdash; Anda</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="pill <?php echo $u['role'] === 'admin' ? 'pill-warn' : 'pill-mute'; ?>">
                            <?php echo $u['role'] === 'admin' ? 'Admin' : 'Siswa'; ?>
                        </span>
                    </td>
                    <td class="num"><?php echo (int) $u['n_project']; ?></td>
                    <td class="num"><?php echo (int) $u['n_db']; ?></td>
                    <td class="num dim"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                    <td class="num">
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Buatkan PIN baru untuk <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?

PIN lamanya langsung tidak berlaku.');">
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" class="btn btn-2 btn-sm">Reset PIN</button>
                        </form>
                        <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Hapus akun <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?

<?php echo (int) $u['n_project']; ?> project dan <?php echo (int) $u['n_db']; ?> catatan database miliknya ikut terhapus.

Database MySQL-nya TIDAK ikut terhapus.');">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_end(); ?>

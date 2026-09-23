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
        $rawRole = $_POST['role'] ?? 'user';
        $peran = in_array($rawRole, ['admin', 'barcode', 'user'], true) ? $rawRole : 'user';

        if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $nama)) {
            $pesan = 'err|Username hanya boleh huruf kecil, angka, dan garis bawah (3-32 karakter).';
        } else {
            $pw = buat_pin();

            try {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $email = ($peran === 'barcode' || $nama === 'barcode')
                    ? $nama . '@barcode.sakuci.id'
                    : $nama . '@siswa.local';
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

// Daftar akun beserta jumlah project, database, dan status online
$users = [];
$q = $conn->query(
    "SELECT u.id, u.username, u.role, u.last_activity, u.created_at,
            (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id) AS n_project,
            (SELECT COUNT(*) FROM db_list d WHERE d.user_id = u.id) AS n_db
       FROM users u ORDER BY u.role DESC, u.username"
);
while ($row = $q->fetch_assoc()) {
    $row['is_online'] = is_user_online($row['last_activity']);
    $users[] = $row;
}

$total_all = count($users);
$total_online = 0;
foreach ($users as $u) {
    if ($u['is_online']) {
        $total_online++;
    }
}
$total_offline = $total_all - $total_online;

// Filter tab: semua, online, offline
$filter = trim((string) ($_GET['filter'] ?? 'semua'));
if (!in_array($filter, ['semua', 'online', 'offline'], true)) {
    $filter = 'semua';
}

$filtered_users = $users;
if ($filter === 'online') {
    $filtered_users = array_values(array_filter($users, fn($u) => $u['is_online']));
} elseif ($filter === 'offline') {
    $filtered_users = array_values(array_filter($users, fn($u) => !$u['is_online']));
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
                    <option value="user">Siswa (Domain *.ukk.sakuci.id &middot; Sakuci Framework)</option>
                    <option value="barcode">Barcode (Domain *.barcode.sakuci.id &middot; Bebas Koding Dari 0)</option>
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
        <div style="display:flex; align-items:center; justify-content:space-between; width:100%; flex-wrap:wrap; gap:.5rem;">
            <div>
                <h2>Daftar Akun</h2>
                <p><?php echo count($filtered_users); ?> dari <?php echo $total_all; ?> akun terdaftar</p>
            </div>
            <div style="display:flex; gap:.35rem; align-items:center;">
                <a href="users.php" class="btn btn-sm <?php echo $filter === 'semua' ? '' : 'btn-2'; ?>">Semua (<?php echo $total_all; ?>)</a>
                <a href="users.php?filter=online" class="btn btn-sm <?php echo $filter === 'online' ? '' : 'btn-2'; ?>" style="<?php echo $filter === 'online' ? 'background:#059669; border-color:#059669;' : ''; ?>">
                    <span class="dot-online pulse-online" style="margin-right:4px;"></span> Online (<?php echo $total_online; ?>)
                </a>
                <a href="users.php?filter=offline" class="btn btn-sm <?php echo $filter === 'offline' ? '' : 'btn-2'; ?>">Offline (<?php echo $total_offline; ?>)</a>
            </div>
        </div>
    </div>
    <div class="card-b flush">
        <table>
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th class="num">Project</th>
                    <th class="num">Database</th>
                    <th class="num">Dibuat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($filtered_users)): ?>
                <tr>
                    <td colspan="7" class="empty" style="padding:2rem; text-align:center;">
                        <span class="dim">Tidak ada akun pada filter "<?php echo htmlspecialchars($filter); ?>".</span>
                    </td>
                </tr>
            <?php else: ?>
            <?php foreach ($filtered_users as $u): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        <?php if ((int) $u['id'] === (int) $me['id']): ?>
                            <span class="dim">&mdash; Anda</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="pill pill-warn">Admin</span>
                        <?php elseif ($u['role'] === 'barcode'): ?>
                            <span class="pill pill-accent" style="background:#e0e7ff; color:#4338ca; font-weight:600">Barcode</span>
                        <?php else: ?>
                            <span class="pill pill-mute">Siswa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_online']): ?>
                            <span class="pill pill-ok" style="display:inline-flex; align-items:center; gap:5px;" title="Aktif dalam 5 menit terakhir">
                                <span class="dot-online pulse-online" style="width:6px; height:6px;"></span> Online
                            </span>
                            <span class="dim" style="font-size:.73rem; margin-left:4px;"><?php echo format_waktu_aktif($u['last_activity']); ?></span>
                        <?php else: ?>
                            <span class="pill pill-mute" style="display:inline-flex; align-items:center; gap:5px;">
                                <span class="dot-offline" style="width:6px; height:6px;"></span> Offline
                            </span>
                            <span class="dim" style="font-size:.73rem; margin-left:4px;"><?php echo format_waktu_aktif($u['last_activity']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="dim">&mdash;</span>
                        <?php elseif ($u['role'] === 'barcode'): ?>
                            <span class="pill pill-mute"><?php echo (int) $u['n_project']; ?></span>
                        <?php else: ?>
                            <span class="pill <?php echo (int)$u['n_project'] >= 1 ? 'pill-warn' : 'pill-mute'; ?>" style="<?php echo (int)$u['n_project'] >= 1 ? 'background:#fef3c7; color:#92400e; font-weight:600' : ''; ?>">
                                <?php echo (int) $u['n_project']; ?> / 1
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="dim">&mdash;</span>
                        <?php elseif ($u['role'] === 'barcode'): ?>
                            <span class="pill pill-mute"><?php echo (int) $u['n_db']; ?></span>
                        <?php else: ?>
                            <span class="pill <?php echo (int)$u['n_db'] >= 1 ? 'pill-warn' : 'pill-mute'; ?>" style="<?php echo (int)$u['n_db'] >= 1 ? 'background:#fef3c7; color:#92400e; font-weight:600' : ''; ?>">
                                <?php echo (int) $u['n_db']; ?> / 1
                            </span>
                        <?php endif; ?>
                    </td>
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
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_end(); ?>

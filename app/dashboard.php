<?php
include '../config/config.php';
include '../config/auth.php';
include 'partials/layout.php';

$user = require_login($conn);
$user_id = $user['id'];
$admin = is_admin($user);

// Pesan dari halaman lain, mis. setelah menambah project.
$pesanOk = '';
$pesanErr = '';
if (isset($_GET['pesan']) && str_contains((string) $_GET['pesan'], '|')) {
    [$jenis, $teks] = explode('|', (string) $_GET['pesan'], 2);
    if ($jenis === 'ok') {
        $pesanOk = htmlspecialchars($teks);
    } else {
        $pesanErr = htmlspecialchars($teks);
    }
}

// Admin melihat milik semua orang; siswa hanya miliknya sendiri.
$projects = [];
$sql = "SELECT p.*, u.username AS owner FROM projects p
        JOIN users u ON u.id = p.user_id"
     . ($admin ? "" : " WHERE p.user_id = $user_id")
     . " ORDER BY p.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

$db_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM db_list" . ($admin ? "" : " WHERE user_id = $user_id"));
if ($result) {
    $db_count = $result->fetch_assoc()['total'];
}

$terclone = 0;
foreach ($projects as $p) {
    if (is_dir($p['local_path'])) {
        $terclone++;
    }
}

layout_start(
    'Dashboard',
    $admin ? 'Seluruh project di panel ini' : 'Project milik Anda',
    'dashboard',
    $user,
    ['teks' => 'Tambah Project', 'href' => 'add-project.php']
);
?>

<?php if ($pesanOk): ?><div class="note note-ok"><?php echo $pesanOk; ?></div><?php endif; ?>
<?php if ($pesanErr): ?><div class="note note-err"><?php echo $pesanErr; ?></div><?php endif; ?>

<div class="stats">
    <div class="stat">
        <div class="stat-n"><?php echo count($projects); ?></div>
        <div class="stat-l">Project</div>
    </div>
    <div class="stat">
        <div class="stat-n"><?php echo $terclone; ?></div>
        <div class="stat-l">Sudah di-clone</div>
    </div>
    <div class="stat">
        <div class="stat-n"><?php echo $db_count; ?></div>
        <div class="stat-l">Database</div>
    </div>
</div>

<div class="card">
    <div class="card-h">
        <div>
            <h2>Project</h2>
            <p><?php echo $admin ? 'Termasuk milik seluruh pengguna' : 'Repo yang Anda daftarkan'; ?></p>
        </div>
    </div>

    <?php if (!$projects): ?>
        <div class="empty">
            <p>Belum ada project. Tambahkan repo Sakuci Framework untuk memulai.</p>
            <a class="btn" href="add-project.php"><?php echo ikon('plus'); ?>Tambah Project</a>
        </div>
    <?php else: ?>
        <div class="card-b" style="display:grid; gap:.85rem">
            <?php foreach ($projects as $project): ?>
                <?php
                $cloned = is_dir($project['local_path']);
                $url = SITE_DOMAIN !== ''
                    ? 'http://' . basename($project['local_path']) . '.' . SITE_DOMAIN
                    : '';
                ?>
                <div class="proyek" data-project="<?php echo $project['id']; ?>">
                    <div class="proyek-h">
                        <div>
                            <div class="proyek-n">
                                <?php echo htmlspecialchars($project['name']); ?>
                                <?php if ($admin): ?>
                                    <span class="pill pill-mute"><?php echo htmlspecialchars($project['owner']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="proyek-m mono"><?php echo htmlspecialchars($project['git_url']); ?></div>
                        </div>
                        <span class="pill <?php echo $cloned ? 'pill-accent' : 'pill-warn'; ?>">
                            <?php echo $cloned ? 'Aktif' : 'Belum di-clone'; ?>
                        </span>
                    </div>

                    <dl class="proyek-d">
                        <div><dt>Alamat</dt><dd>
                            <?php if ($cloned && $url !== ''): ?>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars(basename($project['local_path']) . '.' . SITE_DOMAIN); ?>
                                </a>
                            <?php else: ?>
                                <span class="dim"><?php echo htmlspecialchars($project['domain'] ?? '—'); ?></span>
                            <?php endif; ?>
                        </dd></div>
                        <div><dt>Branch</dt><dd class="mono"><?php echo htmlspecialchars($project['git_branch']); ?></dd></div>
                        <div><dt>Dibuat</dt><dd><?php echo date('d M Y', strtotime($project['created_at'])); ?></dd></div>
                    </dl>

                    <div class="git-actions">
                        <?php if ($cloned): ?>
                            <button class="git-btn" data-action="pull">Pull</button>
                            <span class="git-status">
                                <?php echo $project['last_pull']
                                    ? 'Terakhir ditarik ' . date('d M, H:i', strtotime($project['last_pull']))
                                    : 'Belum pernah ditarik'; ?>
                            </span>
                        <?php else: ?>
                            <button class="git-btn" data-action="clone">Clone</button>
                            <span class="git-status">Belum diambil ke server</span>
                        <?php endif; ?>

                        <span class="git-spacer"></span>

                        <?php if ($cloned && $url !== ''): ?>
                            <a class="git-btn git-btn-open" target="_blank" rel="noopener noreferrer"
                               href="<?php echo htmlspecialchars($url); ?>">Buka Web</a>
                        <?php endif; ?>
                        <a class="git-btn git-btn-file" href="files.php?project=<?php echo $project['id']; ?>">Berkas</a>
                        <button class="git-btn git-btn-danger" data-action="delete"
                                data-name="<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>">Hapus</button>
                    </div>
                    <pre class="git-output"></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php layout_end(true); ?>

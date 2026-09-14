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

// Mengambil informasi commit terakhir dari repositori lokal beserta timestamp
function get_project_commit(string $localPath, string $gitUrl = ''): ?array
{
    if (!is_dir($localPath . '/.git')) {
        return null;
    }
    $cmd = sprintf(
        "cd %s && git -c safe.directory=* log -1 --format='%%h|%%s|%%an|%%cr|%%H|%%ct' 2>/dev/null",
        escapeshellarg($localPath)
    );
    $out = @shell_exec($cmd);
    if (!$out || trim($out) === '') {
        return null;
    }
    $parts = explode('|', trim($out), 6);
    if (count($parts) < 4) {
        return null;
    }
    $url = '';
    if (!empty($gitUrl) && preg_match('#github\.com[:/]([^/]+)/([^/\.]+)(\.git)?#i', $gitUrl, $m)) {
        $url = 'https://github.com/' . $m[1] . '/' . $m[2] . '/commit/' . ($parts[4] ?? $parts[0]);
    }
    return [
        'short'     => $parts[0],
        'subject'   => $parts[1],
        'author'    => $parts[2],
        'relative'  => $parts[3],
        'hash'      => $parts[4] ?? $parts[0],
        'timestamp' => isset($parts[5]) ? (int) $parts[5] : 0,
        'url'       => $url,
    ];
}

// Admin melihat milik semua orang; siswa hanya miliknya sendiri.
$projects = [];
$sql = "SELECT p.*, u.username AS owner, d.id AS db_id, d.db_name FROM projects p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN db_list d ON d.project_id = p.id"
     . ($admin ? "" : " WHERE p.user_id = $user_id")
     . " ORDER BY p.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (empty($row['webhook_secret'])) {
            $newSecret = bin2hex(random_bytes(16));
            $pId = intval($row['id']);
            $conn->query("UPDATE projects SET webhook_secret = '$newSecret' WHERE id = $pId");
            $row['webhook_secret'] = $newSecret;
        }
        $projects[] = $row;
    }
}

// Simpan total seluruh project sebelum difilter
$total_projects_count = count($projects);

// Hitung metadata commit & aktivitas git untuk setiap project
foreach ($projects as &$p) {
    $cloned = is_dir($p['local_path']);
    $commit = $cloned ? get_project_commit($p['local_path'], $p['git_url']) : null;
    $p['cloned'] = $cloned;
    $p['commit'] = $commit;
    $lpTime = !empty($p['last_pull']) ? strtotime($p['last_pull']) : 0;
    $commitTime = $commit['timestamp'] ?? 0;
    $p['git_time'] = max($lpTime, $commitTime);
}
unset($p);

// Filter & Sorting khusus Admin
$q = '';
$sort = 'git'; // default: git terbaru
if ($admin) {
    $q = trim((string) ($_GET['q'] ?? ''));
    $sort = trim((string) ($_GET['sort'] ?? 'git'));

    // Filter pencarian berdasarkan nama pengguna, nama project, atau domain
    if ($q !== '') {
        $qLower = strtolower($q);
        $projects = array_values(array_filter($projects, function ($p) use ($qLower) {
            $owner = strtolower($p['owner'] ?? '');
            $name = strtolower($p['name'] ?? '');
            $domain = strtolower($p['domain'] ?? '');
            return str_contains($owner, $qLower) || str_contains($name, $qLower) || str_contains($domain, $qLower);
        }));
    }

    // Pilihan Sorting
    if ($sort === 'git') {
        // Urutkan berdasarkan aktivitas Git terbaru (last pull atau commit)
        usort($projects, function ($a, $b) {
            if ($b['git_time'] !== $a['git_time']) {
                return $b['git_time'] <=> $a['git_time'];
            }
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
    } elseif ($sort === 'terlama') {
        // Project terlama
        usort($projects, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });
    } elseif ($sort === 'owner') {
        // Berdasarkan nama pengguna A-Z
        usort($projects, function ($a, $b) {
            return strcasecmp($a['owner'], $b['owner']);
        });
    } else {
        // Project terbaru berdasarkan waktu dibuat
        $sort = 'terbaru';
        usort($projects, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
    }
}

$db_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM db_list" . ($admin ? "" : " WHERE user_id = $user_id"));
if ($result) {
    $db_count = (int) $result->fetch_assoc()['total'];
}

$siswa_count = 0;
if ($admin) {
    $resSiswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    if ($resSiswa) {
        $siswa_count = (int) $resSiswa->fetch_assoc()['total'];
    }
}

$terclone = 0;
foreach ($projects as $p) {
    if (is_dir($p['local_path'])) {
        $terclone++;
    }
}



// Tombol aksi di topbar: Admin tidak bisa menambah project. Siswa hanya bisa jika kuota < 1.
$aksiTopbar = [];
if (!$admin && count($projects) === 0) {
    $aksiTopbar = ['teks' => 'Tambah Project (0/1)', 'href' => 'add-project.php'];
}

layout_start(
    'Dashboard',
    $admin ? 'Mode Pemantauan &mdash; Memantau seluruh project dan database siswa' : 'Project milik Anda (Maksimal 1 project & 1 database per siswa)',
    'dashboard',
    $user,
    $aksiTopbar
);
?>

<?php if ($pesanOk): ?><div class="note note-ok"><?php echo $pesanOk; ?></div><?php endif; ?>
<?php if ($pesanErr): ?><div class="note note-err"><?php echo $pesanErr; ?></div><?php endif; ?>

<div class="stats">
    <?php if ($admin): ?>
        <div class="stat">
            <div class="stat-n"><?php echo $siswa_count; ?></div>
            <div class="stat-l">Total Siswa</div>
        </div>
        <div class="stat">
            <div class="stat-n"><?php echo count($projects); ?></div>
            <div class="stat-l">Project Siswa</div>
        </div>
        <div class="stat">
            <div class="stat-n"><?php echo $db_count; ?></div>
            <div class="stat-l">Database Siswa</div>
        </div>
    <?php else: ?>
        <div class="stat">
            <div class="stat-n"><?php echo count($projects); ?> / 1</div>
            <div class="stat-l">Project <?php echo count($projects) >= 1 ? '(Penuh)' : '(Tersedia)'; ?></div>
        </div>
        <div class="stat">
            <div class="stat-n"><?php echo $db_count; ?> / 1</div>
            <div class="stat-l">Database <?php echo $db_count >= 1 ? '(Penuh)' : '(Tersedia)'; ?></div>
        </div>
        <div class="stat">
            <div class="stat-n"><?php echo $terclone; ?></div>
            <div class="stat-l">Sudah di-clone</div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-h">
        <div>
            <h2><?php echo $admin ? 'Daftar Project Siswa' : 'Project Anda'; ?></h2>
            <p>
                <?php if ($admin): ?>
                    Menampilkan seluruh project yang dibuat oleh siswa di panel (Mode Pemantauan Admin)
                <?php else: ?>
                    <?php if (count($projects) >= 1): ?>
                        <span class="pill pill-warn" style="background:#fef3c7; color:#92400e; font-weight:600">1 / 1 Project Digunakan (Kuota Penuh)</span> &mdash; Didukung Vercel-like Git Sync
                    <?php else: ?>
                        Kuota: 0 / 1 Project &mdash; Tambahkan repo GitHub framework Sakuci Anda
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($admin): ?>
        <!-- Toolbar Filter & Search Khusus Role Admin -->
        <div class="admin-project-toolbar">
            <form method="GET" action="dashboard.php" id="form-admin-filter" class="admin-filter-form">
                <div class="filter-search-wrap">
                    <span class="filter-icon">🔍</span>
                    <input type="text" 
                           name="q" 
                           id="filter-search-input" 
                           class="filter-search-input" 
                           placeholder="Cari nama pengguna / siswa / project..." 
                           value="<?php echo htmlspecialchars($q); ?>" 
                           autocomplete="off">
                    <?php if ($q !== ''): ?>
                        <a href="dashboard.php<?php echo $sort !== 'git' ? '?sort=' . urlencode($sort) : ''; ?>" class="filter-clear-btn" title="Hapus pencarian">✕</a>
                    <?php endif; ?>
                </div>

                <div class="filter-sort-wrap">
                    <label for="filter-sort-select" class="filter-sort-label">Urutan:</label>
                    <select name="sort" id="filter-sort-select" class="filter-sort-select" onchange="this.form.submit()">
                        <option value="git" <?php echo $sort === 'git' ? 'selected' : ''; ?>>⚡ Git Terbaru (Aktivitas)</option>
                        <option value="terbaru" <?php echo $sort === 'terbaru' ? 'selected' : ''; ?>>🆕 Project Terbaru (Dibuat)</option>
                        <option value="terlama" <?php echo $sort === 'terlama' ? 'selected' : ''; ?>>⏳ Project Terlama</option>
                        <option value="owner" <?php echo $sort === 'owner' ? 'selected' : ''; ?>>👤 Nama Pengguna (A - Z)</option>
                    </select>
                </div>
            </form>

            <div class="filter-count-badge">
                Menampilkan <strong id="filter-shown-count"><?php echo count($projects); ?></strong> dari <?php echo $total_projects_count; ?> project
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$projects): ?>
        <div class="empty">
            <?php if ($admin): ?>
                <p>Belum ada siswa yang mendaftarkan project.</p>
            <?php else: ?>
                <p>Belum ada project. Anda memiliki kuota 1 project dan 1 database.</p>
                <a class="btn" href="add-project.php"><?php echo ikon('plus'); ?>Tambah Project (0/1)</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div id="empty-filter-message" class="empty" style="display:none; margin:1rem">
            <p style="margin-bottom:.5rem">Tidak ada project yang cocok dengan pencarian.</p>
            <a href="dashboard.php" class="btn btn-2 btn-sm">Reset Pencarian</a>
        </div>
        <div class="card-b" id="projects-container" style="display:grid; gap:.85rem">
            <?php foreach ($projects as $project): ?>
                <?php
                $cloned = $project['cloned'] ?? is_dir($project['local_path']);
                $hasToken = !empty($project['github_token']);
                $url = SITE_DOMAIN !== ''
                    ? 'https://' . basename($project['local_path']) . '.' . SITE_DOMAIN
                    : '';
                $commit = $project['commit'] ?? ($cloned ? get_project_commit($project['local_path'], $project['git_url']) : null);
                $modalData = [
                    'id'       => (int) $project['id'],
                    'name'     => $project['name'],
                    'branch'   => $project['git_branch'] ?: 'main',
                    'secret'   => $project['webhook_secret'],
                    'hasToken' => $hasToken,
                    'token'    => $project['github_token'] ?? '',
                    'gitUrl'   => $project['git_url'],
                ];
                ?>
                <div class="proyek" 
                     data-project="<?php echo $project['id']; ?>"
                     data-owner="<?php echo strtolower(htmlspecialchars($project['owner'])); ?>"
                     data-name="<?php echo strtolower(htmlspecialchars($project['name'])); ?>"
                     data-domain="<?php echo strtolower(htmlspecialchars($project['domain'] ?? '')); ?>">
                    <div class="proyek-h">
                        <div>
                            <div class="proyek-n">
                                <span><?php echo htmlspecialchars($project['name']); ?></span>
                                <?php if ($admin): ?>
                                    <span class="pill pill-mute"><?php echo htmlspecialchars($project['owner']); ?></span>
                                <?php endif; ?>
                                <span class="pill <?php echo $cloned ? 'pill-accent' : 'pill-warn'; ?>">
                                    <?php echo $cloned ? 'Aktif' : 'Belum di-clone'; ?>
                                </span>
                                <?php if ($cloned && $commit): ?>
                                    <span class="pill pill-mute mono" style="font-family:monospace; font-size:.76rem; background:rgba(99,102,241,0.08); color:var(--accent); border:1px solid rgba(99,102,241,0.2)" title="Commit saat ini: <?php echo htmlspecialchars($commit['subject']); ?> (<?php echo htmlspecialchars($commit['relative']); ?>)">
                                        📍 #<?php echo htmlspecialchars($commit['short']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($hasToken): ?>
                                    <span class="pill pill-accent" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd" title="Fitur push & akses repo privat aktif">🔑 Push Aktif</span>
                                <?php else: ?>
                                    <span class="pill pill-mute" title="Token belum diatur (Read-Only)">⚪ Push Nonaktif</span>
                                <?php endif; ?>
                            </div>
                            <div class="proyek-m mono"><?php echo htmlspecialchars($project['git_url']); ?></div>
                        </div>

                    </div>

                    <dl class="proyek-d">
                        <div><dt>Alamat Web</dt><dd>
                            <?php if ($cloned && $url !== ''): ?>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars(basename($project['local_path']) . '.' . SITE_DOMAIN); ?>
                                </a>
                            <?php else: ?>
                                <span class="dim"><?php echo htmlspecialchars($project['domain'] ?? '—'); ?></span>
                            <?php endif; ?>
                        </dd></div>
                        <div><dt>Branch</dt><dd class="mono"><span class="pill pill-mute" style="font-family:monospace; font-size:.78rem">🌿 <?php echo htmlspecialchars($project['git_branch'] ?: 'main'); ?></span></dd></div>
                        <div>
                            <dt>Commit Saat Ini</dt>
                            <dd>
                                <?php if ($cloned && $commit): ?>
                                    <div class="proyek-commit">
                                        <?php if (!empty($commit['url'])): ?>
                                            <a href="<?php echo htmlspecialchars($commit['url']); ?>" target="_blank" rel="noopener noreferrer"
                                               class="commit-hash-link" title="Buka commit <?php echo htmlspecialchars($commit['hash']); ?> di GitHub">
                                                <span>#<?php echo htmlspecialchars($commit['short']); ?></span>
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="commit-hash-link mono">#<?php echo htmlspecialchars($commit['short']); ?></span>
                                        <?php endif; ?>
                                        <span class="commit-msg" title="<?php echo htmlspecialchars($commit['subject']); ?>">
                                            <?php echo htmlspecialchars($commit['subject']); ?>
                                        </span>
                                        <span class="commit-meta" title="Oleh <?php echo htmlspecialchars($commit['author']); ?>">
                                            (<?php echo htmlspecialchars($commit['relative']); ?>)
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="dim"><?php echo $cloned ? 'Belum ada commit' : '—'; ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div><dt>Dibuat</dt><dd><?php echo date('d M Y', strtotime($project['created_at'])); ?></dd></div>
                    </dl>

                    <div class="git-actions">
                        <?php if ($cloned): ?>
                            <button class="git-btn" data-action="pull" title="Tarik pembaruan kode dari GitHub">Pull</button>
                            <?php if ($hasToken): ?>
                                <button class="git-btn git-btn-push" data-action="push" title="Commit & Push perubahan lokal di server ke GitHub">🚀 Push ke GitHub</button>
                            <?php endif; ?>
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

                        <button type="button" class="git-btn git-btn-webhook"
                                onclick="openGitSettings(<?php echo htmlspecialchars(json_encode($modalData), ENT_QUOTES); ?>)">
                            ⚡ Webhook &amp; Git
                        </button>

                        <a class="git-btn git-btn-db" href="<?php echo !empty($project['db_id']) ? 'databases.php?open_designer=' . $project['db_id'] : 'databases.php'; ?>" title="Buka Database &amp; Table Designer">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            <span>Database</span>
                        </a>
                        <a class="git-btn git-btn-file" href="files.php?project=<?php echo $project['id']; ?>" title="Buka Code Editor">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                            <span>Editor</span>
                        </a>
                        <button class="git-btn git-btn-danger" data-action="delete"
                                data-name="<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>">Hapus</button>
                    </div>
                    <pre class="git-output"></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Pengaturan Git & Webhook Auto-Deploy -->
<div id="settings-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeGitSettings()">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0; font-size:1.1rem; font-weight:600; display:flex; align-items:center; gap:.5rem">
                <span>⚡</span> Pengaturan Git: <span id="modal-project-title" style="color:var(--accent)"></span>
            </h3>
            <button type="button" class="modal-close" onclick="closeGitSettings()" title="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Bagian 1: Auto-Deploy Webhook -->
            <div style="background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:1.1rem">
                <div style="font-weight:600; font-size:.92rem; margin-bottom:.3rem; display:flex; align-items:center; gap:.4rem">
                    <span>⚡</span> GitHub Auto-Deploy Webhook (Vercel Style)
                </div>
                <p style="font-size:.81rem; color:var(--ink-2); margin-bottom:.8rem; line-height:1.5">
                    Pasang URL Webhook ini di repositori GitHub Anda. Setiap kali Anda melakukan <code>git push</code> dari laptop, server akan otomatis melakukan deploy dalam hitungan detik!
                </p>
                <div style="margin-bottom:.8rem">
                    <label style="font-size:.76rem; font-weight:600; text-transform:uppercase; color:var(--ink-3); display:block; margin-bottom:.25rem">Payload URL</label>
                    <div style="display:flex; gap:.4rem">
                        <input type="text" id="modal-webhook-url" readonly style="font-family:monospace; font-size:.79rem; background:var(--surface); flex:1; padding:.45rem .6rem">
                        <button type="button" class="btn btn-2 btn-sm" onclick="copyWebhookUrl(this)">Salin URL</button>
                    </div>
                </div>

                <details style="font-size:.8rem; color:var(--ink-2); cursor:pointer; margin-top:.6rem">
                    <summary style="font-weight:600; color:var(--accent)">Lihat Petunjuk Pemasangan di GitHub &rarr;</summary>
                    <ol style="margin:.6rem 0 .2rem 1.2rem; padding:0; line-height:1.6">
                        <li>Buka halaman repositori Anda di GitHub.</li>
                        <li>Klik menu <strong>Settings</strong> &rarr; <strong>Webhooks</strong> &rarr; tombol <strong>Add webhook</strong>.</li>
                        <li>Tempel URL di atas pada isian <strong>Payload URL</strong>.</li>
                        <li>Ubah <strong>Content type</strong> menjadi: <code>application/json</code>.</li>
                        <li>Pada bagian <strong>Secret</strong>, tempel: <code id="modal-secret-code" style="user-select:all; background:var(--surface); padding:.1rem .35rem; border:1px solid var(--line)"></code> <button type="button" class="btn btn-2 btn-sm" style="padding:.1rem .4rem; font-size:.72rem" onclick="copySecret(this)">Salin Secret</button></li>
                        <li>Pilih opsi <strong>Just the push event</strong>, pastikan <em>Active</em> tercentang, lalu klik <strong>Add webhook</strong>.</li>
                    </ol>
                </details>
            </div>

            <!-- Bagian 2: GitHub Personal Access Token (PAT) -->
            <form id="form-git-settings" onsubmit="saveGitSettings(event)" style="display:flex; flex-direction:column; gap:.9rem">
                <input type="hidden" name="project_id" id="modal-project-id">
                
                <div class="field">
                    <label for="modal-branch" style="font-weight:600">Branch Deployment</label>
                    <input type="text" name="git_branch" id="modal-branch" value="main" required style="width:100%">
                    <div class="hint">Branch yang dipantau untuk auto-deploy dan target push.</div>
                </div>

                <div class="field">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.3rem">
                        <label for="modal-token" style="font-weight:600; margin:0">GitHub Personal Access Token (PAT)</label>
                        <div id="modal-token-status"></div>
                    </div>
                    <div style="position:relative; display:flex; align-items:center">
                        <input type="password" name="github_token" id="modal-token" placeholder="ghp_xxxxxxxxxxxx" autocomplete="off" style="width:100%; padding-right:2.5rem">
                        <button type="button" id="btn-toggle-token" onclick="toggleTokenVisibility()" style="position:absolute; right:0.5rem; background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--ink-2); padding:0.2rem" title="Lihat/Sembunyikan Token">👁️</button>
                    </div>
                    <div class="hint" style="line-height:1.45; margin-top:.35rem">
                        Dibutuhkan jika repositori berstatus <strong>Private</strong> atau untuk mengaktifkan fitur <strong>Commit &amp; Push</strong> dua arah langsung dari cPanel. Dapatkan di GitHub: <em>Settings &rarr; Developer Settings &rarr; Personal Access Tokens (Classic)</em> dengan izin <code>repo</code>.
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.6rem; padding-top:.8rem; border-top:1px solid var(--line-soft)">
                    <button type="submit" id="btn-save-settings" class="btn">Simpan Pengaturan</button>
                    <button type="button" id="btn-hapus-token" class="btn btn-danger btn-sm" style="display:none" onclick="removeGitToken()">Hapus Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($admin): ?>
<script>
(function() {
    const searchInput = document.getElementById('filter-search-input');
    const projectsContainer = document.getElementById('projects-container');
    const shownCountEl = document.getElementById('filter-shown-count');
    const emptyMsg = document.getElementById('empty-filter-message');

    if (searchInput && projectsContainer) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const cards = projectsContainer.querySelectorAll('.proyek');
            let matchCount = 0;

            cards.forEach(card => {
                const owner = card.getAttribute('data-owner') || '';
                const name = card.getAttribute('data-name') || '';
                const domain = card.getAttribute('data-domain') || '';
                
                const match = (q === '') || owner.includes(q) || name.includes(q) || domain.includes(q);
                if (match) {
                    card.style.display = '';
                    matchCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (shownCountEl) shownCountEl.textContent = matchCount;
            if (emptyMsg) {
                emptyMsg.style.display = (matchCount === 0) ? 'block' : 'none';
            }
        });
    }
})();
</script>
<?php endif; ?>

<?php layout_end(true); ?>

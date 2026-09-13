<?php
include __DIR__ . '/../config/config.php';
include __DIR__ . '/../config/auth.php';
include __DIR__ . '/../config/jobs.php';
include __DIR__ . '/../config/files.php';

$user = require_login($conn);
$user_id = $user['id'];
$username = $user['username'];

$project_id = intval($_GET['project'] ?? 0);
$project = $project_id > 0 ? find_project($conn, $project_id, $user_id, is_admin($user)) : null;

if (!$project) {
    http_response_code(404);
    exit('Project tidak ditemukan.');
}

$root = $project['local_path'];
if (!is_dir($root)) {
    http_response_code(404);
    exit('Folder project belum ada. Jalankan Clone lebih dulu.');
}

$hasToken = !empty($project['github_token']);
$isAdmin = is_admin($user);
$initialPath = trim($_GET['path'] ?? '');
$webUrl = SITE_DOMAIN !== '' ? 'https://' . basename($root) . '.' . SITE_DOMAIN : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo htmlspecialchars($project['name']); ?> &middot; Sakuci VS Code Editor</title>

<!-- Font Lokal Inter & Styling VS Code IDE -->
<link rel="stylesheet" href="assets/panel.css">
<link rel="stylesheet" href="assets/monaco/vs/editor/editor.main.css">
<link rel="stylesheet" href="assets/vscode-ide.css?v=<?php echo filemtime(__DIR__ . '/assets/vscode-ide.css'); ?>">
<link rel="stylesheet" href="assets/git-actions.css">
</head>
<body>

<div id="vsc-workspace" class="vscode-workspace">
    <!-- Top Menubar / Toolbar -->
    <div class="vsc-topbar">
        <!-- Sisi Kiri: Tombol Kembali ke Dashboard & Tag Proyek -->
        <div class="vsc-topbar-left">
            <a href="dashboard.php" class="vsc-btn vsc-btn-back" onclick="return window.VSC_IDE ? window.VSC_IDE.confirmBackToDashboard(event) : confirm('Kembali ke Dashboard cPanel?');" title="Kembali ke Dashboard cPanel">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <span class="vsc-btn-back-text">Dashboard</span>
            </a>
            <span class="vsc-project-tag">📦 <?php echo htmlspecialchars($project['name']); ?></span>
        </div>

        <!-- Tengah: Tab Switcher (Berkas vs Editor vs Git) -->
        <div class="vsc-view-tabs" id="vsc-view-tabs">
            <button type="button" class="vsc-view-tab" id="tab-btn-explorer" onclick="window.VSC_IDE.switchView('explorer')">
                <span>📁</span> Berkas
            </button>
            <button type="button" class="vsc-view-tab active" id="tab-btn-editor" onclick="window.VSC_IDE.switchView('editor')">
                <span>📝</span> Editor <span id="view-tab-filename" style="opacity:0.8; font-family:var(--vsc-font-mono); font-size:11px"></span>
            </button>
            <button type="button" class="vsc-view-tab" id="tab-btn-git" onclick="window.VSC_IDE.switchView('git')">
                <span>🌿</span> Git <span id="vsc-git-badge-counter" class="vsc-git-badge-counter">0</span>
            </button>
        </div>

        <!-- Sisi Kanan: Aksi Cepat CLI, Simpan, Push & Buka Web -->
        <div class="vsc-topbar-right">
            <button type="button" class="vsc-btn vsc-btn-cli" id="vsc-btn-cli" onclick="openCliModal()" title="Sakuci CLI Helper (Controller, Model, Migrate)">
                ⚡ <span class="hide-mobile">CLI</span>
            </button>
            <button type="button" class="vsc-btn vsc-btn-save" id="vsc-btn-top-save" onclick="window.VSC_IDE.saveActiveFile()" title="Simpan berkas aktif (Ctrl+S)">
                💾 <span class="vsc-save-label">Simpan</span>
            </button>
            <?php if ($hasToken): ?>
                <button type="button" class="vsc-btn vsc-btn-push" onclick="openPushModal()" title="Commit &amp; Push ke GitHub">
                    🚀 <span class="hide-mobile">Push</span>
                </button>
            <?php endif; ?>
            <?php if ($webUrl !== ''): ?>
                <a class="vsc-btn" href="<?php echo htmlspecialchars($webUrl); ?>" target="_blank" rel="noopener noreferrer" title="Buka Web">
                    🌐 <span class="hide-mobile">Web</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Section: Activity Bar + Sidebar Explorer + Monaco Editor + Git View -->
    <div class="vsc-main">
        <!-- Activity Bar (Desktop) -->
        <div class="vsc-activity-bar">
            <button type="button" class="vsc-act-btn active" id="act-btn-explorer" title="Explorer (Daftar Berkas)" onclick="window.VSC_IDE.switchView('explorer')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </button>
            <button type="button" class="vsc-act-btn" id="act-btn-git" title="Git &amp; Source Control" onclick="window.VSC_IDE.switchView('git')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><line x1="6" y1="9" x2="6" y2="21"/></svg>
                <span id="vsc-act-git-dot" class="vsc-act-dot"></span>
            </button>
            <button type="button" class="vsc-act-btn" id="act-btn-cli" title="Sakuci CLI Helper (Controller, Model, Migrate)" onclick="openCliModal()">
                ⚡
            </button>
            <?php if ($hasToken): ?>
                <button type="button" class="vsc-act-btn" title="Commit &amp; Push ke GitHub" onclick="openPushModal()">
                    🚀
                </button>
            <?php endif; ?>
            <a href="dashboard.php" class="vsc-act-btn" onclick="return window.VSC_IDE ? window.VSC_IDE.confirmBackToDashboard(event) : confirm('Kembali ke Dashboard cPanel?');" title="Kembali ke Dashboard">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg>
            </a>
        </div>

        <!-- Sidebar / File Explorer -->
        <div id="vsc-sidebar" class="vsc-sidebar">
            <div class="vsc-sidebar-header">
                <span>EXPLORER: <?php echo htmlspecialchars($project['domain']); ?></span>
                <div class="vsc-sidebar-actions">
                    <button type="button" class="vsc-icon-btn" title="Buat Berkas Baru di Akar" onclick="window.VSC_IDE.promptNewFile('', event)">+📄</button>
                    <button type="button" class="vsc-icon-btn" title="Buat Folder Baru di Akar" onclick="window.VSC_IDE.promptNewFolder('', event)">+📁</button>
                    <button type="button" class="vsc-icon-btn" title="Muat Ulang Berkas" onclick="window.VSC_IDE.loadTree()">🔄</button>
                </div>
            </div>
            <div id="vsc-tree" class="vsc-tree-container">
                <div style="padding:16px; color:#777; font-size:12px; text-align:center">
                    <span class="vsc-spin"></span> Memuat berkas…
                </div>
            </div>
        </div>

        <!-- Resizer Bar (Desktop) -->
        <div id="vsc-resizer" class="vsc-resizer" title="Geser untuk mengatur lebar sidebar"></div>

        <!-- Editor Area -->
        <div class="vsc-editor-area">
            <!-- Tabs Bar -->
            <div id="vsc-tabs-bar" class="vsc-tabs-bar"></div>

            <!-- Breadcrumb Path & Subbar Actions -->
            <div class="vsc-subbar">
                <div id="vsc-breadcrumb" class="vsc-breadcrumb">
                    <span class="vsc-breadcrumb-file">Pilih berkas untuk mulai ngoding</span>
                </div>
                <div class="vsc-subbar-actions">
                    <button type="button" class="vsc-subbar-tool-btn" id="vsc-subbar-undo-btn" onclick="window.VSC_IDE && window.VSC_IDE.undo()" title="Undo / Batalkan perubahan (Ctrl+Z)">
                        ↩️ <span class="vsc-subbar-btn-text">Undo</span>
                    </button>
                    <button type="button" class="vsc-subbar-tool-btn" id="vsc-subbar-redo-btn" onclick="window.VSC_IDE && window.VSC_IDE.redo()" title="Redo / Ulangi perubahan (Ctrl+Y)">
                        ↪️ <span class="vsc-subbar-btn-text">Redo</span>
                    </button>
                    <button type="button" class="vsc-subbar-tool-btn" id="vsc-subbar-bottom-btn" onclick="window.VSC_IDE && window.VSC_IDE.scrollToBottom()" title="Gulir ke Baris Paling Bawah">
                        ⬇️ <span class="vsc-subbar-btn-text">Bawah</span>
                    </button>
                    <button type="button" class="vsc-subbar-save-btn" id="vsc-subbar-save-btn" onclick="window.VSC_IDE.saveActiveFile()" title="Simpan berkas (Ctrl+S)">
                        💾 <span class="vsc-subbar-save-text">Simpan</span>
                    </button>
                </div>
            </div>

            <!-- Read-Only Banner -->
            <div id="vsc-readonly-banner" class="vsc-readonly-banner">
                <div>
                    🔒 <strong>Mode Baca Saja (Read-Only)</strong> &mdash; Berkas ini dilacak oleh repositori Git. Hubungkan <strong>GitHub Personal Access Token (PAT)</strong> Anda di Dashboard untuk mengaktifkan pengeditan langsung dan sinkronisasi <strong>Commit &amp; Push</strong> dua arah. (Berkas konfigurasi <code>.env</code> tetap dapat disunting langsung).
                </div>
                <a href="dashboard.php" class="vsc-btn" style="font-size:11px; padding:2px 8px; background:rgba(0,0,0,0.3); border-color:#888; color:#fff">
                    Hubungkan PAT &rarr;
                </a>
            </div>

            <!-- Monaco Editor Container -->
            <div id="vsc-monaco-wrap" class="vsc-monaco-wrap" style="display:none">
                <div id="monaco-container"></div>
                <!-- Floating Save Button Khusus Layar Sentuh / Handphone -->
                <button type="button" id="vsc-mobile-fab-save" class="vsc-mobile-fab-save" onclick="window.VSC_IDE.saveActiveFile()" title="Simpan berkas aktif">
                    💾 <span id="vsc-mobile-fab-text">Simpan</span>
                </button>
            </div>

            <!-- Empty State -->
            <div id="vsc-empty-state" class="vsc-empty-state">
                <svg class="vsc-empty-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <path d="M16.5 9.4 7.55 4.24a1.78 1.78 0 0 0-2.5 1.55v12.42a1.78 1.78 0 0 0 2.5 1.55L16.5 14.6l-6.8-3.9 6.8-1.3Z"/>
                    <path d="m18.5 2.5-12 19"/>
                </svg>
                <div style="font-size:14px; font-weight:550; color:var(--vsc-text-main)">Sakuci VS Code Web Editor</div>
                <div class="vsc-shortcuts-list">
                    <div class="vsc-shortcut-item">
                        <span class="vsc-key">Pilih Berkas</span>
                        <span>Buka berkas di explorer</span>
                    </div>
                    <div class="vsc-shortcut-item">
                        <span class="vsc-key">h1 + Tab</span>
                        <span>Otomatis jadi &lt;h1&gt;&lt;/h1&gt;</span>
                    </div>
                    <div class="vsc-shortcut-item" style="cursor:pointer" onclick="openCliModal()">
                        <span class="vsc-key">⚡ Sakuci CLI</span>
                        <span>Buat Controller, Model, &amp; Migrate</span>
                    </div>
                    <div class="vsc-shortcut-item">
                        <span class="vsc-key">Ctrl + S</span>
                        <span>Simpan berkas seketika</span>
                    </div>
                    <div class="vsc-shortcut-item">
                        <span class="vsc-key">Ctrl + F</span>
                        <span>Cari &amp; Ganti kata</span>
                    </div>
                    <div class="vsc-shortcut-item">
                        <span class="vsc-key">F1</span>
                        <span>Command Palette</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Git View (Source Control & Commit History) -->
        <div id="vsc-git-view" class="vsc-git-view">
            <!-- Panel 1: Perubahan Berkas (Changes) -->
            <div class="vsc-git-panel">
                <div class="vsc-git-panel-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>🌿 Status Git: <code><span id="vsc-git-branch-name"><?php echo htmlspecialchars($project['git_branch'] ?: 'main'); ?></span></code></span>
                        <span style="font-size:11.5px; font-weight:normal; color:var(--vsc-text-dim)" id="vsc-git-status-summary">Memuat...</span>
                    </div>
                    <div style="display:flex; gap:6px">
                        <button type="button" class="vsc-btn btn-sm" onclick="window.VSC_IDE.fetchGitStatus()">🔄 Refresh</button>
                        <button type="button" class="vsc-btn btn-sm" style="background:#7f1d1d; border-color:#991b1b" onclick="window.VSC_IDE.discardAllChanges()">↩️ Batalkan Semua</button>
                        <?php if ($hasToken): ?>
                            <button type="button" class="vsc-btn vsc-btn-push btn-sm" onclick="openPushModal()">🚀 Commit &amp; Push</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="vsc-git-panel-body">
                    <div style="font-size:11.5px; font-weight:600; text-transform:uppercase; color:var(--vsc-text-dim); margin-bottom:8px">
                        Perubahan Berkas Lokal:
                    </div>
                    <div id="vsc-git-file-list" class="vsc-git-file-list">
                        <div style="color:var(--vsc-text-dim); font-size:12px"><span class="vsc-spin"></span> Memeriksa perubahan…</div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Riwayat Commit & Kembalikan ke Sebelumnya -->
            <div class="vsc-git-panel">
                <div class="vsc-git-panel-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>📜 Riwayat Commit (Commit History)</span>
                    </div>
                    <button type="button" class="vsc-btn btn-sm" onclick="window.VSC_IDE.fetchGitHistory()">🔄 Refresh Riwayat</button>
                </div>
                <div class="vsc-git-panel-body">
                    <p style="font-size:12px; color:var(--vsc-text-dim); margin:0 0 10px 0; line-height:1.4">
                        Daftar commit terbaru pada repositori. Anda dapat mengklik tombol <strong>↩️ Kembalikan ke Ini</strong> untuk me-rollback seluruh kode proyek ke commit tersebut.
                    </p>
                    <div id="vsc-commit-list" class="vsc-commit-list">
                        <div style="color:var(--vsc-text-dim); font-size:12px"><span class="vsc-spin"></span> Memuat riwayat commit…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Status Bar -->
    <div class="vsc-statusbar">
        <div class="vsc-status-left">
            <span class="vsc-status-item" style="cursor:pointer" onclick="window.VSC_IDE.switchView('git')">
                ⎇ <span id="vsc-status-branch"><?php echo htmlspecialchars($project['git_branch'] ?: 'main'); ?></span>
            </span>
            <?php if ($hasToken): ?>
                <button type="button" class="vsc-status-btn" onclick="openPushModal()" title="Kirim perubahan lokal ke GitHub">
                    ☁️ Push
                </button>
            <?php else: ?>
                <span class="vsc-status-item" style="opacity:0.7" title="GitHub PAT belum terhubung">⚪ Push Nonaktif</span>
            <?php endif; ?>
            <button type="button" class="vsc-status-btn vsc-status-save-btn" onclick="window.VSC_IDE.saveActiveFile()" title="Klik untuk simpan berkas aktif (Ctrl+S)">
                <span id="vsc-status-save">Siap</span>
            </button>
        </div>

        <div class="vsc-status-right">
            <span id="vsc-status-cursor" class="vsc-status-item">Ln 1, Col 1</span>
            <span class="vsc-status-item">Spasi: 4</span>
            <span class="vsc-status-item">UTF-8</span>
            <span id="vsc-status-lang" class="vsc-status-item">PHP</span>
        </div>
    </div>
</div>

<!-- Modal Commit & Push ke GitHub -->
<div id="push-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closePushModal()">
    <div class="modal-card" style="max-width:520px">
        <div class="modal-header">
            <h3 style="margin:0; font-size:1.05rem; font-weight:600; display:flex; align-items:center; gap:.5rem">
                <span>🚀</span> Commit &amp; Push ke GitHub
            </h3>
            <button type="button" class="modal-close" onclick="closePushModal()" title="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:.82rem; color:var(--ink-2); line-height:1.5; margin:0">
                Semua berkas yang Anda ubah atau buat di cPanel akan di-commit dan di-push ke branch <strong><code><?php echo htmlspecialchars($project['git_branch'] ?: 'main'); ?></code></strong> pada repositori GitHub Anda.
            </p>

            <form id="form-push" onsubmit="submitPushJob(event)" style="display:flex; flex-direction:column; gap:.85rem">
                <div class="field">
                    <label for="push-commit-msg" style="font-weight:600">Pesan Commit (Commit Message)</label>
                    <input type="text" id="push-commit-msg" name="commit_message" required
                           value="Update kodingan via Sakuci VS Code Editor"
                           placeholder="Contoh: Perbaiki layout navbar & rute web">
                    <div class="hint">Tuliskan ringkasan singkat perubahan yang Anda buat.</div>
                </div>

                <div id="push-progress" style="display:none; padding:.75rem; background:var(--bg); border:1px solid var(--line); border-radius:6px">
                    <div style="display:flex; align-items:center; gap:.5rem; font-size:.82rem; font-weight:550; color:var(--warn)" id="push-status-text">
                        <span class="spinner"></span> Menitipkan pekerjaan push…
                    </div>
                    <pre id="push-output-text" style="display:none; margin-top:.5rem; padding:.5rem; background:#14171f; color:#cdd3e0; border-radius:4px; font-size:.75rem; max-height:140px; overflow:auto; white-space:pre-wrap"></pre>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:.5rem; margin-top:.4rem">
                    <button type="button" class="btn btn-2 btn-sm" onclick="closePushModal()">Batal</button>
                    <button type="submit" id="btn-submit-push" class="btn btn-sm" style="background:#059669; border-color:#059669; color:#fff">
                        🚀 Kirim (Commit &amp; Push)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sakuci CLI -->
<div id="cli-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeCliModal()">
    <div class="modal-card vsc-cli-modal-card">
        <div class="modal-header">
            <h3 style="margin:0; font-size:1.05rem; font-weight:600; display:flex; align-items:center; gap:.5rem">
                <span>⚡</span> Sakuci CLI Helper
            </h3>
            <button type="button" class="modal-close" onclick="closeCliModal()" title="Tutup">&times;</button>
        </div>
        <div class="modal-body" style="padding:14px 18px">
            <!-- Tab Navigasi Aksi CLI -->
            <div class="vsc-cli-tabs">
                <button type="button" class="vsc-cli-tab active" id="cli-tab-btn-controller" onclick="switchCliTab('controller')">
                    🎮 Controller
                </button>
                <button type="button" class="vsc-cli-tab" id="cli-tab-btn-model" onclick="switchCliTab('model')">
                    📦 Model &amp; Migrasi
                </button>
                <button type="button" class="vsc-cli-tab" id="cli-tab-btn-migrate" onclick="switchCliTab('migrate')">
                    🚀 Migrate
                </button>
                <button type="button" class="vsc-cli-tab vsc-cli-tab-danger" id="cli-tab-btn-fresh" onclick="switchCliTab('fresh')">
                    ⚠️ Migrate:Fresh
                </button>
                <button type="button" class="vsc-cli-tab" id="cli-tab-btn-tools" onclick="switchCliTab('tools')">
                    🛠️ Alat Lainnya
                </button>
            </div>

            <!-- Panel 1: Controller -->
            <div class="vsc-cli-pane active" id="cli-pane-controller">
                <p style="font-size:12px; color:var(--vsc-text-dim); margin:0 0 10px 0; line-height:1.4">
                    Membuat file controller baru di <code>app/Controllers/</code>. Akhiran <code>Controller</code> otomatis ditambahkan jika belum ada.
                </p>
                <form onsubmit="runCliController(event)" style="display:flex; flex-direction:column; gap:10px">
                    <div class="field">
                        <label for="cli-ctrl-name" style="font-size:12px; font-weight:600">Nama Controller</label>
                        <input type="text" id="cli-ctrl-name" class="vsc-cli-input" placeholder="Contoh: UserController atau SiswaController" required autocomplete="off" autocapitalize="none">
                    </div>
                    <div style="display:flex; justify-content:flex-end">
                        <button type="submit" id="btn-cli-ctrl" class="vsc-btn" style="background:#2563eb; color:#fff; font-weight:600">
                            ▶ Buat Controller
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel 2: Model & Migrasi -->
            <div class="vsc-cli-pane" id="cli-pane-model" style="display:none">
                <p style="font-size:12px; color:var(--vsc-text-dim); margin:0 0 10px 0; line-height:1.4">
                    Membuat file model di <code>app/Models/</code> dan dapat sekaligus membuat berkas migrasi tabel di <code>database/migrations/</code>.
                </p>
                <form onsubmit="runCliModel(event)" style="display:flex; flex-direction:column; gap:10px">
                    <div class="field">
                        <label for="cli-model-name" style="font-size:12px; font-weight:600">Nama Model</label>
                        <input type="text" id="cli-model-name" class="vsc-cli-input" placeholder="Contoh: User, Siswa, atau Barang" required autocomplete="off" autocapitalize="none">
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer; user-select:none; color:var(--vsc-text-bright)">
                        <input type="checkbox" id="cli-model-migration" checked style="width:16px; height:16px; accent-color:#2563eb">
                        <span>Sertakan file migrasi database (<code>-m</code>)</span>
                    </label>
                    <div style="display:flex; justify-content:flex-end">
                        <button type="submit" id="btn-cli-model" class="vsc-btn" style="background:#2563eb; color:#fff; font-weight:600">
                            ▶ Buat Model
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel 3: Migrate -->
            <div class="vsc-cli-pane" id="cli-pane-migrate" style="display:none">
                <p style="font-size:12px; color:var(--vsc-text-dim); margin:0 0 12px 0; line-height:1.4">
                    Menjalankan seluruh file <code>.sql</code> migrasi yang belum dieksekusi di <code>database/migrations/</code> ke database MySQL proyek Anda.
                </p>
                <div style="display:flex; justify-content:flex-end">
                    <button type="button" id="btn-cli-migrate" class="vsc-btn" style="background:#059669; color:#fff; font-weight:600; padding:8px 16px" onclick="runCliMigrate()">
                        🚀 Jalankan Migrate
                    </button>
                </div>
            </div>

            <!-- Panel 4: Migrate:Fresh (Hapus Semua) -->
            <div class="vsc-cli-pane" id="cli-pane-fresh" style="display:none">
                <div class="vsc-cli-danger-card">
                    <div style="font-size:13px; font-weight:700; color:#ef4444; display:flex; align-items:center; gap:6px; margin-bottom:4px">
                        <span>⚠️</span> PERINGATAN PENGHAPUSAN DATA
                    </div>
                    <div style="font-size:12px; color:#fca5a5; line-height:1.4">
                        Perintah <code>migrate:fresh</code> akan <strong>MENGHAPUS SELURUH TABEL DAN DATA</strong> pada database proyek ini, lalu mengulang migrasi dari awal. Data yang terhapus tidak dapat dipulihkan!
                    </div>
                </div>

                <form onsubmit="runCliMigrateFresh(event)" style="display:flex; flex-direction:column; gap:10px; margin-top:12px">
                    <div class="field">
                        <label for="cli-fresh-pin" style="font-size:12px; font-weight:600; color:#fca5a5">
                            Konfirmasi Keamanan: Masukkan PIN Akun cPanel Anda
                        </label>
                        <input type="password" id="cli-fresh-pin" class="vsc-cli-input pin" inputmode="numeric" maxlength="32" placeholder="Masukkan PIN cPanel (••••••)" required autocomplete="current-password">
                        <div style="font-size:11px; color:var(--vsc-text-dim); margin-top:3px">
                            Gunakan PIN yang sama seperti saat Anda login ke cPanel.
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end">
                        <button type="submit" id="btn-cli-fresh" class="vsc-btn" style="background:#dc2626; border-color:#b91c1c; color:#fff; font-weight:600">
                            ⚠️ Hapus Semua &amp; Mulai Ulang (Migrate:Fresh)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel 5: Alat Lainnya -->
            <div class="vsc-cli-pane" id="cli-pane-tools" style="display:none">
                <p style="font-size:12px; color:var(--vsc-text-dim); margin:0 0 10px 0; line-height:1.4">
                    Perintah diagnostik &amp; utilitas framework:
                </p>
                <div style="display:flex; gap:8px; flex-wrap:wrap">
                    <button type="button" class="vsc-btn" onclick="runCliAction('db_check')">🔍 Uji Koneksi DB (db:check)</button>
                    <button type="button" class="vsc-btn" onclick="runCliAction('route_list')">🛣️ Daftar Rute (route:list)</button>
                    <button type="button" class="vsc-btn" onclick="runCliAction('view_clear')">🧹 Bersihkan Cache View (view:clear)</button>
                </div>
            </div>

            <!-- Terminal Output Window -->
            <div id="cli-terminal-wrap" style="margin-top:14px; display:none">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px">
                    <span style="font-size:11.5px; font-weight:600; color:var(--vsc-text-dim); text-transform:uppercase; letter-spacing:0.5px">
                        Konsol Terminal Sakuci:
                    </span>
                    <div style="display:flex; gap:6px">
                        <button type="button" id="cli-open-file-btn" class="vsc-btn" style="display:none; font-size:11px; padding:2px 8px; background:#047857; color:#fff" onclick="openCreatedCliFile()">
                            📄 Buka Berkas
                        </button>
                        <button type="button" class="vsc-btn" style="font-size:11px; padding:2px 8px" onclick="clearCliTerminal()">
                            Bersihkan
                        </button>
                    </div>
                </div>
                <div id="cli-terminal-output" class="vsc-cli-terminal"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.VSC_PROJECT = {
    id: <?php echo intval($project['id']); ?>,
    name: <?php echo json_encode($project['name']); ?>,
    domain: <?php echo json_encode($project['domain']); ?>,
    branch: <?php echo json_encode($project['git_branch'] ?: 'main'); ?>,
    hasToken: <?php echo $hasToken ? 'true' : 'false'; ?>,
    initialPath: <?php echo json_encode($initialPath); ?>
};

function openPushModal() {
    document.getElementById('push-modal').style.display = 'flex';
    document.getElementById('push-progress').style.display = 'none';
    document.getElementById('btn-submit-push').disabled = false;
    document.getElementById('push-commit-msg').focus();
}

function closePushModal() {
    document.getElementById('push-modal').style.display = 'none';
}

async function submitPushJob(e) {
    e.preventDefault();
    const commitMsg = document.getElementById('push-commit-msg').value.trim();
    const btn = document.getElementById('btn-submit-push');
    const progress = document.getElementById('push-progress');
    const statusText = document.getElementById('push-status-text');
    const outputText = document.getElementById('push-output-text');

    btn.disabled = true;
    progress.style.display = 'block';
    statusText.innerHTML = '<span class="spinner"></span> Menitipkan pekerjaan push…';
    statusText.style.color = 'var(--warn)';
    outputText.style.display = 'none';

    try {
        const res = await fetch(`api/push.php?project_id=${window.VSC_PROJECT.id}&commit_message=${encodeURIComponent(commitMsg)}`, {
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (!data.job_id) {
            statusText.textContent = data.message || data.error || 'Gagal menitipkan push.';
            statusText.style.color = 'var(--danger)';
            btn.disabled = false;
            return;
        }

        pollPushJob(data.job_id);
    } catch (err) {
        statusText.textContent = 'Gagal: ' + err.message;
        statusText.style.color = 'var(--danger)';
        btn.disabled = false;
    }
}

async function pollPushJob(jobId) {
    const statusText = document.getElementById('push-status-text');
    const outputText = document.getElementById('push-output-text');
    const btn = document.getElementById('btn-submit-push');
    const deadline = Date.now() + 15 * 60 * 1000;

    while (Date.now() < deadline) {
        await new Promise(r => setTimeout(r, 2000));
        try {
            const res = await fetch(`api/job-status.php?job_id=${jobId}`, { credentials: 'same-origin' });
            const data = await res.json();

            if (data.output) {
                outputText.textContent = data.output;
                outputText.style.display = 'block';
            }

            if (data.status === 'success') {
                statusText.innerHTML = '✅ <strong>Berhasil!</strong> ' + (data.message || 'Push ke GitHub selesai.');
                statusText.style.color = '#059669';
                setTimeout(() => {
                    alert('Commit & Push ke GitHub berhasil!');
                    closePushModal();
                    if (window.VSC_IDE) window.VSC_IDE.fetchGitStatus();
                }, 1000);
                return;
            }

            if (data.status === 'failed') {
                statusText.innerHTML = '❌ <strong>Gagal:</strong> ' + (data.message || 'Push gagal.');
                statusText.style.color = 'var(--danger)';
                btn.disabled = false;
                return;
            }

            statusText.innerHTML = '<span class="spinner"></span> ' + (data.message || 'Sedang berjalan…');
        } catch (err) {
            statusText.textContent = 'Gagal memantau: ' + err.message;
            statusText.style.color = 'var(--danger)';
            btn.disabled = false;
            return;
        }
    }
}

// ---------------- Sakuci CLI Modal Handler ---------------- //
let lastCreatedFile = null;

function openCliModal(tab = 'controller') {
    const modal = document.getElementById('cli-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    switchCliTab(tab);
    lastCreatedFile = null;
    const btnOpen = document.getElementById('cli-open-file-btn');
    if (btnOpen) btnOpen.style.display = 'none';
}

function closeCliModal() {
    const modal = document.getElementById('cli-modal');
    if (modal) modal.style.display = 'none';
}

function switchCliTab(tabName) {
    const tabs = ['controller', 'model', 'migrate', 'fresh', 'tools'];
    tabs.forEach(t => {
        const btn = document.getElementById('cli-tab-btn-' + t);
        const pane = document.getElementById('cli-pane-' + t);
        if (btn) btn.classList.toggle('active', t === tabName);
        if (pane) pane.style.display = t === tabName ? 'block' : 'none';
    });

    if (tabName === 'controller') {
        setTimeout(() => document.getElementById('cli-ctrl-name')?.focus(), 50);
    } else if (tabName === 'model') {
        setTimeout(() => document.getElementById('cli-model-name')?.focus(), 50);
    } else if (tabName === 'fresh') {
        setTimeout(() => document.getElementById('cli-fresh-pin')?.focus(), 50);
    }
}

function clearCliTerminal() {
    const wrap = document.getElementById('cli-terminal-wrap');
    const out = document.getElementById('cli-terminal-output');
    if (out) out.innerHTML = '';
    if (wrap) wrap.style.display = 'none';
    const btnOpen = document.getElementById('cli-open-file-btn');
    if (btnOpen) btnOpen.style.display = 'none';
    lastCreatedFile = null;
}

function setCliTerminalOutput(html, isRunning = false) {
    const wrap = document.getElementById('cli-terminal-wrap');
    const out = document.getElementById('cli-terminal-output');
    if (!wrap || !out) return;
    wrap.style.display = 'block';
    out.innerHTML = isRunning ? '<span class="vsc-spin"></span> Sedang memproses perintah Sakuci CLI…' : html;
    out.scrollTop = out.scrollHeight;
}

async function runCliAction(action, params = {}) {
    setCliTerminalOutput('', true);
    lastCreatedFile = null;
    const btnOpen = document.getElementById('cli-open-file-btn');
    if (btnOpen) btnOpen.style.display = 'none';

    const formData = new URLSearchParams();
    formData.append('project_id', window.VSC_PROJECT.id);
    formData.append('action', action);
    for (const key in params) {
        formData.append(key, params[key]);
    }

    try {
        const res = await fetch('api/sakuci-cli.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();

        if (data.status === 'success') {
            setCliTerminalOutput(data.output_html || data.output_raw || '✔ Selesai.');
            if (data.created_file) {
                lastCreatedFile = data.created_file;
                if (btnOpen) {
                    btnOpen.style.display = 'inline-flex';
                    btnOpen.textContent = '📄 Buka ' + data.created_file.split('/').pop();
                }
            }
            if (window.VSC_IDE) {
                window.VSC_IDE.loadTree();
                window.VSC_IDE.fetchGitStatus();
            }
        } else {
            const errHtml = '<span style="color:#ef4444;font-weight:600">✘ Gagal:</span> ' +
                            (data.output_html || data.error || 'Terjadi kesalahan');
            setCliTerminalOutput(errHtml);
        }
    } catch (err) {
        setCliTerminalOutput('<span style="color:#ef4444">Kesalahan jaringan: ' + err.message + '</span>');
    }
}

function runCliController(e) {
    e.preventDefault();
    const input = document.getElementById('cli-ctrl-name');
    const name = input ? input.value.trim() : '';
    if (!name) return;
    runCliAction('make_controller', { name });
}

function runCliModel(e) {
    e.preventDefault();
    const input = document.getElementById('cli-model-name');
    const chk = document.getElementById('cli-model-migration');
    const name = input ? input.value.trim() : '';
    if (!name) return;
    runCliAction('make_model', {
        name,
        with_migration: chk && chk.checked ? '1' : '0'
    });
}

function runCliMigrate() {
    runCliAction('migrate');
}

function runCliMigrateFresh(e) {
    e.preventDefault();
    const input = document.getElementById('cli-fresh-pin');
    const pin = input ? input.value.trim() : '';
    if (!pin) {
        alert('Silakan masukkan PIN cPanel Anda.');
        return;
    }
    if (!confirm('PERHATIAN: Seluruh tabel dan data akan DIHAPUS BERSIH secara permanen! Apakah Anda benar-benar yakin?')) {
        return;
    }
    runCliAction('migrate_fresh', { pin });
    if (input) input.value = '';
}

function openCreatedCliFile() {
    if (!lastCreatedFile) return;
    closeCliModal();
    if (window.VSC_IDE) {
        window.VSC_IDE.openFile(lastCreatedFile);
    }
}
</script>

<!-- Monaco Editor AMD Loader & VS Code IDE Controller -->
<script src="assets/monaco/vs/loader.js"></script>
<script src="assets/vscode-ide.js?v=<?php echo filemtime(__DIR__ . '/assets/vscode-ide.js'); ?>"></script>

</body>
</html>

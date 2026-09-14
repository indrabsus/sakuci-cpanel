// Menitipkan pekerjaan git ke antrean, lalu memantau statusnya.
//
// Panel tidak menjalankan git sendiri: PHP web di server dimatikan exec()-nya.
// API hanya membuat baris di job_queue, worker cron yang mengerjakan, dan
// halaman ini menanyakan hasilnya secara berkala.

const POLL_INTERVAL = 2000;
const POLL_TIMEOUT = 15 * 60 * 1000;

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.git-btn');
    if (!btn || !btn.dataset.action) return;

    if (btn.dataset.action === 'delete') {
        deleteProject(btn);
    } else {
        startGitAction(btn);
    }
});

async function deleteProject(btn) {
    const card = btn.closest('[data-project]');
    const projectId = card.dataset.project;
    const name = btn.dataset.name || 'project ini';

    if (!confirm(`Hapus project "${name}"?\n\nSELURUH FILE-nya di server ikut terhapus permanen, termasuk perubahan yang belum di-push ke GitHub.\n\nTidak bisa dikembalikan.`)) {
        return;
    }

    const ui = cardUi(card);
    ui.busy(true);
    ui.berjalan('Menghapus');

    try {
        const res = await fetch('api/delete-project.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'project_id=' + encodeURIComponent(projectId),
        });

        if (res.status === 401) {
            ui.status('err', 'Sesi berakhir, mengalihkan ke login…');
            setTimeout(() => location.href = '../index.php', 1200);
            return;
        }

        const data = await res.json();

        if (data.status === 'deleted') {
            if (!data.folder_terhapus && data.note) {
                alert(data.note);
            }
            card.remove();
            return;
        }

        ui.status('err', data.error || 'Gagal menghapus');
        ui.busy(false);
    } catch (err) {
        ui.status('err', 'Request gagal: ' + err.message);
        ui.busy(false);
    }
}

async function startGitAction(btn) {
    const card = btn.closest('[data-project]');
    const projectId = card.dataset.project;
    const action = btn.dataset.action;

    let commitMsg = '';
    if (action === 'pull' && !confirm('Pull akan memperbarui kode dari repositori GitHub. Lanjutkan?')) {
        return;
    }

    if (action === 'push') {
        const msg = prompt('Masukkan pesan commit (Commit Message):', 'Update via Sakuci cPanel');
        if (msg === null) return;
        commitMsg = msg.trim() || 'Update via Sakuci cPanel';
    }

    const ui = cardUi(card);
    ui.busy(true);

    let label = 'Menitipkan ' + action;
    if (action === 'clone') label = 'Menitipkan clone';
    else if (action === 'pull') label = 'Menitipkan pull';
    else if (action === 'push') label = 'Menitipkan push';

    ui.berjalan(label);
    ui.output('');

    try {
        let url = `api/${action}.php?project_id=${projectId}`;
        if (action === 'push') {
            url += `&commit_message=${encodeURIComponent(commitMsg)}`;
        }

        const data = await request(url, ui);
        if (!data) return;

        if (data.status === 'already_exists') {
            ui.status('ok', data.message);
            markCloned(btn);
            ui.busy(false);
            return;
        }

        if (!data.job_id) {
            ui.status('err', data.message || data.error || 'Gagal menitipkan pekerjaan');
            ui.busy(false);
            return;
        }

        ui.berjalan(data.message.replace(/…$/, ''));
        pollJob(data.job_id, btn, ui);
    } catch (err) {
        ui.status('err', 'Request gagal: ' + err.message);
        ui.busy(false);
    }
}

async function pollJob(jobId, btn, ui) {
    const deadline = Date.now() + POLL_TIMEOUT;

    while (Date.now() < deadline) {
        await sleep(POLL_INTERVAL);

        let data;
        try {
            data = await request(`api/job-status.php?job_id=${jobId}`, ui);
        } catch (err) {
            ui.status('err', 'Gagal memantau: ' + err.message);
            ui.busy(false);
            return;
        }
        if (!data) return;

        if (data.output) {
            ui.output(data.output);
        }

        if (data.status === 'success') {
            ui.status('ok', data.message);
            if (data.action === 'clone') markCloned(btn);
            ui.busy(false);
            return;
        }

        if (data.status === 'failed') {
            ui.status('err', data.message);
            ui.busy(false);
            return;
        }

        ui.berjalan(data.message.replace(/…$/, ''));
    }

    ui.status('err', 'Terlalu lama menunggu. Periksa status worker di server.');
    ui.busy(false);
}

async function request(url, ui) {
    const res = await fetch(url, { credentials: 'same-origin' });

    if (res.status === 401) {
        if (ui) ui.status('err', 'Sesi berakhir, mengalihkan ke login…');
        setTimeout(() => location.href = '../index.php', 1200);
        return null;
    }

    return res.json();
}

function markCloned(btn) {
    btn.dataset.action = 'pull';
    btn.textContent = 'Pull';
}

function cardUi(card) {
    const statusEl = card.querySelector('.git-status');
    const outputEl = card.querySelector('.git-output');
    const buttons = card.querySelectorAll('.git-btn');

    let ticker = null;
    let mulai = 0;
    let teksDasar = '';

    const gambarBerjalan = () => {
        const detik = Math.floor((Date.now() - mulai) / 1000);

        statusEl.className = 'git-status running';
        statusEl.textContent = '';

        const putar = document.createElement('span');
        putar.className = 'spinner';
        statusEl.appendChild(putar);
        statusEl.appendChild(document.createTextNode(teksDasar + ' (' + detik + ' detik)'));

        if (detik >= 8 && /menunggu/i.test(teksDasar)) {
            const catatan = document.createElement('small');
            catatan.className = 'git-catatan';
            catatan.textContent = 'Worker berjalan tiap menit, mohon tunggu.';
            statusEl.appendChild(catatan);
        }
    };

    const hentikanTicker = () => {
        if (ticker) {
            clearInterval(ticker);
            ticker = null;
        }
    };

    return {
        berjalan: (text) => {
            teksDasar = text;
            if (!ticker) {
                mulai = Date.now();
                ticker = setInterval(gambarBerjalan, 1000);
            }
            gambarBerjalan();
        },
        status: (kind, text) => {
            hentikanTicker();
            statusEl.className = 'git-status ' + kind;
            statusEl.textContent = text;
        },
        output: (text) => {
            outputEl.textContent = text;
            outputEl.classList.toggle('show', Boolean(text));
        },
        busy: (on) => {
            if (!on) hentikanTicker();
            buttons.forEach(b => b.disabled = on);
            card.classList.toggle('sedang-proses', on);
        },
    };
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ---------------- Modal Pengaturan Git & Webhook ---------------- //

let currentGitProject = null;

function openGitSettings(project) {
    currentGitProject = project;
    const modal = document.getElementById('settings-modal');
    if (!modal) return;

    document.getElementById('modal-project-title').textContent = project.name;
    document.getElementById('modal-project-id').value = project.id;
    document.getElementById('modal-branch').value = project.branch || 'main';

    const origin = window.location.origin;
    const webhookUrl = `${origin}/app/api/webhook.php?id=${project.id}&secret=${project.secret}`;
    document.getElementById('modal-webhook-url').value = webhookUrl;
    document.getElementById('modal-secret-code').textContent = project.secret;

    const tokenStatus = document.getElementById('modal-token-status');
    if (project.hasToken) {
        tokenStatus.innerHTML = '<span class="pill pill-accent" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd">🟢 GitHub PAT Terhubung &mdash; Fitur Push Aktif</span>';
        document.getElementById('btn-hapus-token').style.display = 'inline-block';
    } else {
        tokenStatus.innerHTML = '<span class="pill pill-mute">⚪ GitHub PAT Belum Terhubung (Read-Only)</span>';
        document.getElementById('btn-hapus-token').style.display = 'none';
    }

    document.getElementById('modal-token').value = project.token || '';
    document.getElementById('modal-token').type = 'password';
    const toggleBtn = document.getElementById('btn-toggle-token');
    if (toggleBtn) toggleBtn.textContent = '👁️';
    modal.style.display = 'flex';
}

function toggleTokenVisibility() {
    const input = document.getElementById('modal-token');
    const btn = document.getElementById('btn-toggle-token');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.textContent = '🙈';
    } else {
        input.type = 'password';
        if (btn) btn.textContent = '👁️';
    }
}

function closeGitSettings() {
    const modal = document.getElementById('settings-modal');
    if (modal) modal.style.display = 'none';
    currentGitProject = null;
}

function copyWebhookUrl(btn) {
    const input = document.getElementById('modal-webhook-url');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Tersalin! ✅';
        setTimeout(() => btn.textContent = originalText, 2000);
    });
}

function copySecret(btn) {
    const code = document.getElementById('modal-secret-code');
    navigator.clipboard.writeText(code.textContent).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Tersalin! ✅';
        setTimeout(() => btn.textContent = originalText, 2000);
    });
}

async function saveGitSettings(e) {
    e.preventDefault();
    if (!currentGitProject) return;

    const projectId = document.getElementById('modal-project-id').value;
    const branch = document.getElementById('modal-branch').value.trim();
    const token = document.getElementById('modal-token').value.trim();
    const btnSubmit = document.getElementById('btn-save-settings');

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Menyimpan…';

    const formData = new URLSearchParams();
    formData.append('project_id', projectId);
    formData.append('git_branch', branch);
    formData.append('github_token', token);

    try {
        const res = await fetch('api/update-project.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();

        if (data.status === 'success') {
            alert('Pengaturan berhasil disimpan!');
            location.reload();
        } else {
            alert('Gagal: ' + (data.error || data.message || 'Terjadi kesalahan'));
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan Pengaturan';
        }
    } catch (err) {
        alert('Gagal menyimpan: ' + err.message);
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Simpan Pengaturan';
    }
}

async function removeGitToken() {
    if (!currentGitProject) return;
    if (!confirm('Hapus GitHub PAT dari project ini? Fitur Push akan dinonaktifkan.')) return;

    const projectId = currentGitProject.id;
    const formData = new URLSearchParams();
    formData.append('project_id', projectId);
    formData.append('github_token', '');

    try {
        const res = await fetch('api/update-project.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert('Token berhasil dihapus.');
            location.reload();
        }
    } catch (err) {
        alert('Gagal: ' + err.message);
    }
}

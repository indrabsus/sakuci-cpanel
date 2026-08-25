// Menitipkan pekerjaan git ke antrean, lalu memantau statusnya.
//
// Panel tidak menjalankan git sendiri: PHP web di server dimatikan exec()-nya.
// API hanya membuat baris di job_queue, worker cron yang mengerjakan, dan
// halaman ini menanyakan hasilnya secara berkala.
//
// Kartu diperbarui di tempat, tanpa reload: halaman ini bisa dicapai lewat
// POST, sehingga memuat ulang akan mengirim ulang form penambahan project.

const POLL_INTERVAL = 2000;
const POLL_TIMEOUT = 15 * 60 * 1000;

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.git-btn');
    // Tautan "Buka Web" dan "File" memakai kelas yang sama demi tampilan,
    // tetapi tidak punya data-action dan harus dibiarkan berperilaku normal.
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
            ui.status('err', '❌ Sesi berakhir, mengalihkan ke login…');
            setTimeout(() => location.href = '../index.php', 1200);
            return;
        }

        const data = await res.json();

        if (data.status === 'deleted') {
            // Folder bisa gagal dihapus meski baris database sudah hilang;
            // beri tahu daripada diam-diam meninggalkan sisa di server.
            if (!data.folder_terhapus && data.note) {
                alert(data.note);
            }
            card.remove();
            return;
        }

        ui.status('err', '❌ ' + (data.error || 'Gagal menghapus'));
        ui.busy(false);
    } catch (err) {
        ui.status('err', '❌ Request gagal: ' + err.message);
        ui.busy(false);
    }
}

async function startGitAction(btn) {
    const card = btn.closest('[data-project]');
    const projectId = card.dataset.project;
    const action = btn.dataset.action;

    if (action === 'pull' && !confirm('Pull akan menimpa perubahan lokal yang belum di-commit di folder project ini. Lanjutkan?')) {
        return;
    }

    const ui = cardUi(card);
    ui.busy(true);
    ui.berjalan(action === 'clone' ? 'Menitipkan clone' : 'Menitipkan pull');
    ui.output('');

    try {
        const data = await request(`api/${action}.php?project_id=${projectId}`, ui);
        if (!data) return;

        // Sudah ter-clone: tidak ada pekerjaan yang perlu dipantau.
        if (data.status === 'already_exists') {
            ui.status('ok', '✅ ' + data.message);
            markCloned(btn);
            ui.busy(false);
            return;
        }

        if (!data.job_id) {
            ui.status('err', '❌ ' + (data.message || data.error || 'Gagal menitipkan pekerjaan'));
            ui.busy(false);
            return;
        }

        ui.berjalan(data.message.replace(/…$/, ''));
        pollJob(data.job_id, btn, ui);
    } catch (err) {
        ui.status('err', '❌ Request gagal: ' + err.message);
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
            ui.status('err', '❌ Gagal memantau: ' + err.message);
            ui.busy(false);
            return;
        }
        if (!data) return;

        if (data.output) {
            ui.output(data.output);
        }

        if (data.status === 'success') {
            ui.status('ok', '✅ ' + data.message);
            if (data.action === 'clone') markCloned(btn);
            ui.busy(false);
            return;
        }

        if (data.status === 'failed') {
            ui.status('err', '❌ ' + data.message);
            ui.busy(false);
            return;
        }

        ui.berjalan(data.message.replace(/…$/, ''));
    }

    ui.status('err', '❌ Terlalu lama menunggu. Periksa status worker di server.');
    ui.busy(false);
}

/** Mengembalikan JSON, atau null bila sesi berakhir (halaman dialihkan). */
async function request(url, ui) {
    const res = await fetch(url, { credentials: 'same-origin' });

    if (res.status === 401) {
        ui.status('err', '❌ Sesi berakhir, mengalihkan ke login…');
        setTimeout(() => location.href = '../index.php', 1200);
        return null;
    }

    return res.json();
}

function markCloned(btn) {
    btn.dataset.action = 'pull';
    btn.textContent = '⬇️ Pull';
}

function cardUi(card) {
    const statusEl = card.querySelector('.git-status');
    const outputEl = card.querySelector('.git-output');
    const buttons = card.querySelectorAll('.git-btn');

    // Penghitung detik berdetak sendiri tiap satu detik, terpisah dari siklus
    // pemantauan yang dua detik sekali. Tanpa itu tampilan terasa membeku,
    // terutama saat menunggu giliran worker yang bisa mencapai satu menit.
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

        // Antrean dikerjakan cron tiap menit, jadi menunggu sampai 60 detik itu
        // wajar. Tanpa keterangan ini orang mengira panelnya menggantung.
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
        /** Keadaan sedang berjalan: berputar, dengan detik yang terus bertambah. */
        berjalan: (text) => {
            teksDasar = text;
            if (!ticker) {
                mulai = Date.now();
                ticker = setInterval(gambarBerjalan, 1000);
            }
            gambarBerjalan();
        },
        /** Keadaan selesai: ticker dimatikan, teks statis. */
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

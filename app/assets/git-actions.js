// Runs clone/pull against the API and reports progress inside the project card.
// Cards are updated in place: these pages can be reached via POST, so reloading
// would re-submit the form that created the project.
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.git-btn');
    if (btn) {
        runGitAction(btn);
    }
});

async function runGitAction(btn) {
    const card = btn.closest('[data-project]');
    const projectId = card.dataset.project;
    const action = btn.dataset.action;

    if (action === 'pull' && !confirm('Pull akan menimpa perubahan lokal yang belum di-commit di folder project ini. Lanjutkan?')) {
        return;
    }

    const statusEl = card.querySelector('.git-status');
    const outputEl = card.querySelector('.git-output');
    const buttons = card.querySelectorAll('.git-btn');

    buttons.forEach(b => b.disabled = true);
    outputEl.classList.remove('show');
    outputEl.textContent = '';
    statusEl.className = 'git-status running';
    statusEl.textContent = action === 'clone' ? '⏳ Cloning…' : '⏳ Pulling…';

    try {
        const res = await fetch(`api/${action}.php?project_id=${projectId}`, { credentials: 'same-origin' });

        if (res.status === 401) {
            statusEl.className = 'git-status err';
            statusEl.textContent = '❌ Sesi berakhir, mengalihkan ke login…';
            setTimeout(() => location.href = '../index.php', 1200);
            return;
        }

        const data = await res.json();
        const ok = data.status === 'success' || data.status === 'already_exists';

        statusEl.className = ok ? 'git-status ok' : 'git-status err';
        statusEl.textContent = (ok ? '✅ ' : '❌ ') + (data.message || data.error || 'Unknown response');

        const detail = data.output || data.error;
        if (detail && detail !== data.message) {
            outputEl.textContent = detail;
            outputEl.classList.add('show');
        }

        // Once cloned, the only action left for this project is pull.
        if (ok && action === 'clone') {
            btn.dataset.action = 'pull';
            btn.textContent = '⬇️ Pull';
        }
    } catch (e) {
        statusEl.className = 'git-status err';
        statusEl.textContent = '❌ Request gagal: ' + e.message;
    } finally {
        buttons.forEach(b => b.disabled = false);
    }
}

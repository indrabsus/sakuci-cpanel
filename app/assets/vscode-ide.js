/**
 * VS Code Web IDE for Sakuci cPanel
 * Powered by Monaco Editor (100% Local Self-Hosted Assets)
 * With HTML/PHP Snippets, Emmet, Git Changes Tracker & Commit Rollback
 */

(function () {
    'use strict';

    // Konfigurasi Project dari window.VSC_PROJECT
    const project = window.VSC_PROJECT || {};
    const projectId = project.id;

    // Status internal
    let editor = null;
    let tabs = [];
    let activeTabPath = null;
    let treeData = [];
    let gitStatusData = { files: [], total_changes: 0, branch: project.branch || 'main' };
    let gitStatusMap = {}; // path => { type, label }
    let currentMode = window.innerWidth <= 768 && !project.initialPath ? 'explorer' : 'editor';
    const expandedFolders = new Set(['', 'app', 'routes', 'public', 'resources']);

    // Elemen DOM
    const elWorkspace = document.getElementById('vsc-workspace');
    const elSidebar = document.getElementById('vsc-sidebar');
    const elTree = document.getElementById('vsc-tree');
    const elTabsBar = document.getElementById('vsc-tabs-bar');
    const elBreadcrumb = document.getElementById('vsc-breadcrumb');
    const elReadonlyBanner = document.getElementById('vsc-readonly-banner');
    const elMonacoWrap = document.getElementById('vsc-monaco-wrap');
    const elEmptyState = document.getElementById('vsc-empty-state');
    const elStatusCursor = document.getElementById('vsc-status-cursor');
    const elStatusLang = document.getElementById('vsc-status-lang');
    const elStatusSave = document.getElementById('vsc-status-save');
    const elStatusBranch = document.getElementById('vsc-status-branch');

    // Tab switcher buttons
    const elBtnTabExplorer = document.getElementById('tab-btn-explorer');
    const elBtnTabEditor = document.getElementById('tab-btn-editor');
    const elBtnTabGit = document.getElementById('tab-btn-git');
    const elGitBadgeCounter = document.getElementById('vsc-git-badge-counter');
    const elActGitDot = document.getElementById('vsc-act-git-dot');
    const elViewTabFilename = document.getElementById('view-tab-filename');

    // Git View Elements
    const elGitView = document.getElementById('vsc-git-view');
    const elGitFileList = document.getElementById('vsc-git-file-list');
    const elCommitList = document.getElementById('vsc-commit-list');
    const elGitBranchName = document.getElementById('vsc-git-branch-name');
    const elGitStatusSummary = document.getElementById('vsc-git-status-summary');

    // ---------------- 1. Otomatisasi HTML / PHP Snippets & Emmet ---------------- //
    function registerSnippetsAndEmmet() {
        const htmlTags = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span', 'a',
            'button', 'form', 'input', 'textarea', 'select', 'option', 'label',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'ul', 'ol', 'li',
            'header', 'footer', 'nav', 'main', 'section', 'article', 'aside',
            'script', 'style', 'link', 'img', 'meta', 'title', 'head', 'body',
            'container', 'row', 'col', 'card', 'badge', 'alert'
        ];

        const createHtmlSuggestions = (range) => {
            const suggestions = [];

            htmlTags.forEach(tag => {
                let snippet = `<${tag}>$0</${tag}>`;
                if (tag === 'input') snippet = `<input type="text" name="$1" class="$2" placeholder="$0">`;
                else if (tag === 'img') snippet = `<img src="$1" alt="$0">`;
                else if (tag === 'a') snippet = `<a href="$1">$0</a>`;
                else if (tag === 'link') snippet = `<link rel="stylesheet" href="$0">`;
                else if (tag === 'meta') snippet = `<meta name="$1" content="$0">`;
                else if (tag === 'button') snippet = `<button type="submit" class="btn">$0</button>`;
                else if (tag === 'form') snippet = `<form method="POST" action="$1">\n\t$0\n</form>`;
                else if (tag === 'container') snippet = `<div class="container">\n\t$0\n</div>`;
                else if (tag === 'row') snippet = `<div class="row">\n\t$0\n</div>`;
                else if (tag === 'col') snippet = `<div class="col">\n\t$0\n</div>`;
                else if (tag === 'card') snippet = `<div class="card">\n\t<div class="card-body">\n\t\t$0\n\t</div>\n</div>`;
                else if (tag === 'alert') snippet = `<div class="alert alert-info">\n\t$0\n</div>`;

                suggestions.push({
                    label: tag,
                    kind: monaco.languages.CompletionItemKind.Snippet,
                    insertText: snippet,
                    insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                    documentation: `Tag HTML otomatis <${tag}>...</${tag}>`,
                    range: range,
                });
            });

            return suggestions;
        };

        // Daftarkan Provider untuk HTML
        monaco.languages.registerCompletionItemProvider('html', {
            provideCompletionItems: function (model, position) {
                const word = model.getWordUntilPosition(position);
                const range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn
                };
                return { suggestions: createHtmlSuggestions(range) };
            }
        });

        // Daftarkan Provider untuk PHP
        monaco.languages.registerCompletionItemProvider('php', {
            provideCompletionItems: function (model, position) {
                const word = model.getWordUntilPosition(position);
                const range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn
                };

                const suggestions = createHtmlSuggestions(range);

                const phpSnippets = [
                    { label: 'php', snippet: '<?php\n\n$0', desc: 'Tag pembuka PHP' },
                    { label: 'echo', snippet: '<?= $0 ?>', desc: 'Short echo tag' },
                    { label: 'pecho', snippet: '<?php echo $0; ?>', desc: 'PHP echo standar' },
                    { label: 'if', snippet: 'if ($1) {\n\t$0\n}', desc: 'Pernyataan if' },
                    { label: 'ifelse', snippet: 'if ($1) {\n\t$2\n} else {\n\t$0\n}', desc: 'Pernyataan if else' },
                    { label: 'foreach', snippet: 'foreach ($$1 as $$2) {\n\t$0\n}', desc: 'Loop foreach' },
                    { label: 'foreachend', snippet: '<?php foreach ($$1 as $$2): ?>\n\t$0\n<?php endforeach; ?>', desc: 'Template loop foreach' },
                    { label: 'ifend', snippet: '<?php if ($1): ?>\n\t$0\n<?php endif; ?>', desc: 'Template blok if' },
                    { label: 'fun', snippet: 'function $1($2)\n{\n\t$0\n}', desc: 'Fungsi PHP baru' },
                    { label: 'try', snippet: 'try {\n\t$1\n} catch (Exception $e) {\n\t$0\n}', desc: 'Blok try catch' },
                ];

                phpSnippets.forEach(p => {
                    suggestions.push({
                        label: p.label,
                        kind: monaco.languages.CompletionItemKind.Snippet,
                        insertText: p.snippet,
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: p.desc,
                        range: range,
                    });
                });

                return { suggestions: suggestions };
            }
        });
    }

    // ---------------- 2. Inisialisasi Monaco Editor ---------------- //
    function initMonaco() {
        require.config({ paths: { vs: 'assets/monaco/vs' } });

        require(['vs/editor/editor.main'], function () {
            registerSnippetsAndEmmet();

            const isMobile = window.innerWidth <= 768;

            editor = monaco.editor.create(document.getElementById('monaco-container'), {
                theme: 'vs-dark',
                fontSize: isMobile ? 13 : 13.5,
                fontFamily: "'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace",
                lineNumbers: 'on',
                lineNumbersMinChars: isMobile ? 3 : 5,
                glyphMargin: false,
                folding: !isMobile,
                roundedSelection: true,
                scrollBeyondLastLine: true, // KUNCI: Memungkinkan gulir bebas melewati baris terakhir sampai tuntas
                readOnly: false,
                tabSize: 4,
                automaticLayout: true,
                minimap: { enabled: !isMobile },
                bracketPairColorization: { enabled: true },
                autoClosingBrackets: 'always',
                autoClosingQuotes: 'always',
                cursorBlinking: 'smooth',
                wordWrap: 'off', // JANGAN bungkus kode agar sintaks tetap lurus dan sangat mudah dibaca
                padding: { top: 8, bottom: isMobile ? 120 : 20 }, // Ruang ekstra di bawah agar baris terakhir tidak terhalang tombol
            });

            // Auto-closing tag saat mengetik '>'
            editor.onKeyDown(function (e) {
                if (e.browserEvent.key === '>') {
                    const pos = editor.getPosition();
                    const model = editor.getModel();
                    if (!model) return;
                    const line = model.getLineContent(pos.lineNumber);
                    const beforeCursor = line.substring(0, pos.column - 1);

                    const match = beforeCursor.match(/<([a-zA-Z0-9_-]+)(?:\s+[^>]*)?$/);
                    if (match && !match[1].startsWith('/') && !match[0].endsWith('/')) {
                        const tagName = match[1];
                        const voidTags = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
                        if (!voidTags.includes(tagName.toLowerCase())) {
                            setTimeout(() => {
                                const newPos = editor.getPosition();
                                editor.executeEdits('auto-close-tag', [{
                                    range: new monaco.Range(newPos.lineNumber, newPos.column, newPos.lineNumber, newPos.column),
                                    text: `</${tagName}>`
                                }]);
                                editor.setPosition(newPos);
                            }, 10);
                        }
                    }
                }
            });

            // Lacak posisi kursor untuk status bar
            editor.onDidChangeCursorPosition(function (e) {
                if (elStatusCursor) {
                    elStatusCursor.textContent = `Ln ${e.position.lineNumber}, Col ${e.position.column}`;
                }
            });

            // Pintasan Ctrl+S / Cmd+S untuk simpan
            editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function () {
                saveActiveFile();
            });

            // Pintasan F2 untuk membuka Sakuci CLI Helper
            editor.addCommand(monaco.KeyCode.F2, function () {
                if (typeof window.openCliModal === 'function') window.openCliModal();
            });

            // Jika ada path awal dari URL
            if (project.initialPath) {
                const parts = project.initialPath.split('/');
                let current = '';
                for (let i = 0; i < parts.length - 1; i++) {
                    current = current ? `${current}/${parts[i]}` : parts[i];
                    expandedFolders.add(current);
                }
                openFile(project.initialPath);
            }
        });
    }

    // ---------------- 3. Tab Switcher (Berkas vs Editor vs Git) ---------------- //
    function switchView(mode) {
        currentMode = mode;
        if (!elWorkspace) return;

        elWorkspace.classList.remove('mode-explorer', 'mode-editor', 'mode-git');
        elWorkspace.classList.add('mode-' + mode);

        if (elBtnTabExplorer) elBtnTabExplorer.classList.toggle('active', mode === 'explorer');
        if (elBtnTabEditor) elBtnTabEditor.classList.toggle('active', mode === 'editor');
        if (elBtnTabGit) elBtnTabGit.classList.toggle('active', mode === 'git');

        const fabBtn = document.getElementById('vsc-mobile-fab-save');
        if (mode === 'editor') {
            if (fabBtn && activeTabPath) fabBtn.classList.add('show');
            if (editor) {
                setTimeout(() => editor.layout(), 60);
                setTimeout(() => editor.layout(), 250);
            }
        } else {
            if (fabBtn) fabBtn.classList.remove('show');
            if (mode === 'git') {
                fetchGitStatus();
                fetchGitHistory();
            }
        }
    }

    // ---------------- 4. Deteksi Bahasa Berkas ---------------- //
    function detectLanguage(path) {
        const lower = path.toLowerCase();
        const base = path.split('/').pop().toLowerCase();

        if (base === '.env' || base.startsWith('.env.') || base.endsWith('.ini')) return 'ini';
        if (base === '.htaccess' || base === '.gitignore') return 'plaintext';
        if (lower.endsWith('.php')) return 'php';
        if (lower.endsWith('.js') || lower.endsWith('.mjs')) return 'javascript';
        if (lower.endsWith('.ts')) return 'typescript';
        if (lower.endsWith('.css')) return 'css';
        if (lower.endsWith('.html') || lower.endsWith('.htm')) return 'html';
        if (lower.endsWith('.json')) return 'json';
        if (lower.endsWith('.sql')) return 'sql';
        if (lower.endsWith('.md')) return 'markdown';
        if (lower.endsWith('.xml') || lower.endsWith('.svg')) return 'xml';
        if (lower.endsWith('.sh')) return 'shell';

        return 'plaintext';
    }

    // ---------------- 5. Pengelolaan Tab & Model ---------------- //
    async function openFile(path) {
        const existing = tabs.find(t => t.path === path);
        if (existing) {
            activateTab(path);
            switchView('editor');
            return;
        }

        setStatusSave('<span class="vsc-spin"></span> Membuka berkas…', '#ffffff');

        try {
            const res = await fetch(`api/file-ops.php?action=read&project_id=${projectId}&path=${encodeURIComponent(path)}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.error) {
                alert('Gagal membuka berkas: ' + data.error);
                setStatusSave('Gagal membuka', '#ff8888');
                return;
            }

            const lang = detectLanguage(path);
            const modelUri = monaco.Uri.parse(`file:///${path}`);
            let model = monaco.editor.getModel(modelUri);

            if (!model) {
                model = monaco.editor.createModel(data.content, lang, modelUri);
            } else {
                model.setValue(data.content);
            }

            const newTab = {
                path: path,
                name: data.name,
                model: model,
                viewState: null,
                isDirty: false,
                canEdit: data.canEdit,
                isEnv: data.isEnv,
                language: lang,
            };

            model.onDidChangeContent(() => {
                if (!newTab.isDirty) {
                    newTab.isDirty = true;
                    renderTabs();
                    if (activeTabPath === newTab.path) {
                        setStatusSave('● Ada perubahan belum disimpan', '#ffcc66');
                        updateSaveButtonsState('dirty');
                    }
                }
            });

            tabs.push(newTab);
            renderTabs();
            activateTab(path);
            switchView('editor');
            setStatusSave('✓ Berkas dibuka', '#ffffff');
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
            setStatusSave('Gagal membuka', '#ff8888');
        }
    }

    function activateTab(path) {
        const tab = tabs.find(t => t.path === path);
        if (!tab) return;

        if (activeTabPath && editor) {
            const prev = tabs.find(t => t.path === activeTabPath);
            if (prev) {
                prev.viewState = editor.saveViewState();
            }
        }

        activeTabPath = path;

        if (editor) {
            editor.setModel(tab.model);
            if (tab.viewState) {
                editor.restoreViewState(tab.viewState);
            }
            editor.updateOptions({ readOnly: !tab.canEdit });
            editor.focus();
            setTimeout(() => editor.layout(), 60);
        }

        if (elEmptyState) elEmptyState.style.display = 'none';
        if (elMonacoWrap) elMonacoWrap.style.display = 'block';

        if (elReadonlyBanner) {
            if (!tab.canEdit) {
                elReadonlyBanner.classList.add('show');
            } else {
                elReadonlyBanner.classList.remove('show');
            }
        }

        updateBreadcrumb(tab.path);
        if (elViewTabFilename) {
            elViewTabFilename.textContent = `(${tab.name})`;
        }

        if (elStatusLang) elStatusLang.textContent = tab.language.toUpperCase();
        if (tab.isDirty) {
            setStatusSave('● Ada perubahan belum disimpan', '#ffcc66');
            updateSaveButtonsState('dirty');
        } else {
            setStatusSave(tab.canEdit ? '✓ Tersimpan' : '🔒 Read-Only', '#ffffff');
            updateSaveButtonsState(tab.canEdit ? 'idle' : 'readonly');
        }

        renderTabs();
        highlightTreeItem(path);
    }

    function closeTab(path, e) {
        if (e) e.stopPropagation();

        const index = tabs.findIndex(t => t.path === path);
        if (index === -1) return;

        const tab = tabs[index];
        if (tab.isDirty) {
            if (!confirm(`Berkas "${tab.name}" memiliki perubahan yang belum disimpan. Tetap tutup?`)) {
                return;
            }
        }

        tab.model.dispose();
        tabs.splice(index, 1);

        if (activeTabPath === path) {
            if (tabs.length > 0) {
                const nextTab = tabs[Math.max(0, index - 1)];
                activateTab(nextTab.path);
            } else {
                activeTabPath = null;
                if (editor) editor.setModel(null);
                if (elMonacoWrap) elMonacoWrap.style.display = 'none';
                if (elEmptyState) elEmptyState.style.display = 'flex';
                if (elReadonlyBanner) elReadonlyBanner.classList.remove('show');
                if (elBreadcrumb) elBreadcrumb.innerHTML = '';
                if (elStatusLang) elStatusLang.textContent = '-';
                setStatusSave('Siap', '#ffffff');
                updateSaveButtonsState('empty');

                if (window.innerWidth <= 768) {
                    switchView('explorer');
                }
            }
        }

        renderTabs();
    }

    function renderTabs() {
        if (!elTabsBar) return;
        elTabsBar.innerHTML = '';

        tabs.forEach(tab => {
            const el = document.createElement('div');
            const gitInfo = gitStatusMap[tab.path];
            let gitClass = '';
            if (gitInfo) {
                if (gitInfo.type === 'M') gitClass = 'git-modified';
                else if (gitInfo.type === 'U' || gitInfo.type === 'A') gitClass = 'git-untracked';
            }

            el.className = `vsc-tab ${tab.path === activeTabPath ? 'active' : ''} ${tab.isDirty ? 'dirty' : ''} ${gitClass}`;
            el.title = tab.path;

            const icon = getFileIconHtml(tab.name);
            const gitBadgeHtml = gitInfo ? `<span class="git-badge git-badge-${gitInfo.type.toLowerCase()}">${gitInfo.type}</span>` : '';

            el.innerHTML = `
                ${icon}
                <span>${escapeHtml(tab.name)}</span>
                ${gitBadgeHtml}
                <span class="vsc-tab-dirty"></span>
                <span class="vsc-tab-close" title="Tutup">&times;</span>
            `;

            el.addEventListener('click', () => activateTab(tab.path));
            el.querySelector('.vsc-tab-close').addEventListener('click', (e) => closeTab(tab.path, e));

            elTabsBar.appendChild(el);
        });
    }

    function updateBreadcrumb(path) {
        if (!elBreadcrumb) return;
        const parts = path.split('/');
        let html = '';
        parts.forEach((p, idx) => {
            const isLast = idx === parts.length - 1;
            html += `<span class="${isLast ? 'vsc-breadcrumb-file' : ''}">${escapeHtml(p)}</span>`;
            if (!isLast) {
                html += '<span class="vsc-breadcrumb-sep">/</span>';
            }
        });
        elBreadcrumb.innerHTML = html;
    }

    // ---------------- 6. Sinkronisasi & Simpan Berkas (Ctrl+S) ---------------- //
    function undo() {
        if (!editor) return;
        try {
            editor.trigger('toolbar', 'undo', null);
        } catch (e) {
            const m = editor.getModel();
            if (m && typeof m.undo === 'function') m.undo();
        }
        editor.focus();
    }

    function redo() {
        if (!editor) return;
        try {
            editor.trigger('toolbar', 'redo', null);
        } catch (e) {
            const m = editor.getModel();
            if (m && typeof m.redo === 'function') m.redo();
        }
        editor.focus();
    }

    function scrollToBottom() {
        if (!editor) return;
        const model = editor.getModel();
        if (!model) return;
        const lastLine = model.getLineCount();
        editor.revealLine(lastLine);
        editor.setPosition({ lineNumber: lastLine, column: model.getLineMaxColumn(lastLine) });
        editor.focus();
    }

    function scrollToTop() {
        if (!editor) return;
        editor.revealLine(1);
        editor.setPosition({ lineNumber: 1, column: 1 });
        editor.focus();
    }

    function updateSaveButtonsState(state) {
        const topBtn = document.getElementById('vsc-btn-top-save') || document.querySelector('.vsc-btn-save');
        const subbarBtn = document.getElementById('vsc-subbar-save-btn');
        const undoBtn = document.getElementById('vsc-subbar-undo-btn');
        const redoBtn = document.getElementById('vsc-subbar-redo-btn');
        const toolBtns = [undoBtn, redoBtn].filter(Boolean);
        const fabBtn = document.getElementById('vsc-mobile-fab-save');
        const fabText = document.getElementById('vsc-mobile-fab-text');
        const topLabel = document.querySelector('.vsc-save-label');
        const subbarText = document.querySelector('.vsc-subbar-save-text');

        const allBtns = [topBtn, subbarBtn, fabBtn].filter(Boolean);

        if (state === 'empty' || state === 'readonly') {
            allBtns.forEach(b => {
                b.disabled = true;
                b.style.opacity = '0.45';
                b.classList.remove('is-dirty', 'is-saved');
            });
            if (fabBtn) fabBtn.classList.remove('show');
            if (subbarBtn) subbarBtn.style.display = 'none';
            if (state === 'empty') {
                toolBtns.forEach(b => {
                    b.style.display = 'none';
                    b.disabled = true;
                });
            } else {
                toolBtns.forEach(b => {
                    b.style.display = 'inline-flex';
                    b.disabled = true;
                    b.style.opacity = '0.45';
                });
            }
            if (topLabel) topLabel.textContent = state === 'readonly' ? 'Read-Only' : 'Simpan';
            return;
        }

        allBtns.forEach(b => {
            b.disabled = false;
            b.style.opacity = '1';
        });

        toolBtns.forEach(b => {
            b.style.display = 'inline-flex';
            b.disabled = false;
            b.style.opacity = '1';
        });

        if (subbarBtn) subbarBtn.style.display = 'inline-flex';
        if (fabBtn && currentMode === 'editor' && activeTabPath) fabBtn.classList.add('show');

        if (state === 'dirty') {
            allBtns.forEach(b => {
                b.classList.add('is-dirty');
                b.classList.remove('is-saved');
            });
            if (topLabel) topLabel.textContent = '● Simpan';
            if (subbarText) subbarText.textContent = '● Simpan';
            if (fabText) fabText.textContent = '● Simpan';
        } else if (state === 'saving') {
            allBtns.forEach(b => {
                b.classList.remove('is-dirty', 'is-saved');
            });
            if (topLabel) topLabel.textContent = 'Menyimpan…';
            if (subbarText) subbarText.textContent = 'Menyimpan…';
            if (fabText) fabText.textContent = 'Menyimpan…';
        } else if (state === 'saved') {
            allBtns.forEach(b => {
                b.classList.remove('is-dirty');
                b.classList.add('is-saved');
            });
            if (topLabel) topLabel.textContent = '✓ Tersimpan!';
            if (subbarText) subbarText.textContent = '✓ Tersimpan!';
            if (fabText) fabText.textContent = '✓ Tersimpan!';

            setTimeout(() => {
                allBtns.forEach(b => b.classList.remove('is-saved'));
                if (topLabel) topLabel.textContent = 'Simpan';
                if (subbarText) subbarText.textContent = 'Simpan';
                if (fabText) fabText.textContent = 'Simpan';
            }, 1800);
        } else { // idle
            allBtns.forEach(b => {
                b.classList.remove('is-dirty', 'is-saved');
            });
            if (topLabel) topLabel.textContent = 'Simpan';
            if (subbarText) subbarText.textContent = 'Simpan';
            if (fabText) fabText.textContent = 'Simpan';
        }
    }

    async function saveActiveFile() {
        if (!activeTabPath) return;
        const tab = tabs.find(t => t.path === activeTabPath);
        if (!tab || !tab.canEdit) return;

        setStatusSave('<span class="vsc-spin"></span> Menyimpan…', '#ffffff');
        updateSaveButtonsState('saving');

        const content = tab.model.getValue();
        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'save');
        formData.append('path', tab.path);
        formData.append('content', content);

        try {
            const res = await fetch('api/file-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();

            if (data.status === 'ok') {
                tab.isDirty = false;
                renderTabs();
                setStatusSave('✓ Tersimpan pada ' + new Date().toLocaleTimeString(), '#9ae6b4');
                updateSaveButtonsState('saved');
                // Perbarui status Git setelah menyimpan berkas
                fetchGitStatus();
            } else {
                alert('Gagal menyimpan: ' + (data.error || 'Terjadi kesalahan'));
                setStatusSave('Gagal menyimpan', '#feb2b2');
                updateSaveButtonsState('dirty');
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
            setStatusSave('Gagal menyimpan', '#feb2b2');
            updateSaveButtonsState('dirty');
        }
    }

    // ---------------- 7. File Explorer Tree ---------------- //
    async function loadTree() {
        try {
            const res = await fetch(`api/file-ops.php?action=tree&project_id=${projectId}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.tree) {
                treeData = data.tree;
                renderTree();
            }
        } catch (err) {
            console.error('Failed to load tree:', err);
        }
    }

    function renderTree() {
        if (!elTree) return;
        elTree.innerHTML = '';
        renderTreeNodes(treeData, elTree, 0);
    }

    function renderTreeNodes(nodes, container, depth) {
        nodes.forEach(node => {
            const item = document.createElement('div');
            const gitInfo = gitStatusMap[node.path];
            let gitClass = '';
            if (gitInfo) {
                if (gitInfo.type === 'M') gitClass = 'git-modified';
                else if (gitInfo.type === 'U') gitClass = 'git-untracked';
                else if (gitInfo.type === 'A') gitClass = 'git-added';
                else if (gitInfo.type === 'D') gitClass = 'git-deleted';
            }

            item.className = `vsc-tree-item ${activeTabPath === node.path ? 'active' : ''} ${gitClass}`;
            item.dataset.path = node.path;
            item.style.paddingLeft = `${depth * 14 + 10}px`;

            if (node.isDir) {
                const isOpen = expandedFolders.has(node.path);
                item.innerHTML = `
                    <span class="vsc-tree-arrow ${isOpen ? 'open' : ''}">▸</span>
                    <span class="vsc-file-icon icon-folder">📁</span>
                    <span class="vsc-tree-name">${escapeHtml(node.name)}</span>
                    <div class="vsc-item-actions">
                        <button type="button" class="vsc-icon-btn" title="Buat Berkas di Sini" onclick="window.VSC_IDE.promptNewFile('${node.path}', event)">+📄</button>
                        <button type="button" class="vsc-icon-btn" title="Ganti Nama" onclick="window.VSC_IDE.promptRename('${node.path}', '${escapeHtml(node.name)}', event)">✏️</button>
                        <button type="button" class="vsc-icon-btn" title="Hapus" onclick="window.VSC_IDE.promptDelete('${node.path}', true, event)">🗑️</button>
                    </div>
                `;

                item.addEventListener('click', (e) => {
                    if (e.target.closest('.vsc-item-actions')) return;
                    if (expandedFolders.has(node.path)) {
                        expandedFolders.delete(node.path);
                    } else {
                        expandedFolders.add(node.path);
                    }
                    renderTree();
                });

                container.appendChild(item);

                if (isOpen && node.children && node.children.length > 0) {
                    renderTreeNodes(node.children, container, depth + 1);
                } else if (isOpen && (!node.children || node.children.length === 0)) {
                    const empty = document.createElement('div');
                    empty.className = 'vsc-tree-item';
                    empty.style.paddingLeft = `${(depth + 1) * 14 + 10}px`;
                    empty.style.color = '#666';
                    empty.style.fontStyle = 'italic';
                    empty.textContent = '(kosong)';
                    container.appendChild(empty);
                }
            } else {
                const icon = getFileIconHtml(node.name);
                const gitBadgeHtml = gitInfo ? `<span class="git-badge git-badge-${gitInfo.type.toLowerCase()}" title="${gitInfo.label}">${gitInfo.type}</span>` : '';

                item.innerHTML = `
                    <span style="width:16px; flex-shrink:0"></span>
                    ${icon}
                    <span class="vsc-tree-name">${escapeHtml(node.name)}</span>
                    ${gitBadgeHtml}
                    <div class="vsc-item-actions">
                        <button type="button" class="vsc-icon-btn" title="Ganti Nama" onclick="window.VSC_IDE.promptRename('${node.path}', '${escapeHtml(node.name)}', event)">✏️</button>
                        <button type="button" class="vsc-icon-btn" title="Hapus" onclick="window.VSC_IDE.promptDelete('${node.path}', false, event)">🗑️</button>
                    </div>
                `;

                item.addEventListener('click', (e) => {
                    if (e.target.closest('.vsc-item-actions')) return;
                    openFile(node.path);
                });

                container.appendChild(item);
            }
        });
    }

    function highlightTreeItem(path) {
        document.querySelectorAll('.vsc-tree-item').forEach(el => {
            el.classList.toggle('active', el.dataset.path === path);
        });
    }

    function getFileIconHtml(name) {
        const lower = name.toLowerCase();
        if (lower.endsWith('.php')) return '<span class="vsc-file-icon icon-php">🐘</span>';
        if (lower.endsWith('.js') || lower.endsWith('.mjs')) return '<span class="vsc-file-icon icon-js">⚡</span>';
        if (lower.endsWith('.css')) return '<span class="vsc-file-icon icon-css">🎨</span>';
        if (lower.endsWith('.html') || lower.endsWith('.htm')) return '<span class="vsc-file-icon icon-html">🌐</span>';
        if (lower.endsWith('.json')) return '<span class="vsc-file-icon icon-json">{}</span>';
        if (lower === '.env' || lower.startsWith('.env.')) return '<span class="vsc-file-icon icon-env">🛡️</span>';
        if (lower.endsWith('.sql')) return '<span class="vsc-file-icon icon-sql">🗄️</span>';
        if (lower.endsWith('.md')) return '<span class="vsc-file-icon icon-md">📝</span>';
        return '<span class="vsc-file-icon icon-file">📄</span>';
    }

    function setStatusSave(html, color) {
        if (!elStatusSave) return;
        elStatusSave.innerHTML = html;
        if (color) elStatusSave.style.color = color;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ---------------- 8. Operasi Berkas (New, Rename, Delete) ---------------- //
    async function promptNewFile(parentPath = '', e) {
        if (e) e.stopPropagation();
        const name = prompt(`Buat Berkas Baru di ${parentPath ? '/' + parentPath : 'akar'}:\nContoh: HomeController.php`, '');
        if (!name || !name.trim()) return;

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'create_file');
        formData.append('parent_path', parentPath);
        formData.append('name', name.trim());

        try {
            const res = await fetch('api/file-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                if (parentPath) expandedFolders.add(parentPath);
                await loadTree();
                openFile(data.path);
                fetchGitStatus();
            } else {
                alert('Gagal membuat berkas: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    async function promptNewFolder(parentPath = '', e) {
        if (e) e.stopPropagation();
        const name = prompt(`Buat Folder Baru di ${parentPath ? '/' + parentPath : 'akar'}:\nContoh: controllers`, '');
        if (!name || !name.trim()) return;

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'create_folder');
        formData.append('parent_path', parentPath);
        formData.append('name', name.trim());

        try {
            const res = await fetch('api/file-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                if (parentPath) expandedFolders.add(parentPath);
                expandedFolders.add(data.path);
                await loadTree();
            } else {
                alert('Gagal membuat folder: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    async function promptRename(path, oldName, e) {
        if (e) e.stopPropagation();
        const newName = prompt(`Ganti nama "${oldName}" menjadi:`, oldName);
        if (!newName || !newName.trim() || newName.trim() === oldName) return;

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'rename');
        formData.append('path', path);
        formData.append('new_name', newName.trim());

        try {
            const res = await fetch('api/file-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                const tab = tabs.find(t => t.path === path);
                if (tab) {
                    tab.path = data.newPath;
                    tab.name = newName.trim();
                }
                if (activeTabPath === path) {
                    activeTabPath = data.newPath;
                    updateBreadcrumb(data.newPath);
                }
                renderTabs();
                await loadTree();
                fetchGitStatus();
            } else {
                alert('Gagal mengganti nama: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    async function promptDelete(path, isDir, e) {
        if (e) e.stopPropagation();
        const msg = isDir
            ? `Hapus folder "${path}" BESERTA SELURUH ISINYA secara permanen?\nTidak dapat dikembalikan.`
            : `Hapus berkas "${path}" secara permanen?`;

        if (!confirm(msg)) return;

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'delete');
        formData.append('path', path);

        try {
            const res = await fetch('api/file-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                closeTab(path);
                await loadTree();
                fetchGitStatus();
            } else {
                alert('Gagal menghapus: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    // ---------------- 9. Git Status & Riwayat Commit ---------------- //
    async function fetchGitStatus() {
        try {
            const res = await fetch(`api/git-ops.php?action=status&project_id=${projectId}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.status === 'ok') {
                gitStatusData = data;
                gitStatusMap = {};
                (data.files || []).forEach(f => {
                    gitStatusMap[f.path] = f;
                });

                // Update badge counter di tab Git
                if (elGitBadgeCounter) {
                    const count = data.total_changes || 0;
                    elGitBadgeCounter.textContent = count;
                    elGitBadgeCounter.classList.toggle('show', count > 0);
                }
                if (elActGitDot) {
                    elActGitDot.classList.toggle('show', (data.total_changes || 0) > 0);
                }
                if (elStatusBranch) {
                    elStatusBranch.textContent = data.branch;
                }
                if (elGitBranchName) {
                    elGitBranchName.textContent = data.branch;
                }
                if (elGitStatusSummary) {
                    elGitStatusSummary.textContent = data.total_changes > 0
                        ? `${data.total_changes} berkas berubah`
                        : 'Bersih (Tidak ada perubahan)';
                }

                renderTree();
                renderTabs();
                renderGitFileList();
            }
        } catch (err) {
            console.error('Failed to fetch git status:', err);
        }
    }

    function renderGitFileList() {
        if (!elGitFileList) return;
        elGitFileList.innerHTML = '';

        const files = gitStatusData.files || [];
        if (files.length === 0) {
            elGitFileList.innerHTML = '<div style="color:var(--vsc-text-dim); font-size:12.5px; font-style:italic; padding:8px 0">Tidak ada perubahan lokal. Semua berkas tersinkronisasi.</div>';
            return;
        }

        files.forEach(f => {
            const row = document.createElement('div');
            row.className = 'vsc-git-file-row';

            row.innerHTML = `
                <div class="vsc-git-file-left" title="Klik untuk membuka di editor">
                    <span class="git-badge git-badge-${f.type.toLowerCase()}">${f.type}</span>
                    <span style="font-family:var(--vsc-font-mono); font-size:12px">${escapeHtml(f.path)}</span>
                </div>
                <div class="vsc-git-file-actions">
                    <button type="button" class="vsc-btn btn-sm" style="padding:2px 8px; font-size:11px" onclick="window.VSC_IDE.discardFile('${escapeHtml(f.path)}')">
                        ↩️ Batalkan
                    </button>
                </div>
            `;

            row.querySelector('.vsc-git-file-left').addEventListener('click', () => {
                if (f.type !== 'D') {
                    openFile(f.path);
                } else {
                    alert('Berkas ini telah dihapus.');
                }
            });

            elGitFileList.appendChild(row);
        });
    }

    async function fetchGitHistory() {
        if (!elCommitList) return;
        elCommitList.innerHTML = '<div style="color:var(--vsc-text-dim); font-size:12px"><span class="vsc-spin"></span> Memuat riwayat commit…</div>';

        try {
            const res = await fetch(`api/git-ops.php?action=history&project_id=${projectId}&limit=20`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.status === 'ok' && data.commits) {
                renderCommitList(data.commits);
            } else {
                elCommitList.innerHTML = '<div style="color:#ff8888; font-size:12px">Gagal memuat riwayat commit.</div>';
            }
        } catch (err) {
            elCommitList.innerHTML = `<div style="color:#ff8888; font-size:12px">Kesalahan: ${err.message}</div>`;
        }
    }

    function renderCommitList(commits) {
        if (!elCommitList) return;
        elCommitList.innerHTML = '';

        if (commits.length === 0) {
            elCommitList.innerHTML = '<div style="color:var(--vsc-text-dim); font-size:12px">Belum ada riwayat commit.</div>';
            return;
        }

        commits.forEach((c, idx) => {
            const row = document.createElement('div');
            row.className = 'vsc-commit-row';

            const isCurrent = idx === 0;
            const currentBadge = isCurrent ? '<span style="background:var(--vsc-green); color:#fff; font-size:10px; padding:1px 5px; border-radius:3px; font-weight:700">TERBARU</span>' : '';

            row.innerHTML = `
                <div class="vsc-commit-info">
                    <div class="vsc-commit-msg" title="${escapeHtml(c.message)}">${escapeHtml(c.message)} ${currentBadge}</div>
                    <div class="vsc-commit-meta">
                        <span class="vsc-commit-hash">${escapeHtml(c.hash)}</span>
                        <span>&bull; ${escapeHtml(c.author)}</span>
                        <span>&bull; ${escapeHtml(c.date)}</span>
                    </div>
                </div>
                <div>
                    ${!isCurrent ? `
                        <button type="button" class="vsc-btn vsc-btn-rollback" onclick="window.VSC_IDE.rollbackToCommit('${c.hash}', '${escapeHtml(c.message)}')">
                            ↩️ Kembalikan ke Ini
                        </button>
                    ` : '<span style="font-size:11px; color:var(--vsc-text-dim)">HEAD Saat Ini</span>'}
                </div>
            `;

            elCommitList.appendChild(row);
        });
    }

    // ---------------- 10. Batalkan Perubahan (Discard) & Rollback ---------------- //
    async function discardFile(path) {
        if (!confirm(`Batalkan semua perubahan pada berkas "${path}" dan kembalikan ke kondisi commit terakhir?`)) {
            return;
        }

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'discard_file');
        formData.append('path', path);

        try {
            const res = await fetch('api/git-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                // Tutup tab jika terbuka lalu muat ulang
                closeTab(path);
                await loadTree();
                await fetchGitStatus();
            } else {
                alert('Gagal membatalkan perubahan: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    async function discardAllChanges() {
        if (!confirm('PERINGATAN: Batalkan SEMUA perubahan yang belum di-commit di seluruh berkas proyek?\n\nSemua perubahan yang belum di-commit akan hilang permanen.')) {
            return;
        }

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'discard_all');

        try {
            const res = await fetch('api/git-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                alert('Semua perubahan berhasil dibatalkan.');
                location.reload();
            } else {
                alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    async function rollbackToCommit(hash, msg) {
        const confirmMsg = `PERINGATAN ROLLBACK:\n\nKembalikan seluruh proyek ke commit [${hash}]?\n"${msg}"\n\nSeluruh kode di server akan direset persis ke commit ini. Lanjutkan?`;
        if (!confirm(confirmMsg)) return;

        const formData = new URLSearchParams();
        formData.append('project_id', projectId);
        formData.append('action', 'rollback_commit');
        formData.append('commit_hash', hash);

        try {
            const res = await fetch('api/git-ops.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data.status === 'ok') {
                alert(`Berhasil! Proyek telah dikembalikan ke commit [${hash}]. Halaman akan dimuat ulang.`);
                location.reload();
            } else {
                alert('Gagal rollback: ' + (data.error || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ---------------- 11. Resizer Antara Sidebar dan Editor ---------------- //
    function initResizer() {
        const resizer = document.getElementById('vsc-resizer');
        if (!resizer || !elSidebar) return;

        let isDragging = false;

        resizer.addEventListener('mousedown', () => {
            isDragging = true;
            resizer.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const containerRect = document.querySelector('.vsc-main').getBoundingClientRect();
            const newWidth = e.clientX - containerRect.left;
            if (newWidth >= 160 && newWidth <= 500) {
                elSidebar.style.width = `${newWidth}px`;
            }
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                resizer.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                if (editor) editor.layout();
            }
        });
    }

    // ---------------- 12. Konfirmasi Kembali ke Dashboard ---------------- //
    function confirmBackToDashboard(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        const dirtyTabs = tabs.filter(t => t.isDirty);
        let msg = 'Yakin ingin kembali ke Dashboard cPanel?';
        if (dirtyTabs.length > 0) {
            const fileList = dirtyTabs.map(t => t.name).join(', ');
            msg = `⚠️ Peringatan: Berkas berikut belum disimpan:
- ${fileList}

Perubahan Anda akan hilang jika keluar sekarang.
Tetap kembali ke Dashboard?`;
        }
        if (confirm(msg)) {
            window.location.href = 'dashboard.php';
        }
        return false;
    }

    // Export ke window untuk event handler HTML
    window.VSC_IDE = {
        confirmBackToDashboard,
        openFile,
        saveActiveFile,
        undo,
        redo,
        scrollToBottom,
        scrollToTop,
        promptNewFile,
        promptNewFolder,
        promptRename,
        promptDelete,
        loadTree,
        switchView,
        discardFile,
        discardAllChanges,
        rollbackToCommit,
        fetchGitStatus,
        fetchGitHistory,
        toggleSidebar: () => {
            if (window.innerWidth <= 768) {
                switchView(currentMode === 'explorer' ? 'editor' : 'explorer');
            } else if (elSidebar) {
                elSidebar.classList.toggle('collapsed');
                if (editor) setTimeout(() => editor.layout(), 160);
            }
        },
        openCliModal: (tab) => {
            if (typeof window.openCliModal === 'function') window.openCliModal(tab);
        }
    };

    // Auto-start saat DOM siap
    document.addEventListener('DOMContentLoaded', () => {
        switchView(currentMode);
        initMonaco();
        loadTree();
        fetchGitStatus();
        initResizer();
        updateSaveButtonsState('empty');

        // Tangkap shortcut global Ctrl+S
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveActiveFile();
            }
        });

        // Peringatan saat menutup tab browser atau reload jika masih ada berkas belum disimpan
        window.addEventListener('beforeunload', (e) => {
            if (tabs.some(t => t.isDirty)) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Responsif saat rotasi HP atau resize jendela
        window.addEventListener('resize', () => {
            const isMobile = window.innerWidth <= 768;
            if (editor) {
                editor.updateOptions({
                    minimap: { enabled: !isMobile },
                    fontSize: isMobile ? 13 : 13.5,
                    lineNumbersMinChars: isMobile ? 3 : 5,
                    scrollBeyondLastLine: true,
                    wordWrap: 'off',
                    padding: {
                        top: 8,
                        bottom: isMobile ? 120 : 20
                    }
                });
                editor.layout();
            }
        });
    });

})();

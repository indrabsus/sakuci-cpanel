/**
 * Sakuci Database Schema Designer ala SQLyog
 * 100% Full Lokal (Tanpa dependensi CDN luar)
 * Fitur: Draggable Table Cards, Real-time SVG Curve Relations, Pan & Zoom, Auto-Arrange, Preview Data
 */

(function () {
    'use strict';

    // State Internal
    let activeDbId = null;
    let activeDbName = '';
    let schemaData = { tables: [], relations: [] };
    let cardPositions = {}; // tableName -> { x, y }

    // Canvas Pan & Zoom
    let scale = 1.0;
    let panX = 60;
    let panY = 60;
    let isPanning = false;
    let panStartX = 0;
    let panStartY = 0;

    // Dragging Card State
    let draggedCard = null;
    let dragStartX = 0;
    let dragStartY = 0;
    let cardStartX = 0;
    let cardStartY = 0;

    // DOM Elements Cache
    let elModal = null;
    let elViewport = null;
    let elWorld = null;
    let elSvgCanvas = null;
    let elCardsLayer = null;
    let elPreviewDrawer = null;

    /**
     * Inisialisasi referensi DOM
     */
    function initDOMElements() {
        elModal = document.getElementById('designer-modal');
        elViewport = document.getElementById('designer-viewport');
        elWorld = document.getElementById('designer-world');
        elSvgCanvas = document.getElementById('designer-svg-canvas');
        elCardsLayer = document.getElementById('designer-cards-layer');
        elPreviewDrawer = document.getElementById('designer-preview-drawer');
    }

    /**
     * Buka modal desainer skema untuk database tertentu
     */
    async function openDatabaseDesigner(dbId, dbName) {
        initDOMElements();
        if (!elModal) return;

        activeDbId = dbId;
        activeDbName = dbName || `Database #${dbId}`;

        // Perbarui judul toolbar
        const titleEl = document.getElementById('designer-db-name');
        const metaEl = document.getElementById('designer-db-meta');
        if (titleEl) titleEl.textContent = activeDbName;
        if (metaEl) metaEl.textContent = 'Memuat skema…';

        // Tampilkan modal
        elModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Reset transform
        scale = 1.0;
        panX = 60;
        panY = 60;
        updateWorldTransform();

        // Tampilkan loading di kanvas
        if (elCardsLayer) {
            elCardsLayer.innerHTML = `
                <div class="designer-empty-state">
                    <div style="font-size:24px; margin-bottom:8px">⏳</div>
                    <div>Menganalisis skema tabel &amp; relasi…</div>
                </div>
            `;
        }
        if (elSvgCanvas) elSvgCanvas.innerHTML = '';

        try {
            const res = await fetch(`api/db-designer.php?db_id=${dbId}&action=schema`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                if (elCardsLayer) {
                    elCardsLayer.innerHTML = `
                        <div class="designer-empty-state">
                            <div style="font-size:24px; color:#ef4444; margin-bottom:8px">⚠️</div>
                            <div style="color:#ef4444">${escapeHtml(data.error || 'Gagal memuat skema.')}</div>
                        </div>
                    `;
                }
                if (metaEl) metaEl.textContent = 'Gagal memuat skema';
                return;
            }

            schemaData = data;
            if (metaEl) {
                metaEl.textContent = `${data.tables_count} tabel • ${data.relations_count} relasi terdeteksi`;
            }

            // Atur posisi awal tabel
            autoArrangePositions();
            renderTables();
            renderRelations();

        } catch (err) {
            console.error('Designer Error:', err);
            if (elCardsLayer) {
                elCardsLayer.innerHTML = `
                    <div class="designer-empty-state">
                        <div style="font-size:24px; color:#ef4444; margin-bottom:8px">❌</div>
                        <div style="color:#ef4444">Kesalahan jaringan: ${escapeHtml(err.message)}</div>
                    </div>
                `;
            }
        }
    }

    /**
     * Tutup modal desainer
     */
    function closeDatabaseDesigner() {
        if (elModal) elModal.classList.remove('show');
        document.body.style.overflow = '';
        closePreview();
    }

    /**
     * Algoritma penataan kartu tabel otomatis (Auto-Arrange Grid)
     */
    function autoArrangePositions() {
        const tables = schemaData.tables || [];
        cardPositions = {};

        const cardWidth = 250;
        const gapX = 70;
        const gapY = 50;
        const startX = 60;
        const startY = 60;

        // Tentukan jumlah kolom berdasarkan jumlah tabel
        let cols = 3;
        if (tables.length <= 2) cols = 2;
        else if (tables.length >= 8) cols = 4;

        tables.forEach((t, i) => {
            const col = i % cols;
            const row = Math.floor(i / cols);
            const x = startX + col * (cardWidth + gapX);
            const y = startY + row * (230 + gapY);
            cardPositions[t.name] = { x, y };
        });
    }

    /**
     * Render kartu-kartu tabel
     */
    function renderTables() {
        if (!elCardsLayer) return;
        elCardsLayer.innerHTML = '';

        const tables = schemaData.tables || [];
        if (tables.length === 0) {
            elCardsLayer.innerHTML = `
                <div class="designer-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7Z"/>
                        <path d="M9 4v16M15 4v16M4 10h16M4 15h16"/>
                    </svg>
                    <div style="font-size:14px; font-weight:600">Database Belum Memiliki Tabel</div>
                    <div style="font-size:12px; margin-top:4px">Buat migrasi atau impor file .sql untuk melihat tabel di sini.</div>
                </div>
            `;
            return;
        }

        tables.forEach(t => {
            const pos = cardPositions[t.name] || { x: 60, y: 60 };
            const card = document.createElement('div');
            card.className = 'designer-card';
            card.id = `designer-card-${t.name}`;
            card.dataset.tableName = t.name;
            card.style.left = `${pos.x}px`;
            card.style.top = `${pos.y}px`;

            // Hitung baris
            const rowsCountStr = `${t.rows} baris`;

            // HTML Header
            let cardHtml = `
                <div class="designer-card-header" data-card-header="${t.name}">
                    <div class="designer-card-title-wrap">
                        <span class="designer-card-icon">📋</span>
                        <span class="designer-card-name" title="${escapeHtml(t.name)}">${escapeHtml(t.name)}</span>
                    </div>
                    <div class="designer-card-actions">
                        <span class="designer-card-badge">${rowsCountStr}</span>
                        <button type="button" class="designer-card-btn-preview" title="Pratinjau isi tabel ${escapeHtml(t.name)}" onclick="window.DB_DESIGNER.previewTableData('${escapeHtml(t.name)}')">
                            👁️
                        </button>
                    </div>
                </div>
                <div class="designer-card-columns">
            `;

            // Kolom
            t.columns.forEach(col => {
                let keyBadge = '<span class="designer-col-key"></span>';
                if (col.is_pk) {
                    keyBadge = '<span class="designer-col-key" title="Primary Key">🔑</span>';
                } else if (isForeignKeyColumn(t.name, col.name)) {
                    keyBadge = '<span class="designer-col-key" title="Foreign Key">🔗</span>';
                } else if (col.is_unique) {
                    keyBadge = '<span class="designer-col-key" title="Unique">✨</span>';
                }

                const pkClass = col.is_pk ? 'is-pk' : (isForeignKeyColumn(t.name, col.name) ? 'is-fk' : '');
                const nullStr = col.null ? '<span class="designer-col-null" title="Nullable">NULL</span>' : '';

                cardHtml += `
                    <div class="designer-col-row" id="col-${t.name}-${col.name}" data-table="${escapeHtml(t.name)}" data-column="${escapeHtml(col.name)}">
                        <div class="designer-col-left">
                            ${keyBadge}
                            <span class="designer-col-name ${pkClass}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</span>
                        </div>
                        <div class="designer-col-right">
                            <span class="designer-col-type">${escapeHtml(col.type)}</span>
                            ${nullStr}
                        </div>
                    </div>
                `;
            });

            cardHtml += `</div>`;
            card.innerHTML = cardHtml;
            elCardsLayer.appendChild(card);

            // Pasang event listener drag pada header kartu
            const header = card.querySelector('.designer-card-header');
            if (header) {
                header.addEventListener('mousedown', (e) => startCardDrag(e, card));
                header.addEventListener('touchstart', (e) => startCardDragTouch(e, card), { passive: false });
            }
        });
    }

    /**
     * Cek apakah suatu kolom terlibat sebagai Foreign Key (eksplisit atau inferensi)
     */
    function isForeignKeyColumn(tableName, colName) {
        if (!schemaData.relations) return false;
        return schemaData.relations.some(r => r.from_table === tableName && r.from_column === colName);
    }

    /**
     * Render SVG Bezier Relation Lines
     */
    function renderRelations() {
        if (!elSvgCanvas) return;
        elSvgCanvas.innerHTML = '';

        const relations = schemaData.relations || [];
        if (relations.length === 0) return;

        // Definisikan SVG Markers (Panah Ujung Relasi)
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML = `
            <marker id="marker-explicit" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                <polygon points="0 1, 8 4, 0 7" fill="#10b981" />
            </marker>
            <marker id="marker-inferred" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                <polygon points="0 1, 8 4, 0 7" fill="#f97316" />
            </marker>
            <marker id="marker-hover" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                <polygon points="0 1, 8 4, 0 7" fill="#e11d48" />
            </marker>
        `;
        elSvgCanvas.appendChild(defs);

        relations.forEach(rel => {
            const fromCard = document.getElementById(`designer-card-${rel.from_table}`);
            const toCard = document.getElementById(`designer-card-${rel.to_table}`);
            if (!fromCard || !toCard) return;

            const fromColEl = document.getElementById(`col-${rel.from_table}-${rel.from_column}`);
            const toColEl = document.getElementById(`col-${rel.to_table}-${rel.to_column}`);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.id = `rel-${rel.id}`;
            path.classList.add('designer-relation-line', rel.type);
            path.setAttribute('marker-end', `url(#marker-${rel.type})`);
            path.dataset.fromTable = rel.from_table;
            path.dataset.toTable = rel.to_table;
            path.dataset.fromCol = rel.from_column;
            path.dataset.toCol = rel.to_column;
            path.dataset.label = rel.label || '';

            // Tooltip title
            const titleEl = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            titleEl.textContent = `${rel.from_table}.${rel.from_column} → ${rel.to_table}.${rel.to_column} (${rel.type === 'explicit' ? 'Foreign Key' : 'Relasi Konvensi'})`;
            path.appendChild(titleEl);

            // Hover effect
            path.addEventListener('mouseenter', () => highlightRelation(rel, true));
            path.addEventListener('mouseleave', () => highlightRelation(rel, false));

            elSvgCanvas.appendChild(path);
        });

        updateRelationsPositions();
    }

    /**
     * Hitung ulang koordinat kurva bezier untuk seluruh garis relasi
     */
    function updateRelationsPositions() {
        if (!elSvgCanvas) return;
        const relations = schemaData.relations || [];

        relations.forEach(rel => {
            const path = document.getElementById(`rel-${rel.id}`);
            if (!path) return;

            const fromCard = document.getElementById(`designer-card-${rel.from_table}`);
            const toCard = document.getElementById(`designer-card-${rel.to_table}`);
            if (!fromCard || !toCard) return;

            const fromPos = cardPositions[rel.from_table] || { x: 0, y: 0 };
            const toPos = cardPositions[rel.to_table] || { x: 0, y: 0 };
            const cardWidth = fromCard.offsetWidth || 250;

            const fromColEl = document.getElementById(`col-${rel.from_table}-${rel.from_column}`);
            const toColEl = document.getElementById(`col-${rel.to_table}-${rel.to_column}`);

            // Offset Y di dalam kartu
            let fromOffsetY = fromColEl ? (fromColEl.offsetTop + fromColEl.offsetHeight / 2) : 50;
            let toOffsetY = toColEl ? (toColEl.offsetTop + toColEl.offsetHeight / 2) : 50;

            let x1, y1, x2, y2;
            y1 = fromPos.y + fromOffsetY;
            y2 = toPos.y + toOffsetY;

            // Sambungkan sisi kanan ke kiri, atau kiri ke kanan berdasarkan posisi kartu
            if (fromPos.x + cardWidth < toPos.x) {
                // From di kiri, To di kanan
                x1 = fromPos.x + cardWidth;
                x2 = toPos.x;
            } else if (fromPos.x > toPos.x + cardWidth) {
                // From di kanan, To di kiri
                x1 = fromPos.x;
                x2 = toPos.x + cardWidth;
            } else {
                // Tumpang tindih horizontal: sambungkan sisi terdekat
                if (fromPos.x <= toPos.x) {
                    x1 = fromPos.x + cardWidth;
                    x2 = toPos.x + cardWidth;
                } else {
                    x1 = fromPos.x;
                    x2 = toPos.x;
                }
            }

            // Gambar Smooth Cubic Bezier Curve
            const dx = Math.abs(x2 - x1) * 0.5 + 20;
            const c1x = (x1 < x2) ? (x1 + dx) : (x1 - dx);
            const c2x = (x1 < x2) ? (x2 - dx) : (x2 + dx);

            const d = `M ${x1} ${y1} C ${c1x} ${y1}, ${c2x} ${y2}, ${x2} ${y2}`;
            path.setAttribute('d', d);
        });
    }

    /**
     * Sorot kartu & kolom saat garis relasi di-hover
     */
    function highlightRelation(rel, isHover) {
        const fromCard = document.getElementById(`designer-card-${rel.from_table}`);
        const toCard = document.getElementById(`designer-card-${rel.to_table}`);
        const fromCol = document.getElementById(`col-${rel.from_table}-${rel.from_column}`);
        const toCol = document.getElementById(`col-${rel.to_table}-${rel.to_column}`);
        const path = document.getElementById(`rel-${rel.id}`);

        if (isHover) {
            if (fromCard) fromCard.classList.add('highlighted');
            if (toCard) toCard.classList.add('highlighted');
            if (fromCol) fromCol.classList.add('highlight');
            if (toCol) toCol.classList.add('highlight');
            if (path) {
                path.classList.add('highlight');
                path.setAttribute('marker-end', 'url(#marker-hover)');
            }
        } else {
            if (fromCard) fromCard.classList.remove('highlighted');
            if (toCard) toCard.classList.remove('highlighted');
            if (fromCol) fromCol.classList.remove('highlight');
            if (toCol) toCol.classList.remove('highlight');
            if (path) {
                path.classList.remove('highlight');
                path.setAttribute('marker-end', `url(#marker-${rel.type})`);
            }
        }
    }

    // ------------------------------------------------------------------
    // DRAG AND DROP KARTU TABEL
    // ------------------------------------------------------------------
    function startCardDrag(e, card) {
        if (e.button !== 0) return; // Hanya klik kiri
        e.stopPropagation();

        draggedCard = card;
        dragStartX = e.clientX;
        dragStartY = e.clientY;

        const tblName = card.dataset.tableName;
        const currentPos = cardPositions[tblName] || { x: card.offsetLeft, y: card.offsetTop };
        cardStartX = currentPos.x;
        cardStartY = currentPos.y;

        card.classList.add('dragging');

        document.addEventListener('mousemove', onCardDragMove);
        document.addEventListener('mouseup', onCardDragEnd);
    }

    function onCardDragMove(e) {
        if (!draggedCard) return;

        const deltaX = (e.clientX - dragStartX) / scale;
        const deltaY = (e.clientY - dragStartY) / scale;

        const newX = Math.max(10, Math.round(cardStartX + deltaX));
        const newY = Math.max(10, Math.round(cardStartY + deltaY));

        draggedCard.style.left = `${newX}px`;
        draggedCard.style.top = `${newY}px`;

        const tblName = draggedCard.dataset.tableName;
        cardPositions[tblName] = { x: newX, y: newY };

        // Perbarui garis relasi langsung
        updateRelationsPositions();
    }

    function onCardDragEnd() {
        if (draggedCard) {
            draggedCard.classList.remove('dragging');
            draggedCard = null;
        }
        document.removeEventListener('mousemove', onCardDragMove);
        document.removeEventListener('mouseup', onCardDragEnd);
    }

    // Sentuhan di Handphone / Tablet
    function startCardDragTouch(e, card) {
        if (e.touches.length !== 1) return;
        e.stopPropagation();

        const touch = e.touches[0];
        draggedCard = card;
        dragStartX = touch.clientX;
        dragStartY = touch.clientY;

        const tblName = card.dataset.tableName;
        const currentPos = cardPositions[tblName] || { x: card.offsetLeft, y: card.offsetTop };
        cardStartX = currentPos.x;
        cardStartY = currentPos.y;

        card.classList.add('dragging');

        document.addEventListener('touchmove', onCardDragMoveTouch, { passive: false });
        document.addEventListener('touchend', onCardDragEndTouch);
    }

    function onCardDragMoveTouch(e) {
        if (!draggedCard || e.touches.length !== 1) return;
        e.preventDefault(); // Cegah scrolling browser bawaan

        const touch = e.touches[0];
        const deltaX = (touch.clientX - dragStartX) / scale;
        const deltaY = (touch.clientY - dragStartY) / scale;

        const newX = Math.max(10, Math.round(cardStartX + deltaX));
        const newY = Math.max(10, Math.round(cardStartY + deltaY));

        draggedCard.style.left = `${newX}px`;
        draggedCard.style.top = `${newY}px`;

        const tblName = draggedCard.dataset.tableName;
        cardPositions[tblName] = { x: newX, y: newY };

        updateRelationsPositions();
    }

    function onCardDragEndTouch() {
        if (draggedCard) {
            draggedCard.classList.remove('dragging');
            draggedCard = null;
        }
        document.removeEventListener('touchmove', onCardDragMoveTouch);
        document.removeEventListener('touchend', onCardDragEndTouch);
    }

    // ------------------------------------------------------------------
    // PAN & ZOOM KANVAS
    // ------------------------------------------------------------------
    function initCanvasPanZoom() {
        if (!elViewport) return;

        elViewport.addEventListener('mousedown', (e) => {
            // Hanya pan jika klik di area kosong viewport (bukan kartu)
            if (e.target.closest('.designer-card') || e.target.closest('.designer-preview-drawer')) return;
            if (e.button !== 0) return;

            isPanning = true;
            panStartX = e.clientX - panX;
            panStartY = e.clientY - panY;
            elViewport.classList.add('panning');

            document.addEventListener('mousemove', onCanvasPanMove);
            document.addEventListener('mouseup', onCanvasPanEnd);
        });

        // Wheel Zoom
        elViewport.addEventListener('wheel', (e) => {
            if (e.target.closest('.designer-card-columns') || e.target.closest('.designer-preview-body')) return;
            e.preventDefault();

            const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
            const newScale = Math.min(Math.max(scale * zoomFactor, 0.35), 2.2);

            // Zoom mengarah ke kursor
            const rect = elViewport.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            panX = mouseX - (mouseX - panX) * (newScale / scale);
            panY = mouseY - (mouseY - panY) * (newScale / scale);
            scale = newScale;

            updateWorldTransform();
        }, { passive: false });
    }

    function onCanvasPanMove(e) {
        if (!isPanning) return;
        panX = e.clientX - panStartX;
        panY = e.clientY - panStartY;
        updateWorldTransform();
    }

    function onCanvasPanEnd() {
        isPanning = false;
        if (elViewport) elViewport.classList.remove('panning');
        document.removeEventListener('mousemove', onCanvasPanMove);
        document.removeEventListener('mouseup', onCanvasPanEnd);
    }

    function updateWorldTransform() {
        if (!elWorld) return;
        elWorld.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;

        const zoomBadge = document.getElementById('designer-zoom-badge');
        if (zoomBadge) {
            zoomBadge.textContent = `${Math.round(scale * 100)}%`;
        }
    }

    function zoomIn() {
        scale = Math.min(scale * 1.2, 2.2);
        updateWorldTransform();
    }

    function zoomOut() {
        scale = Math.max(scale * 0.8, 0.35);
        updateWorldTransform();
    }

    function resetView() {
        scale = 1.0;
        panX = 60;
        panY = 60;
        updateWorldTransform();
    }

    function autoArrange() {
        autoArrangePositions();
        const tables = schemaData.tables || [];
        tables.forEach(t => {
            const card = document.getElementById(`designer-card-${t.name}`);
            const pos = cardPositions[t.name];
            if (card && pos) {
                card.style.transition = 'left 0.3s ease, top 0.3s ease';
                card.style.left = `${pos.x}px`;
                card.style.top = `${pos.y}px`;
                setTimeout(() => {
                    card.style.transition = '';
                    updateRelationsPositions();
                }, 310);
            }
        });
        resetView();
    }

    // ------------------------------------------------------------------
    // PENCARIAN / FILTER TABEL
    // ------------------------------------------------------------------
    function filterTables(query) {
        const q = (query || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.designer-card');

        cards.forEach(card => {
            const name = (card.dataset.tableName || '').toLowerCase();
            if (q === '' || name.includes(q)) {
                card.classList.remove('dimmed');
            } else {
                card.classList.add('dimmed');
            }
        });
    }

    // ------------------------------------------------------------------
    // PRATINJAU DATA 10 BARIS TABEL
    // ------------------------------------------------------------------
    async function previewTableData(tableName) {
        if (!elPreviewDrawer || !activeDbId) return;

        const titleEl = document.getElementById('designer-preview-title');
        const bodyEl = document.getElementById('designer-preview-body');

        if (titleEl) titleEl.textContent = `Pratinjau Data: ${tableName} (Memuat…)`;
        if (bodyEl) bodyEl.innerHTML = `<div style="color:#94a3b8; padding:12px">Mengambil data dari server…</div>`;

        elPreviewDrawer.classList.add('show');

        try {
            const res = await fetch(`api/db-designer.php?db_id=${activeDbId}&action=preview&table=${encodeURIComponent(tableName)}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                if (bodyEl) bodyEl.innerHTML = `<div style="color:#ef4444; padding:12px">${escapeHtml(data.error || 'Gagal mengambil data.')}</div>`;
                return;
            }

            if (titleEl) {
                titleEl.textContent = `Pratinjau Data: ${tableName} (${data.total_rows} total baris, menampilkan maks 10 baris)`;
            }

            if (!data.rows || data.rows.length === 0) {
                if (bodyEl) bodyEl.innerHTML = `<div style="color:#94a3b8; padding:16px; text-align:center">Tabel ini belum memiliki data baris (kosong).</div>`;
                return;
            }

            let tableHtml = `<table class="designer-preview-table"><thead><tr>`;
            data.columns.forEach(col => {
                tableHtml += `<th>${escapeHtml(col)}</th>`;
            });
            tableHtml += `</tr></thead><tbody>`;

            data.rows.forEach(r => {
                tableHtml += `<tr>`;
                data.columns.forEach(col => {
                    const val = r[col];
                    if (val === null) {
                        tableHtml += `<td style="color:#64748b; font-style:italic">NULL</td>`;
                    } else {
                        tableHtml += `<td>${escapeHtml(val)}</td>`;
                    }
                });
                tableHtml += `</tr>`;
            });

            tableHtml += `</tbody></table>`;
            if (bodyEl) bodyEl.innerHTML = tableHtml;

        } catch (err) {
            if (bodyEl) bodyEl.innerHTML = `<div style="color:#ef4444; padding:12px">Kesalahan: ${escapeHtml(err.message)}</div>`;
        }
    }

    function closePreview() {
        if (elPreviewDrawer) elPreviewDrawer.classList.remove('show');
    }

    /**
     * Escape HTML string untuk keamanan XSS
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Pasang API publik ke objek global
    window.DB_DESIGNER = {
        open: openDatabaseDesigner,
        close: closeDatabaseDesigner,
        zoomIn,
        zoomOut,
        resetView,
        autoArrange,
        filterTables,
        previewTableData,
        closePreview,
        reload: () => {
            if (activeDbId) openDatabaseDesigner(activeDbId, activeDbName);
        }
    };

    // Alias fungsi pembuka agar mudah dipanggil tombol onclick
    window.openDatabaseDesigner = openDatabaseDesigner;

    // Inisialisasi event listener setelah DOM siap
    document.addEventListener('DOMContentLoaded', () => {
        initDOMElements();
        initCanvasPanZoom();

        // Shortcut Esc untuk menutup modal desainer
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && elModal && elModal.classList.contains('show')) {
                if (elPreviewDrawer && elPreviewDrawer.classList.contains('show')) {
                    closePreview();
                } else {
                    closeDatabaseDesigner();
                }
            }
        });
    });

})();

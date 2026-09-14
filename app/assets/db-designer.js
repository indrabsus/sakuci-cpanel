/**
 * Sakuci Database Schema Designer ala SQLyog & phpMyAdmin
 * 100% Full Lokal (Tanpa dependensi CDN luar)
 * Fitur:
 * - Draggable Table Cards (Touch & Mouse)
 * - Real-time SVG Curve Relations & Interactive FK Deletion
 * - Pan & Zoom (Mouse drag, wheel, touch pan & pinch zoom)
 * - Auto-Arrange & Smart Grid
 * - Visual Table Creator (Create Table with columns builder)
 * - Drop Table with safe confirmation
 * - Add Column & Drop Column
 * - Create Foreign Key & Drop Foreign Key
 * - Data CRUD Drawer (Preview, Insert New Row, Delete Row)
 */

(function () {
    'use strict';

    // State Internal
    let activeDbId = null;
    let activeDbName = '';
    let schemaData = { tables: [], relations: [] };
    let cardPositions = {}; // tableName -> { x, y }

    // State Drawer Data
    let currentPreviewTable = null;
    let currentTableMeta = null;

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

        const titleEl = document.getElementById('designer-db-name');
        const metaEl = document.getElementById('designer-db-meta');
        if (titleEl) titleEl.textContent = activeDbName;
        if (metaEl) metaEl.textContent = 'Memuat skema…';

        elModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        scale = 1.0;
        panX = 60;
        panY = 60;
        updateWorldTransform();

        if (elCardsLayer) {
            elCardsLayer.innerHTML = `
                <div class="designer-empty-state">
                    <div style="font-size:24px; margin-bottom:8px">⏳</div>
                    <div>Menganalisis skema tabel &amp; relasi…</div>
                </div>
            `;
        }
        if (elSvgCanvas) elSvgCanvas.innerHTML = '';

        await reloadSchema(false);
    }

    /**
     * Muat ulang skema dari backend
     */
    async function reloadSchema(preservePositions = true) {
        if (!activeDbId) return;
        const metaEl = document.getElementById('designer-db-meta');

        try {
            const res = await fetch(`api/db-designer.php?db_id=${activeDbId}&action=schema`, {
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
                metaEl.textContent = `${data.tables_count || (data.tables ? data.tables.length : 0)} tabel • ${data.relations_count || (data.relations ? data.relations.length : 0)} relasi`;
            }

            if (!preservePositions || Object.keys(cardPositions).length === 0) {
                autoArrangePositions();
            } else {
                // Pertahankan posisi lama, tambahkan posisi baru untuk tabel yang baru dibuat
                const cardWidth = 250;
                const gapX = 70;
                const gapY = 50;
                let nextX = 60;
                let nextY = 60;

                (schemaData.tables || []).forEach(t => {
                    if (!cardPositions[t.name]) {
                        // Cari posisi kosong
                        cardPositions[t.name] = { x: nextX, y: nextY };
                        nextX += cardWidth + gapX;
                        if (nextX > 900) {
                            nextX = 60;
                            nextY += 280 + gapY;
                        }
                    }
                });
            }

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
        closeAllSubmodals();
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
                    <div style="font-size:12px; margin-top:4px">Klik tombol "➕ Tabel Baru" di atas untuk mendesain tabel pertamamu.</div>
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

            const rowsCountStr = `${t.rows} baris`;

            // HTML Header Kartu dengan Aksi: Tambah Kolom, Kelola Data, Hapus Tabel
            let cardHtml = `
                <div class="designer-card-header" data-card-header="${escapeHtml(t.name)}">
                    <div class="designer-card-title-wrap">
                        <span class="designer-card-icon">📋</span>
                        <span class="designer-card-name" title="${escapeHtml(t.name)}">${escapeHtml(t.name)}</span>
                    </div>
                    <div class="designer-card-actions">
                        <span class="designer-card-badge" id="card-badge-${escapeHtml(t.name)}">${rowsCountStr}</span>
                        <button type="button" class="designer-card-btn-action" title="Tambah kolom ke ${escapeHtml(t.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.openAddColumnModal('${escapeHtml(t.name)}')">
                            ➕
                        </button>
                        <button type="button" class="designer-card-btn-preview" title="Kelola data tabel ${escapeHtml(t.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.previewTableData('${escapeHtml(t.name)}')">
                            👁️
                        </button>
                        <button type="button" class="designer-card-btn-danger" title="Hapus tabel ${escapeHtml(t.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.dropTable('${escapeHtml(t.name)}')">
                            🗑️
                        </button>
                    </div>
                </div>
                <div class="designer-card-columns">
            `;

            // Render Daftar Kolom
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
                    <div class="designer-col-row" id="col-${escapeHtml(t.name)}-${escapeHtml(col.name)}" data-table="${escapeHtml(t.name)}" data-column="${escapeHtml(col.name)}">
                        <div class="designer-col-left">
                            ${keyBadge}
                            <span class="designer-col-name ${pkClass}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</span>
                        </div>
                        <div class="designer-col-right">
                            <span class="designer-col-type">${escapeHtml(col.type)}</span>
                            ${nullStr}
                            ${!col.is_pk ? `<button type="button" class="designer-col-delete-btn" title="Hapus kolom ${escapeHtml(col.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.dropColumn('${escapeHtml(t.name)}', '${escapeHtml(col.name)}')">✕</button>` : ''}
                        </div>
                    </div>
                `;
            });

            cardHtml += `</div>`;

            // Footer Kartu: Tombol Cepat Tambah Kolom
            cardHtml += `
                <div class="designer-card-footer">
                    <button type="button" class="designer-card-add-col-btn" onclick="window.DB_DESIGNER.openAddColumnModal('${escapeHtml(t.name)}')">
                        + Tambah Kolom
                    </button>
                </div>
            `;

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
     * Cek apakah suatu kolom terlibat sebagai Foreign Key
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

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.id = `rel-${rel.id}`;
            path.classList.add('designer-relation-line', rel.type);
            path.setAttribute('marker-end', `url(#marker-${rel.type})`);
            path.dataset.fromTable = rel.from_table;
            path.dataset.toTable = rel.to_table;
            path.dataset.fromCol = rel.from_column;
            path.dataset.toCol = rel.to_column;
            path.dataset.label = rel.label || '';

            const titleEl = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            titleEl.textContent = `${rel.from_table}.${rel.from_column} → ${rel.to_table}.${rel.to_column} (${rel.type === 'explicit' ? 'Foreign Key: ' + (rel.constraint_name || rel.label) + ' • Klik untuk menghapus relasi' : 'Relasi Konvensi • Klik untuk buat Foreign Key resmi'})`;
            path.appendChild(titleEl);

            // Hover highlight
            path.addEventListener('mouseenter', () => highlightRelation(rel, true));
            path.addEventListener('mouseleave', () => highlightRelation(rel, false));

            // Interaksi klik relasi (Hapus FK atau jadikan FK resmi)
            path.addEventListener('click', (e) => {
                e.stopPropagation();
                onRelationClick(rel);
            });

            elSvgCanvas.appendChild(path);
        });

        updateRelationsPositions();
    }

    /**
     * Aksi klik pada garis kurva relasi
     */
    function onRelationClick(rel) {
        if (rel.type === 'explicit') {
            const cName = rel.constraint_name || rel.label;
            const msg = `Relasi Foreign Key Eksplisit:\nConstraint: ${cName}\n${rel.from_table}.${rel.from_column} ➔ ${rel.to_table}.${rel.to_column}\n\nApakah Anda ingin MENGHAPUS batasan Foreign Key ini dari database?`;
            if (confirm(msg)) {
                dropForeignKey(rel.from_table, cName);
            }
        } else {
            const msg = `Relasi Konvensi Terdeteksi:\n${rel.from_table}.${rel.from_column} ➔ ${rel.to_table}.${rel.to_column}\n\nKolom ini terdeteksi memiliki relasi berdasarkan nama, tetapi belum ada batasan Foreign Key resmi di MySQL.\n\nApakah Anda ingin membuat batasan Foreign Key resmi untuk relasi ini sekarang?`;
            if (confirm(msg)) {
                openAddRelationModal(rel.from_table, rel.from_column, rel.to_table, rel.to_column);
            }
        }
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

            let fromOffsetY = fromColEl ? (fromColEl.offsetTop + fromColEl.offsetHeight / 2) : 50;
            let toOffsetY = toColEl ? (toColEl.offsetTop + toColEl.offsetHeight / 2) : 50;

            let x1, y1, x2, y2;
            y1 = fromPos.y + fromOffsetY;
            y2 = toPos.y + toOffsetY;

            if (fromPos.x + cardWidth < toPos.x) {
                x1 = fromPos.x + cardWidth;
                x2 = toPos.x;
            } else if (fromPos.x > toPos.x + cardWidth) {
                x1 = fromPos.x;
                x2 = toPos.x + cardWidth;
            } else {
                if (fromPos.x <= toPos.x) {
                    x1 = fromPos.x + cardWidth;
                    x2 = toPos.x + cardWidth;
                } else {
                    x1 = fromPos.x;
                    x2 = toPos.x;
                }
            }

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
    // DRAG AND DROP KARTU TABEL (DESKTOP MOUSE)
    // ------------------------------------------------------------------
    function startCardDrag(e, card) {
        if (e.button !== 0) return;
        if (e.target.closest('button') || e.target.closest('input')) return;
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

    // ------------------------------------------------------------------
    // DRAG AND DROP KARTU TABEL (TOUCH MOBILE / TABLET)
    // ------------------------------------------------------------------
    function startCardDragTouch(e, card) {
        if (e.touches.length !== 1) return;
        if (e.target.closest('button') || e.target.closest('input')) return;
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

        document.querySelectorAll('.designer-card').forEach(c => c.style.zIndex = '10');
        card.style.zIndex = '50';

        document.addEventListener('touchmove', onCardDragMoveTouch, { passive: false });
        document.addEventListener('touchend', onCardDragEndTouch);
        document.addEventListener('touchcancel', onCardDragEndTouch);
    }

    function onCardDragMoveTouch(e) {
        if (!draggedCard || e.touches.length !== 1) return;
        if (e.cancelable) e.preventDefault();

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
            draggedCard.style.zIndex = '10';
            draggedCard = null;
        }
        document.removeEventListener('touchmove', onCardDragMoveTouch);
        document.removeEventListener('touchend', onCardDragEndTouch);
        document.removeEventListener('touchcancel', onCardDragEndTouch);
    }

    // ------------------------------------------------------------------
    // PAN & ZOOM KANVAS (DESKTOP & MOBILE)
    // ------------------------------------------------------------------
    function initCanvasPanZoom() {
        if (!elViewport) return;

        elViewport.addEventListener('mousedown', (e) => {
            if (e.target.closest('.designer-card') || e.target.closest('.designer-preview-drawer') || e.target.closest('.designer-submodal-card')) return;
            if (e.button !== 0) return;

            isPanning = true;
            panStartX = e.clientX - panX;
            panStartY = e.clientY - panY;
            elViewport.classList.add('panning');

            document.addEventListener('mousemove', onCanvasPanMove);
            document.addEventListener('mouseup', onCanvasPanEnd);
        });

        elViewport.addEventListener('wheel', (e) => {
            if (e.target.closest('.designer-card-columns') || e.target.closest('.designer-preview-body') || e.target.closest('.designer-submodal-body')) return;
            e.preventDefault();

            const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
            const newScale = Math.min(Math.max(scale * zoomFactor, 0.35), 2.2);

            const rect = elViewport.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            panX = mouseX - (mouseX - panX) * (newScale / scale);
            panY = mouseY - (mouseY - panY) * (newScale / scale);
            scale = newScale;

            updateWorldTransform();
        }, { passive: false });

        let isTouchPanning = false;
        let isPinching = false;
        let touchStartX = 0;
        let touchStartY = 0;
        let pinchStartDist = 0;
        let pinchStartScale = 1;
        let pinchMidX = 0;
        let pinchMidY = 0;
        let pinchStartPanX = 0;
        let pinchStartPanY = 0;

        elViewport.addEventListener('touchstart', (e) => {
            if (e.target.closest('.designer-card') || e.target.closest('.designer-preview-drawer') || e.target.closest('.designer-submodal-card')) return;

            if (e.touches.length === 1) {
                isTouchPanning = true;
                isPinching = false;
                const touch = e.touches[0];
                touchStartX = touch.clientX - panX;
                touchStartY = touch.clientY - panY;
                elViewport.classList.add('panning');
            } else if (e.touches.length === 2) {
                isTouchPanning = false;
                isPinching = true;
                const t1 = e.touches[0];
                const t2 = e.touches[1];
                pinchStartDist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
                pinchStartScale = scale;
                pinchStartPanX = panX;
                pinchStartPanY = panY;

                const rect = elViewport.getBoundingClientRect();
                pinchMidX = (t1.clientX + t2.clientX) / 2 - rect.left;
                pinchMidY = (t1.clientY + t2.clientY) / 2 - rect.top;
            }
        }, { passive: false });

        elViewport.addEventListener('touchmove', (e) => {
            if (isTouchPanning && e.touches.length === 1) {
                if (e.cancelable) e.preventDefault();
                const touch = e.touches[0];
                panX = touch.clientX - touchStartX;
                panY = touch.clientY - touchStartY;
                updateWorldTransform();
            } else if (isPinching && e.touches.length === 2) {
                if (e.cancelable) e.preventDefault();
                const t1 = e.touches[0];
                const t2 = e.touches[1];
                const currentDist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
                if (pinchStartDist > 0) {
                    const factor = currentDist / pinchStartDist;
                    const newScale = Math.min(Math.max(pinchStartScale * factor, 0.3), 2.5);

                    panX = pinchMidX - (pinchMidX - pinchStartPanX) * (newScale / pinchStartScale);
                    panY = pinchMidY - (pinchMidY - pinchStartPanY) * (newScale / pinchStartScale);
                    scale = newScale;
                    updateWorldTransform();
                }
            }
        }, { passive: false });

        const onTouchEnd = () => {
            isTouchPanning = false;
            isPinching = false;
            if (elViewport) elViewport.classList.remove('panning');
        };

        elViewport.addEventListener('touchend', onTouchEnd);
        elViewport.addEventListener('touchcancel', onTouchEnd);
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
    // MANAJEMEN SUBMODAL POPUP
    // ------------------------------------------------------------------
    function openSubmodal(id) {
        const modal = document.getElementById(id.startsWith('designer-modal-') ? id : `designer-modal-${id}`);
        if (modal) modal.classList.add('show');
    }

    function closeSubmodal(id) {
        const modal = document.getElementById(id.startsWith('designer-modal-') ? id : `designer-modal-${id}`);
        if (modal) modal.classList.remove('show');
    }

    function closeAllSubmodals() {
        document.querySelectorAll('.designer-submodal-backdrop').forEach(m => m.classList.remove('show'));
    }

    /**
     * Otomatisasi pengaturan panjang default saat tipe data berubah
     */
    function onColTypeChange(val, lengthTarget) {
        const lenEl = typeof lengthTarget === 'string' ? document.getElementById(lengthTarget) : lengthTarget;
        if (!lenEl) return;

        val = (val || '').toUpperCase();
        if (val === 'VARCHAR') {
            if (!lenEl.value || lenEl.value === '11') lenEl.value = '255';
        } else if (val === 'INT') {
            lenEl.value = '11';
        } else if (val === 'BIGINT') {
            lenEl.value = '20';
        } else if (val === 'DECIMAL') {
            if (!lenEl.value || lenEl.value === '255' || lenEl.value === '11') lenEl.value = '10,2';
        } else if (['TEXT', 'DATE', 'DATETIME', 'TIMESTAMP', 'JSON', 'BOOLEAN', 'TIME'].includes(val)) {
            lenEl.value = '';
        }
    }

    // ------------------------------------------------------------------
    // FITUR 1: BUAT TABEL BARU (CREATE TABLE)
    // ------------------------------------------------------------------
    function openCreateTableModal() {
        const nameInput = document.getElementById('dsg-create-tbl-name');
        const bodyEl = document.getElementById('dsg-create-cols-body');
        if (nameInput) nameInput.value = '';
        if (bodyEl) bodyEl.innerHTML = '';

        // Default 3 baris standar (id, nama, created_at)
        appendCreateTableColumnRow({ name: 'id', type: 'INT', length: '11', is_pk: true, is_ai: true, null: false, default: '' });
        appendCreateTableColumnRow({ name: 'nama', type: 'VARCHAR', length: '255', is_pk: false, is_ai: false, null: false, default: '' });
        appendCreateTableColumnRow({ name: 'created_at', type: 'TIMESTAMP', length: '', is_pk: false, is_ai: false, null: false, default: 'CURRENT_TIMESTAMP' });

        openSubmodal('create-table');
        if (nameInput) setTimeout(() => nameInput.focus(), 150);
    }

    function appendCreateTableColumnRow(def = {}) {
        const bodyEl = document.getElementById('dsg-create-cols-body');
        if (!bodyEl) return;

        const tr = document.createElement('tr');
        const curType = (def.type || 'VARCHAR').toUpperCase();

        tr.innerHTML = `
            <td>
                <input type="text" class="dsg-create-col-name" placeholder="nama_kolom" value="${escapeHtml(def.name || '')}">
            </td>
            <td>
                <select class="dsg-create-col-type">
                    <option value="INT" ${curType === 'INT' ? 'selected' : ''}>INT</option>
                    <option value="VARCHAR" ${curType === 'VARCHAR' ? 'selected' : ''}>VARCHAR</option>
                    <option value="BIGINT" ${curType === 'BIGINT' ? 'selected' : ''}>BIGINT</option>
                    <option value="TEXT" ${curType === 'TEXT' ? 'selected' : ''}>TEXT</option>
                    <option value="DECIMAL" ${curType === 'DECIMAL' ? 'selected' : ''}>DECIMAL</option>
                    <option value="DATE" ${curType === 'DATE' ? 'selected' : ''}>DATE</option>
                    <option value="DATETIME" ${curType === 'DATETIME' ? 'selected' : ''}>DATETIME</option>
                    <option value="TIMESTAMP" ${curType === 'TIMESTAMP' ? 'selected' : ''}>TIMESTAMP</option>
                    <option value="BOOLEAN" ${curType === 'BOOLEAN' ? 'selected' : ''}>BOOLEAN</option>
                    <option value="JSON" ${curType === 'JSON' ? 'selected' : ''}>JSON</option>
                </select>
            </td>
            <td>
                <input type="text" class="dsg-create-col-len" value="${def.length !== undefined ? escapeHtml(def.length) : (curType === 'INT' ? '11' : (curType === 'VARCHAR' ? '255' : ''))}">
            </td>
            <td style="text-align:center">
                <input type="checkbox" class="dsg-create-col-pk" ${def.is_pk ? 'checked' : ''} title="Jadikan Primary Key">
            </td>
            <td style="text-align:center">
                <input type="checkbox" class="dsg-create-col-ai" ${def.is_ai ? 'checked' : ''} title="Auto Increment">
            </td>
            <td style="text-align:center">
                <input type="checkbox" class="dsg-create-col-null" ${def.null ? 'checked' : ''} title="Boleh bernilai NULL">
            </td>
            <td>
                <input type="text" class="dsg-create-col-def" placeholder="NULL / nilai" value="${escapeHtml(def.default || '')}">
            </td>
            <td style="text-align:center">
                <button type="button" class="designer-card-btn-danger" style="font-size:13px; padding:2px 6px" onclick="window.DB_DESIGNER.removeCreateColRow(this)" title="Hapus baris kolom">
                    🗑️
                </button>
            </td>
        `;

        const typeSelect = tr.querySelector('.dsg-create-col-type');
        const lenInput = tr.querySelector('.dsg-create-col-len');
        if (typeSelect && lenInput) {
            typeSelect.addEventListener('change', () => onColTypeChange(typeSelect.value, lenInput));
        }

        bodyEl.appendChild(tr);
    }

    function removeCreateColRow(btn) {
        const tr = btn.closest('tr');
        if (tr) tr.remove();
    }

    async function submitCreateTable() {
        if (!activeDbId) return;

        const nameInput = document.getElementById('dsg-create-tbl-name');
        const tableName = (nameInput ? nameInput.value : '').trim();

        if (!tableName) {
            alert('Mohon isi nama tabel terlebih dahulu.');
            if (nameInput) nameInput.focus();
            return;
        }

        const rows = document.querySelectorAll('#dsg-create-cols-body tr');
        if (rows.length === 0) {
            alert('Tabel minimal harus memiliki 1 baris kolom.');
            return;
        }

        const colsData = [];
        let hasInvalidName = false;

        rows.forEach(r => {
            const cName = (r.querySelector('.dsg-create-col-name')?.value || '').trim();
            const cType = r.querySelector('.dsg-create-col-type')?.value || 'VARCHAR';
            const cLen = (r.querySelector('.dsg-create-col-len')?.value || '').trim();
            const isPk = !!r.querySelector('.dsg-create-col-pk')?.checked;
            const isAi = !!r.querySelector('.dsg-create-col-ai')?.checked;
            const isNull = !!r.querySelector('.dsg-create-col-null')?.checked;
            const defVal = (r.querySelector('.dsg-create-col-def')?.value || '').trim();

            if (!cName) {
                hasInvalidName = true;
                return;
            }

            colsData.push({
                name: cName,
                type: cType,
                length: cLen,
                is_pk: isPk,
                is_ai: isAi,
                is_null: isNull,
                default: defVal
            });
        });

        if (hasInvalidName || colsData.length === 0) {
            alert('Pastikan semua baris kolom memiliki nama kolom yang valid.');
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'create_table');
        fd.append('table_name', tableName);
        fd.append('columns', JSON.stringify(colsData));

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal membuat tabel: ' + (data.error || 'Terjadi kesalahan sistem.'));
                return;
            }

            closeSubmodal('create-table');
            await reloadSchema(true);

            // Sorot tabel yang baru dibuat
            setTimeout(() => {
                const card = document.getElementById(`designer-card-${tableName}`);
                if (card) {
                    card.classList.add('highlighted');
                    setTimeout(() => card.classList.remove('highlighted'), 2000);
                }
            }, 300);

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 2: HAPUS TABEL (DROP TABLE)
    // ------------------------------------------------------------------
    async function dropTable(tableName) {
        if (!activeDbId || !tableName) return;

        const promptText = `⚠️ PERINGATAN HAPUS TABEL:\n\nApakah Anda yakin ingin MENGHAPUS tabel '${tableName}' secara permanen beserta seluruh baris data di dalamnya?\n\nTindakan ini TIDAK DAPAT DIBATALKAN!`;
        if (!confirm(promptText)) return;

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'drop_table');
        fd.append('table', tableName);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menghapus tabel: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            if (currentPreviewTable === tableName) {
                closePreview();
            }

            delete cardPositions[tableName];
            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 3: TAMBAH KOLOM (ADD COLUMN)
    // ------------------------------------------------------------------
    function openAddColumnModal(tableName) {
        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) return;

        const hiddenTarget = document.getElementById('dsg-add-col-target-table');
        const titleEl = document.getElementById('dsg-add-col-title');
        const nameInput = document.getElementById('dsg-add-col-name');
        const typeSelect = document.getElementById('dsg-add-col-type');
        const lengthInput = document.getElementById('dsg-add-col-length');
        const nullCheck = document.getElementById('dsg-add-col-null');
        const defInput = document.getElementById('dsg-add-col-default');
        const posSelect = document.getElementById('dsg-add-col-position');

        if (hiddenTarget) hiddenTarget.value = tableName;
        if (titleEl) titleEl.textContent = `Tambah Kolom ke: ${tableName}`;
        if (nameInput) nameInput.value = '';
        if (typeSelect) typeSelect.value = 'VARCHAR';
        if (lengthInput) lengthInput.value = '255';
        if (nullCheck) nullCheck.checked = true;
        if (defInput) defInput.value = '';

        // Bangun opsi posisi kolom (FIRST, AFTER last, atau AFTER kolom tertentu)
        if (posSelect) {
            let posHtml = `
                <option value="AFTER_LAST">Di Akhir Tabel (Bawaan)</option>
                <option value="FIRST">Di Awal Tabel (Paling Pertama)</option>
            `;
            tableObj.columns.forEach(c => {
                posHtml += `<option value="AFTER_${escapeHtml(c.name)}">Setelah kolom ${escapeHtml(c.name)}</option>`;
            });
            posSelect.innerHTML = posHtml;
        }

        openSubmodal('add-column');
        if (nameInput) setTimeout(() => nameInput.focus(), 150);
    }

    async function submitAddColumn() {
        if (!activeDbId) return;

        const tableName = document.getElementById('dsg-add-col-target-table')?.value || '';
        const colName = (document.getElementById('dsg-add-col-name')?.value || '').trim();
        const cType = document.getElementById('dsg-add-col-type')?.value || 'VARCHAR';
        const cLen = (document.getElementById('dsg-add-col-length')?.value || '').trim();
        const isNull = document.getElementById('dsg-add-col-null')?.checked ? '1' : '';
        const defVal = (document.getElementById('dsg-add-col-default')?.value || '').trim();
        const posVal = document.getElementById('dsg-add-col-position')?.value || 'AFTER_LAST';

        if (!tableName || !colName) {
            alert('Nama kolom wajib diisi.');
            return;
        }

        let position = '';
        let afterCol = '';

        if (posVal === 'FIRST') {
            position = 'FIRST';
        } else if (posVal.startsWith('AFTER_') && posVal !== 'AFTER_LAST') {
            position = 'AFTER';
            afterCol = posVal.replace('AFTER_', '');
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'add_column');
        fd.append('table', tableName);
        fd.append('column_name', colName);
        fd.append('type', cType);
        fd.append('length', cLen);
        fd.append('is_null', isNull);
        fd.append('default', defVal);
        fd.append('position', position);
        fd.append('after_column', afterCol);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menambah kolom: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            closeSubmodal('add-column');
            await reloadSchema(true);

            if (currentPreviewTable === tableName) {
                previewTableData(tableName);
            }

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 4: HAPUS KOLOM (DROP COLUMN)
    // ------------------------------------------------------------------
    async function dropColumn(tableName, colName) {
        if (!activeDbId || !tableName || !colName) return;

        if (!confirm(`Apakah Anda yakin ingin MENGHAPUS kolom '${colName}' dari tabel '${tableName}'?`)) {
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'drop_column');
        fd.append('table', tableName);
        fd.append('column', colName);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menghapus kolom: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            await reloadSchema(true);
            if (currentPreviewTable === tableName) {
                previewTableData(tableName);
            }

        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 5: RELASI FOREIGN KEY (TAMBAH & HAPUS)
    // ------------------------------------------------------------------
    function openAddRelationModal(defaultFromTable, defaultFromCol, defaultToTable, defaultToCol) {
        const tables = schemaData.tables || [];
        if (tables.length < 2) {
            alert('Diperlukan minimal 2 tabel untuk menghubungkan relasi Foreign Key.');
            return;
        }

        const fromTableSel = document.getElementById('dsg-rel-from-table');
        const toTableSel = document.getElementById('dsg-rel-to-table');

        if (!fromTableSel || !toTableSel) return;

        let optionsHtml = '';
        tables.forEach(t => {
            optionsHtml += `<option value="${escapeHtml(t.name)}">${escapeHtml(t.name)}</option>`;
        });

        fromTableSel.innerHTML = optionsHtml;
        toTableSel.innerHTML = optionsHtml;

        const selFrom = defaultFromTable || tables[0].name;
        const selTo = defaultToTable || (tables[1] ? tables[1].name : tables[0].name);

        fromTableSel.value = selFrom;
        toTableSel.value = selTo;

        onRelFromTableChange(selFrom, defaultFromCol);
        onRelToTableChange(selTo, defaultToCol);

        openSubmodal('add-relation');
    }

    function onRelFromTableChange(tableName, defaultCol) {
        const colSel = document.getElementById('dsg-rel-from-col');
        if (!colSel) return;

        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) {
            colSel.innerHTML = '';
            return;
        }

        let html = '';
        tableObj.columns.forEach(c => {
            html += `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)} (${escapeHtml(c.type)})</option>`;
        });
        colSel.innerHTML = html;

        if (defaultCol) colSel.value = defaultCol;
    }

    function onRelToTableChange(tableName, defaultCol) {
        const colSel = document.getElementById('dsg-rel-to-col');
        if (!colSel) return;

        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) {
            colSel.innerHTML = '';
            return;
        }

        // Urutkan Primary Key di urutan paling pertama agar memudahkan siswa
        const colsSorted = [...tableObj.columns].sort((a, b) => (b.is_pk ? 1 : 0) - (a.is_pk ? 1 : 0));

        let html = '';
        colsSorted.forEach(c => {
            const pkLabel = c.is_pk ? ' [Primary Key]' : '';
            html += `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}${pkLabel} (${escapeHtml(c.type)})</option>`;
        });
        colSel.innerHTML = html;

        if (defaultCol) colSel.value = defaultCol;
    }

    async function submitAddRelation() {
        if (!activeDbId) return;

        const fromTable = document.getElementById('dsg-rel-from-table')?.value;
        const fromCol = document.getElementById('dsg-rel-from-col')?.value;
        const toTable = document.getElementById('dsg-rel-to-table')?.value;
        const toCol = document.getElementById('dsg-rel-to-col')?.value;
        const onDelete = document.getElementById('dsg-rel-on-delete')?.value || 'CASCADE';
        const onUpdate = document.getElementById('dsg-rel-on-update')?.value || 'CASCADE';

        if (!fromTable || !fromCol || !toTable || !toCol) {
            alert('Pastikan semua tabel dan kolom relasi telah dipilih.');
            return;
        }

        if (fromTable === toTable && fromCol === toCol) {
            alert('Kolom asal dan tujuan tidak boleh sama persis pada tabel yang sama.');
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'add_foreign_key');
        fd.append('from_table', fromTable);
        fd.append('from_column', fromCol);
        fd.append('to_table', toTable);
        fd.append('to_column', toCol);
        fd.append('on_delete', onDelete);
        fd.append('on_update', onUpdate);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal membuat Foreign Key:\n\n' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            closeSubmodal('add-relation');
            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    async function dropForeignKey(tableName, constraintName) {
        if (!activeDbId || !tableName || !constraintName) return;

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'drop_foreign_key');
        fd.append('table', tableName);
        fd.append('constraint_name', constraintName);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menghapus Foreign Key: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 6: KELOLA DATA (PREVIEW, INSERT ROW, DELETE ROW)
    // ------------------------------------------------------------------
    async function previewTableData(tableName) {
        if (!elPreviewDrawer || !activeDbId) return;

        currentPreviewTable = tableName;

        const titleEl = document.getElementById('designer-preview-title');
        const bodyEl = document.getElementById('designer-preview-body');

        if (titleEl) titleEl.textContent = `Data: ${tableName} (Memuat…)`;
        if (bodyEl) bodyEl.innerHTML = `<div style="color:#94a3b8; padding:16px; text-align:center">Mengambil data dari server…</div>`;

        elPreviewDrawer.classList.add('show');

        try {
            const res = await fetch(`api/db-designer.php?db_id=${activeDbId}&action=preview&table=${encodeURIComponent(tableName)}&limit=50`, {
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                if (bodyEl) bodyEl.innerHTML = `<div style="color:#ef4444; padding:16px">${escapeHtml(data.error || 'Gagal mengambil data.')}</div>`;
                return;
            }

            currentTableMeta = data;

            if (titleEl) {
                titleEl.textContent = `Tabel: ${tableName} (${data.total_rows} total baris, menampilkan maks 50 baris)`;
            }

            // Update badge di kartu tabel jika berubah
            const badgeEl = document.getElementById(`card-badge-${tableName}`);
            if (badgeEl) badgeEl.textContent = `${data.total_rows} baris`;

            if (!data.rows || data.rows.length === 0) {
                if (bodyEl) {
                    bodyEl.innerHTML = `
                        <div style="color:#94a3b8; padding:24px; text-align:center">
                            <div style="font-size:24px; margin-bottom:6px">📭</div>
                            <div>Tabel ini belum memiliki data baris (kosong).</div>
                            <button type="button" class="designer-btn designer-btn-primary" style="margin-top:10px" onclick="window.DB_DESIGNER.openInsertRowModal()">
                                ➕ Tambah Data Pertama
                            </button>
                        </div>
                    `;
                }
                return;
            }

            const pks = data.pks || [];

            let tableHtml = `<table class="designer-preview-table"><thead><tr>`;
            tableHtml += `<th style="width:50px; text-align:center">Aksi</th>`;

            data.columns.forEach(col => {
                const isPk = pks.includes(col);
                tableHtml += `<th>${escapeHtml(col)}${isPk ? ' 🔑' : ''}</th>`;
            });
            tableHtml += `</tr></thead><tbody>`;

            data.rows.forEach(r => {
                // Siapkan kunci identitas baris untuk penghapusan
                const pkObj = {};
                if (pks.length > 0) {
                    pks.forEach(k => pkObj[k] = r[k]);
                } else {
                    // Jika tabel tidak punya PK, gunakan seluruh nilai kolom sebagai filter
                    data.columns.forEach(c => pkObj[c] = r[c]);
                }
                const pkJson = escapeHtml(JSON.stringify(pkObj));

                tableHtml += `<tr>`;
                tableHtml += `
                    <td style="text-align:center">
                        <button type="button" class="designer-row-del-btn" title="Hapus baris ini" onclick="window.DB_DESIGNER.deleteTableRow('${escapeHtml(tableName)}', '${pkJson}')">
                            🗑️
                        </button>
                    </td>
                `;

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
            if (bodyEl) bodyEl.innerHTML = `<div style="color:#ef4444; padding:16px">Kesalahan: ${escapeHtml(err.message)}</div>`;
        }
    }

    function refreshCurrentTableData() {
        if (currentPreviewTable) {
            previewTableData(currentPreviewTable);
        }
    }

    async function deleteTableRow(tableName, pkJsonStr) {
        if (!activeDbId || !tableName || !pkJsonStr) return;

        let pkObj = {};
        try {
            pkObj = JSON.parse(pkJsonStr);
        } catch (e) {
            alert('Data Primary Key tidak valid.');
            return;
        }

        if (!confirm('Apakah Anda yakin ingin menghapus baris data ini?')) {
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'delete_row');
        fd.append('table', tableName);
        fd.append('pk', JSON.stringify(pkObj));

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menghapus baris data: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            refreshCurrentTableData();

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    function openInsertRowModal() {
        if (!currentPreviewTable) {
            alert('Pilih tabel terlebih dahulu dari tombol 👁️ di kartu tabel.');
            return;
        }

        const titleEl = document.getElementById('dsg-insert-title');
        const targetInput = document.getElementById('dsg-insert-table-name');
        const wrapEl = document.getElementById('dsg-insert-fields-wrap');

        if (titleEl) titleEl.textContent = `Tambah Data Baris: ${currentPreviewTable}`;
        if (targetInput) targetInput.value = currentPreviewTable;
        if (!wrapEl) return;

        wrapEl.innerHTML = '';

        const colsMeta = (currentTableMeta && currentTableMeta.columns_meta) ? currentTableMeta.columns_meta : [];

        if (colsMeta.length === 0) {
            wrapEl.innerHTML = '<div style="color:#94a3b8">Memuat struktur kolom...</div>';
            return;
        }

        colsMeta.forEach(col => {
            const isText = (col.type || '').toLowerCase().includes('text');
            const isAi = col.is_ai;
            const placeholder = isAi ? '(Auto Increment - Dibuat Otomatis)' : (col.default !== null ? `Default: ${col.default}` : (col.null ? 'Boleh NULL' : 'Wajib diisi'));

            const group = document.createElement('div');
            group.className = 'designer-form-group';

            let labelHtml = `
                <label class="designer-form-label" style="display:flex; justify-content:space-between; align-items:center">
                    <span>
                        ${escapeHtml(col.name)}
                        ${col.is_pk ? ' <span style="color:#f59e0b" title="Primary Key">🔑</span>' : ''}
                        ${isAi ? ' <span style="color:#10b981; font-size:11px; font-weight:normal">(Auto Increment)</span>' : ''}
                        ${!col.null && !isAi ? ' <span style="color:#ef4444">*</span>' : ''}
                    </span>
                    <span style="font-size:11px; color:#64748b; font-family:monospace">${escapeHtml(col.type)}</span>
                </label>
            `;

            let inputHtml = '';
            if (isText) {
                inputHtml = `<textarea class="designer-input dsg-insert-field" data-col="${escapeHtml(col.name)}" rows="3" placeholder="${escapeHtml(placeholder)}"></textarea>`;
            } else {
                inputHtml = `<input type="text" class="designer-input dsg-insert-field" data-col="${escapeHtml(col.name)}" placeholder="${escapeHtml(placeholder)}" ${isAi ? 'style="opacity:0.75"' : ''}>`;
            }

            group.innerHTML = labelHtml + inputHtml;
            wrapEl.appendChild(group);
        });

        openSubmodal('insert-row');
    }

    async function submitInsertRow() {
        if (!activeDbId || !currentPreviewTable) return;

        const fields = document.querySelectorAll('#dsg-insert-fields-wrap .dsg-insert-field');
        const dataObj = {};

        fields.forEach(f => {
            const colName = f.dataset.col;
            if (colName) {
                dataObj[colName] = f.value;
            }
        });

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'insert_row');
        fd.append('table', currentPreviewTable);
        fd.append('data', JSON.stringify(dataObj));

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal menyimpan baris: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            closeSubmodal('insert-row');
            refreshCurrentTableData();

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
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

    // ------------------------------------------------------------------
    // API PUBLIK WINDOW.DB_DESIGNER
    // ------------------------------------------------------------------
    window.DB_DESIGNER = {
        open: openDatabaseDesigner,
        close: closeDatabaseDesigner,
        zoomIn,
        zoomOut,
        resetView,
        autoArrange,
        filterTables,
        previewTableData,
        refreshCurrentTableData,
        closePreview,
        openCreateTableModal,
        appendCreateTableColumnRow,
        removeCreateColRow,
        submitCreateTable,
        dropTable,
        openAddColumnModal,
        onColTypeChange,
        submitAddColumn,
        dropColumn,
        openAddRelationModal,
        onRelFromTableChange,
        onRelToTableChange,
        submitAddRelation,
        dropForeignKey,
        openInsertRowModal,
        submitInsertRow,
        deleteTableRow,
        closeSubmodal,
        reload: () => reloadSchema(false)
    };

    window.openDatabaseDesigner = openDatabaseDesigner;

    // Inisialisasi event listener setelah DOM siap
    document.addEventListener('DOMContentLoaded', () => {
        initDOMElements();
        initCanvasPanZoom();

        // Tutup submodal jika backdrop-nya diklik
        document.querySelectorAll('.designer-submodal-backdrop').forEach(submodal => {
            submodal.addEventListener('click', (e) => {
                if (e.target === submodal) {
                    submodal.classList.remove('show');
                }
            });
        });

        // Shortcut Esc
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openSub = document.querySelector('.designer-submodal-backdrop.show');
                if (openSub) {
                    openSub.classList.remove('show');
                    return;
                }
                if (elPreviewDrawer && elPreviewDrawer.classList.contains('show')) {
                    closePreview();
                    return;
                }
                if (elModal && elModal.classList.contains('show')) {
                    closeDatabaseDesigner();
                }
            }
        });
    });

})();

/**
 * Sakuci Database Schema Designer ala SQLyog & phpMyAdmin
 * 100% Full Lokal (Tanpa dependensi CDN luar)
 * Fitur:
 * - Draggable Table Cards (Touch & Mouse)
 * - Real-time SVG Curve Relations & Interactive FK Deletion
 * - Touch Hitbox 28px for accurate mobile tapping on relations
 * - Compatibility Checker & Auto-Align Types for Foreign Keys
 * - Dedicated Manage Relations Modal (Daftar & Hapus FK)
 * - Toggle Inferred Relations (On/Off)
 * - Edit Table Structure: Rename Table & Modify Columns
 * - Edit Column Modal (Change column name, type, length, null, default, AI)
 * - Pan & Zoom (Mouse drag, wheel, touch pan & pinch zoom)
 * - Auto-Arrange & Smart Grid
 * - Visual Table Creator (Create Table with columns builder)
 * - Drop Table with safe confirmation
 * - Add Column & Drop Column
 * - Data CRUD Drawer (Preview, Insert New Row, Edit Row, Delete Row)
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
            const explicitCount = (data.relations || []).filter(r => r.type === 'explicit').length;
            const inferredCount = (data.relations || []).filter(r => r.type === 'inferred').length;

            if (metaEl) {
                metaEl.textContent = `${data.tables_count || (data.tables ? data.tables.length : 0)} tabel • ${explicitCount} FK resmi • ${inferredCount} relasi otomatis`;
            }

            if (!preservePositions || Object.keys(cardPositions).length === 0) {
                autoArrangePositions();
            } else {
                const cardWidth = 250;
                const gapX = 70;
                const gapY = 50;
                let nextX = 60;
                let nextY = 60;

                (schemaData.tables || []).forEach(t => {
                    if (!cardPositions[t.name]) {
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

            const manageModal = document.getElementById('designer-modal-manage-relations');
            if (manageModal && manageModal.classList.contains('show')) {
                renderManageRelationsContent();
            }

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

            let cardHtml = `
                <div class="designer-card-header" data-card-header="${escapeHtml(t.name)}">
                    <div class="designer-card-title-wrap">
                        <span class="designer-card-icon">📋</span>
                        <span class="designer-card-name" title="${escapeHtml(t.name)}">${escapeHtml(t.name)}</span>
                    </div>
                    <div class="designer-card-actions">
                        <span class="designer-card-badge" id="card-badge-${escapeHtml(t.name)}">${rowsCountStr}</span>
                        <button type="button" class="designer-card-btn-action" title="Ubah struktur &amp; nama tabel ${escapeHtml(t.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.openEditTableModal('${escapeHtml(t.name)}')">
                            ✏️
                        </button>
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

            t.columns.forEach(col => {
                let keyBadge = '<span class="designer-col-key"></span>';
                const hasFk = isForeignKeyColumn(t.name, col.name);

                if (col.is_pk) {
                    keyBadge = '<span class="designer-col-key" title="Primary Key">🔑</span>';
                } else if (hasFk) {
                    keyBadge = `<span class="designer-col-key" title="Foreign Key (Klik untuk kelola/hapus)" style="cursor:pointer" onclick="event.stopPropagation(); window.DB_DESIGNER.onColFkClick('${escapeHtml(t.name)}', '${escapeHtml(col.name)}')">🔗</span>`;
                } else if (col.is_unique) {
                    keyBadge = '<span class="designer-col-key" title="Unique">✨</span>';
                }

                const pkClass = col.is_pk ? 'is-pk' : (hasFk ? 'is-fk' : '');
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
                            <button type="button" class="designer-col-edit-btn" title="Ubah kolom ${escapeHtml(col.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.openEditColumnModal('${escapeHtml(t.name)}', '${escapeHtml(col.name)}')">✏️</button>
                            ${!col.is_pk ? `<button type="button" class="designer-col-delete-btn" title="Hapus kolom ${escapeHtml(col.name)}" onclick="event.stopPropagation(); window.DB_DESIGNER.dropColumn('${escapeHtml(t.name)}', '${escapeHtml(col.name)}')">✕</button>` : ''}
                        </div>
                    </div>
                `;
            });

            cardHtml += `</div>`;
            cardHtml += `
                <div class="designer-card-footer">
                    <button type="button" class="designer-card-add-col-btn" onclick="window.DB_DESIGNER.openAddColumnModal('${escapeHtml(t.name)}')">
                        + Tambah Kolom
                    </button>
                </div>
            `;

            card.innerHTML = cardHtml;
            elCardsLayer.appendChild(card);

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
     * Aksi klik pada ikon 🔗 di kolom kartu tabel
     */
    function onColFkClick(tableName, colName) {
        const rel = (schemaData.relations || []).find(r => r.from_table === tableName && r.from_column === colName);
        if (rel) {
            onRelationClick(rel);
        } else {
            openAddRelationModal(tableName, colName);
        }
    }

    /**
     * Render SVG Bezier Relation Lines dengan Hitbox 28px untuk sentuhan HP
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

            const hitArea = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            hitArea.id = `rel-hit-${rel.id}`;
            hitArea.classList.add('designer-relation-hitarea');
            hitArea.dataset.relType = rel.type;
            hitArea.setAttribute('stroke', 'transparent');
            hitArea.setAttribute('stroke-width', '28');
            hitArea.setAttribute('fill', 'none');
            hitArea.style.pointerEvents = 'stroke';
            hitArea.style.cursor = 'pointer';

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
            titleEl.textContent = `${rel.from_table}.${rel.from_column} → ${rel.to_table}.${rel.to_column} (${rel.type === 'explicit' ? 'Foreign Key: ' + (rel.constraint_name || rel.label) + ' • Klik untuk hapus' : 'Relasi Konvensi (Otomatis) • Klik untuk buat FK resmi'})`;
            path.appendChild(titleEl);

            const setHighlight = (hover) => highlightRelation(rel, hover);
            hitArea.addEventListener('mouseenter', () => setHighlight(true));
            hitArea.addEventListener('mouseleave', () => setHighlight(false));
            path.addEventListener('mouseenter', () => setHighlight(true));
            path.addEventListener('mouseleave', () => setHighlight(false));

            const handleClick = (e) => {
                e.stopPropagation();
                onRelationClick(rel);
            };
            hitArea.addEventListener('click', handleClick);
            path.addEventListener('click', handleClick);

            elSvgCanvas.appendChild(hitArea);
            elSvgCanvas.appendChild(path);
        });

        updateRelationsPositions();
    }

    /**
     * Aksi klik pada garis relasi
     */
    function onRelationClick(rel) {
        if (rel.type === 'explicit') {
            const cName = rel.constraint_name || rel.label;
            const delRule = rel.on_delete ? ` • ON DELETE ${rel.on_delete}` : '';
            const msg = `🔗 RELASI FOREIGN KEY (InnoDB):\n\n` +
                `Hubungan: ${rel.from_table}.${rel.from_column} ➔ ${rel.to_table}.${rel.to_column}\n` +
                `Constraint: ${cName}${delRule}\n\n` +
                `Apakah Anda ingin MENGHAPUS batasan Foreign Key ini dari database MySQL?`;
            if (confirm(msg)) {
                dropForeignKey(rel.from_table, cName);
            }
        } else {
            const msg = `🔍 RELASI KONVENSI (Deteksi Otomatis):\n\n` +
                `Hubungan: ${rel.from_table}.${rel.from_column} ➔ ${rel.to_table}.${rel.to_column}\n\n` +
                `ℹ️ CATATAN PENTING:\n` +
                `Garis putus-putus oranye ini BUKAN Foreign Key di MySQL, melainkan prediksi sistem karena nama kolom berakhiran '_id'.\n\n` +
                `• Klik OK untuk membuat Foreign Key resmi di MySQL sekarang.\n` +
                `• Klik Batal untuk menutup dialog ini.`;
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
            const hitArea = document.getElementById(`rel-hit-${rel.id}`);
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
            if (hitArea) hitArea.setAttribute('d', d);
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

    /**
     * Toggle sembunyikan/tampilkan garis relasi konvensi (inferred)
     */
    function toggleInferredRelations(show) {
        const viewport = document.getElementById('designer-viewport');
        if (!viewport) return;
        if (show) {
            viewport.classList.remove('hide-inferred');
        } else {
            viewport.classList.add('hide-inferred');
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
    // FITUR 2B: EDIT STRUKTUR TABEL & RENAME TABEL
    // ------------------------------------------------------------------
    function openEditTableModal(tableName) {
        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) return;

        const titleEl = document.getElementById('dsg-edit-tbl-title');
        const oldNameInput = document.getElementById('dsg-edit-tbl-old-name');
        const newNameInput = document.getElementById('dsg-edit-tbl-new-name');
        const colsWrap = document.getElementById('dsg-edit-tbl-cols-wrap');

        if (titleEl) titleEl.textContent = `Edit Struktur Tabel: ${tableName}`;
        if (oldNameInput) oldNameInput.value = tableName;
        if (newNameInput) newNameInput.value = tableName;

        if (colsWrap) {
            let html = `
                <table class="designer-input-table">
                    <thead>
                        <tr>
                            <th style="min-width:140px">Nama Kolom</th>
                            <th style="min-width:120px">Tipe Data</th>
                            <th style="min-width:60px; text-align:center">Null</th>
                            <th style="min-width:110px">Default</th>
                            <th style="min-width:120px; text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            tableObj.columns.forEach(col => {
                const pkLabel = col.is_pk ? ' 🔑 [PK]' : (isForeignKeyColumn(tableName, col.name) ? ' 🔗 [FK]' : '');
                const nullText = col.null ? '<span style="color:#34d399">NULL</span>' : '<span style="color:#64748b">NOT NULL</span>';
                const defText = col.default !== null ? escapeHtml(col.default) : '<span style="color:#64748b; font-style:italic">Tidak ada</span>';

                html += `
                    <tr>
                        <td>
                            <strong style="color:#f8fafc">${escapeHtml(col.name)}</strong>
                            <span style="font-size:11px; color:#f59e0b">${pkLabel}</span>
                        </td>
                        <td>
                            <span style="font-family:monospace; color:#38bdf8">${escapeHtml(col.type)}</span>
                        </td>
                        <td style="text-align:center">${nullText}</td>
                        <td>${defText}</td>
                        <td style="text-align:center">
                            <button type="button" class="designer-row-edit-btn" onclick="window.DB_DESIGNER.openEditColumnModal('${escapeHtml(tableName)}', '${escapeHtml(col.name)}')">
                                ✏️ Ubah
                            </button>
                            ${!col.is_pk ? `<button type="button" class="designer-row-del-btn" onclick="window.DB_DESIGNER.dropColumn('${escapeHtml(tableName)}', '${escapeHtml(col.name)}')">🗑️</button>` : ''}
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            colsWrap.innerHTML = html;
        }

        openSubmodal('edit-table');
    }

    async function submitRenameTable() {
        if (!activeDbId) return;

        const oldName = document.getElementById('dsg-edit-tbl-old-name')?.value || '';
        const newName = (document.getElementById('dsg-edit-tbl-new-name')?.value || '').trim();

        if (!oldName || !newName) {
            alert('Nama tabel baru tidak boleh kosong.');
            return;
        }

        if (oldName === newName) {
            alert('Nama tabel baru sama dengan nama saat ini.');
            return;
        }

        if (!confirm(`Apakah Anda yakin ingin mengubah nama tabel '${oldName}' menjadi '${newName}'?`)) {
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'rename_table');
        fd.append('table', oldName);
        fd.append('new_table_name', newName);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal mengubah nama tabel: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            alert(data.pesan || 'Nama tabel berhasil diperbarui!');
            if (cardPositions[oldName]) {
                cardPositions[newName] = cardPositions[oldName];
                delete cardPositions[oldName];
            }

            closeSubmodal('edit-table');
            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 2C: EDIT / UBAH KOLOM (MODIFY COLUMN)
    // ------------------------------------------------------------------
    function openEditColumnModal(tableName, colName) {
        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) return;

        const colObj = (tableObj.columns || []).find(c => c.name === colName);
        if (!colObj) return;

        const titleEl = document.getElementById('dsg-edit-col-title');
        const targetTable = document.getElementById('dsg-edit-col-target-table');
        const oldName = document.getElementById('dsg-edit-col-old-name');
        const nameInput = document.getElementById('dsg-edit-col-name');
        const typeSelect = document.getElementById('dsg-edit-col-type');
        const lengthInput = document.getElementById('dsg-edit-col-length');
        const nullCheck = document.getElementById('dsg-edit-col-null');
        const defInput = document.getElementById('dsg-edit-col-default');
        const aiCheck = document.getElementById('dsg-edit-col-ai');

        if (titleEl) titleEl.textContent = `Ubah Kolom: ${tableName}.${colName}`;
        if (targetTable) targetTable.value = tableName;
        if (oldName) oldName.value = colName;
        if (nameInput) nameInput.value = colName;

        let baseType = 'VARCHAR';
        let lenVal = '';
        const rawType = (colObj.type || '').toUpperCase();

        const match = rawType.match(/^([A-Z]+)(?:\(([^)]+)\))?/);
        if (match) {
            baseType = match[1];
            lenVal = match[2] || '';
        }

        if (typeSelect) {
            const exists = Array.from(typeSelect.options).some(o => o.value === baseType);
            if (exists) {
                typeSelect.value = baseType;
            } else {
                typeSelect.value = 'VARCHAR';
            }
        }
        if (lengthInput) lengthInput.value = lenVal || (baseType === 'VARCHAR' ? '255' : (baseType === 'INT' ? '11' : ''));
        if (nullCheck) nullCheck.checked = !!colObj.null;
        if (defInput) defInput.value = colObj.default !== null ? colObj.default : '';
        if (aiCheck) aiCheck.checked = !!colObj.is_ai;

        openSubmodal('edit-column');
        if (nameInput) setTimeout(() => nameInput.focus(), 150);
    }

    async function submitEditColumn() {
        if (!activeDbId) return;

        const tableName = document.getElementById('dsg-edit-col-target-table')?.value || '';
        const oldCol = document.getElementById('dsg-edit-col-old-name')?.value || '';
        const newCol = (document.getElementById('dsg-edit-col-name')?.value || '').trim();
        const cType = document.getElementById('dsg-edit-col-type')?.value || 'VARCHAR';
        const cLen = (document.getElementById('dsg-edit-col-length')?.value || '').trim();
        const isNull = document.getElementById('dsg-edit-col-null')?.checked ? '1' : '';
        const defVal = (document.getElementById('dsg-edit-col-default')?.value || '').trim();
        const isAi = document.getElementById('dsg-edit-col-ai')?.checked ? '1' : '';

        if (!tableName || !oldCol || !newCol) {
            alert('Nama kolom wajib diisi.');
            return;
        }

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'modify_column');
        fd.append('table', tableName);
        fd.append('old_column', oldCol);
        fd.append('column_name', newCol);
        fd.append('type', cType);
        fd.append('length', cLen);
        fd.append('is_null', isNull);
        fd.append('default', defVal);
        fd.append('is_ai', isAi);

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal mengubah kolom: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            alert(data.pesan || 'Kolom berhasil diperbarui!');
            closeSubmodal('edit-column');
            await reloadSchema(true);

            if (document.getElementById('designer-modal-edit-table')?.classList.contains('show')) {
                openEditTableModal(tableName);
            }

            if (currentPreviewTable === tableName) {
                previewTableData(tableName);
            }

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
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

            if (document.getElementById('designer-modal-edit-table')?.classList.contains('show')) {
                openEditTableModal(tableName);
            }

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

            if (document.getElementById('designer-modal-edit-table')?.classList.contains('show')) {
                openEditTableModal(tableName);
            }

            if (currentPreviewTable === tableName) {
                previewTableData(tableName);
            }

        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // FITUR 5: RELASI FOREIGN KEY (TAMBAH, VALIDASI TIPE & HAPUS)
    // ------------------------------------------------------------------
    function getColumnMeta(tableName, colName) {
        const t = (schemaData.tables || []).find(tbl => tbl.name === tableName);
        if (!t || !t.columns) return null;
        return t.columns.find(c => c.name === colName) || null;
    }

    function checkRelationCompatibility() {
        const fromTbl = document.getElementById('dsg-rel-from-table')?.value;
        const fromCol = document.getElementById('dsg-rel-from-col')?.value;
        const toTbl   = document.getElementById('dsg-rel-to-table')?.value;
        const toCol   = document.getElementById('dsg-rel-to-col')?.value;
        const compatBox = document.getElementById('dsg-rel-compat-box');
        const autoAlignWrap = document.getElementById('dsg-rel-autoalign-wrap');
        const autoAlignCheck = document.getElementById('dsg-rel-auto-align');

        if (!compatBox || !fromTbl || !fromCol || !toTbl || !toCol) {
            if (compatBox) compatBox.style.display = 'none';
            if (autoAlignWrap) autoAlignWrap.style.display = 'none';
            return;
        }

        const fromMeta = getColumnMeta(fromTbl, fromCol);
        const toMeta   = getColumnMeta(toTbl, toCol);

        if (!fromMeta || !toMeta) {
            compatBox.style.display = 'none';
            if (autoAlignWrap) autoAlignWrap.style.display = 'none';
            return;
        }

        const fromType = (fromMeta.type || '').toLowerCase();
        const toType   = (toMeta.type || '').toLowerCase();
        const isExactMatch = (fromType === toType);

        compatBox.style.display = 'block';

        if (isExactMatch) {
            compatBox.className = 'designer-rel-compat-box compatible';
            compatBox.innerHTML = `
                <div style="font-weight:600; margin-bottom:2px">✓ Tipe Data Kompatibel</div>
                <div>Kedua kolom bertipe <strong>${escapeHtml(toType)}</strong>. Siap dihubungkan sebagai Foreign Key!</div>
            `;
            if (autoAlignWrap) autoAlignWrap.style.display = 'none';
        } else {
            compatBox.className = 'designer-rel-compat-box incompatible';
            compatBox.innerHTML = `
                <div style="font-weight:600; margin-bottom:2px">⚠️ Peringatan: Tipe Data Tidak Sama!</div>
                <div>Kolom asal bertipe <strong>${escapeHtml(fromType)}</strong> sedangkan kolom referensi bertipe <strong>${escapeHtml(toType)}</strong>.</div>
                <div style="font-size:11.5px; margin-top:4px; opacity:0.9">MySQL mewajibkan tipe data dan unsigned sama persis agar tidak muncul error <em>"are incompatible"</em>.</div>
            `;
            if (autoAlignWrap) {
                autoAlignWrap.style.display = 'block';
                if (autoAlignCheck) autoAlignCheck.checked = true;
            }
        }
    }

    function openAddRelationModal(defaultFromTable, defaultFromCol, defaultToTable, defaultToCol) {
        const tables = schemaData.tables || [];
        if (tables.length < 2) {
            alert('Diperlukan minimal 2 tabel untuk menghubungkan relasi Foreign Key.');
            return;
        }

        const fromTableSel = document.getElementById('dsg-rel-from-table');
        const toTableSel = document.getElementById('dsg-rel-to-table');
        const fromColSel = document.getElementById('dsg-rel-from-col');
        const toColSel = document.getElementById('dsg-rel-to-col');

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

        if (fromColSel) fromColSel.onchange = checkRelationCompatibility;
        if (toColSel) toColSel.onchange = checkRelationCompatibility;

        checkRelationCompatibility();
        openSubmodal('add-relation');
    }

    function onRelFromTableChange(tableName, defaultCol) {
        const colSel = document.getElementById('dsg-rel-from-col');
        if (!colSel) return;

        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) {
            colSel.innerHTML = '';
            checkRelationCompatibility();
            return;
        }

        let html = '';
        tableObj.columns.forEach(c => {
            html += `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)} (${escapeHtml(c.type)})</option>`;
        });
        colSel.innerHTML = html;

        if (defaultCol) colSel.value = defaultCol;
        checkRelationCompatibility();
    }

    function onRelToTableChange(tableName, defaultCol) {
        const colSel = document.getElementById('dsg-rel-to-col');
        if (!colSel) return;

        const tableObj = (schemaData.tables || []).find(t => t.name === tableName);
        if (!tableObj) {
            colSel.innerHTML = '';
            checkRelationCompatibility();
            return;
        }

        const colsSorted = [...tableObj.columns].sort((a, b) => (b.is_pk ? 1 : 0) - (a.is_pk ? 1 : 0));

        let html = '';
        colsSorted.forEach(c => {
            const pkLabel = c.is_pk ? ' [Primary Key]' : '';
            html += `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}${pkLabel} (${escapeHtml(c.type)})</option>`;
        });
        colSel.innerHTML = html;

        if (defaultCol) colSel.value = defaultCol;
        checkRelationCompatibility();
    }

    async function submitAddRelation() {
        if (!activeDbId) return;

        const fromTable = document.getElementById('dsg-rel-from-table')?.value;
        const fromCol = document.getElementById('dsg-rel-from-col')?.value;
        const toTable = document.getElementById('dsg-rel-to-table')?.value;
        const toCol = document.getElementById('dsg-rel-to-col')?.value;
        const onDelete = document.getElementById('dsg-rel-on-delete')?.value || 'CASCADE';
        const onUpdate = document.getElementById('dsg-rel-on-update')?.value || 'CASCADE';
        const autoAlignCheck = document.getElementById('dsg-rel-auto-align');
        const autoAlignVal = autoAlignCheck ? (autoAlignCheck.checked ? '1' : '0') : '1';

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
        fd.append('auto_align', autoAlignVal);

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

            if (data.pesan) {
                alert(data.pesan);
            }

            closeSubmodal('add-relation');
            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    }

    async function dropForeignKey(tableName, constraintName) {
        if (!activeDbId || !tableName || !constraintName) return;

        if (!confirm(`Apakah Anda yakin ingin MENGHAPUS relasi Foreign Key '${constraintName}' dari tabel '${tableName}'?`)) {
            return;
        }

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

            alert(data.pesan || 'Relasi Foreign Key berhasil dihapus!');
            await reloadSchema(true);

        } catch (err) {
            alert('Kesalahan: ' + err.message);
        }
    }

    // ------------------------------------------------------------------
    // MODAL DAFTAR & KELOLA SELURUH RELASI (MANAGE RELATIONS)
    // ------------------------------------------------------------------
    function openManageRelationsModal() {
        renderManageRelationsContent();
        openSubmodal('manage-relations');
    }

    function renderManageRelationsContent() {
        const wrap = document.getElementById('dsg-manage-relations-wrap');
        if (!wrap) return;

        const relations = schemaData.relations || [];

        if (relations.length === 0) {
            wrap.innerHTML = `
                <div style="padding:32px; text-align:center; color:#94a3b8">
                    <div style="font-size:28px; margin-bottom:8px">📭</div>
                    <div style="font-size:13.5px; font-weight:600; color:#f8fafc">Belum Ada Relasi Terdeteksi</div>
                    <div style="font-size:12px; margin-top:4px">Klik tombol "➕ Tambah Relasi Baru" di atas untuk menghubungkan tabel.</div>
                </div>
            `;
            return;
        }

        let html = `
            <table class="designer-input-table">
                <thead>
                    <tr>
                        <th style="min-width:140px">Tabel &amp; Kolom Asal (FK)</th>
                        <th style="min-width:30px; text-align:center">➔</th>
                        <th style="min-width:140px">Tabel &amp; Kolom Induk (PK)</th>
                        <th style="min-width:140px">Jenis Relasi</th>
                        <th style="min-width:110px; text-align:center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
        `;

        relations.forEach(rel => {
            const isExplicit = (rel.type === 'explicit');
            const badgeClass = isExplicit ? 'dsg-badge-fk-explicit' : 'dsg-badge-fk-inferred';
            const badgeText = isExplicit ? `🟢 InnoDB FK (${escapeHtml(rel.constraint_name || rel.label)})` : `🟠 Konvensi Otomatis`;
            const rules = isExplicit 
                ? `<div style="font-size:10.5px; color:#94a3b8; margin-top:3px">ON DELETE ${escapeHtml(rel.on_delete || 'CASCADE')} &bull; ON UPDATE ${escapeHtml(rel.on_update || 'CASCADE')}</div>`
                : `<div style="font-size:10.5px; color:#f97316; margin-top:3px">Prediksi nama kolom, belum ada batasan MySQL</div>`;

            html += `
                <tr>
                    <td>
                        <span style="font-weight:600; color:#38bdf8">${escapeHtml(rel.from_table)}</span>.<span style="color:#f8fafc">${escapeHtml(rel.from_column)}</span>
                    </td>
                    <td style="text-align:center; color:#64748b">➔</td>
                    <td>
                        <span style="font-weight:600; color:#10b981">${escapeHtml(rel.to_table)}</span>.<span style="color:#f8fafc">${escapeHtml(rel.to_column)}</span>
                    </td>
                    <td>
                        <span class="${badgeClass}">${badgeText}</span>
                        ${rules}
                    </td>
                    <td style="text-align:center">
            `;

            if (isExplicit) {
                html += `
                    <button type="button" class="designer-card-btn-danger" style="padding:4px 9px; font-size:11.5px" onclick="window.DB_DESIGNER.dropForeignKey('${escapeHtml(rel.from_table)}', '${escapeHtml(rel.constraint_name || rel.label)}')">
                        🗑️ Hapus FK
                    </button>
                `;
            } else {
                html += `
                    <button type="button" class="designer-btn designer-btn-primary designer-btn-sm" style="padding:3px 8px; font-size:11px" onclick="window.DB_DESIGNER.closeSubmodal('manage-relations'); window.DB_DESIGNER.openAddRelationModal('${escapeHtml(rel.from_table)}', '${escapeHtml(rel.from_column)}', '${escapeHtml(rel.to_table)}', '${escapeHtml(rel.to_column)}')">
                        ➕ Buat FK
                    </button>
                `;
            }

            html += `</td></tr>`;
        });

        html += `</tbody></table>`;
        wrap.innerHTML = html;
    }

    // ------------------------------------------------------------------
    // FITUR 6: KELOLA DATA (PREVIEW, INSERT ROW, EDIT ROW, DELETE ROW)
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
            tableHtml += `<th style="width:90px; text-align:center">Aksi</th>`;

            data.columns.forEach(col => {
                const isPk = pks.includes(col);
                tableHtml += `<th>${escapeHtml(col)}${isPk ? ' 🔑' : ''}</th>`;
            });
            tableHtml += `</tr></thead><tbody>`;

            data.rows.forEach(r => {
                const pkObj = {};
                if (pks.length > 0) {
                    pks.forEach(k => pkObj[k] = r[k]);
                } else {
                    data.columns.forEach(c => pkObj[c] = r[c]);
                }
                const pkJson = escapeHtml(JSON.stringify(pkObj));
                const rowJson = escapeHtml(JSON.stringify(r));

                tableHtml += `<tr>`;
                tableHtml += `
                    <td style="text-align:center; white-space:nowrap">
                        <button type="button" class="designer-row-edit-btn" title="Edit baris data ini" onclick="window.DB_DESIGNER.openEditRowModal('${escapeHtml(tableName)}', '${pkJson}', '${rowJson}')">
                            ✏️ Edit
                        </button>
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

    // ------------------------------------------------------------------
    // FITUR 6B: EDIT DATA BARIS (UPDATE ROW)
    // ------------------------------------------------------------------
    function openEditRowModal(tableName, pkJsonStr, rowJsonStr) {
        let pkObj = {};
        let rowObj = {};
        try {
            pkObj = JSON.parse(pkJsonStr);
            rowObj = JSON.parse(rowJsonStr);
        } catch (e) {
            alert('Data baris tidak valid.');
            return;
        }

        const titleEl = document.getElementById('dsg-edit-row-title');
        const targetTable = document.getElementById('dsg-edit-row-table-name');
        const hiddenPk = document.getElementById('dsg-edit-row-pk');
        const fieldsWrap = document.getElementById('dsg-edit-row-fields-wrap');

        if (titleEl) titleEl.textContent = `Edit Data Baris: ${tableName}`;
        if (targetTable) targetTable.value = tableName;
        if (hiddenPk) hiddenPk.value = JSON.stringify(pkObj);
        if (!fieldsWrap) return;

        fieldsWrap.innerHTML = '';

        const colsMeta = (currentTableMeta && currentTableMeta.columns_meta) ? currentTableMeta.columns_meta : [];

        colsMeta.forEach(col => {
            const isText = (col.type || '').toLowerCase().includes('text');
            const isAi = col.is_ai;
            const currentVal = rowObj[col.name] !== undefined && rowObj[col.name] !== null ? rowObj[col.name] : '';

            const group = document.createElement('div');
            group.className = 'designer-form-group';

            let labelHtml = `
                <label class="designer-form-label" style="display:flex; justify-content:space-between; align-items:center">
                    <span>
                        ${escapeHtml(col.name)}
                        ${col.is_pk ? ' <span style="color:#f59e0b" title="Primary Key">🔑</span>' : ''}
                        ${isAi ? ' <span style="color:#10b981; font-size:11px; font-weight:normal">(Auto Increment)</span>' : ''}
                    </span>
                    <span style="font-size:11px; color:#64748b; font-family:monospace">${escapeHtml(col.type)}</span>
                </label>
            `;

            let inputHtml = '';
            if (isText) {
                inputHtml = `<textarea class="designer-input dsg-edit-row-field" data-col="${escapeHtml(col.name)}" rows="3">${escapeHtml(currentVal)}</textarea>`;
            } else {
                inputHtml = `<input type="text" class="designer-input dsg-edit-row-field" data-col="${escapeHtml(col.name)}" value="${escapeHtml(currentVal)}" ${isAi ? 'style="opacity:0.75" title="Nilai Auto Increment"' : ''}>`;
            }

            group.innerHTML = labelHtml + inputHtml;
            fieldsWrap.appendChild(group);
        });

        openSubmodal('edit-row');
    }

    async function submitEditRow() {
        if (!activeDbId) return;

        const tableName = document.getElementById('dsg-edit-row-table-name')?.value;
        const pkStr = document.getElementById('dsg-edit-row-pk')?.value;
        if (!tableName || !pkStr) return;

        const fields = document.querySelectorAll('#dsg-edit-row-fields-wrap .dsg-edit-row-field');
        const dataObj = {};

        fields.forEach(f => {
            const colName = f.dataset.col;
            if (colName) {
                dataObj[colName] = f.value;
            }
        });

        const fd = new FormData();
        fd.append('db_id', activeDbId);
        fd.append('action', 'update_row');
        fd.append('table', tableName);
        fd.append('pk', pkStr);
        fd.append('data', JSON.stringify(dataObj));

        try {
            const res = await fetch('api/db-designer.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Gagal memperbarui data: ' + (data.error || 'Terjadi kesalahan.'));
                return;
            }

            closeSubmodal('edit-row');
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
        openEditTableModal,
        submitRenameTable,
        openEditColumnModal,
        submitEditColumn,
        openAddColumnModal,
        onColTypeChange,
        submitAddColumn,
        dropColumn,
        openAddRelationModal,
        onRelFromTableChange,
        onRelToTableChange,
        checkRelationCompatibility,
        submitAddRelation,
        dropForeignKey,
        openManageRelationsModal,
        onColFkClick,
        toggleInferredRelations,
        openInsertRowModal,
        submitInsertRow,
        openEditRowModal,
        submitEditRow,
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

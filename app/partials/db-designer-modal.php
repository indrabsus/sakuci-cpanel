<!-- Modal Desainer Skema Database ala SQLyog -->
<div id="designer-modal" class="designer-modal-backdrop">
    <div class="designer-container">
        <!-- Top Toolbar -->
        <div class="designer-toolbar">
            <div class="designer-title-group">
                <span class="designer-app-icon">📐</span>
                <div>
                    <div class="designer-db-title" id="designer-db-name">Database Designer</div>
                    <div class="designer-db-meta" id="designer-db-meta">Memuat skema…</div>
                </div>
            </div>

            <div class="designer-controls">
                <input type="text" class="designer-search-input" placeholder="🔍 Cari tabel…" oninput="window.DB_DESIGNER && window.DB_DESIGNER.filterTables(this.value)">
                
                <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER && window.DB_DESIGNER.openCreateTableModal()" title="Buat Tabel Baru">
                    ➕ <span class="btn-label">Tabel Baru</span>
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.openAddRelationModal()" title="Hubungkan Relasi Foreign Key">
                    🔗 <span class="btn-label">Tambah Relasi</span>
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.openManageRelationsModal()" title="Lihat dan kelola seluruh relasi tabel">
                    📋 <span class="btn-label">Kelola Relasi</span>
                </button>
                <button type="button" class="designer-btn designer-btn-sync" onclick="window.DB_DESIGNER && window.DB_DESIGNER.openSyncCodeModal()" title="Sinkronkan struktur tabel ke Model & Berkas Migrasi kodingan projek">
                    ⚡ <span class="btn-label">Sinkron ke Kodingan</span>
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.autoArrange()" title="Tata rapi tabel secara otomatis">
                    🪄 <span class="btn-label">Tata Otomatis</span>
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.zoomIn()" title="Perbesar (Zoom In)">
                    ➕
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.zoomOut()" title="Perkecil (Zoom Out)">
                    ➖
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.resetView()" title="Reset Tampilan (100%)">
                    <span id="designer-zoom-badge">100%</span>
                </button>
                <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.reload()" title="Muat ulang skema">
                    🔄
                </button>
                <button type="button" class="designer-btn designer-btn-close" onclick="window.DB_DESIGNER && window.DB_DESIGNER.close()" title="Tutup Desainer (Esc)">
                    ✕ <span class="btn-label">Tutup</span>
                </button>
            </div>
        </div>

        <!-- Legend Strip -->
        <div class="designer-legend">
            <span class="designer-legend-item"><strong>Keterangan:</strong></span>
            <span class="designer-legend-item">🔑 Primary Key</span>
            <span class="designer-legend-item">✨ Unique</span>
            <span class="designer-legend-item">🔗 Foreign Key</span>
            <span class="designer-legend-item"><span class="legend-line-solid"></span> Relasi Eksplisit (InnoDB FK)</span>
            <span class="designer-legend-item"><span class="legend-line-dashed"></span> Relasi Konvensi (Inferred)</span>
            <label class="designer-legend-toggle" title="Tampilkan atau sembunyikan garis relasi otomatis (konvensi)" style="margin-left:auto; display:flex; align-items:center; gap:6px; font-size:11.5px; color:#cbd5e1; cursor:pointer; user-select:none">
                <input type="checkbox" id="dsg-toggle-inferred" checked onchange="window.DB_DESIGNER && window.DB_DESIGNER.toggleInferredRelations(this.checked)">
                <span>Tampilkan Relasi Konvensi</span>
            </label>
        </div>

        <!-- Canvas Viewport -->
        <div id="designer-viewport" class="designer-viewport">
            <div id="designer-world" class="designer-world">
                <!-- SVG Canvas for Bezier Relation Lines -->
                <svg id="designer-svg-canvas" class="designer-svg-canvas"></svg>

                <!-- Draggable Table Cards Layer -->
                <div id="designer-cards-layer"></div>
            </div>
        </div>

        <!-- Data Preview & Management Drawer -->
        <div id="designer-preview-drawer" class="designer-preview-drawer">
            <div class="designer-preview-header">
                <div style="display:flex; align-items:center; gap:8px">
                    <span style="font-size:16px">📊</span>
                    <div class="designer-preview-title" id="designer-preview-title">Kelola Data Tabel</div>
                </div>
                <div style="display:flex; gap:6px">
                    <button type="button" class="designer-btn designer-btn-primary" id="btn-drawer-add-row" onclick="window.DB_DESIGNER && window.DB_DESIGNER.openInsertRowModal()">
                        ➕ Tambah Data
                    </button>
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER && window.DB_DESIGNER.refreshCurrentTableData()" title="Muat Ulang Data">
                        🔄
                    </button>
                    <button type="button" class="designer-btn" style="padding:2px 8px" onclick="window.DB_DESIGNER && window.DB_DESIGNER.closePreview()">✕ Tutup</button>
                </div>
            </div>
            <div class="designer-preview-body" id="designer-preview-body"></div>
        </div>

        <!-- Submodal 1: Buat Tabel Baru ala phpMyAdmin -->
        <div id="designer-modal-create-table" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:820px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>➕</span>
                        <span>Buat Tabel Baru</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('create-table')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <div class="designer-form-group" style="margin-bottom:14px">
                        <label class="designer-form-label">Nama Tabel: <span style="color:#ef4444">*</span></label>
                        <input type="text" id="dsg-create-tbl-name" class="designer-input" placeholder="contoh: produk, transaksi, kategori" style="max-width:320px">
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px">
                        <div style="font-size:12.5px; font-weight:600; color:#38bdf8">Struktur Kolom Tabel:</div>
                        <div style="display:flex; gap:6px">
                            <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.addTimestampsRow()" title="Tambahkan created_at & updated_at jika belum ada">
                                + Timestamps
                            </button>
                            <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.appendCreateTableColumnRow()">
                                + Tambah Baris Kolom
                            </button>
                        </div>
                    </div>
                    <div class="designer-table-scroll">
                        <table class="designer-input-table">
                            <thead>
                                <tr>
                                    <th style="min-width:140px">Nama Kolom</th>
                                    <th style="min-width:110px">Tipe Data</th>
                                    <th style="min-width:70px">Panjang</th>
                                    <th style="min-width:40px; text-align:center" title="Primary Key">PK</th>
                                    <th style="min-width:40px; text-align:center" title="Auto Increment">AI</th>
                                    <th style="min-width:45px; text-align:center" title="Boleh NULL">Null</th>
                                    <th style="min-width:110px">Default</th>
                                    <th style="min-width:40px; text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="dsg-create-cols-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('create-table')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitCreateTable()">💾 Simpan Tabel Baru</button>
                </div>
            </div>
        </div>

        <!-- Submodal 2: Tambah Kolom ke Tabel yang Sudah Ada -->
        <div id="designer-modal-add-column" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:480px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>➕</span>
                        <span id="dsg-add-col-title">Tambah Kolom</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('add-column')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <input type="hidden" id="dsg-add-col-target-table" value="">
                    <div class="designer-form-group">
                        <label class="designer-form-label">Nama Kolom: <span style="color:#ef4444">*</span></label>
                        <input type="text" id="dsg-add-col-name" class="designer-input" placeholder="contoh: harga, stok, deskripsi">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Tipe Data:</label>
                            <select id="dsg-add-col-type" class="designer-select" onchange="window.DB_DESIGNER.onColTypeChange(this.value, 'dsg-add-col-length')">
                                <option value="VARCHAR">VARCHAR</option>
                                <option value="INT">INT</option>
                                <option value="BIGINT">BIGINT</option>
                                <option value="TEXT">TEXT</option>
                                <option value="DECIMAL">DECIMAL</option>
                                <option value="DATE">DATE</option>
                                <option value="DATETIME">DATETIME</option>
                                <option value="TIMESTAMP">TIMESTAMP</option>
                                <option value="BOOLEAN">BOOLEAN</option>
                                <option value="JSON">JSON</option>
                            </select>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Panjang / Nilai:</label>
                            <input type="text" id="dsg-add-col-length" class="designer-input" value="255">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Boleh NULL:</label>
                            <label style="display:flex; align-items:center; gap:6px; font-size:12px; margin-top:6px; color:#e2e8f0; cursor:pointer">
                                <input type="checkbox" id="dsg-add-col-null" checked> Ya, boleh NULL
                            </label>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Nilai Default:</label>
                            <input type="text" id="dsg-add-col-default" class="designer-input" placeholder="NULL, 0, dll">
                        </div>
                    </div>
                    <div class="designer-form-group">
                        <label class="designer-form-label">Posisi Kolom:</label>
                        <select id="dsg-add-col-position" class="designer-select">
                            <option value="AFTER_LAST">Di Akhir Tabel (Bawaan)</option>
                            <option value="FIRST">Di Awal Tabel (Paling Pertama)</option>
                        </select>
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('add-column')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitAddColumn()">Simpan Kolom</button>
                </div>
            </div>
        </div>

        <!-- Submodal 3: Tambah Relasi Foreign Key -->
        <div id="designer-modal-add-relation" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:520px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>🔗</span>
                        <span>Hubungkan Relasi Foreign Key</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('add-relation')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <div style="background:rgba(2,132,199,0.1); border:1px solid rgba(2,132,199,0.3); border-radius:6px; padding:10px; font-size:11.5px; color:#38bdf8; margin-bottom:14px; line-height:1.5">
                        ℹ️ Hubungkan kolom kunci tamu (Foreign Key) pada tabel anak ke kolom kunci utama (Primary Key) pada tabel induk.
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Tabel Asal (Anak / FK):</label>
                            <select id="dsg-rel-from-table" class="designer-select" onchange="window.DB_DESIGNER.onRelFromTableChange(this.value)"></select>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Kolom Asal (FK):</label>
                            <select id="dsg-rel-from-col" class="designer-select"></select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Tabel Referensi (Induk / PK):</label>
                            <select id="dsg-rel-to-table" class="designer-select" onchange="window.DB_DESIGNER.onRelToTableChange(this.value)"></select>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Kolom Referensi (PK):</label>
                            <select id="dsg-rel-to-col" class="designer-select"></select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">ON DELETE:</label>
                            <select id="dsg-rel-on-delete" class="designer-select">
                                <option value="CASCADE">CASCADE (Hapus anak jika induk dihapus)</option>
                                <option value="RESTRICT">RESTRICT (Tolak hapus jika masih ada anak)</option>
                                <option value="SET NULL">SET NULL (Ubah FK jadi NULL)</option>
                                <option value="NO ACTION">NO ACTION</option>
                            </select>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">ON UPDATE:</label>
                            <select id="dsg-rel-on-update" class="designer-select">
                                <option value="CASCADE">CASCADE (Ikut update anak)</option>
                                <option value="RESTRICT">RESTRICT</option>
                                <option value="SET NULL">SET NULL</option>
                                <option value="NO ACTION">NO ACTION</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kotak Status Kompatibilitas Tipe Data -->
                    <div id="dsg-rel-compat-box" style="margin-top:14px; padding:10px 12px; border-radius:6px; font-size:12px; display:none;"></div>
                    <div id="dsg-rel-autoalign-wrap" style="margin-top:8px; display:none;">
                        <label style="display:flex; align-items:center; gap:8px; font-size:12px; color:#e2e8f0; cursor:pointer">
                            <input type="checkbox" id="dsg-rel-auto-align" checked>
                            <span>Otomatis samakan tipe data kolom asal agar cocok (Cegah error incompatible)</span>
                        </label>
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('add-relation')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitAddRelation()">🔗 Hubungkan Relasi</button>
                </div>
            </div>
        </div>

        <!-- Submodal 4: Input Baris Data Baru (Insert Row) -->
        <div id="designer-modal-insert-row" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:560px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>➕</span>
                        <span id="dsg-insert-title">Tambah Data Baris</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('insert-row')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <input type="hidden" id="dsg-insert-table-name" value="">
                    <div id="dsg-insert-fields-wrap" style="display:flex; flex-direction:column; gap:10px"></div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('insert-row')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitInsertRow()">💾 Simpan Data</button>
                </div>
            </div>
        </div>
        <!-- Submodal 5: Kelola Seluruh Relasi Database -->
        <div id="designer-modal-manage-relations" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:760px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>🔗</span>
                        <span>Daftar &amp; Kelola Relasi Database</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('manage-relations')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px">
                        <div style="font-size:12px; color:#94a3b8">
                            Daftar relasi Foreign Key (InnoDB) dan relasi konvensi otomatis dalam database ini.
                        </div>
                        <button type="button" class="designer-btn designer-btn-primary designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('manage-relations'); window.DB_DESIGNER.openAddRelationModal()">
                            ➕ Tambah Relasi Baru
                        </button>
                    </div>
                    <div id="dsg-manage-relations-wrap" class="designer-table-scroll" style="max-height:360px">
                        <!-- Diisi dinamis oleh DB_DESIGNER.renderManageRelationsList() -->
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('manage-relations')">Tutup</button>
                </div>
            </div>
        </div>

        <!-- Submodal 6: Edit Struktur & Nama Tabel -->
        <div id="designer-modal-edit-table" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:760px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>✏️</span>
                        <span id="dsg-edit-tbl-title">Edit Struktur Tabel</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('edit-table')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <div style="background:rgba(255,255,255,0.03); border:1px solid #334155; border-radius:8px; padding:12px; margin-bottom:16px">
                        <label class="designer-form-label" style="margin-bottom:6px; display:block">Ubah Nama Tabel:</label>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
                            <input type="hidden" id="dsg-edit-tbl-old-name" value="">
                            <input type="text" id="dsg-edit-tbl-new-name" class="designer-input" style="max-width:320px" placeholder="Nama tabel baru">
                            <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitRenameTable()">
                                💾 Ganti Nama Tabel
                            </button>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px">
                        <div style="font-size:12.5px; font-weight:600; color:#38bdf8">Daftar Kolom &amp; Tipe Data:</div>
                        <button type="button" class="designer-btn designer-btn-primary designer-btn-sm" onclick="window.DB_DESIGNER.openAddColumnModal(document.getElementById('dsg-edit-tbl-old-name').value)">
                            ➕ Tambah Kolom Baru
                        </button>
                    </div>
                    <div id="dsg-edit-tbl-cols-wrap" class="designer-table-scroll" style="max-height:300px">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('edit-table')">Tutup</button>
                </div>
            </div>
        </div>

        <!-- Submodal 7: Ubah Kolom (Modify Column) -->
        <div id="designer-modal-edit-column" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:480px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>✏️</span>
                        <span id="dsg-edit-col-title">Ubah Kolom</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('edit-column')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <input type="hidden" id="dsg-edit-col-target-table" value="">
                    <input type="hidden" id="dsg-edit-col-old-name" value="">
                    <div class="designer-form-group">
                        <label class="designer-form-label">Nama Kolom: <span style="color:#ef4444">*</span></label>
                        <input type="text" id="dsg-edit-col-name" class="designer-input" placeholder="contoh: harga, deskripsi">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Tipe Data:</label>
                            <select id="dsg-edit-col-type" class="designer-select" onchange="window.DB_DESIGNER.onColTypeChange(this.value, 'dsg-edit-col-length')">
                                <option value="VARCHAR">VARCHAR</option>
                                <option value="INT">INT</option>
                                <option value="BIGINT">BIGINT</option>
                                <option value="TEXT">TEXT</option>
                                <option value="DECIMAL">DECIMAL</option>
                                <option value="DATE">DATE</option>
                                <option value="DATETIME">DATETIME</option>
                                <option value="TIMESTAMP">TIMESTAMP</option>
                                <option value="BOOLEAN">BOOLEAN</option>
                                <option value="JSON">JSON</option>
                            </select>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Panjang / Nilai:</label>
                            <input type="text" id="dsg-edit-col-length" class="designer-input" value="255">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                        <div class="designer-form-group">
                            <label class="designer-form-label">Boleh NULL:</label>
                            <label style="display:flex; align-items:center; gap:6px; font-size:12px; margin-top:6px; color:#e2e8f0; cursor:pointer">
                                <input type="checkbox" id="dsg-edit-col-null"> Ya, boleh NULL
                            </label>
                        </div>
                        <div class="designer-form-group">
                            <label class="designer-form-label">Nilai Default:</label>
                            <input type="text" id="dsg-edit-col-default" class="designer-input" placeholder="NULL, 0, dll">
                        </div>
                    </div>
                    <div class="designer-form-group">
                        <label style="display:flex; align-items:center; gap:6px; font-size:12px; margin-top:4px; color:#e2e8f0; cursor:pointer">
                            <input type="checkbox" id="dsg-edit-col-ai"> Auto Increment (Hanya untuk Primary Key INT)
                        </label>
                    </div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('edit-column')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitEditColumn()">💾 Simpan Perubahan Kolom</button>
                </div>
            </div>
        </div>

        <!-- Submodal 8: Edit Baris Data (Update Row) -->
        <div id="designer-modal-edit-row" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:560px">
                <div class="designer-submodal-header">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span>✏️</span>
                        <span id="dsg-edit-row-title">Edit Data Baris</span>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('edit-row')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <input type="hidden" id="dsg-edit-row-table-name" value="">
                    <input type="hidden" id="dsg-edit-row-pk" value="">
                    <div id="dsg-edit-row-fields-wrap" style="display:flex; flex-direction:column; gap:10px"></div>
                </div>
                <div class="designer-submodal-footer">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('edit-row')">Batal</button>
                    <button type="button" class="designer-btn designer-btn-primary" onclick="window.DB_DESIGNER.submitEditRow()">💾 Simpan Perubahan Data</button>
                </div>
            </div>
        </div>
        <!-- Submodal 9: Sinkronisasi Database ke Kodingan Projek (Model & Migration) -->
        <div id="designer-modal-sync-code" class="designer-submodal-backdrop">
            <div class="designer-submodal-card" style="max-width:820px">
                <div class="designer-submodal-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(6,182,212,0.15)); border-bottom: 1px solid rgba(16,185,129,0.3)">
                    <div style="display:flex; align-items:center; gap:8px">
                        <span style="font-size:18px">⚡</span>
                        <div>
                            <span style="font-weight:700; color:#10b981">Sinkronisasi Database ke Kodingan Projek</span>
                            <div id="dsg-sync-project-info" style="font-size:11.5px; color:#94a3b8; font-weight:normal; margin-top:2px">
                                Memeriksa kodingan projek...
                            </div>
                        </div>
                    </div>
                    <button type="button" class="designer-btn designer-btn-sm" onclick="window.DB_DESIGNER.closeSubmodal('sync-code')">✕</button>
                </div>
                <div class="designer-submodal-body">
                    <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:8px; padding:12px; margin-bottom:14px; font-size:12.5px; color:#cbd5e1; line-height:1.5">
                        <div style="font-weight:600; color:#34d399; margin-bottom:4px; display:flex; align-items:center; gap:6px">
                            <span>💡</span> <span>Otomatisasi Model &amp; Migrasi</span>
                        </div>
                        Fitur ini membaca tabel di database dan secara otomatis membuat/memperbarui <b>Model PHP</b> di folder <code>app/Models/</code> serta berkas <b>Migrasi SQL</b> di folder <code>database/migrations/</code> projek kodingan Anda. Riwayat migrasi juga didaftarkan agar perintah <code>php sakuci migrate</code> tetap konsisten dan tidak error.
                    </div>

                    <div id="dsg-sync-loading" style="text-align:center; padding:30px 10px; color:#94a3b8">
                        <div class="spinner-border spinner-border-sm text-success" role="status" style="margin-bottom:8px"></div>
                        <div>Menganalisis perbedaan tabel database dengan Model &amp; Berkas Migrasi...</div>
                    </div>

                    <div id="dsg-sync-error" style="display:none; padding:14px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:8px; color:#fca5a5; font-size:13px; margin-bottom:12px"></div>

                    <div id="dsg-sync-content" style="display:none">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px">
                            <div style="font-size:12px; color:#94a3b8">
                                Pilih tabel dan item yang ingin disinkronkan ke kodingan:
                            </div>
                            <div style="display:flex; gap:12px; font-size:12px">
                                <label style="display:flex; align-items:center; gap:4px; cursor:pointer; color:#38bdf8">
                                    <input type="checkbox" id="dsg-sync-select-all" onchange="window.DB_DESIGNER.toggleSyncAll(this.checked)" checked>
                                    Pilih Semua
                                </label>
                            </div>
                        </div>

                        <div id="dsg-sync-tables-wrap" class="designer-table-scroll" style="max-height:360px">
                            <!-- Diisi dinamis oleh DB_DESIGNER.renderSyncTableDiff() -->
                        </div>
                    </div>
                </div>
                <div class="designer-submodal-footer" style="justify-content:space-between">
                    <button type="button" class="designer-btn" onclick="window.DB_DESIGNER.closeSubmodal('sync-code')">Tutup</button>
                    <button type="button" id="dsg-btn-do-sync" class="designer-btn designer-btn-sync" onclick="window.DB_DESIGNER.submitSyncCodebase()" style="display:inline-flex; align-items:center; gap:6px">
                        <span>⚡</span>
                        <span>Mulai Sinkronisasi</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

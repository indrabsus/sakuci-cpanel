-- Satu repo hanya boleh dipakai satu project, berlaku lintas pengguna.
--
-- Kolom git_key menyimpan bentuk baku dari git_url ("host/pemilik/nama"),
-- karena satu repo yang sama bisa ditulis dalam banyak bentuk -- dengan atau
-- tanpa .git, huruf besar-kecil berbeda, www., http vs https. Membandingkan
-- teks mentah akan meloloskan semuanya sebagai repo berbeda.
--
-- git_url tetap disimpan apa adanya untuk ditampilkan.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/004-git-url-unik.sql
--
-- Aman dijalankan berulang.

SET @adaKolom := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'projects'
       AND COLUMN_NAME  = 'git_key'
);

SET @sql := IF(@adaKolom = 0,
    "ALTER TABLE projects ADD COLUMN git_key varchar(255) NOT NULL DEFAULT '' AFTER git_url",
    "SELECT 'Kolom git_key sudah ada, dilewati' AS info"
);
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- Isi bentuk baku untuk baris yang sudah ada. Penormalan di SQL ini sengaja
-- disederhanakan (huruf kecil, buang skema, www., dan akhiran .git/garis
-- miring); bentuk SSH ditangani PHP saat project baru ditambahkan.
UPDATE projects
   SET git_key = TRIM(TRAILING '/' FROM
         TRIM(TRAILING '.git' FROM
           TRIM(TRAILING '/' FROM
             REPLACE(REPLACE(REPLACE(LOWER(git_url),
               'https://', ''), 'http://', ''), 'www.', '')
           )
         )
       )
 WHERE git_key = '';

-- Bila ada repo kembar yang telanjur masuk, yang lebih baru diberi akhiran
-- angka supaya indeks unik bisa dipasang. Pengelola bisa merapikannya lewat
-- panel setelahnya.
UPDATE projects p
  JOIN (
    SELECT id, git_key,
           ROW_NUMBER() OVER (PARTITION BY git_key ORDER BY id) AS urutan
      FROM projects
  ) d ON d.id = p.id
   SET p.git_key = CONCAT(p.git_key, '#', d.urutan)
 WHERE d.urutan > 1;

SET @adaIndeks := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'projects'
       AND INDEX_NAME   = 'unik_git_key'
);

SET @sql2 := IF(@adaIndeks = 0,
    "ALTER TABLE projects ADD UNIQUE KEY unik_git_key (git_key)",
    "SELECT 'Indeks unik git_key sudah ada, dilewati' AS info"
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

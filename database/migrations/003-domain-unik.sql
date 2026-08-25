-- Domain project harus unik.
--
-- Tanpa ini, dua siswa bisa memakai domain yang sama. Keduanya lalu menunjuk
-- folder yang sama di projects/ dan subdomain yang sama, sehingga pekerjaan
-- mereka saling menimpa saat di-clone.
--
-- Yang dijadikan unik adalah local_path, bukan kolom domain, karena nama
-- folder dibentuk dari domain yang sudah dibersihkan: "my-app" dan "myapp"
-- berbeda sebagai teks tetapi menghasilkan folder yang sama.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/003-domain-unik.sql
--
-- Aman dijalankan berulang.

-- Bila sudah terlanjur ada domain kembar, yang lebih baru diberi akhiran angka
-- supaya penambahan indeks unik tidak gagal. Pengelola bisa merapikannya lewat
-- panel setelahnya.
UPDATE projects p
  JOIN (
    SELECT id, local_path,
           ROW_NUMBER() OVER (PARTITION BY local_path ORDER BY id) AS urutan
      FROM projects
  ) d ON d.id = p.id
   SET p.local_path = CONCAT(p.local_path, '-', d.urutan),
       p.domain     = CONCAT(p.domain, '-', d.urutan)
 WHERE d.urutan > 1;

SET @ada := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'projects'
       AND INDEX_NAME   = 'unik_local_path'
);

SET @sql := IF(@ada = 0,
    "ALTER TABLE projects ADD UNIQUE KEY unik_local_path (local_path)",
    "SELECT 'Indeks unik sudah ada, dilewati' AS info"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

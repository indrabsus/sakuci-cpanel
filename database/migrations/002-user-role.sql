-- Peran pengguna.
--
-- 'admin' melihat dan mengelola seluruh project serta database milik semua
-- orang, dan boleh menambah atau menghapus akun. 'user' -- siswa -- hanya
-- melihat miliknya sendiri.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/002-user-role.sql
--
-- Aman dijalankan berulang.

-- MySQL tidak mengenal "ADD COLUMN IF NOT EXISTS" (itu sintaks MariaDB), jadi
-- keberadaan kolom diperiksa dulu ke information_schema. Tanpa ini, menjalankan
-- migrasi dua kali berhenti dengan galat "Duplicate column name".
SET @ada := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'role'
);

SET @sql := IF(@ada = 0,
    "ALTER TABLE users ADD COLUMN role enum('admin','user') NOT NULL DEFAULT 'user' AFTER password",
    "SELECT 'Kolom role sudah ada, dilewati' AS info"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Akun pertama dijadikan admin agar panel tidak kehilangan pengelolanya.
-- Bila 'admin' tidak ada, akun dengan id terkecil yang dipakai. Dilewati bila
-- sudah ada admin, supaya migrasi ulang tidak menimpa peran yang sengaja diubah.
SET @sudahAdaAdmin := (SELECT COUNT(*) FROM users WHERE role = 'admin');

UPDATE users SET role = 'admin'
 WHERE @sudahAdaAdmin = 0
   AND (username = 'admin'
        OR id = (SELECT * FROM (SELECT MIN(id) FROM users) AS t));

-- Migrasi 007: Pencatatan waktu aktivitas terakhir untuk pemantauan pengguna online.
--
-- Menambahkan kolom last_activity pada tabel users.
-- Digunakan untuk mengetahui siapa saja yang sedang aktif membuka cPanel.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/007-online-activity.sql
--
-- Aman dijalankan berulang.

SET @adaKolom := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'last_activity'
);

SET @sql := IF(@adaKolom = 0,
    "ALTER TABLE users ADD COLUMN last_activity timestamp NULL DEFAULT NULL AFTER role, ADD KEY idx_last_activity (last_activity)",
    "SELECT 'Kolom last_activity sudah ada, dilewati' AS info"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

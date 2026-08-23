-- Peran pengguna.
--
-- 'admin' melihat dan mengelola seluruh project serta database milik semua
-- orang, dan boleh menambah atau menghapus akun. 'user' -- siswa -- hanya
-- melihat miliknya sendiri.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/002-user-role.sql

ALTER TABLE users
    ADD COLUMN role enum('admin','user') NOT NULL DEFAULT 'user' AFTER password;

-- Akun pertama dijadikan admin agar panel tidak kehilangan pengelolanya.
-- Bila 'admin' tidak ada, akun dengan id terkecil yang dipakai.
UPDATE users SET role = 'admin'
 WHERE username = 'admin'
    OR id = (SELECT * FROM (SELECT MIN(id) FROM users) AS t);

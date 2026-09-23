-- 008-commit-cache.sql
-- Menambahkan kolom cache commit git pada tabel projects
-- Menghindari pemanggilan shell_exec("git log") 34 kali pada setiap page load dashboard admin

ALTER TABLE projects
  ADD COLUMN commit_cache TEXT DEFAULT NULL COMMENT 'JSON cache dari git log terakhir',
  ADD COLUMN commit_mtime BIGINT DEFAULT 0 COMMENT 'mtime terakhir file .git/HEAD saat cache dibuat';

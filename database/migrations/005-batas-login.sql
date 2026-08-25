-- Catatan percobaan login gagal, untuk membatasi penebakan password.
--
-- Menjadi penting sejak password akun berupa PIN enam angka: ruang tebakannya
-- hanya sejuta kemungkinan, yang tanpa pembatasan bisa ditelusuri mesin dalam
-- hitungan menit.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/005-batas-login.sql
--
-- Aman dijalankan berulang.

CREATE TABLE IF NOT EXISTS login_gagal (
  id       int         NOT NULL AUTO_INCREMENT,
  ip       varchar(45) NOT NULL,
  username varchar(50) DEFAULT NULL,
  waktu    timestamp   NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_waktu (ip, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

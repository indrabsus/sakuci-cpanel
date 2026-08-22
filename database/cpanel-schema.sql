-- Skema cPanel Sakuci
--
--   mysql -u <user> -p <nama_database> < database/cpanel-schema.sql
--
-- Tidak memuat CREATE DATABASE agar bisa dijalankan oleh user terbatas
-- (mis. database yang dibuat lewat aaPanel). Urutan tabel mengikuti
-- ketergantungan foreign key.

CREATE TABLE IF NOT EXISTS users (
  id         int          NOT NULL AUTO_INCREMENT,
  username   varchar(50)  NOT NULL,
  email      varchar(100) NOT NULL,
  password   varchar(255) NOT NULL,          -- hash bcrypt, bukan teks polos
  created_at timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id          int          NOT NULL AUTO_INCREMENT,
  user_id     int          NOT NULL,
  name        varchar(100) NOT NULL,
  domain      varchar(255) DEFAULT NULL,
  git_url     varchar(255) NOT NULL,
  git_branch  varchar(50)  DEFAULT 'main',
  local_path  varchar(255) NOT NULL,
  description text,
  status      enum('active','inactive') DEFAULT 'active',
  created_at  timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  last_pull   timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  CONSTRAINT projects_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dinamai db_list, bukan "databases", karena itu kata kunci MySQL.
CREATE TABLE IF NOT EXISTS db_list (
  id         int          NOT NULL AUTO_INCREMENT,
  project_id int          DEFAULT NULL,
  user_id    int          NOT NULL,
  db_name    varchar(100) NOT NULL,
  db_host    varchar(50)  DEFAULT 'localhost',
  db_port    int          DEFAULT 3306,
  created_at timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_db (db_name),
  KEY project_id (project_id),
  KEY idx_user_id (user_id),
  CONSTRAINT db_list_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT db_list_ibfk_2 FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS db_users (
  id         int          NOT NULL AUTO_INCREMENT,
  db_id      int          NOT NULL,
  username   varchar(50)  NOT NULL,
  password   varchar(255) NOT NULL,
  privileges varchar(100) DEFAULT 'all',
  created_at timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_db_id (db_id),
  CONSTRAINT db_users_ibfk_1 FOREIGN KEY (db_id) REFERENCES db_list (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User awal. Password sengaja diisi hash yang tidak cocok dengan apa pun,
-- sehingga login mustahil sampai Anda menetapkannya sendiri:
--
--   php tools/set-password.php admin
--
INSERT INTO users (username, email, password)
SELECT 'admin', 'admin@localhost', '*'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Antrean pekerjaan git. PHP web di aaPanel dimatikan exec()-nya, jadi panel
-- hanya menitipkan permintaan di sini dan tools/worker.php (cron, PHP CLI)
-- yang mengerjakannya.
CREATE TABLE IF NOT EXISTS job_queue (
  id           int NOT NULL AUTO_INCREMENT,
  project_id   int NOT NULL,
  user_id      int NOT NULL,
  action       enum('clone','pull') NOT NULL,
  status       enum('pending','running','success','failed') NOT NULL DEFAULT 'pending',
  output       text,
  requested_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  started_at   timestamp NULL DEFAULT NULL,
  finished_at  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_project (project_id),
  CONSTRAINT job_queue_ibfk_1 FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT job_queue_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

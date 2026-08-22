-- Antrean pekerjaan git.
--
-- PHP web di aaPanel dimatikan exec()-nya, jadi panel tidak boleh menjalankan
-- git sendiri. Panel hanya menitipkan permintaan di tabel ini; tools/worker.php
-- yang dipanggil cron (PHP CLI, exec masih aktif) yang mengerjakannya.
--
-- Jalankan pada instalasi yang sudah ada:
--   mysql -h 127.0.0.1 -u <user> -p <db> < database/migrations/001-job-queue.sql

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

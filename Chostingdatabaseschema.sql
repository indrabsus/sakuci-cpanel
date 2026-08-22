CREATE DATABASE IF NOT EXISTS sakuci_cpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sakuci_cpanel;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255),
    git_url VARCHAR(255) NOT NULL,
    git_branch VARCHAR(50) DEFAULT 'main',
    local_path VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_pull TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

CREATE TABLE db_list (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    user_id INT NOT NULL,
    db_name VARCHAR(100) NOT NULL,
    db_host VARCHAR(50) DEFAULT 'localhost',
    db_port INT DEFAULT 3306,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    UNIQUE KEY unique_db (db_name),
    INDEX idx_user_id (user_id)
);

CREATE TABLE db_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    db_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    privileges VARCHAR(100) DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (db_id) REFERENCES db_list(id) ON DELETE CASCADE,
    INDEX idx_db_id (db_id)
);

INSERT INTO users (username, email, password) VALUES ('admin', 'admin@local.dev', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36DRcx3u');

-- Sakuci Hosting Database Schema - Clean Version
CREATE DATABASE IF NOT EXISTS sakuci_hosting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sakuci_hosting;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    country VARCHAR(50),
    postal_code VARCHAR(10),
    company_name VARCHAR(100),
    role ENUM('user', 'admin', 'reseller') DEFAULT 'user',
    user_status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Packages Table
CREATE TABLE packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    disk_space INT NOT NULL,
    bandwidth INT NOT NULL,
    num_databases INT NOT NULL,
    email_accounts INT NOT NULL,
    ftp_accounts INT NOT NULL,
    addon_domains INT NOT NULL,
    ssl_certificates BOOLEAN DEFAULT TRUE,
    backup_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
    php_version VARCHAR(10) DEFAULT '8.1',
    price_monthly DECIMAL(10, 2),
    price_yearly DECIMAL(10, 2),
    price_biennial DECIMAL(10, 2),
    is_popular BOOLEAN DEFAULT FALSE,
    features JSON,
    package_status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
);

-- Servers Table
CREATE TABLE servers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(20),
    hostname VARCHAR(255),
    os VARCHAR(50),
    disk_capacity INT,
    bandwidth_capacity INT,
    php_version VARCHAR(20),
    mysql_version VARCHAR(20),
    control_panel VARCHAR(50),
    server_status ENUM('active', 'maintenance', 'offline') DEFAULT 'active',
    load_percentage INT DEFAULT 0,
    max_accounts INT,
    current_accounts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hosting Accounts Table
CREATE TABLE hosting_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    username VARCHAR(20) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    subdomain VARCHAR(255),
    server_id INT,
    control_panel_password VARCHAR(255),
    account_status ENUM('active', 'pending', 'suspended', 'terminated') DEFAULT 'pending',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    renewal_date DATE,
    disk_used INT DEFAULT 0,
    bandwidth_used INT DEFAULT 0,
    auto_renewal BOOLEAN DEFAULT TRUE,
    notes TEXT,
    INDEX idx_user_id (user_id),
    INDEX idx_package_id (package_id),
    INDEX idx_domain (domain),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id)
);

-- Databases Table
CREATE TABLE hosting_databases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hosting_account_id INT NOT NULL,
    db_name VARCHAR(100) NOT NULL,
    db_user VARCHAR(50) NOT NULL,
    db_password VARCHAR(255),
    db_type ENUM('mysql', 'postgresql') DEFAULT 'mysql',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_db (hosting_account_id, db_name)
);

-- Orders Table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hosting_account_id INT,
    package_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly', 'biennial') DEFAULT 'yearly',
    order_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    invoice_number VARCHAR(50) UNIQUE,
    transaction_id VARCHAR(100),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    paid_date DATETIME,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES packages(id),
    INDEX idx_user_id (user_id)
);

-- Support Tickets Table
CREATE TABLE support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hosting_account_id INT,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    ticket_status ENUM('open', 'in_progress', 'waiting', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id)
);

-- Invoices Table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE,
    due_date DATE,
    paid_date DATE,
    amount DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    invoice_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_invoice_number (invoice_number)
);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(200) NOT NULL,
    description TEXT,
    ip_address VARCHAR(20),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Settings Table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    description TEXT,
    category VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Packages
INSERT INTO packages (name, slug, description, disk_space, bandwidth, num_databases, email_accounts, ftp_accounts, addon_domains, price_monthly, price_yearly) VALUES
('Starter', 'starter', 'Perfect untuk memulai', 1024, 50, 1, 5, 2, 0, 29.99, 299.99),
('Professional', 'professional', 'Untuk bisnis berkembang', 5120, 200, 5, 20, 10, 5, 79.99, 799.99),
('Business', 'business', 'Solusi bisnis lengkap', 20480, 1000, 25, 100, 50, 50, 199.99, 1999.99),
('Enterprise', 'enterprise', 'Untuk enterprise besar', 102400, 5000, 100, 500, 200, 200, 499.99, 4999.99);

-- Insert Default Server
INSERT INTO servers (name, ip_address, hostname, os, disk_capacity, bandwidth_capacity, php_version, mysql_version, control_panel, server_status) VALUES
('Server 1 (SG)', '185.199.100.1', 'server1.sakuci-hosting.com', 'Ubuntu 22.04 LTS', 1000, 1000, '8.1,8.2,8.3', '8.0,5.7', 'cPanel', 'active');

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, description, category) VALUES
('company_name', 'Sakuci Hosting', 'Company name', 'general'),
('company_email', 'support@sakuci-hosting.com', 'Company email', 'general'),
('phone_number', '+62-XXX-XXX-XXXX', 'Company phone', 'general'),
('address', 'Indonesia', 'Company address', 'general'),
('currency', 'IDR', 'Currency symbol', 'billing'),
('tax_rate', '10', 'Tax percentage', 'billing');

-- Insert Test Admin User (password: admin123)
INSERT INTO users (username, email, password, full_name, role, user_status) VALUES
('admin', 'admin@sakuci-hosting.com', '$2y$10$mI2W.Jz8l.eQp9X5D3E5K.kYVrZvJ7Y8e3X5K8l5Y5Y5Y5Y5Y5Y', 'Administrator', 'admin', 'active');

-- Insert Test Regular User (password: password123)
INSERT INTO users (username, email, password, full_name, role, user_status) VALUES
('johndoe', 'john@example.com', '$2y$10$K5Y8l5Y5Y5Y5Y5Y5Y5Y5Y.kYVrZvJ7Y8e3X5K8l5Y5Y5Y5Y5Y5Y', 'John Doe', 'user', 'active');

-- Insert Test Hosting Accounts
INSERT INTO hosting_accounts (user_id, package_id, username, domain, account_status, registration_date, expiry_date) VALUES
(2, 2, 'example', 'example.com', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR)),
(2, 1, 'mybusiness', 'mybusiness.id', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR));

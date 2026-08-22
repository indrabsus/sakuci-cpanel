-- Sakuci Hosting Database Schema
-- Create Database
CREATE DATABASE IF NOT EXISTS sakuci_hosting;
USE sakuci_hosting;

-- Users Table (Customers)
CREATE TABLE IF NOT EXISTS users (
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
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
);

-- Hosting Packages Table
CREATE TABLE IF NOT EXISTS packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    disk_space INT NOT NULL, -- in MB
    bandwidth INT NOT NULL, -- in GB
    `databases` INT NOT NULL,
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
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status)
);

-- Hosting Accounts Table
CREATE TABLE IF NOT EXISTS hosting_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    username VARCHAR(20) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    subdomain VARCHAR(255),
    server_id INT,
    control_panel_password VARCHAR(255),
    status ENUM('active', 'pending', 'suspended', 'terminated') DEFAULT 'pending',
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

-- Servers Table
CREATE TABLE IF NOT EXISTS servers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(20),
    hostname VARCHAR(255),
    os VARCHAR(50),
    disk_capacity INT, -- in GB
    bandwidth_capacity INT, -- in Gbps
    php_version VARCHAR(20),
    mysql_version VARCHAR(20),
    control_panel VARCHAR(50),
    status ENUM('active', 'maintenance', 'offline') DEFAULT 'active',
    load_percentage INT DEFAULT 0,
    max_accounts INT,
    current_accounts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- Databases Table
CREATE TABLE IF NOT EXISTS databases (
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
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hosting_account_id INT,
    package_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly', 'biennial') DEFAULT 'yearly',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    invoice_number VARCHAR(50) UNIQUE,
    transaction_id VARCHAR(100),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    paid_date DATETIME,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES packages(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Support Tickets Table
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hosting_account_id INT,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'waiting', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Ticket Replies Table
CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    reply_text TEXT NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
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
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_invoice_number (invoice_number)
);

-- Backups Table
CREATE TABLE IF NOT EXISTS backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hosting_account_id INT NOT NULL,
    backup_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    backup_size INT, -- in MB
    backup_type ENUM('full', 'incremental') DEFAULT 'full',
    status ENUM('completed', 'failed', 'pending') DEFAULT 'pending',
    download_count INT DEFAULT 0,
    last_download DATETIME,
    FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (hosting_account_id),
    INDEX idx_backup_date (backup_date)
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_logs (
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

-- Admin Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    description TEXT,
    category VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Packages
INSERT INTO packages (name, slug, description, disk_space, bandwidth, databases, email_accounts, ftp_accounts, addon_domains, price_monthly, price_yearly) VALUES
('Starter', 'starter', 'Perfect untuk memulai', 1024, 50, 1, 5, 2, 0, 29.99, 299.99),
('Professional', 'professional', 'Untuk bisnis berkembang', 5120, 200, 5, 20, 10, 5, 79.99, 799.99),
('Business', 'business', 'Solusi bisnis lengkap', 20480, 1000, 25, 100, 50, 50, 199.99, 1999.99),
('Enterprise', 'enterprise', 'Untuk enterprise besar', 102400, 5000, 100, 500, 200, 200, 499.99, 4999.99);

-- Insert Default Server
INSERT INTO servers (name, ip_address, hostname, os, disk_capacity, bandwidth_capacity, php_version, mysql_version, control_panel, status) VALUES
('Server 1 (SG)', '185.199.100.1', 'server1.sakuci-hosting.com', 'Ubuntu 22.04 LTS', 1000, 1000, '8.1,8.2,8.3', '8.0,5.7', 'cPanel', 'active');

-- Insert Default Admin Settings
INSERT INTO settings (setting_key, setting_value, description, category) VALUES
('company_name', 'Sakuci Hosting', 'Company name', 'general'),
('company_email', 'support@sakuci-hosting.com', 'Company email', 'general'),
('phone_number', '+62-XXX-XXX-XXXX', 'Company phone', 'general'),
('address', 'Indonesia', 'Company address', 'general'),
('currency', 'IDR', 'Currency symbol', 'billing'),
('tax_rate', '10', 'Tax percentage', 'billing');

# Sakuci Hosting Platform

Sistem web hosting profesional untuk Sakuci Framework dengan fitur lengkap termasuk database management, PhpMyAdmin, dan panel admin.

## 🚀 Fitur Utama

### Untuk Customer
- ✅ Registrasi & Login User
- ✅ Dashboard Management
- ✅ Kelola Hosting Account
- ✅ File Manager
- ✅ Database Management
- ✅ Backup & Restore
- ✅ Support Tickets
- ✅ Billing & Invoice

### Untuk Admin
- ✅ Dashboard Statistik
- ✅ Manage Customers
- ✅ Manage Packages
- ✅ Manage Servers
- ✅ Database Manager (phpMyAdmin)
- ✅ Activity Logs
- ✅ Revenue Tracking

### Fitur Teknis
- ✅ SSL Certificate
- ✅ Automatic Backups
- ✅ Uptime Monitoring
- ✅ Bandwidth Tracking
- ✅ PHP 8.1+
- ✅ MySQL 8.0+
- ✅ SSD Storage

## 📋 Requirement

- PHP 8.1 atau lebih tinggi
- MySQL 8.0 atau PostgreSQL 12+
- Web Server (Apache/Nginx)
- cURL Extension
- PDO MySQL

## ⚙️ Instalasi

### 1. Setup Database

```bash
# Import database schema
mysql -u root < database/schema.sql
```

Atau manual:
1. Buka phpMyAdmin
2. Buat database baru: `sakuci_hosting`
3. Import file `database/schema.sql`

### 2. Konfigurasi Database

Edit file `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sakuci_hosting');
define('DB_PORT', 3306);
```

### 3. Setup Web Server

#### Apache (.htaccess)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . index.php [L]
</IfModule>
```

#### Nginx
```nginx
location / {
    if (!-e $request_filename){
        rewrite ^(.*)$ /index.php break;
    }
}
```

### 4. Permission Settings

```bash
chmod 755 public/
chmod 644 public/css/
chmod 644 public/js/
chmod 755 config/
```

## 📁 Struktur File

```
hosting/
├── index.php                 # Homepage/Landing Page
├── config/
│   └── database.php         # Konfigurasi Database
├── public/
│   ├── css/
│   │   └── style.css        # Stylesheet
│   ├── js/
│   │   └── script.js        # JavaScript
│   └── assets/              # Images & Files
├── user/
│   ├── register.php         # Halaman Registrasi
│   ├── login.php            # Halaman Login
│   ├── dashboard.php        # Dashboard User
│   ├── manage-hosting.php   # Kelola Hosting
│   └── logout.php           # Logout
├── admin/
│   ├── index.php            # Admin Dashboard
│   ├── customers.php        # Manage Customers
│   ├── packages.php         # Manage Packages
│   ├── servers.php          # Manage Servers
│   ├── phpmyadmin-setup.php # Database Manager
│   └── logout.php           # Admin Logout
├── api/
│   ├── auth.php             # API Authentication
│   ├── hosting.php          # Hosting API
│   └── files.php            # File Manager API
└── database/
    └── schema.sql           # Database Schema
```

## 🔐 Akun Default

### Admin Account
- **Username:** admin
- **Email:** admin@sakuci-hosting.com
- **Password:** admin123 (gunakan untuk testing)

Untuk production, ubah password immediately!

## 🛠️ Konfigurasi Paket Hosting

Edit file atau database untuk menambah/edit paket:

```sql
INSERT INTO packages (
    name, slug, description, 
    disk_space, bandwidth, databases,
    email_accounts, ftp_accounts, addon_domains,
    price_monthly, price_yearly
) VALUES (
    'Premium', 'premium', 'Paket premium untuk bisnis',
    10240, 500, 10,
    50, 25, 25,
    99.99, 999.99
);
```

## 📊 Dashboard Admin

### Menu Utama
- **Dashboard:** Statistik umum dan summary
- **Customers:** Kelola semua customer dan status
- **Packages:** Edit harga dan fitur paket
- **Servers:** Kelola server dan resource
- **PhpMyAdmin:** Database management interface

## 🔧 PhpMyAdmin Setup

PhpMyAdmin terintegrasi untuk management database:

### Akses PhpMyAdmin
1. URL: `http://phpmyadmin.sakuci-hosting.com`
2. Username: Database user yang sudah dibuat
3. Password: Password database

### Fitur Database Manager
- Buat database baru
- Manage users & permissions
- Import/Export data
- Query builder
- Backup database

## 📈 Fitur Billing

### Order Management
- Status tracking: pending, completed, failed, cancelled
- Invoice generation otomatis
- Payment method tracking
- Billing cycle: monthly, yearly, biennial

### Revenue Tracking
- Total revenue dashboard
- Order history
- Invoice management
- Tax calculation

## 🎫 Support Ticket System

### Features
- Create ticket dengan kategori
- Priority levels: low, medium, high, urgent
- Status tracking: open, in_progress, waiting, resolved
- Admin assignment
- Reply notifications

## 📱 Responsive Design

Website fully responsive untuk:
- 💻 Desktop (1200px+)
- 📱 Tablet (768px - 1199px)
- 📲 Mobile (< 768px)

## 🔐 Security Features

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF token (implementasi)
- ✅ Session security
- ✅ HTTP headers security

## 📝 Database Tables

### Users
- user management
- role-based access (user, admin, reseller)
- status tracking

### Hosting Accounts
- domain management
- package assignment
- server allocation
- status monitoring

### Orders & Invoices
- order tracking
- invoice generation
- payment processing

### Support Tickets
- ticket management
- priority handling
- admin assignment

### Databases
- MySQL database creation
- User credentials
- Database permissions

### Servers
- server information
- resource tracking
- account allocation

### Activity Logs
- audit trail
- user actions
- system events

## 🚀 Deployment

### Production Checklist
- [ ] Update database credentials
- [ ] Change admin password
- [ ] Enable HTTPS
- [ ] Set proper file permissions
- [ ] Enable error logging
- [ ] Disable debug mode
- [ ] Setup backups
- [ ] Configure email notifications
- [ ] Setup firewall rules
- [ ] Monitor server resources

## 🐛 Troubleshooting

### Database Connection Error
```
Solusi: Periksa config/database.php
- Host, user, password, database name
- MySQL service running
```

### Permission Denied
```
Solusi: Update file permissions
chmod 755 direktori
chmod 644 file PHP
```

### Session Problems
```
Solusi: Periksa session.save_path
Buat folder: /tmp atau /var/lib/php/sessions
```

## 📚 API Documentation

### Authentication
```php
POST /api/auth.php
Parameters: username, password
Response: user_id, username, role
```

### Hosting Management
```php
GET /api/hosting.php?action=list&user_id=1
POST /api/hosting.php?action=create
PUT /api/hosting.php?action=update&id=1
DELETE /api/hosting.php?action=delete&id=1
```

## 🔄 Backup & Restore

### Backup Database
```bash
mysqldump -u root sakuci_hosting > backup_$(date +%Y%m%d).sql
```

### Restore Database
```bash
mysql -u root sakuci_hosting < backup_20240101.sql
```

## 📞 Support & Contact

- Email: support@sakuci-hosting.com
- Phone: +62-XXX-XXX-XXXX
- Hours: 24/7
- Website: www.sakuci-hosting.com

## 📄 License

This project is proprietary software for Sakuci Hosting. All rights reserved.

## 🤝 Kontribusi

Untuk bug reports dan feature requests, hubungi tim development.

---

**Version:** 1.0.0  
**Last Updated:** 2024  
**Status:** Production Ready

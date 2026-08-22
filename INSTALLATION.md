# Panduan Instalasi Sakuci Hosting Platform

Panduan lengkap untuk instalasi dan setup Sakuci Hosting Platform.

## 📋 Pre-requisites

Sebelum memulai, pastikan Anda memiliki:

- PHP 8.1 atau lebih tinggi
- MySQL 8.0 atau PostgreSQL 12+
- Web Server (Apache dengan mod_rewrite atau Nginx)
- phpMyAdmin (untuk management database)
- Git (opsional)

## 🔧 Installation Steps

### Step 1: Download/Clone Project

#### Menggunakan Git
```bash
git clone https://github.com/yourusername/sakuci-hosting.git
cd hosting
```

#### Atau Download Manual
```bash
# Download file zip
# Extract ke folder /hosting atau /var/www/hosting
```

### Step 2: Set Folder Permissions

#### Linux/Mac
```bash
# Navigate ke project folder
cd /path/to/hosting

# Set permissions
chmod -R 755 .
chmod -R 777 logs/
chmod -R 777 public/uploads/
chmod 644 config/database.php
```

#### Windows
```bash
# Klik kanan folder → Properties → Security
# Berikan Full Control untuk IUSR dan IIS_IUSRS
```

### Step 3: Create Database

#### Method 1: Menggunakan phpMyAdmin
1. Buka phpMyAdmin: `http://localhost/phpmyadmin`
2. Klik "New" atau "Databases"
3. Database name: `sakuci_hosting`
4. Collation: `utf8mb4_unicode_ci`
5. Klik "Create"
6. Pilih database `sakuci_hosting`
7. Klik tab "Import"
8. Select file: `database/schema.sql`
9. Klik "Import"

#### Method 2: Command Line
```bash
# Login ke MySQL
mysql -u root -p

# Buat database
CREATE DATABASE sakuci_hosting;
USE sakuci_hosting;

# Import schema
source /path/to/database/schema.sql;

# Verify
SHOW TABLES;
```

#### Method 3: PowerShell (Windows)
```powershell
# Download MySQL CLI atau gunakan
mysql -u root < database/schema.sql
```

### Step 4: Configure Database Connection

Edit file `config/database.php`:

```php
<?php
define('DB_HOST', 'localhost');      // Your MySQL Host
define('DB_USER', 'root');           // Your MySQL User
define('DB_PASS', '');               // Your MySQL Password
define('DB_NAME', 'sakuci_hosting'); // Database Name
define('DB_PORT', 3306);             // MySQL Port (default 3306)
?>
```

**Testing Connection:**
```bash
# Buat file test.php untuk test koneksi
<?php
include 'config/database.php';
echo "Connection successful!";
?>

# Akses: http://localhost/hosting/test.php
```

### Step 5: Configure Web Server

#### Apache Configuration

1. **Enable mod_rewrite**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

2. **Virtual Host** (opsional)
```apache
<VirtualHost *:80>
    ServerName sakuci-hosting.local
    DocumentRoot /var/www/hosting

    <Directory /var/www/hosting>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/hosting-error.log
    CustomLog ${APACHE_LOG_DIR}/hosting-access.log combined
</VirtualHost>
```

3. **Enable Virtual Host**
```bash
sudo a2ensite hosting.conf
sudo systemctl restart apache2
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name sakuci-hosting.local;
    root /var/www/hosting;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

### Step 6: Add Hosts Entry (opsional)

Edit file `/etc/hosts` (Linux/Mac) atau `C:\Windows\System32\drivers\etc\hosts` (Windows):

```
127.0.0.1   sakuci-hosting.local
127.0.0.1   phpmyadmin.sakuci-hosting.local
```

Kemudian akses: `http://sakuci-hosting.local`

### Step 7: Create Admin Account

#### Method 1: Manual SQL
```sql
-- Hash password: admin123 dengan password_hash()
-- Gunakan PHP untuk generate hash:
<?php echo password_hash('admin123', PASSWORD_BCRYPT); ?>
```

```sql
INSERT INTO users (username, email, password, full_name, role, status) 
VALUES (
    'admin',
    'admin@sakuci-hosting.com',
    '$2y$10$...[hashed_password]...',
    'Administrator',
    'admin',
    'active'
);
```

#### Method 2: Menggunakan Form
1. Daftar akun normal via register.php
2. Login ke phpmyadmin
3. Update tabel users, set role = 'admin'

### Step 8: Verify Installation

Buka browser dan akses:

```
1. Homepage:        http://localhost/hosting
2. User Register:   http://localhost/hosting/user/register.php
3. User Login:      http://localhost/hosting/user/login.php
4. Admin Panel:     http://localhost/hosting/admin/
5. PhpMyAdmin:      http://localhost/phpmyadmin
```

### Step 9: Create Logs Folder (opsional)

```bash
# Create logs folder
mkdir -p logs
chmod 777 logs

# Test error logging
touch logs/error.log
chmod 666 logs/error.log
```

## 🛡️ Security Configuration

### 1. Update Admin Password

```bash
# Via phpMyAdmin
# Edit users table, set password hash
```

### 2. Set Proper File Permissions

```bash
# Config files (read-only)
chmod 644 config/database.php

# Upload folders (writable)
chmod 777 public/uploads/
chmod 777 logs/

# Rest (readable)
chmod 755 -R public/
chmod 755 -R user/
chmod 755 -R admin/
```

### 3. Enable HTTPS

Update file dengan HTTPS URL:
- CSS path
- JavaScript path
- Form actions

Configure SSL:
```apache
# Apache
SSLEngine on
SSLCertificateFile /path/to/ssl/certificate.crt
SSLCertificateKeyFile /path/to/ssl/private.key
```

### 4. Configure Firewall

```bash
# Allow HTTP
sudo ufw allow 80/tcp

# Allow HTTPS
sudo ufw allow 443/tcp

# Allow SSH (if needed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

## 📊 Post-Installation Configuration

### 1. Update Company Information

Login sebagai Admin, edit tabel `settings`:

```sql
UPDATE settings SET setting_value = 'Sakuci Hosting Indonesia' 
WHERE setting_key = 'company_name';

UPDATE settings SET setting_value = 'support@sakuci-hosting.com' 
WHERE setting_key = 'company_email';
```

### 2. Configure Packages

Edit `admin/packages.php` untuk customize paket hosting

### 3. Setup Email Configuration

Untuk email notifications (implementasi nantinya):
```php
// config/mail.php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
```

### 4. Configure Payment Gateway (opsional)

Setup integration dengan:
- Stripe
- PayPal
- Bank Transfer
- etc.

## 🧪 Testing

### Test Database Connection
```php
<?php
include 'config/database.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully!";
?>
```

### Test User Registration
1. Buka `http://localhost/hosting/user/register.php`
2. Isi form lengkap
3. Submit
4. Verify di database: `SELECT * FROM users;`

### Test Admin Panel
1. Login dengan admin account
2. Akses `http://localhost/hosting/admin/`
3. Verify statistik tampil dengan benar

### Test PhpMyAdmin
1. Akses phpmyadmin
2. Login dengan root user
3. Verify database terlihat

## 📱 Browser Compatibility

Tested pada:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers

## 🐛 Common Issues & Solutions

### Issue: Database Connection Error

**Error:** "Connection failed: Access denied for user"

**Solution:**
```bash
# Verify MySQL running
sudo systemctl status mysql

# Check credentials di config/database.php
# Reset MySQL password jika perlu
```

### Issue: 404 Not Found

**Error:** "The requested URL was not found on this server"

**Solution:**
```bash
# Ensure mod_rewrite enabled (Apache)
sudo a2enmod rewrite
sudo systemctl restart apache2

# Or verify .htaccess exists in root folder
```

### Issue: Permission Denied

**Error:** "Permission denied" when accessing files

**Solution:**
```bash
# Fix permissions
chmod 755 -R .
chmod 777 logs/
chmod 777 public/uploads/
```

### Issue: Blank Page

**Error:** Page loads but showing nothing

**Solution:**
1. Check error logs: `tail -f logs/error.log`
2. Enable display_errors temporarily:
   ```php
   ini_set('display_errors', 1);
   ```
3. Check PHP version: `php -v`

### Issue: CORS Error

**Error:** "No 'Access-Control-Allow-Origin' header"

**Solution:**
```apache
# Add to .htaccess
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
</IfModule>
```

## 🚀 Next Steps

Setelah instalasi berhasil:

1. **Customize** branding dan warna
2. **Add** payment gateway integration
3. **Setup** email notifications
4. **Configure** backup automation
5. **Implement** monitoring & alerting
6. **Launch** untuk production

## 📞 Support

Jika mengalami kesulitan:

1. Cek README.md
2. Review installation steps
3. Check error logs di folder `logs/`
4. Contact: support@sakuci-hosting.com

---

**Installation Guide Version:** 1.0  
**Last Updated:** 2024  
**Compatible with:** Sakuci Hosting v1.0+

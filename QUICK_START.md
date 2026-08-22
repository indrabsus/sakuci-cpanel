# Quick Start Guide - Sakuci Hosting Platform

Panduan cepat untuk mulai menggunakan Sakuci Hosting Platform.

## ⚡ 5 Menit Setup

### Step 1: Extract File (1 min)
```bash
# Extract zip file ke folder
# /var/www/hosting atau /home/user/public_html/hosting
cd /path/to/hosting
```

### Step 2: Import Database (1 min)
```bash
# Gunakan phpMyAdmin atau command line
mysql -u root -p sakuci_hosting < database/schema.sql
```

### Step 3: Configure Database (1 min)
Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');      // Your MySQL password
define('DB_NAME', 'sakuci_hosting');
```

### Step 4: Enable mod_rewrite (1 min)
```bash
# Apache
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Step 5: Access Application (1 min)
```
Homepage:    http://localhost/hosting
User Login:  http://localhost/hosting/user/login.php
Admin Panel: http://localhost/hosting/admin/
PhpMyAdmin:  http://localhost/phpmyadmin
```

## 🔑 Default Credentials

### Admin Account
- **Username:** admin
- **Password:** admin123

> ⚠️ PENTING: Ubah password admin setelah login pertama kali!

## 📊 Dashboard Overview

### Customer Dashboard
```
1. Lihat statistik hosting accounts
2. Kelola hosting accounts
3. Manage database
4. View backups
5. Contact support
```

### Admin Dashboard
```
1. Total users, accounts, revenue
2. Recent orders
3. Server status
4. Quick actions
```

## 🎯 Common Tasks

### Task 1: Add New Package
```
1. Login as Admin
2. Go to Packages menu
3. Edit existing package atau gunakan SQL:

INSERT INTO packages (name, slug, disk_space, bandwidth, 
    databases, price_monthly, price_yearly, status)
VALUES ('Budget', 'budget', 512, 20, 1, 19.99, 199.99, 'active');
```

### Task 2: Create User Account
```
1. Click Register at homepage
2. Fill form:
   - Full Name
   - Username
   - Email
   - Password
3. Submit
4. User dapat login immediately
```

### Task 3: Create Hosting Account
```
1. Login as Customer
2. Go to Dashboard
3. Click "Beli Hosting Baru"
4. Select package
5. Enter domain name
6. Complete payment (simulate)
7. Account created!
```

### Task 4: Create Database
```
1. Login as Admin
2. Go to PhpMyAdmin menu
3. Click "Create New Database"
4. Select hosting account
5. Enter database name
6. System generates:
   - Database name
   - DB user
   - DB password
7. Done!
```

### Task 5: Add New Server
```
1. Login as Admin
2. Go to Servers menu
3. Click "Add New Server"
4. Fill form:
   - Server Name
   - IP Address
   - Hostname
   - OS
   - Disk Capacity
   - Bandwidth Capacity
5. Submit
6. Server ready!
```

## 📁 Key Files

```
config/database.php      ← Database configuration
index.php               ← Homepage
user/register.php       ← User registration
user/login.php          ← User login
user/dashboard.php      ← User dashboard
admin/index.php         ← Admin dashboard
admin/phpmyadmin-setup.php ← Database manager
database/schema.sql     ← Database structure
public/css/style.css    ← Styling
public/js/script.js     ← JavaScript
```

## 🛠️ Quick Configuration

### Change Company Name
```sql
UPDATE settings SET setting_value = 'My Hosting Company' 
WHERE setting_key = 'company_name';
```

### Change Company Email
```sql
UPDATE settings SET setting_value = 'support@myhosting.com' 
WHERE setting_key = 'company_email';
```

### Add New Package
```sql
INSERT INTO packages (name, slug, description, disk_space, 
    bandwidth, databases, email_accounts, ftp_accounts, 
    addon_domains, price_monthly, price_yearly, status)
VALUES (
    'Starter Premium', 'starter-premium', 'Paket premium untuk starter',
    2048, 100, 3, 10, 5, 2, 49.99, 499.99, 'active'
);
```

## 🔍 Testing Checklist

- [ ] Homepage loads correctly
- [ ] User can register
- [ ] User can login
- [ ] Admin can login
- [ ] Can view packages
- [ ] Can create hosting account
- [ ] Can create database
- [ ] PhpMyAdmin accessible
- [ ] Database shows data

## 📞 Support

### If Something Goes Wrong

#### 1. Database Connection Error
```
✅ Check config/database.php
✅ Verify MySQL is running
✅ Test credentials in phpMyAdmin
```

#### 2. Page Not Found (404)
```
✅ Enable mod_rewrite: sudo a2enmod rewrite
✅ Check .htaccess exists
✅ Verify DocumentRoot correct
```

#### 3. Permission Denied
```
✅ chmod 755 public/
✅ chmod 777 logs/
✅ chmod 644 config/database.php
```

#### 4. Blank Page
```
✅ Check logs/php_error.log
✅ Enable ini_set('display_errors', 1);
✅ Verify PHP version 8.1+
```

## 🚀 Next Steps

1. ✅ Setup complete
2. 📝 Read full documentation (README.md)
3. 🔐 Change admin password
4. 📦 Customize packages
5. 🖥️ Add servers
6. 💳 Setup payment gateway (optional)
7. 📧 Configure email (optional)
8. 🌐 Deploy to production

## 📚 More Resources

- **Full Documentation:** See `README.md`
- **Installation Guide:** See `INSTALLATION.md`
- **Features Overview:** See `FEATURES.md`
- **API Documentation:** See `API.md`

## 💡 Pro Tips

### Tip 1: Use PhpMyAdmin
```
PhpMyAdmin makes database management easier:
- Browse tables
- Execute SQL queries
- Import/Export data
- Manage users
```

### Tip 2: Check Error Logs
```bash
# View PHP errors
tail -f logs/php_error.log

# View Apache logs
tail -f /var/log/apache2/error.log
```

### Tip 3: Debug Mode
```php
// Add to top of index.php temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Tip 4: SQL Queries
```sql
-- View all users
SELECT id, username, email, role, status FROM users;

-- View all hosting accounts
SELECT ha.*, p.name as package_name 
FROM hosting_accounts ha
LEFT JOIN packages p ON ha.package_id = p.id;

-- View all databases
SELECT * FROM databases;
```

## 🎓 Learning Path

### Beginner (1-2 hours)
- [ ] Read Quick Start (this file)
- [ ] Create user account
- [ ] Login and explore dashboard
- [ ] Create hosting account

### Intermediate (3-4 hours)
- [ ] Read README.md
- [ ] Login as admin
- [ ] Create packages
- [ ] Manage customers
- [ ] Understand database structure

### Advanced (5+ hours)
- [ ] Read API.md
- [ ] Read Installation.md
- [ ] Customize styling
- [ ] Integrate payment gateway
- [ ] Deploy to production

---

**Quick Start Guide Version:** 1.0  
**Estimated Setup Time:** 5 minutes  
**Last Updated:** 2024

🎉 **Selamat! Platform Anda sudah siap digunakan!**

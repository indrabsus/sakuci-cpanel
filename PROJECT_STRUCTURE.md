# Project Structure - Sakuci Hosting Platform

Struktur lengkap direktori dan file Sakuci Hosting Platform.

## 📁 Directory Tree

```
hosting/
│
├── 📄 index.php                    # Homepage & Landing Page
├── 📄 README.md                    # Project documentation
├── 📄 QUICK_START.md               # Quick start guide
├── 📄 INSTALLATION.md              # Installation guide
├── 📄 FEATURES.md                  # Feature list
├── 📄 API.md                       # API documentation
├── 📄 PROJECT_STRUCTURE.md         # This file
├── 📄 .htaccess                    # Apache rewrite rules
├── 📄 .gitignore                   # Git ignore file
├── 📄 .env.example                 # Environment variables example
│
├── 📁 config/
│   └── 📄 database.php             # Database configuration
│
├── 📁 public/
│   ├── 📁 css/
│   │   └── 📄 style.css            # Main stylesheet
│   ├── 📁 js/
│   │   └── 📄 script.js            # Main JavaScript
│   ├── 📁 assets/                  # Images, fonts, etc
│   └── 📁 uploads/                 # User uploads (optional)
│
├── 📁 user/
│   ├── 📄 register.php             # User registration page
│   ├── 📄 login.php                # User login page
│   ├── 📄 dashboard.php            # User dashboard
│   ├── 📄 manage-hosting.php       # Manage hosting accounts
│   ├── 📄 logout.php               # User logout
│   └── 📄 ...                      # Other user pages
│
├── 📁 admin/
│   ├── 📄 index.php                # Admin dashboard
│   ├── 📄 customers.php            # Customer management
│   ├── 📄 packages.php             # Package management
│   ├── 📄 servers.php              # Server management
│   ├── 📄 phpmyadmin-setup.php     # Database manager (PhpMyAdmin)
│   ├── 📄 logout.php               # Admin logout
│   └── 📄 ...                      # Other admin pages
│
├── 📁 api/
│   ├── 📄 auth.php                 # Authentication API
│   ├── 📄 hosting.php              # Hosting management API
│   ├── 📄 files.php                # File manager API
│   ├── 📄 packages.php             # Package API
│   ├── 📄 orders.php               # Order API
│   ├── 📄 tickets.php              # Support ticket API
│   └── 📄 ...                      # Other APIs
│
├── 📁 database/
│   ├── 📄 schema.sql               # Database schema & structure
│   ├── 📁 backups/                 # Database backups
│   └── 📄 ...                      # Migration files (future)
│
├── 📁 logs/
│   ├── 📄 php_error.log            # PHP error logs
│   ├── 📄 access.log               # Access logs
│   └── 📄 ...                      # Other logs
│
└── 📁 vendor/                      # Composer packages (future)
```

## 📄 File Descriptions

### Root Directory

| File | Purpose |
|------|---------|
| `index.php` | Homepage with landing page, features, packages, testimonials |
| `README.md` | Project overview, installation, features, requirements |
| `QUICK_START.md` | 5-minute quick start guide |
| `INSTALLATION.md` | Detailed installation instructions |
| `FEATURES.md` | Complete feature list |
| `API.md` | API documentation with examples |
| `PROJECT_STRUCTURE.md` | This file - project structure documentation |
| `.htaccess` | Apache rewrite rules & security headers |
| `.gitignore` | Git ignore patterns |
| `.env.example` | Environment variables template |

### config/ Directory

| File | Purpose |
|------|---------|
| `database.php` | Database connection configuration |

### public/ Directory

| File | Purpose |
|------|---------|
| `css/style.css` | Main stylesheet (responsive design) |
| `js/script.js` | Main JavaScript (interactions, validation) |
| `assets/` | Images, fonts, icons |
| `uploads/` | User file uploads |

### user/ Directory

| File | Purpose |
|------|---------|
| `register.php` | User registration form |
| `login.php` | User login form |
| `dashboard.php` | User main dashboard |
| `manage-hosting.php` | Manage individual hosting accounts |
| `logout.php` | Logout handler |

### admin/ Directory

| File | Purpose |
|------|---------|
| `index.php` | Admin dashboard with statistics |
| `customers.php` | Manage customers & their status |
| `packages.php` | Create/edit hosting packages |
| `servers.php` | Manage hosting servers |
| `phpmyadmin-setup.php` | Database management interface |
| `logout.php` | Admin logout handler |

### api/ Directory

| File | Purpose |
|------|---------|
| `auth.php` | Login, logout, register API |
| `hosting.php` | Hosting account management API |
| `files.php` | File manager operations API |
| `packages.php` | Package listing & details API |
| `orders.php` | Order & invoice management API |
| `tickets.php` | Support ticket API |

### database/ Directory

| File | Purpose |
|------|---------|
| `schema.sql` | Complete database schema & initial data |
| `backups/` | Database backup files |

### logs/ Directory

| File | Purpose |
|------|---------|
| `php_error.log` | PHP errors & warnings |
| `access.log` | Web server access logs |

## 📊 Database Tables

```sql
-- User Management
users              # Customer & admin accounts
user_sessions      # Session data (future)

-- Hosting Services
packages           # Hosting packages
hosting_accounts   # Customer hosting accounts
servers            # Hosting servers
databases          # MySQL/PostgreSQL databases

-- Business
orders             # Customer orders
invoices           # Invoice records
support_tickets    # Support ticket system
ticket_replies     # Ticket response tracking

-- Infrastructure
backups            # Automated backups
activity_logs      # Audit trail
settings           # System configuration

-- Monitoring (future)
performance_logs   # System performance
error_logs         # Error tracking
```

## 🔄 Request Flow

### User Registration Flow
```
user/register.php 
    → validation 
    → database INSERT 
    → redirect login
```

### User Login Flow
```
user/login.php 
    → credential check 
    → session start 
    → role-based redirect
```

### Hosting Account Creation Flow
```
user/dashboard.php 
    → new hosting form 
    → API request 
    → order creation 
    → account provisioning
```

### Admin Dashboard Flow
```
admin/index.php 
    → role verification 
    → statistics query 
    → recent orders fetch 
    → display dashboard
```

## 🔐 Security Layers

### Layer 1: Input Validation
```
User Input → Validation → Sanitization → Database
```

### Layer 2: Authentication
```
Login Check → Session Verification → Role Validation
```

### Layer 3: Authorization
```
Permission Check → Function Access → Data Access
```

### Layer 4: Encryption
```
Password Hash → bcrypt → Database Storage
```

## 📱 Responsive Design Structure

### CSS Breakpoints
```
Mobile:   < 768px
Tablet:   768px - 1199px
Desktop:  1200px+
```

### Layout Components
```
Header (Navigation)
  ├── Logo
  ├── Menu Links
  └── Auth Buttons

Main Content
  ├── Sidebar (Dashboard)
  └── Content Area

Footer
  ├── Links
  ├── Company Info
  └── Copyright
```

## 🔌 API Structure

### Authentication Endpoints
```
POST /api/auth.php
  - login
  - logout
  - register
```

### Resource Endpoints
```
GET    /api/hosting.php?action=list      # Get all
GET    /api/hosting.php?action=get&id=1  # Get one
POST   /api/hosting.php                  # Create
PUT    /api/hosting.php                  # Update
DELETE /api/hosting.php                  # Delete
```

## 📈 Data Flow Diagram

```
┌─────────────────┐
│   User Browser  │
└────────┬────────┘
         │ HTTP Request
         ↓
┌─────────────────┐
│ Web Server      │
│ (Apache/Nginx)  │
└────────┬────────┘
         │ PHP Script
         ↓
┌─────────────────┐
│ PHP Application │
│ (index.php)     │
└────────┬────────┘
         │ Include
         ↓
┌─────────────────┐     ┌──────────────┐
│ Config Files    │────→│ Database     │
│ (database.php)  │     │ (MySQL)      │
└────────┬────────┘     └──────────────┘
         │
         ↓
┌─────────────────┐
│ HTML Response   │
└────────┬────────┘
         │ HTTP Response
         ↓
┌─────────────────┐
│ User Browser    │
│ (Rendered Page) │
└─────────────────┘
```

## 🛠️ Development Workflow

### 1. Local Development
```
Code → Test → Commit → Push
```

### 2. Database Changes
```
schema.sql → import → test → commit
```

### 3. Feature Addition
```
feature branch → code → test → PR → merge
```

## 📦 Dependencies

### Runtime
```
PHP 8.1+
MySQL 8.0+ / PostgreSQL 12+
Apache 2.4+ / Nginx 1.20+
```

### Optional
```
Composer (for future packages)
Node.js (for future build tools)
Git (for version control)
Docker (for containerization)
```

## 🎯 Key Architecture Points

### 1. Separation of Concerns
```
- config/: Configuration
- public/: Static assets
- user/: User-facing pages
- admin/: Admin-facing pages
- api/: API endpoints
- database/: Schema & backups
```

### 2. Database Normalization
```
- No duplicate data
- Proper foreign keys
- Indexed queries
- Transaction support
```

### 3. Security Principles
```
- Input validation
- Output escaping
- Password hashing
- SQL injection prevention
- XSS protection
```

### 4. Performance Optimization
```
- Indexed queries
- Caching ready
- Async operations ready
- Asset compression ready
```

## 🔄 Version Control

### Branch Strategy
```
main         ← Production ready
develop      ← Development branch
feature/*    ← Feature branches
hotfix/*     ← Bug fixes
```

### Commit Convention
```
feat: Add new feature
fix: Fix bug
docs: Documentation
style: Code style
refactor: Code refactor
test: Add tests
chore: Maintenance
```

## 📊 File Statistics

```
Total Files:      20+
Total Directories: 10+
Lines of Code:    5000+
Database Tables:  15+
API Endpoints:    20+
Pages:            15+
```

## 🎓 Learning Resources

### For Beginners
```
1. QUICK_START.md  - Start here
2. README.md       - Overview
3. index.php       - Simple page
```

### For Intermediate
```
1. INSTALLATION.md - Setup details
2. admin/index.php - Dashboard
3. database/schema.sql - DB structure
```

### For Advanced
```
1. API.md          - API details
2. FEATURES.md     - Feature deep dive
3. config/database.php - Config details
```

---

**Project Structure Documentation Version:** 1.0  
**Last Updated:** 2024  
**Status:** Complete & Documented

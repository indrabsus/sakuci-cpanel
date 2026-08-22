# Sakuci Hosting API Documentation

API documentation untuk integrasi dengan Sakuci Hosting Platform.

## 🔐 Authentication

Semua API requests harus menginclude session atau API token.

### Session-Based Auth
```php
// Login dulu via /user/login.php
// Session akan otomatis di-maintain
```

### API Key Auth (Future Implementation)
```php
// Header
Authorization: Bearer YOUR_API_KEY
```

## 📡 API Endpoints

### Authentication APIs

#### 1. User Login
```
POST /api/auth.php
Content-Type: application/json

{
    "action": "login",
    "username": "user@example.com",
    "password": "password123"
}

Response:
{
    "success": true,
    "user_id": 1,
    "username": "user123",
    "role": "user"
}
```

#### 2. User Logout
```
POST /api/auth.php
Content-Type: application/json

{
    "action": "logout"
}

Response:
{
    "success": true,
    "message": "Logged out successfully"
}
```

#### 3. User Register
```
POST /api/auth.php
Content-Type: application/json

{
    "action": "register",
    "username": "newuser",
    "email": "user@example.com",
    "password": "password123",
    "full_name": "John Doe"
}

Response:
{
    "success": true,
    "user_id": 2,
    "message": "User registered successfully"
}
```

### Hosting Account APIs

#### 1. Get User Hosting Accounts
```
GET /api/hosting.php?action=list&user_id=1

Response:
{
    "success": true,
    "accounts": [
        {
            "id": 1,
            "user_id": 1,
            "domain": "example.com",
            "package_id": 1,
            "status": "active",
            "registration_date": "2024-01-15",
            "expiry_date": "2025-01-15"
        }
    ]
}
```

#### 2. Get Single Hosting Account
```
GET /api/hosting.php?action=get&id=1

Response:
{
    "success": true,
    "account": {
        "id": 1,
        "domain": "example.com",
        "package_name": "Professional",
        "disk_space": 5120,
        "bandwidth": 200,
        "status": "active",
        "disk_used": 1024,
        "bandwidth_used": 45
    }
}
```

#### 3. Create Hosting Account
```
POST /api/hosting.php
Content-Type: application/json

{
    "action": "create",
    "user_id": 1,
    "package_id": 2,
    "domain": "mydomain.com"
}

Response:
{
    "success": true,
    "account_id": 2,
    "username": "mydomain",
    "control_panel_password": "generated_password",
    "message": "Hosting account created successfully"
}
```

#### 4. Update Hosting Account
```
PUT /api/hosting.php
Content-Type: application/json

{
    "action": "update",
    "id": 1,
    "status": "active",
    "auto_renewal": true
}

Response:
{
    "success": true,
    "message": "Account updated successfully"
}
```

#### 5. Delete/Terminate Hosting Account
```
DELETE /api/hosting.php
Content-Type: application/json

{
    "action": "delete",
    "id": 1
}

Response:
{
    "success": true,
    "message": "Account terminated successfully"
}
```

### Database APIs

#### 1. Get Account Databases
```
GET /api/hosting.php?action=databases&hosting_account_id=1

Response:
{
    "success": true,
    "databases": [
        {
            "id": 1,
            "db_name": "myapp_db",
            "db_user": "myapp_user",
            "db_type": "mysql",
            "created_at": "2024-01-15"
        }
    ]
}
```

#### 2. Create Database
```
POST /api/hosting.php
Content-Type: application/json

{
    "action": "create_database",
    "hosting_account_id": 1,
    "db_name": "myapp_db",
    "db_type": "mysql"
}

Response:
{
    "success": true,
    "database": {
        "id": 1,
        "db_name": "myapp_db",
        "db_user": "myapp_user",
        "db_password": "generated_password"
    }
}
```

### File Manager APIs

#### 1. List Files
```
GET /api/files.php?action=list&account_id=1&path=/public_html

Response:
{
    "success": true,
    "files": [
        {
            "name": "index.php",
            "type": "file",
            "size": 2048,
            "modified": "2024-01-15 10:30:00"
        }
    ]
}
```

#### 2. Upload File
```
POST /api/files.php
Content-Type: multipart/form-data

FormData:
- action: upload
- account_id: 1
- path: /public_html
- file: [binary file]

Response:
{
    "success": true,
    "file": {
        "name": "image.png",
        "size": 102400,
        "path": "/public_html/image.png"
    }
}
```

#### 3. Delete File
```
DELETE /api/files.php
Content-Type: application/json

{
    "action": "delete",
    "account_id": 1,
    "path": "/public_html/oldfile.php"
}

Response:
{
    "success": true,
    "message": "File deleted successfully"
}
```

### Package APIs

#### 1. Get All Packages
```
GET /api/packages.php?action=list

Response:
{
    "success": true,
    "packages": [
        {
            "id": 1,
            "name": "Starter",
            "disk_space": 1024,
            "bandwidth": 50,
            "databases": 1,
            "price_monthly": 29.99,
            "price_yearly": 299.99
        }
    ]
}
```

#### 2. Get Package Details
```
GET /api/packages.php?action=get&id=2

Response:
{
    "success": true,
    "package": {
        "id": 2,
        "name": "Professional",
        "description": "Untuk bisnis berkembang",
        "disk_space": 5120,
        "bandwidth": 200,
        "databases": 5,
        "email_accounts": 20,
        "ftp_accounts": 10,
        "addon_domains": 5,
        "features": [...]
    }
}
```

### Order & Invoice APIs

#### 1. Get User Orders
```
GET /api/orders.php?action=list&user_id=1

Response:
{
    "success": true,
    "orders": [
        {
            "id": 1,
            "invoice_number": "INV-2024-001",
            "amount": 299.99,
            "status": "completed",
            "order_date": "2024-01-15",
            "package": "Professional"
        }
    ]
}
```

#### 2. Get Invoice
```
GET /api/orders.php?action=invoice&invoice_id=1

Response:
{
    "success": true,
    "invoice": {
        "invoice_number": "INV-2024-001",
        "order_id": 1,
        "amount": 299.99,
        "tax": 30,
        "total": 329.99,
        "issue_date": "2024-01-15",
        "due_date": "2024-02-15"
    }
}
```

### Support Ticket APIs

#### 1. Create Ticket
```
POST /api/tickets.php
Content-Type: application/json

{
    "action": "create",
    "user_id": 1,
    "subject": "Cannot login to control panel",
    "description": "I'm unable to access my control panel",
    "category": "technical",
    "priority": "high"
}

Response:
{
    "success": true,
    "ticket_id": 5,
    "ticket_number": "TICK-5",
    "status": "open"
}
```

#### 2. Get User Tickets
```
GET /api/tickets.php?action=list&user_id=1

Response:
{
    "success": true,
    "tickets": [
        {
            "id": 5,
            "ticket_number": "TICK-5",
            "subject": "Cannot login",
            "status": "open",
            "priority": "high",
            "created_at": "2024-01-15"
        }
    ]
}
```

#### 3. Reply to Ticket
```
POST /api/tickets.php
Content-Type: application/json

{
    "action": "reply",
    "ticket_id": 5,
    "message": "Please check your email for password reset link"
}

Response:
{
    "success": true,
    "reply_id": 1,
    "message": "Reply added successfully"
}
```

## 🔄 Response Format

### Success Response
```json
{
    "success": true,
    "data": {...},
    "message": "Operation successful"
}
```

### Error Response
```json
{
    "success": false,
    "error": "error_code",
    "message": "Human readable error message"
}
```

## ⏱️ Rate Limiting

- **Limit:** 1000 requests per hour
- **Per IP:** 100 requests per minute
- **Header:** `X-RateLimit-Remaining`

## 🛡️ Security Best Practices

1. **Always use HTTPS** for API calls
2. **Validate input** on both client & server
3. **Use CSRF tokens** for state-changing operations
4. **Never expose** sensitive data in URLs
5. **Implement timeout** for long operations
6. **Log all API** access for audit trail

## 📋 Error Codes

```
200 - OK
201 - Created
400 - Bad Request
401 - Unauthorized
403 - Forbidden
404 - Not Found
409 - Conflict
422 - Unprocessable Entity
500 - Internal Server Error
503 - Service Unavailable
```

## 🧪 Testing API

### Using cURL
```bash
# List packages
curl -X GET "http://localhost/hosting/api/packages.php?action=list"

# Create account
curl -X POST "http://localhost/hosting/api/hosting.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "user_id": 1,
    "package_id": 2,
    "domain": "test.com"
  }'
```

### Using Postman
1. Import API collection
2. Set base URL
3. Configure authentication
4. Run requests

### Using JavaScript/Fetch
```javascript
// Get packages
fetch('/hosting/api/packages.php?action=list')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));

// Create order
fetch('/hosting/api/orders.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    action: 'create',
    user_id: 1,
    package_id: 2
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## 📚 Webhook Events (Future)

```
Potential webhook events:
- order.created
- order.completed
- account.active
- account.expired
- account.terminated
- payment.received
- ticket.created
- ticket.closed
```

---

**API Version:** 1.0  
**Last Updated:** 2024  
**Status:** Active Development

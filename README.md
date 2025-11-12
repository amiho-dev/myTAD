# myTAD - Secure Login System

A comprehensive, production-ready authentication and authorization system for PHP applications with advanced security features.

## Features

### Core Authentication
- ✅ Secure password hashing with bcrypt (cost: 12)
- ✅ Session management with database-backed tokens
- ✅ "Remember Me" functionality with secure cookies
- ✅ Email verification system
- ✅ Secure password reset flow

### Security Features
- ✅ Rate limiting & brute force protection
- ✅ Account lockout after failed attempts
- ✅ IP-based device tracking
- ✅ Two-Factor Authentication (2FA/TOTP)
- ✅ Backup codes for account recovery
- ✅ Comprehensive audit logging
- ✅ CSRF token protection
- ✅ Session fixation prevention
- ✅ Secure HTTP headers

### Admin Features
- ✅ User management (enable/disable)
- ✅ Account locking/unlocking
- ✅ Audit log viewing
- ✅ User activity tracking
- ✅ Session management

## Installation

### 1. Database Setup

Update `php/db-config.php` with your database credentials:

```php
define('DB_HOST', 'your-db-host:3306');
define('DB_USER', 'your-db-user');
define('DB_PASS', 'your-db-password');
define('DB_NAME', 'your-database');
```

Then initialize the database:

```bash
curl "https://yourdomain.com/php/db-config.php?action=init"
```

Or access it through your browser.

### 2. PHP Requirements

- PHP 7.4+
- MySQLi extension
- OpenSSL extension (for encryption)

### 3. Configuration

Create a `.env` file (optional, for additional configuration):

```env
# Email Configuration
MAIL_FROM=security@yourdomain.com
MAIL_SMTP_HOST=your-smtp-host
MAIL_SMTP_PORT=587
MAIL_SMTP_USER=your-smtp-user
MAIL_SMTP_PASS=your-smtp-password

# Security
SESSION_TIMEOUT=24 hours
REMEMBER_ME_DURATION=30 days
MAX_LOGIN_ATTEMPTS=5
ACCOUNT_LOCK_DURATION=30 minutes
```

## API Endpoints

### Authentication

#### Register User
```
POST /php/register.php
Content-Type: application/json

{
  "username": "john_doe",
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

#### Login
```
POST /php/login.php
Content-Type: application/json

{
  "username": "john_doe",
  "password": "SecurePassword123!",
  "remember_me": true
}
```

**Response:**
```json
{
  "success": true,
  "requires_2fa": false,
  "token": "abc123...",
  "user_id": 1,
  "username": "john_doe",
  "email": "john@example.com"
}
```

#### Verify 2FA
```
POST /php/verify-2fa.php
Authorization: Bearer <session_token>
Content-Type: application/json

{
  "code": "123456",
  "backup_code": "ABC12345"
}
```

### Password Management

#### Forgot Password
```
POST /php/forgot-password.php
Content-Type: application/json

{
  "email": "john@example.com"
}
```

#### Reset Password
```
POST /php/reset-password-confirm.php
Content-Type: application/json

{
  "token": "reset_token_from_email",
  "password": "NewPassword123!"
}
```

### Session Management

#### Get Current Session
```
GET /php/session-handler.php?action=get
Authorization: Bearer <token>
```

#### Refresh Token
```
POST /php/session-handler.php?action=refresh
Authorization: Bearer <token>
```

#### List User Sessions
```
GET /php/session-handler.php?action=list-sessions
Authorization: Bearer <token>
```

#### Terminate Session
```
POST /php/session-handler.php?action=terminate-session
Authorization: Bearer <token>
Content-Type: application/json

{
  "token": "session_token_to_terminate"
}
```

#### Logout
```
POST /php/session-handler.php?action=logout
Authorization: Bearer <token>
```

### Two-Factor Authentication

#### Setup 2FA
```
GET /php/setup-2fa.php
Authorization: Bearer <token>
```

**Response includes:**
- `secret`: The TOTP secret
- `qr_code_url`: URL to scan with authenticator app
- `backup_codes`: Emergency backup codes

#### Enable 2FA
```
POST /php/setup-2fa.php
Authorization: Bearer <token>
Content-Type: application/json

{
  "code": "123456",
  "secret": "secret_from_setup"
}
```

### Admin Operations

#### View Audit Log
```
GET /php/admin-audit-log.php?user_id=1&action=LOGIN&limit=50
Authorization: Bearer <admin_token>
```

#### Manage User
```
POST /php/admin-user-manage.php
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "action": "disable|enable|lock|unlock",
  "user_id": 123
}
```

## Security Best Practices

### Password Requirements

Passwords must meet these requirements:
- Minimum 10 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (!@#$%^&*(),.?":{}|<>)

### Rate Limiting

- **Login attempts**: 5 failures per 15 minutes
- **Password reset**: 3 requests per hour
- **2FA setup**: 10 attempts per hour

### Account Security

- Accounts lock for 30 minutes after 5 failed login attempts
- Sessions expire after 24 hours of inactivity
- "Remember Me" cookies expire after 30 days
- All password resets invalidate existing sessions
- New devices trigger email notifications

### Data Protection

- All passwords hashed with bcrypt (cost: 12)
- Secure session tokens (32 bytes of random data)
- HttpOnly cookies prevent XSS attacks
- CSRF tokens for state-changing operations
- SQL injection prevention with prepared statements

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_email_verified TINYINT(1) DEFAULT 0,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(255),
    failed_login_attempts INT DEFAULT 0,
    account_locked_until TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    last_password_change TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Sessions Table
```sql
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    last_activity TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Login Attempts Table
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    username VARCHAR(255),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,
    reason VARCHAR(255)
);
```

### Audit Log Table
```sql
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## Deployment

### Production Checklist

- [ ] Update all database credentials
- [ ] Enable HTTPS/SSL
- [ ] Configure secure CORS headers
- [ ] Set up proper email service for notifications
- [ ] Enable HTTP security headers
- [ ] Regular database backups
- [ ] Monitor audit logs for suspicious activity
- [ ] Keep PHP and dependencies updated
- [ ] Use environment variables for sensitive data
- [ ] Implement rate limiting at firewall level

### HTTP Security Headers

Add to your web server configuration:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
Referrer-Policy: strict-origin-when-cross-origin
```

## Two-Factor Authentication Setup

1. User clicks "Enable 2FA"
2. System generates secret and QR code
3. User scans QR code with authenticator app (Google Authenticator, Authy, etc.)
4. User enters 6-digit code to verify
5. System generates 10 backup codes
6. 2FA is now enabled

### Backup Codes

- Can be used instead of TOTP code
- Each code can only be used once
- User should store securely
- Displayed only once during setup

## Troubleshooting

### "Session expired" error
- Sessions expire after 24 hours
- Use `/php/session-handler.php?action=refresh` to get a new token
- Check that Authorization header includes "Bearer " prefix

### "Too many login attempts"
- Account is rate-limited for 15 minutes
- Check IP address is correct
- Try again after waiting

### "2FA not working"
- Ensure device time is synchronized
- TOTP codes are valid for 30 seconds
- Use backup codes as fallback

## Support & Security Issues

For security vulnerabilities, please email: security@yourdomain.com

For general support: support@yourdomain.com

## License

This project is licensed under the MIT License - see LICENSE file for details.

## Changelog

### Version 1.0.0 (November 2025)
- Initial release
- Complete authentication system
- Two-factor authentication
- Audit logging
- Admin features

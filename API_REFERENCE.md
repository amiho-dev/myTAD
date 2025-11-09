# API Reference

## Base URL
```
https://yourdomain.com/php
```

## Authentication

All protected endpoints require one of:
1. **Authorization Header**: `Authorization: Bearer {token}`
2. **Cookie**: `mytad_session={token}`

## Response Format

All responses are JSON with the following structure:

**Success:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

**Error:**
```json
{
  "error": "Error message",
  "details": [] // Optional
}
```

## HTTP Status Codes
- `200`: Success
- `400`: Bad Request (validation error)
- `401`: Unauthorized (authentication required)
- `403`: Forbidden (authorization failed)
- `404`: Not Found
- `405`: Method Not Allowed
- `429`: Too Many Requests (rate limited)
- `500`: Server Error

---

## Authentication Endpoints

### POST /register.php
Create a new user account.

**Request:**
```json
{
  "username": "john_doe",
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Account created successfully",
  "user_id": 1,
  "email_verification_token": "abc123..."
}
```

**Errors:**
- `400`: Username/email already exists, invalid password
- `400`: Email not valid
- `400`: Username too short/long

---

### POST /login.php
Authenticate user and create session.

**Request:**
```json
{
  "username": "john_doe",
  "password": "SecurePass123!",
  "remember_me": false
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "requires_2fa": false,
  "user_id": 1,
  "username": "john_doe",
  "email": "john@example.com",
  "token": "abc123...",
  "is_email_verified": true
}
```

**Response (200 - 2FA Required):**
```json
{
  "success": true,
  "requires_2fa": true,
  "message": "Please complete two-factor authentication"
}
```

**Errors:**
- `401`: Invalid username or password
- `403`: Account disabled
- `429`: Too many login attempts
- `429`: Account locked

---

### POST /verify-2fa.php
Verify two-factor authentication code during login.

**Required Authentication:** Pending 2FA session

**Request:**
```json
{
  "code": "123456"
}
```

Or with backup code:
```json
{
  "backup_code": "ABC12345"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "user_id": 1,
  "username": "john_doe",
  "email": "john@example.com",
  "token": "abc123..."
}
```

**Errors:**
- `401`: Invalid authentication code
- `401`: No pending 2FA verification

---

## Password Management Endpoints

### POST /forgot-password.php
Request password reset token.

**Request:**
```json
{
  "email": "john@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "If an account exists with this email, you will receive a password reset link."
}
```

**Note:** Always returns success for security (doesn't reveal if email exists).

**Errors:**
- `429`: Too many reset requests

---

### POST /reset-password-confirm.php
Verify reset token and set new password.

**Request:**
```json
{
  "token": "reset_token_from_email",
  "password": "NewPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password reset successfully. Please log in with your new password."
}
```

**Errors:**
- `400`: Invalid or expired token
- `400`: Password not strong enough

---

## Session Management Endpoints

### GET /session-handler.php?action=get
Get current session information.

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "user_id": 1,
  "username": "john_doe",
  "email": "john@example.com",
  "is_email_verified": true
}
```

**Errors:**
- `401`: Invalid or expired session

---

### POST /session-handler.php?action=refresh
Refresh session token to extend expiration.

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "token": "new_token_abc123...",
  "message": "Token refreshed"
}
```

**Errors:**
- `401`: Invalid session

---

### GET /session-handler.php?action=list-sessions
List all active sessions for current user.

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "sessions": [
    {
      "token": "abc123...",
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2025-11-09 10:30:00",
      "last_activity": "2025-11-09 11:00:00",
      "expires_at": "2025-11-10 10:30:00"
    }
  ]
}
```

---

### POST /session-handler.php?action=terminate-session
Terminate a specific session.

**Required Authentication:** Session token

**Request:**
```json
{
  "token": "session_token_to_terminate"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Session terminated"
}
```

**Errors:**
- `403`: Cannot terminate other user's sessions

---

### POST /session-handler.php?action=logout
Logout and invalidate current session.

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## Two-Factor Authentication Endpoints

### GET /setup-2fa.php
Get 2FA setup information (QR code, secret, backup codes).

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "secret": "ABCDEF...",
  "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/...",
  "provisioning_uri": "otpauth://totp/...",
  "backup_codes": [
    "ABC12345",
    "DEF67890",
    ...
  ],
  "message": "Scan the QR code with your authenticator app"
}
```

---

### POST /setup-2fa.php
Enable 2FA after verifying code.

**Required Authentication:** Session token

**Request:**
```json
{
  "code": "123456",
  "secret": "ABCDEF..."
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Two-factor authentication has been enabled"
}
```

**Errors:**
- `400`: Invalid authentication code

---

### POST /check-auth.php
Verify if user is authenticated.

**Required Authentication:** Session token (optional)

**Request:** (optional)
```json
{
  "token": "session_token"
}
```

**Response (200 - Authenticated):**
```json
{
  "authenticated": true,
  "user_id": 1,
  "username": "john_doe"
}
```

**Response (200 - Not Authenticated):**
```json
{
  "authenticated": false
}
```

---

## Admin Endpoints

### GET /admin-audit-log.php
View audit log (requires admin privileges).

**Required Authentication:** Admin session token

**Query Parameters:**
- `user_id` (optional): Filter by user ID
- `action` (optional): Filter by action type
- `limit` (optional): Number of results (default 100, max 1000)
- `offset` (optional): Pagination offset (default 0)

**Request:**
```
GET /admin-audit-log.php?user_id=1&action=LOGIN&limit=50&offset=0
```

**Response (200):**
```json
{
  "success": true,
  "logs": [
    {
      "id": 1,
      "user_id": 1,
      "username": "john_doe",
      "action": "LOGIN",
      "description": "User login",
      "ip_address": "192.168.1.1",
      "created_at": "2025-11-09 10:30:00"
    }
  ],
  "limit": 50,
  "offset": 0,
  "count": 10
}
```

---

### POST /admin-user-manage.php
Manage user account (enable/disable/lock/unlock).

**Required Authentication:** Admin session token

**Request:**
```json
{
  "action": "disable|enable|lock|unlock",
  "user_id": 123
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "User disabled successfully",
  "action": "disable",
  "user_id": 123,
  "username": "john_doe"
}
```

**Errors:**
- `403`: Cannot manage your own account
- `404`: User not found

---

### GET /admin-get-users.php
List all users (requires admin privileges).

**Required Authentication:** Admin session token

**Query Parameters:**
- `limit` (optional): Number of results (default 50, max 500)
- `offset` (optional): Pagination offset (default 0)
- `search` (optional): Search by username or email

**Response (200):**
```json
{
  "success": true,
  "users": [
    {
      "id": 1,
      "username": "john_doe",
      "email": "john@example.com",
      "is_active": true,
      "is_email_verified": true,
      "two_factor_enabled": false,
      "created_at": "2025-11-01 09:00:00",
      "last_login": "2025-11-09 10:30:00"
    }
  ],
  "total": 1,
  "limit": 50,
  "offset": 0
}
```

---

## Account Management Endpoints

### POST /update-password.php
Change user password.

**Required Authentication:** Session token

**Request:**
```json
{
  "current_password": "OldPassword123!",
  "new_password": "NewPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password updated successfully"
}
```

**Errors:**
- `401`: Current password incorrect
- `400`: New password not strong enough

---

### POST /update-email.php
Change user email address.

**Required Authentication:** Session token

**Request:**
```json
{
  "new_email": "newemail@example.com",
  "password": "CurrentPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Email updated successfully"
}
```

**Errors:**
- `400`: Email already in use
- `401`: Password incorrect

---

### POST /delete-account.php
Delete user account permanently.

**Required Authentication:** Session token

**Request:**
```json
{
  "password": "ConfirmPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Account deleted successfully"
}
```

**Errors:**
- `401`: Password incorrect

---

### GET /get-account-stats.php
Get user account statistics.

**Required Authentication:** Session token

**Response (200):**
```json
{
  "success": true,
  "stats": {
    "user_id": 1,
    "username": "john_doe",
    "email": "john@example.com",
    "account_age_days": 8,
    "total_logins": 15,
    "last_login": "2025-11-09 10:30:00",
    "active_sessions": 2,
    "two_factor_enabled": false,
    "password_last_changed": "2025-11-01 09:00:00"
  }
}
```

---

## Error Codes & Messages

### Authentication Errors
- `Invalid username or password`: Incorrect credentials
- `Account has been disabled`: User account is disabled
- `Account is temporarily locked`: Too many failed attempts
- `No session token found`: Missing authentication
- `Invalid or expired session`: Session expired or invalid

### Validation Errors
- `Missing username or password`: Required field missing
- `Email not valid`: Invalid email format
- `Password is not strong enough`: Password doesn't meet requirements
- `Username already exists`: Username is taken
- `Email already in use`: Email is taken

### Rate Limiting
- `Too many login attempts`: Rate limited (429)
- `Too many password reset requests`: Rate limited (429)
- `Too many 2FA attempts`: Rate limited (429)

### Server Errors
- `Database connection error`: Database unavailable (500)
- `Server error`: Unexpected error (500)

---

## Rate Limiting

### Login Attempts
- Limit: 5 failures per 15 minutes per IP
- Status: 429 (Too Many Requests)

### Password Reset
- Limit: 3 requests per hour per IP
- Status: 429 (Too Many Requests)

### 2FA Setup
- Limit: 10 attempts per hour per user
- Status: 429 (Too Many Requests)

### General API
- Recommended: 100 requests per minute per IP
- Implement at firewall/load balancer level

---

## Examples

### JavaScript Fetch

Login:
```javascript
const response = await fetch('/php/login.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    username: 'john_doe',
    password: 'SecurePass123!',
    remember_me: true
  })
});

const data = await response.json();
if (data.success) {
  localStorage.setItem('token', data.token);
}
```

API Request with Token:
```javascript
const response = await fetch('/php/session-handler.php?action=get', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`
  }
});

const data = await response.json();
console.log(data);
```

### cURL

Login:
```bash
curl -X POST https://yourdomain.com/php/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_doe",
    "password": "SecurePass123!"
  }'
```

API Request with Token:
```bash
curl -X GET https://yourdomain.com/php/session-handler.php?action=get \
  -H "Authorization: Bearer abc123..."
```

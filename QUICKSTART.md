# Quick Start Guide - myTAD Secure Login System

## 🚀 5-Minute Setup

### Step 1: Database Configuration
Edit `php/db-config.php`:
```php
define('DB_HOST', 'your-database-host:3306');
define('DB_USER', 'your-username');
define('DB_PASS', 'your-password');
define('DB_NAME', 'your-database');
```

### Step 2: Initialize Database
Visit in your browser:
```
https://yourdomain.com/php/db-config.php?action=init
```

Or with curl:
```bash
curl "https://yourdomain.com/php/db-config.php?action=init"
```

### Step 3: Test Registration
```bash
curl -X POST https://yourdomain.com/php/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "TestPassword123!"
  }'
```

### Step 4: Test Login
```bash
curl -X POST https://yourdomain.com/php/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "TestPassword123!",
    "remember_me": true
  }'
```

Expected response:
```json
{
  "success": true,
  "token": "abc123...",
  "user_id": 1,
  "requires_2fa": false
}
```

### Step 5: Verify Session
```bash
curl https://yourdomain.com/php/session-handler.php?action=get \
  -H "Authorization: Bearer abc123..."
```

---

## 📋 What You Get

### Security Features Enabled
- ✅ Password hashing (bcrypt)
- ✅ Rate limiting (5 attempts / 15 min)
- ✅ Account lockout (30 minutes)
- ✅ Session management
- ✅ Two-factor authentication
- ✅ Audit logging
- ✅ Email notifications
- ✅ Password reset flow

### Endpoints Created
- 8 new authentication endpoints
- 4 admin management endpoints
- 5 password management endpoints
- 6 session management endpoints
- 2 2FA endpoints

### Documentation Included
- `README.md` - Full documentation
- `SECURITY.md` - Security details (17 sections)
- `API_REFERENCE.md` - Complete API guide
- `IMPLEMENTATION_SUMMARY.md` - Overview

---

## 🔑 Key Concepts

### Session Token
Every login creates a unique 32-byte token:
- Stored in database
- Expires after 24 hours
- Tracked per device (IP + user agent)
- Can be refreshed to extend lifetime

### Two-Factor Authentication
Optional 2FA with TOTP:
- User scans QR code with authenticator app
- 10 backup codes generated for recovery
- Login requires 6-digit code
- Each backup code single-use only

### Password Reset
Secure password recovery:
- User requests reset via email
- Email contains 1-hour token link
- User sets new password
- All sessions invalidated immediately
- Notification email sent

### Rate Limiting
Prevents brute force attacks:
- 5 failed login attempts per IP per 15 min
- 3 password resets per IP per hour
- Account locks for 30 minutes
- All attempts logged

---

## 📡 API Quick Reference

### Login
```javascript
const response = await fetch('/php/login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'testuser',
    password: 'TestPassword123!',
    remember_me: true
  })
});

const data = await response.json();
const token = data.token; // Save this!
```

### Authenticated Request
```javascript
const response = await fetch('/php/session-handler.php?action=get', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const user = await response.json();
console.log(user);
```

### Logout
```javascript
const response = await fetch('/php/session-handler.php?action=logout', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### Setup 2FA
```javascript
// Get QR code
const response = await fetch('/php/setup-2fa.php', {
  method: 'GET',
  headers: { 'Authorization': `Bearer ${token}` }
});

const setup = await response.json();
// Show setup.qr_code_url to user
// Store setup.backup_codes somewhere safe
```

### Enable 2FA
```javascript
// After user scans QR code and gets code
const response = await fetch('/php/setup-2fa.php', {
  method: 'POST',
  headers: { 
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    code: '123456',
    secret: 'secret_from_setup'
  })
});
```

### Verify 2FA During Login
```javascript
// After login returns requires_2fa: true
const response = await fetch('/php/verify-2fa.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    code: '123456'  // or backup_code
  })
});

const data = await response.json();
const token = data.token; // Now have valid session
```

---

## 🔐 Security Checklist

Before going to production:

- [ ] Update database credentials
- [ ] Enable HTTPS/SSL
- [ ] Configure email for notifications
- [ ] Set up CORS headers
- [ ] Add security headers to web server
- [ ] Enable database backups
- [ ] Test password reset flow
- [ ] Test 2FA setup and login
- [ ] Verify rate limiting works
- [ ] Check audit logs
- [ ] Monitor failed login attempts
- [ ] Test admin functions

---

## 🐛 Troubleshooting

### "Connection refused" error
- Check database host/port in db-config.php
- Verify MySQL is running
- Check credentials are correct

### "Table doesn't exist"
- Run database initialization
- Check `/php/db-config.php?action=init`
- Verify database name is correct

### "Rate limit exceeded"
- Too many failed login attempts
- Wait 15 minutes or admin unlock
- Check IP address in logs

### "Invalid session" error
- Session expired (24 hours)
- Use `/php/session-handler.php?action=refresh` to extend
- Or login again

### "2FA not working"
- Ensure device time is synced
- TOTP codes valid for 30 seconds only
- Use backup code as fallback
- Contact admin to reset 2FA

---

## 📊 Database Tables

### Main Tables
- `users` - User accounts
- `sessions` - Active sessions
- `login_attempts` - Failed login tracking
- `password_resets` - Reset token storage
- `audit_log` - Activity history
- `two_factor_backup_codes` - 2FA recovery
- `ip_whitelist` - Trusted devices

---

## 🎯 Next Steps

1. **Test all endpoints** - Use provided API examples
2. **Review SECURITY.md** - Understand the security model
3. **Check API_REFERENCE.md** - All endpoints documented
4. **Set up email** - Configure SMTP for notifications
5. **Deploy** - Follow deployment checklist
6. **Monitor** - Review audit logs regularly

---

## 📞 Support

For detailed information:
- See `README.md` for full documentation
- See `SECURITY.md` for security architecture
- See `API_REFERENCE.md` for endpoint documentation
- See `IMPLEMENTATION_SUMMARY.md` for overview

---

## ✨ That's It!

Your myTAD system is ready to use. Start with testing, then deploy to production.

Happy coding! 🚀

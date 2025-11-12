# Security Implementation Guide

## Overview

This document details the security measures implemented in the myTAD authentication system.

## 1. Password Security

### Hashing Algorithm
- **Algorithm**: bcrypt
- **Cost Factor**: 12 (balanced for security and performance)
- **Update Frequency**: Passwords should be rehashed if cost factor increases

### Password Requirements
All new passwords must meet these criteria:
- Minimum 10 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one special character (!@#$%^&*(),.?":{}|<>)

### Password Best Practices
- Never store passwords in plain text
- Always use prepared statements (prevents SQL injection)
- Hash passwords on server-side only
- Force password change after admin reset
- Notify user via email when password is changed

## 2. Session Management

### Session Storage
- All sessions stored in database (`sessions` table)
- Tokens are 32 bytes of cryptographically random data
- Sessions linked to user ID, IP address, and user agent
- Each session has an expiration time

### Session Lifecycle
1. User logs in → Token generated and stored in DB
2. Token sent to client (JavaScript or cookie)
3. Client includes token in Authorization header or cookie
4. Server validates token exists, is active, and not expired
5. Session updates last_activity timestamp
6. Token can be refreshed to extend lifetime

### Session Expiration
- Default timeout: 24 hours
- Inactive session cleanup: Implement periodic cleanup task
- "Remember Me" tokens: 30 days (refresh on each login)
- Password reset invalidates all sessions immediately

### Cookies (HttpOnly)
- `mytad_session`: Session token (HttpOnly, Secure)
- `mytad_user`: Encoded user info (HttpOnly, Secure)
- Not accessible via JavaScript (prevents XSS attacks)
- Secure flag set (HTTPS only)
- SameSite attribute recommended

## 3. Attack Prevention

### Brute Force Protection
**Rate Limiting:**
- Maximum 5 failed login attempts per IP address
- Window: 15 minutes
- After threshold: 429 (Too Many Requests) response
- Recommendation: Implement at firewall level too

**Account Lockout:**
- Automatic lockout after 5 failed attempts
- Duration: 30 minutes
- Failures reset to 0 on successful login
- Admin can manually unlock

### SQL Injection Prevention
- All queries use prepared statements with parameterized bindings
- User input never directly interpolated into SQL
- Type hints for all parameters (s=string, i=integer, etc.)

### Cross-Site Scripting (XSS)
- All user input escaped before output
- Content-Security-Policy headers recommended
- HttpOnly cookies prevent JavaScript access
- Use json_encode() for JSON responses

### Cross-Site Request Forgery (CSRF)
- CSRF tokens generated per session
- Can be validated for state-changing operations
- Implement token in HTML forms when needed

### Session Fixation
- New session token generated on login
- Old sessions invalidated on logout
- Session data regenerated after password change

## 4. Two-Factor Authentication

### TOTP (Time-based One-Time Password)
- Based on HMAC-based One-Time Password (HOTP) standard
- Time window: 30 seconds
- Compatible with: Google Authenticator, Authy, Microsoft Authenticator

### Backup Codes
- 10 codes generated per user
- Each code single-use only
- Codes hashed before storage
- Can be used if phone/authenticator unavailable

### 2FA Verification Flow
1. User logs in successfully
2. Session marked as "pending 2FA"
3. User enters 6-digit code or backup code
4. Code verified against secret
5. Full session created after verification

## 5. Data Protection

### Database Security
- All sensitive data: passwords, tokens, secrets
- Encrypted at rest (configure database encryption)
- Backups encrypted and stored securely
- Access limited to application

### Encryption in Transit
- HTTPS/TLS 1.2+ required
- Secure cookies flag set
- HSTS header recommended

### Sensitive Data Handling
- Never log passwords or secrets
- Sanitize error messages (don't reveal user existence)
- Audit log contains: user_id, action, IP, user agent, timestamp
- Delete old audit logs regularly (compliance requirement)

## 6. Audit Logging

### Logged Events
- `LOGIN`: Successful login
- `LOGOUT`: User logout
- `LOGIN_ERROR`: Failed login with reason
- `PASSWORD_RESET`: Password changed
- `PASSWORD_RESET_REQUESTED`: Reset token requested
- `ACCOUNT_LOCKED`: Account locked (failed attempts)
- `2FA_ENABLED`: Two-factor authentication enabled
- `2FA_VERIFIED`: Successful 2FA verification
- `SESSION_CREATED`: New session started
- `DEVICE_LOGIN`: Login from new device

### Audit Trail Contents
- User ID (if applicable)
- Action type
- Description
- IP address (with proxy handling)
- User agent (browser/device info)
- Timestamp

### Access Control
- Audit logs readable by admins only
- Cannot be modified or deleted (append-only)
- Regular backups for compliance

## 7. IP Address Tracking

### Client IP Detection
Checks in order:
1. `HTTP_CF_CONNECTING_IP` (Cloudflare)
2. `HTTP_X_FORWARDED_FOR` (proxies)
3. `HTTP_X_FORWARDED` (proxies)
4. `HTTP_FORWARDED_FOR` (standard)
5. `REMOTE_ADDR` (direct connection)

### Device Recognition
- IP address + user agent combination
- New device alerts sent via email
- IP whitelist can be maintained per user
- Admin notifications for suspicious locations

## 8. Email Security

### Authentication Notifications
- Login from new device triggers email
- Password reset email sent with time-limited link
- Account verification email for new accounts
- Email includes: timestamp, IP, device info

### Email Best Practices
- Use authenticated SMTP (credentials required)
- Enable DKIM/SPF/DMARC records
- Rate limit password resets (3 per hour)
- Rate limit registration from same IP
- Include unsubscribe/manage preferences

## 9. API Security

### Authorization
- All protected endpoints require valid session token
- Token in Authorization header: `Bearer {token}`
- Or in session/cookie (if using sessions)
- Token validated on every request

### CORS Configuration
```
Access-Control-Allow-Origin: https://yourdomain.com
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Credentials: true
```

### Error Responses
- Generic messages to prevent information disclosure
- Specific errors logged server-side
- No stack traces in production responses
- HTTP status codes properly set

## 10. Admin Security

### Admin Operations
- Require authentication + authorization
- All actions logged with admin user ID
- Cannot manage own account (prevent privilege escalation)
- Audit trail of all admin actions

### Admin Tasks
- User enable/disable
- Account unlock
- Password reset
- View audit logs
- View active sessions
- Terminate sessions

## 11. Configuration Security

### Environment Variables
- Database credentials in environment variables (not code)
- Email credentials in environment
- Session timeout in configuration
- Rate limiting thresholds in configuration

### .env File
- Should not be committed to git
- Listed in .gitignore
- Copied from .env.example for deployment
- Permissions: 600 (read/write owner only)

### Configuration Management
- Production config differs from development
- Sensitive values not in logs
- Configuration backups encrypted
- Version control excludes sensitive files

## 12. Deployment Security

### Server Hardening
- Disable unnecessary PHP functions
- Disable directory listing
- Set proper file permissions
- Remove development files

### HTTPS/TLS
- TLS 1.2 minimum
- Valid SSL certificate
- HSTS header (Strict-Transport-Security)
- Redirect HTTP to HTTPS

### Database
- Restricted database user (not root)
- Separate user per application
- Read-only users for reporting
- Connection SSL if over network

### Rate Limiting
- Implement at firewall/load balancer level
- Cloudflare WAF rules
- nginx/Apache rate limiting modules

## 13. Monitoring & Maintenance

### Regular Tasks
- Review audit logs weekly
- Check for failed login patterns
- Monitor failed password attempts
- Update security patches
- Verify backups
- Test disaster recovery

### Security Alerts
- Multiple failed logins (same user/IP)
- Password changes outside normal hours
- Account lockouts
- Admin actions
- Database errors

### Incident Response
1. Identify the issue
2. Review audit logs
3. Notify affected users
4. Reset passwords/sessions if needed
5. Implement preventive measures
6. Document incident

## 14. Compliance

### GDPR
- Users can request data export
- Users can request deletion
- Audit logs retained per policy
- Privacy notice displayed

### PCI DSS
- If handling payment cards: additional security needed
- This system not PCI-compliant by default
- Would need: encryption, tokenization, etc.

### HIPAA
- If handling health information: encryption required
- Audit logs must be retained
- Access controls needed

## 15. Security Headers

Recommended HTTP response headers:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

## 16. Code Security

### Input Validation
- Always validate input type and length
- Use type hints in function signatures
- Whitelist allowed values for enums
- Sanitize file uploads

### Output Encoding
- HTML: htmlspecialchars()
- JSON: json_encode()
- URLs: urlencode()
- SQL: Prepared statements

### Error Handling
- Catch all exceptions
- Log detailed errors server-side
- Return generic messages to client
- Never expose stack traces

## 17. Third-Party Dependencies

### Security Updates
- Monitor for CVEs
- Subscribe to security advisories
- Update promptly
- Test before deploying

### Dependency Management
- Use Composer for PHP packages
- Lock versions in composer.lock
- Regular security audits
- Minimize external dependencies

---

## Security Checklist

- [ ] All passwords use bcrypt hashing
- [ ] Sessions stored in database with expiration
- [ ] Rate limiting implemented and tested
- [ ] HTTPS/TLS enforced
- [ ] Input validation on all endpoints
- [ ] Output encoding for all responses
- [ ] SQL injection prevention (prepared statements)
- [ ] CSRF protection implemented
- [ ] Audit logging comprehensive
- [ ] Error messages don't leak information
- [ ] HttpOnly/Secure cookies set
- [ ] CORS headers configured
- [ ] Admin operations require authorization
- [ ] Password reset tokens expire
- [ ] 2FA backup codes work
- [ ] Session timeout enforced
- [ ] IP address tracking enabled
- [ ] Suspicious activity alerts
- [ ] Regular security reviews
- [ ] Security headers configured

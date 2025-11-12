# 401 Authentication Error - Debugging Guide

## Problem
The admin panel cannot load the protected users list - getting HTTP 401 "Missing or invalid token" error.

## Root Cause Analysis
The 401 error indicates one of these issues:
1. **Authorization header not being sent** - Frontend not including the bearer token
2. **Token not in database** - Login didn't properly save the token to the sessions table
3. **Token extracted incorrectly** - Bearer token format issue or header extraction method
4. **Token expired** - Session token has already expired
5. **Admin check failed** - User not marked as admin in the admins table

## Changes Made

### 1. Enhanced Backend Debugging (manage-ban-exclusions.php)
Added comprehensive error logging to track:
- All HTTP headers received (`HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, etc.)
- Token extraction attempt and success
- Token lookup in database (row count)
- Admin role verification
- Database errors at each step

### 2. Improved Frontend Error Handling (index.html)
- Added token validation check before sending request
- Show exact HTTP status code in error message
- Log full response body for debugging
- Added `testHeaders()` function to diagnose header issues

### 3. Created Test Endpoint (php/test-headers.php)
- Simple endpoint to verify Authorization header is being sent
- Shows all headers received by server
- Helps confirm HTTP_AUTHORIZATION is present
- Can be called from browser console: `testHeaders()`

### 4. CORS Configuration Fix
- Changed from `credentials: 'include'` (which conflicts with CORS wildcard)
- Now uses standard Authorization header without credentials mode
- Added OPTIONS preflight request handling

### 5. CORS Helper (php/cors-helper.php)
- Created reusable CORS setup function
- Properly handles credentials when needed
- Can be applied to all endpoint files

## Testing Steps

### Step 1: Verify Authorization Header is Sent
1. Open browser DevTools (F12)
2. Go to Admin panel
3. Click admin tab to trigger protected users load
4. In console, run: `testHeaders()`
5. Look for `HTTP_AUTHORIZATION` in the response
6. It should contain: `Bearer {token}`

### Step 2: Check Server Error Logs
Run this to see the logging output:
```bash
tail -f /path/to/php/error_log
```

Look for patterns like:
```
=== REQUEST HEADERS ===
Token from HTTP_AUTHORIZATION
TOKEN FOUND: abc123def456...
Token lookup returned 1 rows
```

If you see "Token lookup returned 0 rows", the token isn't in the database.

### Step 3: Verify Token in Database
Connect to your database and check:
```sql
SELECT token, user_id, expires_at FROM sessions 
ORDER BY created_at DESC 
LIMIT 5;
```

- Verify tokens exist for your user
- Check if `expires_at` is in the future
- Compare token format with what's in localStorage

### Step 4: Check Admin Role
```sql
SELECT user_id, role FROM admins 
WHERE user_id = 123; -- Replace 123 with your user ID
```

Verify the admin row exists and has the correct role.

### Step 5: Check Login Token Generation
Look in `php/login.php` around line 284-330 where tokens are created:
- Token should be 32+ bytes (random binary)
- Token should be base64 or hex encoded for transmission
- Expiration should be 24 hours in future

## Key Files Involved

| File | Purpose | Changes |
|------|---------|---------|
| `index.html` | Frontend UI | Added token validation, better error messages, testHeaders() |
| `php/manage-ban-exclusions.php` | API endpoint | Enhanced logging at each step |
| `php/test-headers.php` | Debug endpoint | Created to verify header transmission |
| `php/login.php` | Authentication | No changes (but verify token creation) |
| `php/security.php` | Auth helpers | No changes (but verify functions exist) |

## Authorization Flow Diagram

```
Frontend (index.html)
    ↓
    Token: Get from localStorage.getItem('token')
    ↓
    Fetch GET /manage-ban-exclusions.php
    Headers: Authorization: Bearer {token}
    ↓
Backend (manage-ban-exclusions.php)
    ↓
    Extract Authorization header
    ↓
    Parse Bearer token
    ↓
    Query sessions table: SELECT user_id WHERE token = ? AND expires_at > NOW()
    ↓
    If found: Check if admin role
    If admin: Return protected users list
    ↓
Response: JSON with success/error

```

## Common Issues & Solutions

### Issue: "No bearer token found in header"
**Solution:**
- Check Authorization header is being sent (use testHeaders())
- Verify header format is exactly: `Authorization: Bearer {token}`
- Check if server is stripping headers (some hosting configs do this)

### Issue: "Invalid or expired token"
**Cause:** Token not found in sessions table
**Solution:**
- Check sessions table has rows for current user
- Verify login.php successfully created the token
- Check token expiration time (should be 24 hours from login)

### Issue: "Admin privileges required"
**Cause:** User found but doesn't have admin role
**Solution:**
- Check admins table for your user_id
- Verify user was given admin role in admin panel
- Check if admin role was revoked

### Issue: Still getting 401 after everything above
**Debug Steps:**
1. Check php error_log for backend errors
2. Verify database connection in db-config.php
3. Test token lookup manually: `mysql> SELECT * FROM sessions LIMIT 1;`
4. Check if prepare() failed: "Prepare failed:" in error log
5. Enable PHP error reporting in db-config.php temporarily

## Next Actions

1. **Run testHeaders()** in browser console and share the output
2. **Check server error logs** for the detailed debugging output
3. **Verify token in database** - confirm sessions table has your token
4. **Confirm admin role** - verify you're an admin user
5. **Share findings** and we'll troubleshoot from there

## Notes

- All protected endpoints use the same Authorization header pattern
- Token should persist in localStorage after login
- Token needs to be refreshed daily (24-hour expiration)
- Each admin endpoint checks both token validity AND admin role

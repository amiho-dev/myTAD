# 🔐 Token System Setup Guide

## How the Token System Works

### The Flow
```
1. User logs in with username/password
2. Server creates unique token (32 random bytes)
3. Token stored in database (sessions table)
4. Token sent back to browser in response
5. Browser stores token in localStorage
6. Browser sends token in Authorization header for all requests
7. Server verifies token before allowing access
```

---

## Step 1: Make Sure You're Logged In

### Via UI (Easiest)
1. Open `myTAD.html` or the app
2. You should see a login form
3. Enter your username and password
4. Click "Login"

### What Happens During Login
- Backend: Creates random token, saves to database
- Frontend: Receives token in response
- Frontend: Stores token in `localStorage` with key `'token'`
- You should be redirected to dashboard

---

## Step 2: Verify Token Was Created

### Check 1: In Browser
```javascript
// Open browser console (F12)
// Type this:
localStorage.getItem('token')

// Should return: a long string like:
// "abc123def456ghi789jkl..."
// If empty or null, login failed
```

### Check 2: In Database
```sql
-- Connect to your database and run:
SELECT id, user_id, token, expires_at, created_at 
FROM sessions 
ORDER BY created_at DESC 
LIMIT 5;

-- Should show recent tokens with:
-- - Token: a long hex/base64 string
-- - Expires_at: future date (24 hours from now)
-- - Created_at: just now
```

---

## Step 3: Verify Token in Requests

### Check if Authorization Header is Sent
```javascript
// In browser console, run:
testHeaders()

// Look for "HTTP_AUTHORIZATION" in output
// Should contain: "Bearer {your_token_here}"
// If not found, the header isn't being sent
```

---

## Step 4: Understand Token Structure

### Token Format
```
Authorization: Bearer {token}
                ^^^^^^ This "Bearer" prefix is required
                       {token} is your actual session token
```

### Token in Database
```
sessions table columns:
- user_id: Your user ID
- token: The actual token string (32+ characters)
- expires_at: When the token becomes invalid (24 hours later)
- ip_address: Your IP when token was created
- user_agent: Your browser info
- created_at: When token was created
```

---

## Step 5: Fix "Missing or Invalid Token" Error

### Cause 1: Token Not in localStorage
**Symptom:** 
```javascript
localStorage.getItem('token')  // Returns null or empty
```

**Fix:**
1. Make sure you're logged in
2. Check login was successful (no error message)
3. Try logging in again
4. Check if localStorage is enabled in browser

**To Clear and Start Fresh:**
```javascript
localStorage.clear()  // Clears everything
// Then log in again
```

### Cause 2: Token Not in Database
**Symptom:**
```sql
-- No rows returned for current user
SELECT * FROM sessions WHERE user_id = 1 LIMIT 5;
```

**Fix:**
1. Login again to create new token
2. Check login.php for errors (look at logs)
3. Verify database connection works

### Cause 3: Token Expired
**Symptom:**
```sql
-- shows expires_at in the past
SELECT expires_at FROM sessions WHERE token = 'abc123...';
-- Result: 2025-01-01 12:00:00 (and current time is after this)
```

**Fix:**
- Tokens expire after 24 hours
- Simply log in again to get a new token
- If this keeps happening, check server time (might be wrong)

### Cause 4: Authorization Header Not Sent
**Symptom:**
```javascript
testHeaders()  // doesn't show HTTP_AUTHORIZATION
```

**Fix:**
1. Clear browser cache (Ctrl+Shift+Del)
2. Hard refresh page (Ctrl+Shift+R)
3. Close and reopen browser
4. Try different browser
5. Check if fetch() is working (testHeaders should confirm)

### Cause 5: Wrong Token or Token Mismatch
**Symptom:**
```
Token in localStorage doesn't match token in database
```

**Fix:**
1. Log out completely
2. Clear localStorage: `localStorage.clear()`
3. Clear cookies if needed
4. Log in again
5. Run: `debugToken()` to verify

---

## Step 6: Debug with Complete Information

### Run Full Diagnostic
```javascript
// In browser console:
debugToken()

// This shows:
{
  "token_info": {
    "parsed": "SUCCESS",
    "token_length": 128
  },
  "database_info": {
    "found": true,
    "user_id": 1,
    "username": "admin",
    "is_admin": true,
    "expired": false,
    "expires_in_seconds": 86400
  }
}

// What each means:
// - "parsed": "SUCCESS" → Token format is correct
// - "found": true → Token exists in database
// - "expired": false → Token is still valid
// - "is_admin": true → User has admin role
```

### If any are false/negative, here's what to do:

| Field | Issue | Fix |
|-------|-------|-----|
| `"parsed": "FAILED"` | Token format wrong | Login again |
| `"found": false` | Token not in database | Login again |
| `"expired": true` | Token is too old | Login again |
| `"is_admin": false` | User not admin | Add to admins table |

---

## Step 7: Manual Token Creation (Advanced)

If you need to create a token without login:

### Method 1: Via Database
```sql
-- First, get your user ID:
SELECT id, username FROM users WHERE username = 'admin';
-- Let's say ID is 1

-- Create token:
INSERT INTO sessions (user_id, token, ip_address, user_agent, created_at, expires_at, last_activity)
VALUES (
  1,                                    -- user_id
  'my_test_token_abc123def456',         -- token (can be anything)
  '127.0.0.1',                          -- ip_address
  'Test Browser',                       -- user_agent
  NOW(),                                -- created_at
  DATE_ADD(NOW(), INTERVAL 24 HOUR),   -- expires_at (24 hours from now)
  NOW()                                 -- last_activity
);

-- Now in browser console:
localStorage.setItem('token', 'my_test_token_abc123def456')

-- Try accessing protected endpoints
```

### Method 2: Via Browser Console (if PHP debug mode enabled)
```javascript
// NOT RECOMMENDED for production
// Only if you have a debug endpoint

localStorage.setItem('token', 'your_token_here')
```

---

## Step 8: Token Validation Flow

### What Happens on Each Request
```
1. Browser makes request (GET /php/manage-ban-exclusions.php)
2. Includes header: Authorization: Bearer {token}
3. Server receives request
4. Server extracts token from Authorization header
5. Server queries: SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()
6. If found:
   - Check if user is admin
   - Return data (success)
7. If not found:
   - Return 401 Unauthorized
   - Error: "Missing or invalid token"
```

---

## Step 9: Common Mistakes

### ❌ Mistake 1: Clearing localStorage After Login
```javascript
// DON'T DO THIS after you login:
localStorage.clear()

// Your token is gone now!
// Just log in again instead
```

### ❌ Mistake 2: Trying to Use Old Token
```javascript
// Each login creates a NEW token
// Old tokens in localStorage are discarded
// Don't try to save and reuse old tokens
// Always log in to get fresh token
```

### ❌ Mistake 3: Token in Wrong Format
```javascript
// WRONG:
localStorage.setItem('token', 'Bearer abc123')  // Don't include "Bearer"

// RIGHT:
localStorage.setItem('token', 'abc123')         // Just the token
// Browser adds "Bearer" prefix automatically
```

### ❌ Mistake 4: Checking Token Before Login
```javascript
// If you haven't logged in yet:
localStorage.getItem('token')  // Returns null - normal!

// You need to login first to get a token
```

---

## Step 10: Full Setup Checklist

- [ ] User account created in `users` table
- [ ] User logged in successfully
- [ ] Token appears in localStorage: `localStorage.getItem('token')`
- [ ] Token exists in database: `SELECT * FROM sessions LIMIT 1;`
- [ ] Token not expired: `expires_at > NOW()`
- [ ] User marked as admin (if needed): `SELECT * FROM admins WHERE user_id = X;`
- [ ] Authorization header sent: `testHeaders()` shows it
- [ ] Run `debugToken()` - all green checkmarks
- [ ] Try accessing admin panel
- [ ] Verify protected users list loads

---

## 🆘 Still Getting "Missing or Invalid Token"?

### Quick Diagnostic
```javascript
// Run these commands in browser console:

1. localStorage.getItem('token')
   // If empty → Need to login
   // If has value → Continue to next

2. testHeaders()
   // If shows HTTP_AUTHORIZATION → Header is sent
   // If not shown → Header transmission issue

3. debugToken()
   // Shows complete status
   // Tell me the output if confused
```

### Database Check
```sql
-- Replace 1 with your user ID
SELECT 
  s.token,
  s.user_id,
  s.expires_at,
  s.created_at,
  a.role
FROM sessions s
LEFT JOIN admins a ON s.user_id = a.user_id
WHERE s.user_id = 1
ORDER BY s.created_at DESC
LIMIT 1;

-- Should return:
-- token: Your actual token
-- user_id: 1 (or your ID)
-- expires_at: Future date
-- role: 'administrator' (if needed)
```

---

## 📞 Next Steps

1. **Do you see a token in localStorage?**
   - YES → Run `debugToken()` and share output
   - NO → Log in again and try again

2. **Does the token show in database?**
   - YES → Run `testHeaders()` and check
   - NO → Login might have failed, check error logs

3. **Is Authorization header being sent?**
   - YES → Run `debugToken()` for complete info
   - NO → Browser/network issue, try clearing cache

4. **Share the output of `debugToken()`** and I'll tell you exactly what to fix!

---

## 📚 Related Files

- `php/login.php` - Creates tokens
- `php/debug-token.php` - Debugs tokens
- `php/manage-ban-exclusions.php` - Uses tokens
- `index.html` - Stores tokens in localStorage

---

**Version:** 1.0  
**Status:** Complete Setup Guide  
**Last Updated:** Current Session

# Token System - Complete Visual Guide

## 🎯 What is a Token?

A **token** is a unique code that proves you're logged in.

```
YOU                          SERVER
 │                             │
 │─ Username & Password ───→ [Login]
 │                             │
 │                        [Create Token]
 │                        "abc123def456"
 │                             │
 │ ←─ Token Returned ─────────┤
 │                             │
 [Store in Browser]            │
 localStorage['token'] =        │
 "abc123def456"                │
 │                             │
 │─ Future Request ────────→   │
 │ + Authorization Header:     │
 │   "Bearer abc123def456"     │
 │                             │
 │                        [Verify Token]
 │                        [Check Database]
 │                             │
 │ ←─ Success + Data ─────────┤
 │   (if token valid)          │
 │                             │
 │ ←─ 401 Unauthorized ───────┤
 │   (if token invalid)        │
```

---

## 📊 Token Lifecycle

```
TIME        YOUR APP                DATABASE
────────────────────────────────────────────────────────

[T0] 
 └─→ You click Login          Token created:
     Enter: username, password  - user_id: 1
                                - token: "abc123"
                                - expires_at: T0+24hrs
                                - created_at: T0

[T0+1s]
 └─→ Token stored in          localStorage['token']
     localStorage              = "abc123"

[T0+2s]
 └─→ You click "Admin"        Token sent in header:
     Request protected data    Authorization: Bearer abc123

                              Server validates:
                              SELECT user_id FROM sessions
                              WHERE token = 'abc123'
                              AND expires_at > NOW()
                              → Found! ✓

[T0+24hrs]
 └─→ Token expires            expires_at = NOW()
                              Token invalid!
                              Need to log in again

[T0+24hrs+1s]
 └─→ Try to access admin      Token check fails:
     Button returns 401        expires_at < NOW()
                              → Expired! ✗
                              → Login again
```

---

## 🔄 How Requests Work

### Request with Valid Token
```
BROWSER:
GET /php/manage-ban-exclusions.php
Headers: {
  Authorization: Bearer abc123def456xyz789,
  Content-Type: application/json
}

SERVER:
1. Extract "abc123def456xyz789" from Authorization header
2. Query: SELECT user_id FROM sessions 
          WHERE token = 'abc123def456xyz789' 
          AND expires_at > NOW()
3. If found → Return 200 OK + data
4. If not found → Return 401 Unauthorized

RESPONSE (if valid):
{
  "success": true,
  "exclusions": [ { "user_id": 2, "username": "john" } ]
}

RESPONSE (if invalid):
{
  "success": false,
  "error": "Missing or invalid token"
}
```

### Request with Missing Token
```
BROWSER:
GET /php/manage-ban-exclusions.php
Headers: {
  Content-Type: application/json
  (NO Authorization header)
}

SERVER:
1. Look for Authorization header → Not found!
2. Return 401 Unauthorized

RESPONSE:
{
  "success": false,
  "error": "Missing or invalid token"
}
```

---

## 📍 Where Token Lives

```
┌─────────────────────────────────────────┐
│  BROWSER (Client-Side)                  │
├─────────────────────────────────────────┤
│ localStorage:                           │
│   {                                     │
│     token: "abc123def456..."            │
│   }                                     │
│                                         │
│ (Stays here until:                      │
│  - You log out                          │
│  - You clear localStorage               │
│  - Browser is closed + cache cleared)   │
└─────────────────────────────────────────┘
                    ↓
           Sent with each request
           Authorization: Bearer ...
                    ↓
┌─────────────────────────────────────────┐
│  DATABASE (Server-Side)                 │
├─────────────────────────────────────────┤
│ sessions table:                         │
│  user_id | token         | expires_at   │
│  1       | abc123def...  | 2025-01-13  │
│  1       | old_token...  | 2025-01-10  │
│                                         │
│ (Each login creates new token)          │
│ (Tokens expire after 24 hours)          │
│ (Used to verify Authorization header)   │
└─────────────────────────────────────────┘
```

---

## 🔍 How to Find Your Token

### In Browser
```
Open Developer Tools (F12)
→ Application/Storage tab
→ Local Storage
→ Select your domain
→ Look for key: "token"
→ Value: "abc123def456..."

Or in Console:
localStorage.getItem('token')
// Returns: "abc123def456..."
```

### In Database
```sql
-- Find your user ID first:
SELECT id, username FROM users WHERE username = 'YOUR_USERNAME';
-- Let's say ID is 1

-- Find your tokens:
SELECT token, created_at, expires_at 
FROM sessions 
WHERE user_id = 1 
ORDER BY created_at DESC;

-- Result:
-- token: "abc123def456..."
-- created_at: "2025-01-12 10:30:00"
-- expires_at: "2025-01-13 10:30:00"
```

---

## ✅ Token Health Check

### Healthy Token
```
localStorage.getItem('token')
→ Returns: "abc123def456... (long string)"

debugToken()
→ Returns:
{
  "token_info": {
    "parsed": "SUCCESS",
    "token_length": 128
  },
  "database_info": {
    "found": true,
    "expired": false,
    "expires_in_seconds": 86400,
    "is_admin": true
  }
}

✓ All green!
```

### Unhealthy Tokens

#### Expired Token
```
debugToken()
→ Shows: "expired": true

FIX: Log in again to get fresh token
```

#### Missing Token
```
localStorage.getItem('token')
→ Returns: null or undefined

FIX: Log in to create token
```

#### Not in Database
```
debugToken()
→ Shows: "found": false

FIX: Log in again, database will get updated
```

#### Not Admin
```
debugToken()
→ Shows: "is_admin": false

FIX: Database: INSERT INTO admins VALUES (your_user_id, 'administrator');
```

---

## 🚀 Token Workflow Diagram

```
                    START
                      │
                      ▼
            ┌──────────────────┐
            │  User Logged In? │
            └──┬───────────────┘
               │
        NO─────┴──→ [SHOW LOGIN PAGE]
               │
               ▼ YES
            ┌────────────────────────────┐
            │ localStorage has token?    │
            └──┬──────────────────────┬──┘
               │                      │
          NO───┴──→ [ERROR 401]       ▼ YES
                                   ┌──────────────────┐
                                   │ Token not expired?│
                                   └─┬────────────────┘
                                     │
                              NO─────┴──→ [LOG IN AGAIN]
                                     │
                                     ▼ YES
                                  ┌────────────────────┐
                                  │ User is admin?    │
                                  └─┬──────────────┬───┘
                                    │              │
                             NO─────┴──→ [ERROR   │
                                    403]   ▼ YES
                                       ┌─────────────────┐
                                       │ SHOW ADMIN PANEL│
                                       └─────────────────┘
```

---

## 📞 Token Troubleshooting Map

```
Getting 401 Error?
     │
     ├─→ No token in localStorage?
     │   └─→ You need to LOG IN
     │
     ├─→ Token found but not in database?
     │   └─→ LOG IN AGAIN (new token will be created)
     │
     ├─→ Token in database but EXPIRED?
     │   └─→ LOG IN AGAIN (tokens only last 24 hours)
     │
     ├─→ Token valid but not admin?
     │   └─→ RUN: INSERT INTO admins (user_id, role) 
     │       VALUES (1, 'administrator');
     │
     └─→ Everything looks good but still 401?
         └─→ Hard refresh (Ctrl+Shift+R)
         └─→ Clear cache (Ctrl+Shift+Del)
         └─→ Try different browser
         └─→ Check server logs for errors
```

---

## 🎯 Remember This

```
TOKEN = PROOF YOU LOGGED IN

Without token:     With token:
❌ Can't access   ✓ Can access
❌ Get 401 error  ✓ See dashboard
❌ No auth        ✓ Full access
```

```
TOKEN LIFETIME = 24 HOURS

Created: 2025-01-12 10:00 AM
Expires: 2025-01-13 10:00 AM
                    ↓
After expiration, you need to log in again
```

```
TOKEN STORAGE = 2 PLACES

1. Browser (localStorage)
   └─ Used to create Authorization header

2. Database (sessions table)
   └─ Used by server to verify token is real
```

---

## 🔧 The 3-Command Fix

```javascript
// 1. Clear and log in again
localStorage.clear()
// Go log in in the login form

// 2. Verify token was created
localStorage.getItem('token')
// Should show a long string now

// 3. Check it's valid
debugToken()
// Should show all good ✓
```

---

## 📚 Related Files to Read

- `TOKEN_SETUP_GUIDE.md` - Complete setup explanation
- `TOKEN_QUICK_FIX.md` - Specific issue fixes
- `php/login.php` - Where tokens are created
- `php/debug-token.php` - Token verification endpoint

---

**Now you understand the token system! 🎉**

```
Login
  ↓
Get Token
  ↓
Store Token
  ↓
Send Token with Requests
  ↓
Server Validates Token
  ↓
Access Granted ✓
```

**It's that simple!**


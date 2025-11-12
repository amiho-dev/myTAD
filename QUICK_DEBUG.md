# 🔍 AUTH 401 Debugging - Quick Start

## The Problem
Admin panel shows "Error loading protected users: HTTP 401:" when trying to view protected users list.

## The Solution
We've added comprehensive debugging tools. Here's how to use them:

---

## 🎯 Three Simple Steps

### Step 1: Open Browser Console
```
Press F12 or Ctrl+Shift+I
Click "Console" tab
```

### Step 2: Run Debug Command
```javascript
debugToken()
```

### Step 3: Check Output
Look for:
- ✅ `"found": true` → Token in database
- ✅ `"is_admin": true` → User is admin
- ✅ `"expired": false` → Token not expired
- ✅ All three = **Should work!**

---

## 🐛 What to Check

### If token is NOT found:
```
Login again → New token should be created
```

### If user is NOT admin:
```
User needs admin role in database
```

### If token IS expired:
```
Expires_in_seconds will be negative
Login again to get fresh token
```

### If Authorization header NOT sent:
```javascript
testHeaders()
// Look for "HTTP_AUTHORIZATION" in output
```

---

## 📋 All Available Commands

| Command | What it does |
|---------|-------------|
| `debugToken()` | Shows complete token & auth info |
| `testHeaders()` | Confirms Authorization header sent |
| `loadBanExclusionList()` | Manually load protected users |
| `localStorage.getItem('token')` | Show raw token value |

---

## 📊 Expected Debug Output

### ✅ Success Case:
```json
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
```

### ❌ Token Not Found:
```json
{
  "database_info": {
    "found": false,
    "message": "Token not found in sessions table"
  }
}
```

### ⏰ Token Expired:
```json
{
  "database_info": {
    "found": true,
    "expired": true,
    "expires_in_seconds": -3600
  }
}
```

### 👤 Not Admin:
```json
{
  "database_info": {
    "found": true,
    "expired": false,
    "is_admin": false
  }
}
```

---

## 🔧 If Still Not Working

### Check 1: Database Directly
```sql
-- Show me my token
SELECT * FROM sessions WHERE user_id = 1 ORDER BY created_at DESC LIMIT 1;

-- Show me my admin status
SELECT * FROM admins WHERE user_id = 1;
```

### Check 2: Server Logs
```bash
tail -f /path/to/error_log
```
Look for lines like:
```
=== REQUEST HEADERS ===
TOKEN FOUND: abc123...
Token lookup returned 1 rows
```

### Check 3: Login Flow
1. Make sure you're logged in
2. Open DevTools → Console
3. Run: `localStorage.getItem('token')`
4. Should show a token string, not empty

---

## 🗂️ New Files for Debugging

| File | Purpose |
|------|---------|
| `php/debug-token.php` | Complete token info endpoint |
| `php/test-headers.php` | Header transmission test |
| `AUTH_DEBUGGING.md` | Full debugging guide |
| `DEBUGGING_QUICK_REFERENCE.md` | Commands & troubleshooting |

---

## 💡 Most Common Fixes

| Error | Fix |
|-------|-----|
| Token not found | Login again |
| Not admin | Add to admins table |
| Token expired | Login again |
| Header not sent | Browser issue, try testHeaders() |

---

## 📞 How to Report Issues

Run these commands and share results:
1. `debugToken()` → Copy JSON output
2. `testHeaders()` → Copy JSON output  
3. Browser Console → Right-click → Save as → pastie.org

**Include:**
- Debug token output
- Test headers output
- Server error log (last 20 lines)
- Steps to reproduce

---

## ✨ Key Points

✅ Token stored in `localStorage`  
✅ Sent in `Authorization: Bearer {token}` header  
✅ Verified against `sessions` table in database  
✅ User must be in `admins` table  
✅ Token expires after 24 hours  
✅ All debug info available in browser console  

---

## 🚀 Quick Test Flow

```
1. Login as admin user
2. Go to Admin tab
3. If no error → WORKING! ✅
4. If error → Run: debugToken()
5. Share output from console
6. We debug together!
```

---

## 📖 Full Documentation

- **AUTH_DEBUGGING.md** - Complete guide with diagrams
- **DEBUGGING_QUICK_REFERENCE.md** - All commands & troubleshooting
- **SESSION_SUMMARY.md** - Technical changes made

---

**Last Updated:** Current Session  
**Status:** Ready for Testing  
**Next Step:** Open browser, run `debugToken()`, and share output!

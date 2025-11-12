# Auth Debugging - Visual Reference Card

## 🎯 The Challenge

```
User clicks "Admin" tab
         ↓
Browser sends: GET /manage-ban-exclusions.php
              With: Authorization: Bearer {token}
         ↓
Server receives request
         ↓
[?] What happens here?
         ↓
Server returns: HTTP 401 ❌
```

## 🔍 Our Solution

```
debugToken()
    ↓
[✓] Token extracted?
[✓] Token in database?
[✓] Token expired?
[✓] User is admin?
    ↓
Output shows EXACTLY which step fails!
```

---

## 📊 Three Debug Levels

### Level 1: Quick Check (10 seconds)
```javascript
// In browser console:
debugToken()

// Look at the output - if all shows true/found, it should work
```

### Level 2: Header Verification (20 seconds)
```javascript
// If debugToken shows problem, verify header is sent:
testHeaders()

// Should show HTTP_AUTHORIZATION in output
```

### Level 3: Deep Dive (Database)
```sql
-- Show current tokens
SELECT token, user_id, expires_at FROM sessions 
WHERE user_id = 1 
ORDER BY created_at DESC LIMIT 5;

-- Show admin status
SELECT * FROM admins WHERE user_id = 1;
```

---

## 🚦 Decision Tree

```
                    ┌─ Run debugToken() ─┐
                    │                     │
                    ▼                     ▼
        ┌─ Token Found? ─┐
        │                │
       NO              YES
        │                │
        ▼                ▼
    Login again      ┌─ Expired? ─┐
                     │            │
                    YES          NO
                     │            │
                     ▼            ▼
                 Login again   ┌─ Admin? ─┐
                               │          │
                              NO         YES
                               │          │
                               ▼          ▼
                          Fix admin   SHOULD WORK! ✓
                          role in DB
```

---

## 📋 Checklist

```
[ ] Token in localStorage?
    console.log(localStorage.getItem('token'))
    
[ ] Authorization header sent?
    testHeaders() → look for HTTP_AUTHORIZATION
    
[ ] Token in database?
    debugToken() → look for "found": true
    
[ ] Token not expired?
    debugToken() → look for "expired": false
    
[ ] User is admin?
    debugToken() → look for "is_admin": true
    
[ ] All true?
    Admin panel should work! ✓
```

---

## 🔴 Common Problems

| Problem | Sign | Fix |
|---------|------|-----|
| Not logged in | No token in localStorage | Login again |
| Header not sent | testHeaders() doesn't show authorization | Browser issue |
| Bad token | debugToken() says "found": false | Login again |
| Old token | debugToken() says "expired": true | Login again |
| Not admin | debugToken() says "is_admin": false | Add to admins table |

---

## 🟢 Success Indicators

```
debugToken() output includes:

✓ "parsed": "SUCCESS"
✓ "found": true
✓ "expired": false  
✓ "is_admin": true
✓ user_id: 1
✓ username: "yourname"

If all ✓ → Admin should work!
```

---

## 📞 Communication Flow

```
You:    Open app, click Admin
         ↓
System: Check token via debugToken()
         ↓
System: Tell you exactly what's wrong
         ↓
You:    Share the output with us
         ↓
We:     Give you exact fix
         ↓
Done:   Admin works! ✓
```

---

## 🛠️ Available Tools

| Tool | Command | Returns |
|------|---------|---------|
| Full Debug | `debugToken()` | Complete auth status |
| Header Test | `testHeaders()` | Shows all HTTP headers |
| Manual Load | `loadBanExclusionList()` | Test endpoint directly |
| Raw Token | `localStorage.getItem('token')` | Your actual token |

---

## 📍 File Locations

```
Frontend:          index.html
├─ debugToken()    (line ~1498)
├─ testHeaders()   (line ~1517)
└─ loadBanExclusionList() (line ~1536)

Backend:
├─ php/debug-token.php           (reads token, checks DB)
├─ php/test-headers.php          (shows headers)
└─ php/manage-ban-exclusions.php (main endpoint)
```

---

## ⏱️ How Long Each Test Takes

| Test | Time | Info Gained |
|------|------|-------------|
| debugToken() | 1 second | 90% of issues |
| testHeaders() | 1 second | Confirms header sent |
| Database check | 5 seconds | Confirms DB state |
| Server logs | 30 seconds | Full detailed trace |

---

## 💡 Pro Tips

1. **Run debugToken() first** - tells you 90% of the problem
2. **Check localStorage before debugging** - rule out not logged in
3. **Database check if debugging complex** - double-check DB state
4. **Save console output** - easier to debug with full output
5. **Use testHeaders() if token exists but not found** - header issue

---

## 🎓 How It Works (Simple Version)

```
1. You log in
   → Token stored in browser (localStorage)
   
2. You request protected data
   → Browser sends Authorization: Bearer {token}
   → Server extracts token from header
   
3. Server checks token
   → Looks in sessions table
   → Checks expiration
   → Checks admin role
   
4. If all good → ✓ Show data
   If anything wrong → ✗ Return 401

5. debugToken() shows what happened at each step
```

---

## 📞 Before Reporting an Issue

1. Run: `debugToken()` 
2. Copy the output
3. Run: `testHeaders()`
4. Copy that output too
5. Tell us what you see

**With those two outputs, we can fix it immediately!**

---

## ✨ The Big Picture

```
Old way:  "It's a 401"  → Wild guessing
New way:  Run debugToken() → See exactly what's wrong → Fix it
```

**That's it. You're ready!** 🚀

Press F12, run `debugToken()`, and share the output!

---

**Version:** 1.0  
**Date:** Current Session  
**Status:** Ready to test  

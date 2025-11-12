# Token System - Complete Documentation Index

**Issue:** "Missing or invalid token" error when trying to access admin panel  
**Solution:** Follow the guides below based on your needs

---

## 📚 Documentation Files (Pick One)

### 🏃 For the Impatient (5 minutes)
**→ Start with: `TOKEN_QUICK_FIX.md`**
- Quick diagnostic steps
- Common issues + fixes
- 50-second solution for most cases
- Specific error codes

### 🎓 For Learning (20 minutes)
**→ Then read: `TOKEN_VISUAL_GUIDE.md`**
- Diagrams showing how tokens work
- Where tokens live (browser vs database)
- Token lifecycle (creation to expiration)
- Visual troubleshooting map

### 🔧 For Complete Understanding (30 minutes)
**→ Then read: `TOKEN_SETUP_GUIDE.md`**
- How the token system works
- Step-by-step setup instructions
- Database queries to verify tokens
- Manual token creation
- Common mistakes to avoid

---

## ⚡ The 60-Second Version

### Step 1: Are you logged in?
```
- See a logout button?
- See your username displayed?
- If NO → Go log in
- If YES → Continue
```

### Step 2: Check Token Exists
```javascript
// Open browser console (F12)
localStorage.getItem('token')

- Empty or null? → Log in again
- Shows long string? → Continue
```

### Step 3: Verify Token is Valid
```javascript
debugToken()

Expected output:
{
  "database_info": {
    "found": true,
    "expired": false,
    "is_admin": true
  }
}

- found: false? → Log in again
- expired: true? → Log in again
- is_admin: false? → Add admin role in database
- All good? → Admin should work!
```

---

## 🎯 Pick Your Scenario

### Scenario 1: "I just get a 401 error"
1. Read: `TOKEN_QUICK_FIX.md` (10 min)
2. Run: `debugToken()` in console
3. Share output with me

### Scenario 2: "I don't understand tokens"
1. Read: `TOKEN_VISUAL_GUIDE.md` (15 min)
2. Now understand how they work
3. Try troubleshooting again

### Scenario 3: "I need to set it up from scratch"
1. Read: `TOKEN_SETUP_GUIDE.md` (30 min)
2. Follow all steps carefully
3. You'll have it working

### Scenario 4: "I want to check everything"
1. Run all commands in order:
   ```javascript
   localStorage.getItem('token')
   testHeaders()
   debugToken()
   ```
2. Check database:
   ```sql
   SELECT * FROM sessions LIMIT 1;
   SELECT * FROM admins WHERE user_id = 1;
   ```
3. Everything healthy? → Try hard refresh (Ctrl+Shift+R)

---

## 🔍 Quick Diagnostic (Copy & Paste)

```javascript
// In browser console (F12), run these one by one:

// Check 1: Do you have a token?
console.log('Token:', localStorage.getItem('token') ? 'EXISTS' : 'MISSING');

// Check 2: Get full token info
debugToken()

// Check 3: Verify headers are sent
testHeaders()

// Then tell me:
// 1. What check 1 showed
// 2. What check 2 returned (full JSON)
// 3. What check 3 returned (full JSON)
// 4. Any error messages you see
```

---

## 🚨 Common Fixes

### Fix 1: "Token Missing" (90% of cases)
```
Cause: You haven't logged in
Fix:
  1. Go to login page
  2. Enter username and password
  3. Click Login
  4. Wait for dashboard to load
  Done!
```

### Fix 2: "Token Expired" (5% of cases)
```
Cause: You logged in more than 24 hours ago
Fix:
  1. Log out
  2. Log in again
  3. You get a fresh token
  Done!
```

### Fix 3: "Not Admin" (4% of cases)
```
Cause: You're logged in but don't have admin role
Fix:
  1. In database, run:
     INSERT INTO admins (user_id, role) 
     VALUES (1, 'administrator');
  2. Refresh page
  3. Now you can access admin panel
  Done!
```

### Fix 4: "Token Not Sent in Header" (1% of cases)
```
Cause: Browser not sending Authorization header
Fix:
  1. Hard refresh: Ctrl+Shift+R
  2. Clear cache: Ctrl+Shift+Del
  3. Close browser completely
  4. Reopen and try again
  5. If still failing: Different browser
```

---

## 🛠️ Tools Available

| Tool | Command | Shows |
|------|---------|-------|
| Token in Browser | `localStorage.getItem('token')` | Your actual token string |
| Complete Status | `debugToken()` | Everything about your auth |
| Header Test | `testHeaders()` | All HTTP headers received |
| Manual Load | `loadBanExclusionList()` | Test admin endpoint directly |
| Browser Storage | F12 → Application → Local Storage | Where token is stored |

---

## 📊 Token Status Meanings

### ✅ Healthy Token
```json
{
  "found": true,
  "expired": false,
  "is_admin": true,
  "expires_in_seconds": 86400
}
```
**What it means:** Token is valid, you're admin, will work for 24 hours  
**Action:** Try accessing admin panel, should work!

### ❌ Token Not Found
```json
{
  "found": false,
  "message": "Token not found in sessions table"
}
```
**What it means:** Token doesn't exist in database  
**Action:** Log in again to create a new token

### ⏰ Token Expired
```json
{
  "found": true,
  "expired": true,
  "expires_in_seconds": -3600
}
```
**What it means:** Token was valid but is now older than 24 hours  
**Action:** Log in again to get a fresh token

### 👤 Not Admin
```json
{
  "found": true,
  "expired": false,
  "is_admin": false
}
```
**What it means:** Token is valid but user doesn't have admin role  
**Action:** Add user to admins table in database

### 🚫 All Problems
```json
{
  "token_info": {
    "parsed": "FAILED"
  }
}
```
**What it means:** Token format is corrupted  
**Action:** Clear localStorage and log in again

---

## 📞 Before You Contact Me

Have you run these commands?
```javascript
1. localStorage.getItem('token')
   - Confirms if you have a token

2. debugToken()
   - Shows complete token status

3. testHeaders()
   - Shows if Authorization header is being sent
```

Have you checked the database?
```sql
SELECT * FROM sessions LIMIT 1;
SELECT * FROM admins WHERE user_id = 1;
```

If you've done all of this, share:
1. The output from `debugToken()`
2. The output from `testHeaders()`
3. What the database queries returned
4. Any error messages you see

**With that info, I can fix it immediately!**

---

## 🎯 Success Checklist

When everything is working:
- [ ] `localStorage.getItem('token')` shows a long string
- [ ] `debugToken()` shows all green ✓
- [ ] `testHeaders()` shows Authorization header
- [ ] Admin panel loads without error
- [ ] Protected users list displays
- [ ] No 401 errors in console
- [ ] Database has valid token in sessions table
- [ ] User is in admins table with correct role

---

## 🔗 Related Files

**Core Files:**
- `index.html` - Frontend, stores token
- `php/login.php` - Creates tokens
- `php/manage-ban-exclusions.php` - Uses tokens for access
- `php/debug-token.php` - Debug endpoint

**Database:**
- `sessions` table - Stores active tokens
- `admins` table - Stores admin roles
- `users` table - User accounts

**Debugging:**
- `php/test-headers.php` - Verify header transmission
- `DEBUG_REFERENCE_CARD.md` - Visual debugging guide

---

## 🆘 Still Not Working?

### Step 1: Run Diagnostics
```javascript
// Copy these commands into browser console:
console.log(localStorage.getItem('token'))
debugToken()
testHeaders()
```

### Step 2: Check Database
```sql
SELECT * FROM sessions ORDER BY created_at DESC LIMIT 5;
SELECT * FROM admins;
```

### Step 3: Get Error Message
- F12 → Console → Look for red errors
- F12 → Network → See failed requests
- Server error_log → Check for backend errors

### Step 4: Share Information
Tell me:
1. Your user ID
2. Output from `debugToken()`
3. Output from `testHeaders()`
4. What database queries showed
5. Any error messages

**I'll diagnose the exact issue!**

---

## 🎓 Understanding the System

```
Login
  ↓ (creates token)
Token stored in browser (localStorage)
  ↓ (token sent with each request)
Authorization: Bearer {token}
  ↓ (server validates)
Server checks database for token
  ↓ (verify it exists and not expired)
Access granted ✓ or Denied ✗
```

**That's the entire system in 6 steps!**

---

## 🚀 Next Action

Choose one:

**A) Quick Fix (5 min)**
→ Read: `TOKEN_QUICK_FIX.md`
→ Follow: Step-by-step fixes
→ Result: Most cases solved

**B) Learn System (20 min)**
→ Read: `TOKEN_VISUAL_GUIDE.md`
→ Understand: How tokens work
→ Apply: Knowledge to troubleshoot

**C) Complete Setup (30 min)**
→ Read: `TOKEN_SETUP_GUIDE.md`
→ Follow: All steps carefully
→ Result: Deep understanding + working system

**D) Manual Diagnosis (10 min)**
→ Run: `debugToken()` in console
→ Share: Output with me
→ Result: Immediate fix from me

---

**You're ready! Pick an option above and let's get your token working!** 🎉


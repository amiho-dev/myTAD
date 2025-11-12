# Quick Troubleshooting Commands

## Browser Console Commands (Press F12, then paste into Console)

### 1. Debug Token Information
```javascript
debugToken()
```
Shows:
- Token extraction from localStorage
- Headers sent to server
- Token found in database (yes/no)
- User ID and admin status
- Token expiration info

### 2. Test Header Transmission
```javascript
testHeaders()
```
Shows:
- All HTTP headers received by server
- Confirms Authorization header is sent
- Shows header format

### 3. Load Protected Users
```javascript
loadBanExclusionList()
```
Shows:
- Success: List of protected users
- Error: Specific error message and HTTP status

### 4. Check localStorage Token
```javascript
console.log('Token:', localStorage.getItem('token'))
```

### 5. Manually Check Admin Tab
- Click the "Admin" tab in the menu
- Should load protected users automatically
- Check browser console (F12) for detailed logs

## Server-Side Checks

### Check Sessions Table
```sql
SELECT * FROM sessions ORDER BY created_at DESC LIMIT 5;
```

### Check Current User Admin Status
```sql
SELECT * FROM admins WHERE user_id = 1;
```

### Check Logs
```bash
tail -f /path/to/php/error_log
```
Should show debugging output like:
```
=== REQUEST HEADERS ===
HTTP_AUTHORIZATION: Bearer abc123...
TOKEN FOUND: abc123def456...
Token lookup returned 1 rows
```

## What Each Debug Endpoint Returns

### `/php/debug-token.php`
```json
{
  "status": "debug",
  "token_info": {
    "source": "HTTP_AUTHORIZATION",
    "header_value": "Bearer abc123...",
    "parsed": "SUCCESS",
    "token_length": 128
  },
  "database_info": {
    "found": true,
    "user_id": 1,
    "username": "admin_user",
    "email": "admin@example.com",
    "expires_at": "2024-01-15 12:00:00",
    "expired": false,
    "expires_in_seconds": 86400,
    "is_admin": true,
    "admin_role": "administrator"
  }
}
```

### `/php/test-headers.php`
```json
{
  "method": "GET",
  "headers": {
    "HTTP_AUTHORIZATION": "Bearer abc123...",
    "HTTP_CONTENT_TYPE": "application/json",
    ...
  },
  "HTTP_AUTHORIZATION": "Bearer abc123...",
  "getallheaders": { ... }
}
```

## Debugging Flow Chart

```
1. Browser Page Loads
   ↓
2. Login (stores token in localStorage)
   ↓
3. Open Admin Tab
   ↓
4. In Console: debugToken()
   ↓
   ├─ NO TOKEN in localStorage?
   │  └─ Need to login again
   │
   ├─ Token extracted?
   │  ├─ NO → Use testHeaders() to check header transmission
   │  │
   │  └─ YES → Continue
   │
   ├─ Token found in database?
   │  ├─ NO → Check if:
   │  │       - Login saved token correctly
   │  │       - Token expired (check expires_at)
   │  │       - Wrong database queried
   │  │
   │  └─ YES → Continue
   │
   ├─ Token expired?
   │  ├─ YES → Need to login again
   │  └─ NO → Continue
   │
   ├─ User is admin?
   │  ├─ NO → User needs admin role in admins table
   │  └─ YES → Continue
   │
   └─ Protected users should load!
```

## Quick Fix Checklist

- [ ] User logged in successfully?
- [ ] Token appears in localStorage? (`console.log(localStorage.getItem('token'))`)
- [ ] Token in database? (Check sessions table)
- [ ] Token not expired? (Check expires_at > NOW())
- [ ] User is admin? (Check admins table)
- [ ] Authorization header sent? (`testHeaders()` shows HTTP_AUTHORIZATION)
- [ ] Bearer token format correct? (`Bearer {token}`)
- [ ] Server error logs show no prepare/query errors?

## Most Likely Issue

Based on typical 401 errors, the most common causes are:

1. **Token not in database** (50%)
   - Fix: Verify login.php creates token correctly

2. **Token not sent in header** (30%)
   - Fix: testHeaders() should show Authorization header

3. **User not admin** (15%)
   - Fix: Add user to admins table

4. **Token expired** (5%)
   - Fix: Login again to get fresh token

## Getting Help

If you run `debugToken()` and provide the output, we can pinpoint the exact issue!

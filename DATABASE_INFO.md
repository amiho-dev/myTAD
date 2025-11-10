# Database Information - myTAD Admin System

## Database Connection

**Database Name:** `mytad`  
**Host:** `localhost:3306`  
**Username:** `mytad`  
**Engine:** MariaDB/MySQL with InnoDB

---

## Tables Used by Admin Functions

### 1. **users** - Main User Table
Stores all user account information

**Columns:**
```
id (PRIMARY KEY)
username (UNIQUE)
email (UNIQUE)
password (hashed)
is_active (0=banned, 1=active)
is_verified (email verification)
failed_login_attempts
account_locked_until
two_factor_enabled
two_factor_secret
created_at
updated_at
last_login
password_changed_at
...and more security fields
```

**Used by:**
- ✅ Search users (searchUsers function)
- ✅ Get users list (admin-get-users.php)
- ✅ Reset password (admin-reset-password.php)
- ✅ Update email (admin-update-email.php)
- ✅ Ban/Unban users (admin-user-action.php)

---

### 2. **admins** - Admin Role Table (NEW)
Stores who has admin privileges

**Columns:**
```
id (PRIMARY KEY)
user_id (FOREIGN KEY → users.id) (UNIQUE)
is_active (0=revoked, 1=active)
created_at
created_by (which admin created this role)
```

**Used by:**
- ✅ Check if user is admin (check-admin.php)
- ✅ Promote users to admin (manage-admin-role.php)
- ✅ Verify admin permissions (all admin endpoints)

---

### 3. **audit_log** - Audit Trail Table
Logs all admin actions

**Columns:**
```
id (PRIMARY KEY)
user_id (who performed action)
action (GRANT_ADMIN, REVOKE_ADMIN, BAN_USER, UNBAN_USER, etc)
target_user_id (who the action was performed on)
details (JSON with more info)
ip_address
created_at
```

**Used by:**
- ✅ Log all admin actions automatically
- ✅ Track who banned/unbanned users
- ✅ Track who sent warnings
- ✅ Track who changed passwords
- ✅ View audit log (admin-audit-log.php)

---

## Admin Function Data Flow

### When Admin Searches for Users:
```
searchUsers() → admin-get-users.php
    ↓
SELECT from users table
    ↓
WHERE username LIKE '%search%' OR email LIKE '%search%'
    ↓
Returns: username, email, id, is_active
```

### When Admin Resets Password:
```
handleAdminResetPassword() → admin-reset-password.php
    ↓
1. Check admins table (is caller admin?)
    ↓
2. UPDATE users table SET password = hashed_password
    ↓
3. INSERT INTO audit_log (what admin did this)
    ↓
4. Return success
```

### When Admin Bans User:
```
handleAdminUserAction('ban') → admin-user-action.php
    ↓
1. Check admins table (is caller admin?)
    ↓
2. UPDATE users table SET is_active = 0 WHERE id = target_id
    ↓
3. INSERT INTO audit_log (logged the ban)
    ↓
4. Return success
```

### When Admin Sends Warning:
```
handleAdminSendWarning() → admin-send-warning.php
    ↓
1. Check admins table (is caller admin?)
    ↓
2. UPDATE users table SET warning_count = warning_count + 1
    ↓
3. INSERT INTO audit_log (logged the warning)
    ↓
4. Send email to user (notifications)
    ↓
5. Return success
```

### When Admin Promotes User to Admin:
```
handleAddAdmin() → manage-admin-role.php
    ↓
1. Check admins table (is caller admin AND owner?)
    ↓
2. INSERT INTO admins (user_id, is_active=1, created_by)
    ↓
3. INSERT INTO audit_log (logged the promotion)
    ↓
4. Return success
```

---

## Complete Table List

### All 8 Tables in `mytad` Database:

| Table | Purpose | Used by Admin? |
|-------|---------|----------------|
| `users` | User accounts | ✅ YES |
| `admins` | Admin roles | ✅ YES |
| `sessions` | Active sessions | ❌ No (auth system) |
| `login_attempts` | Failed logins | ❌ No (rate limiting) |
| `password_resets` | Password reset tokens | ❌ No (password recovery) |
| `audit_log` | Activity log | ✅ YES |
| `two_factor_backup_codes` | 2FA recovery codes | ❌ No (2FA system) |
| `ip_whitelist` | Trusted devices | ❌ No (device tracking) |

---

## SQL Query Examples

### Get all users (for search):
```sql
SELECT id, username, email, is_active, created_at 
FROM users 
WHERE username LIKE '%search%' OR email LIKE '%search%'
ORDER BY created_at DESC;
```

### Check if user is admin:
```sql
SELECT id FROM admins 
WHERE user_id = ? AND is_active = 1;
```

### Ban a user:
```sql
UPDATE users 
SET is_active = 0, updated_at = NOW() 
WHERE id = ?;
```

### Reset user password:
```sql
UPDATE users 
SET password = ?, password_changed_at = NOW(), updated_at = NOW() 
WHERE id = ?;
```

### Update user email:
```sql
UPDATE users 
SET email = ?, updated_at = NOW() 
WHERE id = ?;
```

### Log admin action:
```sql
INSERT INTO audit_log (user_id, action, target_user_id, details, ip_address, created_at)
VALUES (?, 'BAN_USER', ?, json_object('reason', ?), ?, NOW());
```

### Promote user to admin:
```sql
INSERT INTO admins (user_id, is_active, created_by, created_at)
VALUES (?, 1, ?, NOW())
ON DUPLICATE KEY UPDATE is_active = 1;
```

---

## Data Flow Summary

```
┌─────────────────────────────────────────────────────────┐
│                    index.html (Frontend)                │
│  • Admin panel                                          │
│  • Search form                                          │
│  • Action buttons                                       │
└────────────────────┬────────────────────────────────────┘
                     │ Bearer Token
                     ↓
┌─────────────────────────────────────────────────────────┐
│              PHP Endpoints (/php/*.php)                 │
│  • check-admin.php                                      │
│  • admin-get-users.php                                  │
│  • admin-reset-password.php                             │
│  • admin-update-email.php                               │
│  • admin-user-action.php (ban/unban)                    │
│  • admin-send-warning.php                               │
│  • manage-admin-role.php (promote/revoke)               │
└────────────────────┬────────────────────────────────────┘
                     │ SQL Queries
                     ↓
┌─────────────────────────────────────────────────────────┐
│           MariaDB Database: mytad                       │
│  • users table (search, update, ban)                    │
│  • admins table (verify permissions, promote)           │
│  • audit_log table (log all actions)                    │
└─────────────────────────────────────────────────────────┘
```

---

## To View Database Tables

### Option 1: Command Line
```bash
# Connect to database
mysql -h localhost:3306 -u mytad -p

# Enter password: y+nQzZa4BS?!,;A

# Select database
USE mytad;

# See all tables
SHOW TABLES;

# See users table structure
DESCRIBE users;

# See all users
SELECT id, username, email, is_active FROM users;

# See admin roles
SELECT u.username, a.is_active, a.created_at 
FROM admins a
JOIN users u ON a.user_id = u.id;
```

### Option 2: PhpMyAdmin (Web GUI)
If available on your server:
```
https://my.thatoneamiho.cc/phpmyadmin
```

### Option 3: Check Database via PHP
```php
// In any PHP file:
require_once('db-config.php');
$conn = getDBConnection();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo $row[0] . "\n";
}
```

---

## Which Table for Which Admin Action?

| Admin Action | Main Table | Helper Tables | What Gets Updated |
|--------------|-----------|--------------|-------------------|
| Search users | users | - | Read only |
| Reset password | users | audit_log | password, password_changed_at |
| Update email | users | audit_log | email |
| Ban user | users | audit_log | is_active = 0 |
| Unban user | users | audit_log | is_active = 1 |
| Send warning | users | audit_log | warning_count |
| Promote admin | admins | audit_log | INSERT new admin row |
| Revoke admin | admins | audit_log | is_active = 0 |

---

## Key Information

🔐 **Admin Verification:**
- Before any action, system checks `admins` table
- Must have `is_active = 1` in admins table
- Returns 403 Forbidden if not admin

📊 **User Data:**
- All user data lives in `users` table
- `is_active` flag controls account status:
  - `1` = account is active
  - `0` = account is banned
- `password` is stored as Bcrypt hash (never plaintext)

📝 **Audit Trail:**
- Every admin action logged to `audit_log`
- Includes: who did it, what they did, who it affected, when, from what IP
- Cannot be edited (only read)

🎯 **Admin Roles:**
- Stored separately in `admins` table
- Links user_id to admin privileges
- Can be revoked without deleting user account
- Tracks who promoted each admin

---

## Summary

**Everything comes from:** `mytad` database  
**Main user data:** `users` table  
**Admin permissions:** `admins` table  
**Action history:** `audit_log` table  

When you search for a user, ban them, reset their password, or promote them to admin - all of that data is being read from and written to these 3 tables in the `mytad` MariaDB database at `localhost:3306`.

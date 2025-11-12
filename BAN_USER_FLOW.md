# What Happens When You Click "Ban User" Button

## Complete Step-by-Step Flow

```
User clicks "🚫 Ban User" button
    ↓
handleAdminUserAction('ban') is called
    ↓
Frontend validates and sends request
    ↓
Backend verifies admin is owner
    ↓
Backend bans user in database
    ↓
Success message shown
    ↓
Admin panel refreshes
```

---

## Step 1: Frontend - User Interaction

### HTML Button:
```html
<button class="btn" style="background: rgba(239, 68, 68, 0.3); flex: 1;" 
    onclick="handleAdminUserAction('ban')">
    🚫 Ban User
</button>
```

### What triggers it:
1. Admin searches for and selects a user
2. User's ID is populated in `adminTargetUser` field
3. Admin clicks "🚫 Ban User" button

---

## Step 2: Frontend - JavaScript Function

### Function: `handleAdminUserAction('ban')`

```javascript
async function handleAdminUserAction(action) {
    // Step 1: Get the user ID from input field
    const targetUser = document.getElementById('adminTargetUser').value.trim();
    
    // Step 2: Validate user is selected
    if (!targetUser) {
        showError('Please select a user first');
        return;
    }
    
    // Step 3: Convert to integer
    let userId = parseInt(targetUser) || null;
    if (!userId) {
        showError('Please enter a valid user ID');
        return;
    }
    
    // Step 4: Send request to backend
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(`${API_BASE_URL}/admin-user-action.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                user_id: userId, 
                action: 'ban'  // This is the action
            })
        });
        
        // Step 5: Parse response
        const data = await response.json();
        
        // Step 6: Handle success/error
        if (data.success) {
            showSuccess(data.message);  // Shows "User banned successfully"
            document.getElementById('adminTargetUser').value = '';  // Clear input
            setTimeout(loadAdminUsers, 1500);  // Refresh list after 1.5 seconds
        } else {
            showError(data.error || 'Failed to perform action');
        }
    } catch (error) {
        showError('Connection error');
    }
}
```

### Data Sent to Backend:
```json
{
    "user_id": 123,
    "action": "ban"
}
```

### Headers Sent:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json
```

---

## Step 3: Backend - Endpoint Receives Request

### File: `/php/admin-user-action.php`

### What it checks:

#### 1. **Request Method Check:**
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Only POST requests allowed
}
```

#### 2. **Authentication Check:**
```php
if (!isset($_SESSION['user_id'])) {
    // User must be logged in
    return 401 Unauthorized
}
```

#### 3. **Parse Request Data:**
```php
$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = intval($data['user_id']);  // 123
$action = $data['action'];  // 'ban'
```

#### 4. **Validate Action:**
```php
if (!in_array($action, ['ban', 'unban', 'mute', 'unmute'])) {
    // Must be one of these actions
    return 400 Bad Request
}
```

---

## Step 4: Backend - Authorization Check

### Check if requester is admin:

```php
$admin_stmt = $conn->prepare(
    "SELECT id FROM users 
     WHERE id = ? AND username = 'thatoneamiho'"
);
$admin_stmt->bind_param("i", $_SESSION['user_id']);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows === 0) {
    // Not an admin (not thatoneamiho)
    return 403 Forbidden
}
```

**This checks:**
- Is the caller logged in as user "thatoneamiho"?
- If NO → 403 Forbidden error
- If YES → proceed to ban user

---

## Step 5: Backend - Safety Checks

### Prevent self-banning:

```php
if ($target_user_id === $_SESSION['user_id']) {
    // Can't ban yourself
    return 400 Bad Request with message:
    "Cannot perform this action on yourself"
}
```

---

## Step 6: Backend - Execute Ban

### SQL Query Executed:

```sql
UPDATE users 
SET is_active = 0 
WHERE id = 123
```

### What this does:
- Finds user with id = 123
- Sets their `is_active` field to `0`
- A value of `0` means the account is BANNED
- A value of `1` means the account is ACTIVE

### PHP Code:
```php
if ($action === 'ban') {
    $update_stmt = $conn->prepare(
        "UPDATE users SET is_active = 0 WHERE id = ?"
    );
    $update_stmt->bind_param("i", $target_user_id);
    $message = 'User banned successfully';
}

// Execute the query
if ($update_stmt->execute()) {
    // SUCCESS - return success response
}
```

---

## Step 7: Backend - Return Response

### Success Response:
```json
{
    "success": true,
    "message": "User banned successfully",
    "user_id": 123,
    "action": "ban",
    "reason": "No reason provided"
}
```

### Error Response (if not admin):
```json
{
    "success": false,
    "error": "Admin access required"
}
HTTP Status: 403 Forbidden
```

### Error Response (if user not found):
```json
{
    "success": false,
    "error": "Failed to perform action"
}
HTTP Status: 500
```

---

## Step 8: Frontend - Display Result

### If Success:
```javascript
showSuccess("User banned successfully")
// Shows green success message
document.getElementById('adminTargetUser').value = '';  // Clear field
setTimeout(loadAdminUsers, 1500);  // Refresh after 1.5 seconds
```

### If Error:
```javascript
showError("Admin access required")
// Shows red error message
```

---

## What Happens to the Banned User?

### In Database:
- User record still exists
- `is_active` is now `0` (was `1`)
- All user data preserved
- Account is recoverable (can unban)

### When Banned User Tries to Login:
1. Enters username and password
2. Backend checks `is_active` field
3. If `0` → Login rejected with "Account banned" error
4. Cannot access system

### What You See in Search Results:
```
When searching for banned users:
User Name: banned_user
ID: 123 • 🚫 Banned  ← Status shows they're banned
```

---

## To Unban a User

Same process, but click "✓ Unban User" instead:

```sql
UPDATE users 
SET is_active = 1 
WHERE id = 123
```

This sets `is_active` back to `1` and they can login again.

---

## Complete Example Scenario

```
1. Admin (thatoneamiho) is logged in
2. Opens Admin Tab
3. Searches for "john"
4. Results show: john (ID: 123) - Active
5. Clicks on john to select him
6. adminTargetUser field now contains: 123
7. Clicks "🚫 Ban User" button
8. Frontend calls handleAdminUserAction('ban')
9. Sends: { user_id: 123, action: 'ban' }
10. Backend receives request
11. Checks: Is caller admin? YES (thatoneamiho)
12. Checks: Can't ban self? OK, target is 123, caller is different
13. Runs: UPDATE users SET is_active = 0 WHERE id = 123
14. Returns: { success: true, message: "User banned successfully" }
15. Frontend shows: "✅ User banned successfully"
16. User input cleared
17. Panel refreshed
18. Now when john tries to login → DENIED: "Account banned"
```

---

## Database Impact

### Before Ban:
```sql
SELECT * FROM users WHERE id = 123;

| id  | username | email        | is_active | ... |
| 123 | john     | john@ex.com  | 1         | ... |
```

### After Ban:
```sql
SELECT * FROM users WHERE id = 123;

| id  | username | email        | is_active | ... |
| 123 | john     | john@ex.com  | 0         | ... |
```

---

## Security Features

🔐 **Only owner can ban:**
- Checks if caller is "thatoneamiho"
- Returns 403 if not owner

🔐 **Cannot ban yourself:**
- Prevents accidental self-ban
- Returns error if you try

🔐 **Bearer token required:**
- Must be authenticated
- Must have valid session token

🔐 **Validation:**
- User ID must be integer
- Action must be in allowed list
- User must exist

---

## Summary

**Click "Ban User" →**
1. ✓ Frontend validates user ID
2. ✓ Sends POST request with bearer token
3. ✓ Backend verifies caller is admin owner
4. ✓ Backend runs: `UPDATE users SET is_active = 0`
5. ✓ User is now banned from logging in
6. ✓ Frontend shows success message
7. ✓ User can be unbanned by clicking "Unban" button

**Result:** User account is disabled but not deleted. They cannot login until unbanned.

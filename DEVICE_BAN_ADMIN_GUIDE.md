# Device Ban System - Admin Quick Reference

## How the System Works

When you ban a user, the system:
1. Marks their account as inactive (`is_active = 0`)
2. Records the IP address and device fingerprint of all their active sessions
3. Creates device bans that prevent access from those devices
4. Logs them out from all active sessions
5. Shows a special banned message to the user on the login screen

## What Users See

### When Banned
Users see a modal dialog with:
- **⛔ Warning Icon**
- **Title**: "Your device/account access has been restricted"
- **Ban Duration**: Shows the exact date and time when they can access again (if temporary)
- **Or**: "⛔ PERMANENT RESTRICTION" (if permanent ban)
- **Warning Message**:
  ```
  ⚠️ Warning:
  Creating a new account will result in all of your accounts being 
  permanently banned, and your device will not be able to access myTAD.
  ```

### Why This Design?
- **Deters Circumvention**: Warning discourages creating new accounts
- **Clear Information**: Users know exactly when they can access again
- **Transparent Enforcement**: No confusion with "Account disabled" messages
- **Device-Level Blocking**: Can't access from that IP/device even with new account

## Banning a User

### Via Admin Panel
1. Go to User Management
2. Search for the user
3. Click "🚫 Ban User" button
4. (Optional) Enter ban duration in hours, or leave blank for permanent

### Expected Outcome
- User can no longer login from any device they previously used
- If they try: See ban warning with expiration date
- If they try to register a new account from same device: Also blocked
- All their current sessions are terminated immediately

## Unbanning a User

### Via Admin Panel
1. Go to User Management
2. Search for the user
3. Click "✅ Unban User" button

### Expected Outcome
- User's account becomes active (`is_active = 1`)
- All device bans for that user are cleared
- User can login normally from any device
- All sessions remain terminated (they must login fresh)

## Ban Types

### Temporary Ban
- **Format**: Specified number of hours (e.g., 24 hours)
- **Automatic Expiration**: Ban lifts at specified time
- **Device**: Still cannot access from same device until ban expires
- **New Accounts**: Also blocked from device until ban expires

### Permanent Ban
- **Format**: No duration specified (0 hours)
- **Expiration**: Never expires unless admin unbans
- **Device**: Device permanently blocked from accessing
- **New Accounts**: Cannot register from device ever

## Device Identification

The system tracks users by:

1. **IP Address**
   - Primary identifier
   - Blocks entire IP range
   - Example: `192.168.1.100`

2. **Device Fingerprint**
   - Browser + OS characteristics
   - Hash of User Agent + Accept headers
   - Unique per browser/device combination
   - Example: `sha256hash...`

### Security Note
Users CANNOT bypass bans by:
- ❌ Creating a new account (device blocked)
- ❌ Clearing cookies (ban stored server-side)
- ❌ Using incognito mode (IP/fingerprint still same)
- ❌ Using different browser (same IP blocks)
- ✅ Only way: Different IP address or admin unban

## Important Considerations

### When to Use Temporary Bans
- Rule violations that warrant reflection time
- First-time offenders
- Minor infractions
- Allowing reformation

### When to Use Permanent Bans
- Severe violations (hacking, harassment, etc.)
- Repeated offenses after warnings
- Creating accounts to evade previous bans
- Major security threats

### Unban Reasons
- User appeals and receives approval
- Ban was applied in error
- Sufficient time has passed for reformation
- Policy change affecting existing bans

## Database Behind the Scenes

The system stores:
- **User Account Ban**: `users.is_active = 0`, `users.account_locked_until = [time]`
- **Device Ban**: `device_bans` table with:
  - IP address that was banned
  - Device fingerprint (hash)
  - When ban expires (NULL for permanent)
  - Reason for ban
  - Timestamp

### Checking Ban Status
To verify a ban in database:
```sql
SELECT * FROM device_bans 
WHERE user_id = [user_id] 
AND (is_permanent = 1 OR banned_until > NOW());
```

## Troubleshooting

### User Says Ban Won't Expire
- Check: `banned_until` timestamp in `device_bans` table
- Verify: Server time is correct
- Solution: Manually unban if needed

### User Claims New Account Still Blocked
- Expected behavior: Device is blocked for [X] hours/permanently
- Check: Verify same IP/device is attempting registration
- Solution: Change device/IP OR unban

### Admin Accidentally Banned Wrong User
- Solution: Click "✅ Unban User" immediately
- Check: Verify user shows in active sessions before next login

## Cookies Set on Banned Device

When a device is banned, server sets:
- `mytad_device_banned` - Flag indicating device is banned
- `mytad_device_fingerprint` - Device fingerprint for tracking

These are:
- **HttpOnly**: Cannot be accessed by JavaScript
- **Persistent**: Survive browser restart
- **Secure**: Only sent over HTTPS
- **Session**: Stored with long expiration matching ban duration

## Response Messages

### Banned Device (Login Screen)
```json
{
  "error": "banned_device",
  "is_permanent": false,
  "banned_until_formatted": "November 25, 2025 at 2:30 PM"
}
```

### Banned Account (Login Screen)
```json
{
  "error": "account_banned",
  "is_permanent": false,
  "banned_until_formatted": "November 25, 2025 at 2:30 PM"
}
```

### Device Banned (Registration Screen)
```json
{
  "error": "device_banned",
  "message": "This device is restricted from accessing myTAD"
}
```

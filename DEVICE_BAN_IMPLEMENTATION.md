# Device Ban Implementation Summary

## Overview
Implemented a comprehensive device-ban system that restricts access to banned accounts and their associated devices, while preventing device circumvention through new account creation.

## Components Implemented

### 1. Database Changes (`php/db-config.php`)
**New Table: `device_bans`**
- Tracks banned devices by multiple identifiers
- Stores ban metadata (duration, reason, timestamp)
- Contains fields:
  - `id` - Primary key
  - `user_id` - Associated user account (nullable)
  - `ip_address` - Device IP address
  - `device_fingerprint` - SHA256 hash of device characteristics
  - `mac_address` - MAC address (for future use)
  - `ban_reason` - Reason for ban
  - `banned_at` - Timestamp when ban was applied
  - `banned_until` - Expiration of temporary bans (NULL for permanent)
  - `is_permanent` - Boolean flag for permanent bans
  - `user_agent` - Browser/device user agent string

### 2. Security Functions (`php/security.php`)
Added four new methods to the `SecurityManager` class:

#### `getDeviceFingerprint()`
- Creates a unique device identifier using:
  - Client IP address
  - User Agent string
  - Accept-Language header
  - Accept header
  - Accept-Encoding header
- Returns SHA256 hash for privacy

#### `getDeviceIdentifier()`
- Alternative device identifier using IP + User Agent hash
- Used for persistent device tracking

#### `isDeviceBanned($conn, $device_fingerprint, $ip_address)`
- Checks if a device is currently banned
- Returns ban details if found (user_id, ban_until, is_permanent)
- Queries both device fingerprint and IP address
- Respects temporary ban expiration times

#### `recordDeviceBan($conn, $user_id, $ip_address, $device_fingerprint, $ban_reason, $ban_duration_hours, $is_permanent, $user_agent)`
- Records a new device ban in the database
- Supports both temporary and permanent bans
- Stores all device identifiers for multi-layer protection

#### `unbanDevice($conn, $device_fingerprint, $ip_address)`
- Removes device bans for unbanning operations

### 3. Login Process Modifications (`php/login.php`)

#### Device Ban Pre-Check
- Added device fingerprinting on every login attempt
- Checks if device is banned BEFORE username/password verification
- Returns special `banned_device` error response
- Sets persistent cookies:
  - `mytad_device_banned` - Marks device as banned
  - `mytad_device_fingerprint` - Stores device fingerprint for future checks

#### Account Ban Response Enhancement
Instead of generic "Account disabled" message, now returns:
```json
{
  "error": "account_banned",
  "message": "Your access has been restricted until further notice",
  "is_permanent": false,
  "banned_until": "2025-11-25 14:30:00",
  "banned_until_formatted": "November 25, 2025 at 2:30 PM"
}
```

### 4. Registration Protection (`php/register.php`)
- Added device ban check at registration start
- Prevents account creation from banned devices
- Returns same ban response format as login
- Blocks device circumvention attempts

### 5. Ban Enforcement on Admin Ban (`php/admin-user-action.php`)

When admin bans a user:
1. Sets user `is_active = 0` and locks account with `account_locked_until`
2. Queries all active sessions for the user
3. Creates device fingerprint from each session's IP + User Agent
4. Records device ban for each session's device
5. Invalidates all user sessions
6. Supports ban duration or permanent ban

When admin unbans a user:
1. Sets user `is_active = 1` and clears lock
2. Deletes all device bans associated with the user's previous sessions

### 6. Frontend Display (`index.html`)

#### Login Handler Update
- Detects `banned_device` and `account_banned` error types
- Calls `showBannedMessage(data)` for special handling

#### New Modal Display Function: `showBannedMessage(data)`
Displays a prominent warning modal with:
- ⛔ Red warning styling
- **Title**: "Your device/account access has been restricted"
- **Ban End Date**: "Access restricted until: [date, time]" (if temporary)
- **Permanent Ban Indicator**: "⛔ PERMANENT RESTRICTION" (if permanent)
- **Warning Message**:
  ```
  ⚠️ Warning:
  Creating a new account will result in all of your accounts being 
  permanently banned, and your device will not be able to access myTAD.
  ```
- Red "Understand" button to dismiss

#### Registration Handler Update
- Detects `device_banned` error on registration attempts
- Displays same warning modal to prevent circumvention

## Flow Diagrams

### Login Flow with Device Ban
```
User attempts login
    ↓
System calculates device fingerprint
    ↓
Check: Is device banned? ──YES→ Return banned_device error
    ↓                           Display warning modal
   NO                           Show ban expiration date
    ↓
Check: Is account disabled? ──YES→ Return account_banned error
    ↓                              Display warning modal
   NO                              Show ban expiration date
    ↓
Continue normal login flow
```

### Ban Application Flow
```
Admin clicks "Ban User"
    ↓
System bans user account (is_active = 0)
    ↓
Query all active sessions for user
    ↓
For each session:
  - Calculate device fingerprint
  - Record device ban in database
    ↓
Invalidate all user sessions
    ↓
User cannot login from any device
Next attempt shows device_banned error
```

## Security Features

1. **Multi-Layer Device Identification**
   - IP address
   - Device fingerprint (browser/OS characteristics)
   - MAC address field (for future implementation)

2. **Permanent & Temporary Bans**
   - Temporary bans expire at specified time
   - Permanent bans persist indefinitely
   - Both prevent device access completely

3. **Prevention of Circumvention**
   - Device ban checked before registration
   - New account creation blocked from banned devices
   - Warning message dissuades user from trying

4. **Persistent Cookies**
   - Browser remembers device ban status
   - Survives browser restarts
   - Used for client-side pre-checking (future enhancement)

5. **Session Termination**
   - All sessions invalidated when user is banned
   - No way to maintain existing access
   - Forces complete re-authentication

## Database Migration

To apply these changes to existing database:
```php
// Access the database initialization endpoint
GET /php/db-config.php?action=init
```

The `device_bans` table will be created automatically if it doesn't exist.

## API Responses Reference

### Successful Login
```json
{
  "success": true,
  "message": "Login successful",
  "token": "unique_session_token",
  "user_id": 123,
  "username": "player_name"
}
```

### Device Banned (Login)
```json
{
  "error": "banned_device",
  "message": "Your access has been restricted",
  "is_permanent": false,
  "banned_until": "2025-11-25 14:30:00",
  "banned_until_formatted": "November 25, 2025 at 2:30 PM"
}
```

### Account Banned (Login)
```json
{
  "error": "account_banned",
  "message": "Your access has been restricted until further notice",
  "is_permanent": false,
  "banned_until": "2025-11-25 14:30:00",
  "banned_until_formatted": "November 25, 2025 at 2:30 PM"
}
```

### Device Banned (Registration)
```json
{
  "error": "device_banned",
  "message": "This device is restricted from accessing myTAD",
  "is_permanent": true
}
```

## Testing Checklist

- [ ] Temporary ban expires correctly at specified time
- [ ] Permanent bans persist indefinitely
- [ ] Device ban prevents both login and registration
- [ ] Multiple IPs from same device fingerprint are caught
- [ ] Admin can successfully ban and unban users
- [ ] Ban messages display correctly with proper formatting
- [ ] Device ban cookies are set and persist
- [ ] Sessions are properly invalidated on ban
- [ ] Warning modal appears before allowing registration from banned device
- [ ] Account unlock clears associated device bans

## Future Enhancements

1. **Client-Side Detection**: Use cookies to show ban message before server call
2. **MAC Address Collection**: Implement via WMI on Windows clients
3. **Hardware Fingerprinting**: Add more device characteristics (GPU, RAM, etc.)
4. **Geolocation Tracking**: Track bans by location
5. **Admin Dashboard**: View and manage device bans
6. **Ban Analytics**: Track ban statistics and patterns
7. **Appeal System**: Allow users to request ban review

## Files Modified

1. `/php/db-config.php` - Added device_bans table
2. `/php/security.php` - Added device fingerprinting functions
3. `/php/login.php` - Added device ban checking and enhanced banned response
4. `/php/register.php` - Added device ban prevention
5. `/php/admin-user-action.php` - Added device ban recording and clearing
6. `/index.html` - Added ban message modal and response handling

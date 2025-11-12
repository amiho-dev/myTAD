# MyTAD Setup Guide - Secure Database Configuration

## 🔒 Security First

Your database password should **NEVER** be hardcoded in version control. This project uses a secure setup process to keep your credentials safe.

---

## ⚡ Quick Setup (Recommended)

### Method 1: Using the Setup Wizard

1. **Upload `setup.php` to your server**
   ```
   https://yourdomain.com/setup.php
   ```

2. **Enter your database credentials:**
   - Database Host (e.g., `localhost` or `sql.example.com`)
   - Database User
   - Database Password
   - Database Name

3. **Click "Save Configuration"**

4. **The system will:**
   - Test the connection
   - Create `php/config.local.php` with your credentials
   - Set restricted file permissions (mode 600)
   - Automatically add it to `.gitignore`

5. **Delete `setup.php` after configuration** (it won't be needed again)

---

## 📝 Manual Setup (Alternative)

If you prefer to configure manually:

1. **Copy the template:**
   ```bash
   cp php/config.local.template.php php/config.local.php
   ```

2. **Edit `php/config.local.php` with your credentials:**
   ```php
   define('DB_HOST', 'localhost:3306');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'your_database');
   ```

3. **Set restricted permissions:**
   ```bash
   chmod 600 php/config.local.php
   ```

---

## 🛡️ Security Features

### File Permissions
- `php/config.local.php` is automatically created with **mode 600**
- Only the file owner (web server) can read and write
- No other users can access the file

### Version Control Protection
- `php/config.local.php` is in `.gitignore`
- The password is **never** committed to git
- Even if your repository is public, credentials are safe

### Setup File Security
- `setup.php` is in `.gitignore`
- Should be deleted after setup
- If left on the server, it will ask for current credentials before allowing changes

---

## 🔍 File Structure

```
myTAD/
├── setup.php                        ← Run this first (delete after)
├── .gitignore                       ← Protects config.local.php
├── php/
│   ├── config.local.php             ← YOUR CREDENTIALS (not in git)
│   ├── config.local.template.php    ← Template for manual setup
│   ├── db-config.php                ← Loads config.local.php
│   ├── login.php
│   ├── register.php
│   └── ... other PHP files
├── index.html
└── ... other files
```

---

## ✅ Verify Setup

After configuration, verify everything is working:

1. Visit `https://yourdomain.com/php/test-connection.php`
2. Should show:
   - All constants defined: YES
   - Connection result: SUCCESS
   - Tables found: 11

3. Try logging in:
   - Visit the login page
   - Enter test credentials
   - Should work without "Connection error"

---

## 🚨 Troubleshooting

### "Database configuration missing"
- Run `setup.php` to configure
- Or manually create `php/config.local.php`

### "Connection failed"
- Check database host, user, password, and name
- Verify database server is running
- Check database user has permissions

### "Failed to write config file"
- Ensure `php/` directory is writable
- Set directory permissions: `chmod 755 php/`

### "Permission denied" when running setup
- Server needs write access to `php/` directory
- Run: `chmod 755 php/`

---

## 🔐 Best Practices

✅ **DO:**
- Use strong database passwords
- Set file permissions to 600
- Delete `setup.php` after configuration
- Use `.gitignore` to protect sensitive files
- Regenerate passwords regularly

❌ **DON'T:**
- Commit `php/config.local.php` to git
- Share your database password
- Leave `setup.php` on the server permanently
- Use weak passwords
- Commit hardcoded credentials

---

## 🔄 Updating Configuration

If you need to change your database credentials:

1. **Delete the old config:**
   ```bash
   rm php/config.local.php
   ```

2. **Run setup again:**
   - Visit `setup.php`
   - Enter new credentials
   - Click "Save Configuration"

---

## ❓ Questions?

- Check `setup.php` for an interactive configuration wizard
- Review `php/config.local.template.php` for the correct format
- Verify permissions: `ls -la php/config.local.php` should show `600`

---

## 📋 Configuration Checklist

- [ ] Run setup.php
- [ ] Enter database credentials
- [ ] Connection test passes
- [ ] config.local.php created
- [ ] File permissions set to 600
- [ ] .gitignore includes config.local.php
- [ ] Test login works
- [ ] Delete setup.php
- [ ] Verify credentials not in git history

---

**Last Updated:** November 12, 2025
**Security Level:** 🟢 Secure (No hardcoded credentials)

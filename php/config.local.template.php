<?php
/**
 * MyTAD - Database Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to: php/config.local.php
 * 2. Update the values with your actual database credentials
 * 3. Set file permissions to 600: chmod 600 php/config.local.php
 * 4. OR use setup.php to generate this automatically
 * 
 * DO NOT COMMIT THIS FILE TO GIT!
 * It contains sensitive information and should be in .gitignore
 */

// Your database host (usually localhost for shared hosting)
define('DB_HOST', 'localhost:3306');

// Your database username
define('DB_USER', 'database_username');

// Your database password
define('DB_PASS', 'your_secure_password_here');

// Your database name
define('DB_NAME', 'database_name');

// Optional: Add these if you need to connect to a different port
// Modify DB_HOST above to use a different port, e.g., 'hostname:3307'

?>

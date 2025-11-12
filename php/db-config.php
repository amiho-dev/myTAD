<?php
/**
 * Iron Dominion - Database Configuration & Initialization
 * 
 * Instructions for Plesk Database Setup:
 * 1. Log in to your Plesk Control Panel
 * 2. Go to Databases
 * 3. Create a new database (e.g., "iron_dominion")
 * 4. Note the database name, username, and password
 * 5. Update the configuration below
 * 6. Run this file once or visit it in your browser to initialize the database
 */

// Database credentials - UPDATE THESE FOR YOUR PLESK DATABASE
define('DB_HOST', 'localhost:3306');      // dominion subdomain MariaDB
define('DB_USER', 'mytad');               // MariaDB username
define('DB_PASS', 'y+nQzZa4BS?!,;A');     // MariaDB password
define('DB_NAME', 'mytad');               // MariaDB database name

// Cookie settings
define('REMEMBER_ME_DURATION', 30 * 24 * 60 * 60); // 30 days in seconds
define('SESSION_DURATION', 24 * 60 * 60);          // 24 hours in seconds

/**
 * Establish database connection
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        // Set charset to utf8mb4
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection error: ' . $e->getMessage()]));
    }
}

/**
 * Initialize database - Create all security-related tables
 * Call this function once to set up the database structure
 */
function initializeDatabase() {
    $conn = getDBConnection();
    $errors = [];
    
    // SQL to create users table with enhanced security fields
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        last_password_change TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        is_email_verified TINYINT(1) DEFAULT 0,
        email_verification_token VARCHAR(255) NULL,
        two_factor_enabled TINYINT(1) DEFAULT 0,
        two_factor_secret VARCHAR(255) NULL,
        password_reset_token VARCHAR(255) NULL,
        password_reset_expires TIMESTAMP NULL,
        account_locked_until TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_email_verified (is_email_verified)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_users) === TRUE) {
        $result_users = ['success' => true];
    } else {
        $result_users = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Users table: ' . $conn->error;
    }
    
    // Create sessions table
    $sql_sessions = "CREATE TABLE IF NOT EXISTS sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        last_activity TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_token (token),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_sessions) === TRUE) {
        $result_sessions = ['success' => true];
    } else {
        $result_sessions = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Sessions table: ' . $conn->error;
    }
    
    // Create login attempts table for brute force protection
    $sql_login_attempts = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        username VARCHAR(255),
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) DEFAULT 0,
        reason VARCHAR(255),
        INDEX idx_ip_address (ip_address),
        INDEX idx_username (username),
        INDEX idx_attempted_at (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_login_attempts) === TRUE) {
        $result_login_attempts = ['success' => true];
    } else {
        $result_login_attempts = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Login attempts table: ' . $conn->error;
    }
    
    // Create password reset tokens table
    $sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        used_at TIMESTAMP NULL,
        ip_address VARCHAR(45),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_token (token),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_password_resets) === TRUE) {
        $result_password_resets = ['success' => true];
    } else {
        $result_password_resets = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Password resets table: ' . $conn->error;
    }
    
    // Create audit log table
    $sql_audit_log = "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(255) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_audit_log) === TRUE) {
        $result_audit_log = ['success' => true];
    } else {
        $result_audit_log = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Audit log table: ' . $conn->error;
    }
    
    // Create two factor backup codes table
    $sql_backup_codes = "CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(255) NOT NULL UNIQUE,
        used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_backup_codes) === TRUE) {
        $result_backup_codes = ['success' => true];
    } else {
        $result_backup_codes = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Backup codes table: ' . $conn->error;
    }
    
    // Create IP whitelist table
    $sql_ip_whitelist = "CREATE TABLE IF NOT EXISTS ip_whitelist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        device_name VARCHAR(255),
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_ip (user_id, ip_address),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_ip_whitelist) === TRUE) {
        $result_ip_whitelist = ['success' => true];
    } else {
        $result_ip_whitelist = ['success' => false, 'error' => $conn->error];
        $errors[] = 'IP whitelist table: ' . $conn->error;
    }
    
    // Create admin table
    $sql_admins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_admins) === TRUE) {
        $result_admins = ['success' => true];
        
        // Insert first admin user "tad" if they exist
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $username = "tad";
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt = $conn->prepare("INSERT IGNORE INTO admins (user_id) VALUES (?)");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $result_admins = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Admins table: ' . $conn->error;
    }
    
    // Create ban_exclusions table
    $sql_ban_exclusions = "CREATE TABLE IF NOT EXISTS ban_exclusions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        reason VARCHAR(500),
        added_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_ban_exclusions) === TRUE) {
        $result_ban_exclusions = ['success' => true];
        
        // Add initial exclusions: tad and thatoneamiho
        $exclusion_users = ['tad', 'thatoneamiho'];
        foreach ($exclusion_users as $excl_user) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $excl_user);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $reason = 'Protected admin account';
                $stmt = $conn->prepare("INSERT IGNORE INTO ban_exclusions (user_id, reason) VALUES (?, ?)");
                $stmt->bind_param("is", $user['id'], $reason);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $result_ban_exclusions = ['success' => false, 'error' => $conn->error];
        $errors[] = 'Ban exclusions table: ' . $conn->error;
    }
    
    $conn->close();
    
    if (count($errors) === 0) {
        return ['success' => true, 'message' => 'All security tables created successfully'];
    } else {
        return ['success' => false, 'errors' => $errors];
    }
}

/**
 * AJAX endpoint to initialize database
 * This should only be accessible during first setup
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'init') {
    // Initialize database
    echo json_encode(initializeDatabase());
    exit;
}

/**
 * Enable CORS for development (adjust for production)
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>

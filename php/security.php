<?php
/**
 * Security Utilities
 * Comprehensive security functions for the login system
 */

class SecurityManager {
    
    /**
     * Hash a password using bcrypt (industry standard)
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify a password against its hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate a secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get client IP address (handles proxies)
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }
    
    /**
     * Get user agent
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    }
    
    /**
     * Check rate limiting (brute force protection)
     */
    public static function checkRateLimit($conn, $identifier, $max_attempts = 5, $window_minutes = 15) {
        $cutoff_time = date('Y-m-d H:i:s', time() - ($window_minutes * 60));
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempted_at > ?
            AND success = 0
        ");
        
        $stmt->bind_param('ss', $identifier, $cutoff_time);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result['attempt_count'] >= $max_attempts;
    }
    
    /**
     * Log login attempt
     */
    public static function logLoginAttempt($conn, $ip_address, $username, $success, $reason = '') {
        $stmt = $conn->prepare("
            INSERT INTO login_attempts (ip_address, username, success, reason)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param('ssis', $ip_address, $username, $success, $reason);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Log action to audit log
     */
    public static function logAction($conn, $user_id, $action, $description = '', $ip_address = '', $user_agent = '') {
        $ip_address = $ip_address ?: self::getClientIP();
        $user_agent = $user_agent ?: self::getUserAgent();
        $user_id = $user_id ?: null;
        
        $stmt = $conn->prepare("
            INSERT INTO audit_log (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('issss', $user_id, $action, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Lock user account after failed attempts
     */
    public static function lockUserAccount($conn, $user_id, $duration_minutes = 30) {
        $lock_until = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET account_locked_until = ?, failed_login_attempts = failed_login_attempts + 1
            WHERE id = ?
        ");
        
        $stmt->bind_param('si', $lock_until, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Check if user account is locked
     */
    public static function isAccountLocked($conn, $user_id) {
        $stmt = $conn->prepare("
            SELECT account_locked_until FROM users WHERE id = ?
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) return false;
        
        if ($result['account_locked_until'] === null) {
            return false;
        }
        
        $lock_time = strtotime($result['account_locked_until']);
        return time() < $lock_time;
    }
    
    /**
     * Unlock user account
     */
    public static function unlockUserAccount($conn, $user_id) {
        $stmt = $conn->prepare("
            UPDATE users 
            SET account_locked_until = NULL, failed_login_attempts = 0
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Validate email format
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return $errors;
    }
    
    /**
     * Sanitize username
     */
    public static function sanitizeUsername($username) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    }
    
    /**
     * Generate backup codes for 2FA
     */
    public static function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8));
        }
        return $codes;
    }
    
    /**
     * Store backup codes in database
     */
    public static function storeBackupCodes($conn, $user_id, $codes) {
        // Clear old codes
        $stmt = $conn->prepare("DELETE FROM two_factor_backup_codes WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Store new codes (hashed)
        foreach ($codes as $code) {
            $hashed_code = self::hashPassword($code);
            $stmt = $conn->prepare("
                INSERT INTO two_factor_backup_codes (user_id, code)
                VALUES (?, ?)
            ");
            $stmt->bind_param('is', $user_id, $hashed_code);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Validate backup code and mark as used
     */
    public static function validateAndUseBackupCode($conn, $user_id, $code) {
        $stmt = $conn->prepare("
            SELECT id FROM two_factor_backup_codes 
            WHERE user_id = ? AND used_at IS NULL
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $found = false;
        while ($row = $result->fetch_assoc()) {
            if (self::verifyPassword($code, $row['code'])) {
                $found = true;
                // Mark as used
                $stmt = $conn->prepare("
                    UPDATE two_factor_backup_codes 
                    SET used_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $row['id']);
                $stmt->execute();
                $stmt->close();
                break;
            }
        }
        
        return $found;
    }
    
    /**
     * Send email notification
     */
    public static function sendEmail($to, $subject, $body, $html = false) {
        $headers = "From: security@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: support@" . $_SERVER['HTTP_HOST'] . "\r\n";
        
        if ($html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }
        
        $headers .= "X-Priority: 3\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Send login notification email
     */
    public static function sendLoginNotificationEmail($email, $ip, $user_agent, $is_new_device = false) {
        $subject = "Login Activity - " . date('Y-m-d H:i:s');
        
        $html = "<h2>Login Activity Detected</h2>";
        if ($is_new_device) {
            $html .= "<p><strong>⚠️ NEW DEVICE</strong></p>";
        }
        $html .= "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $html .= "<p><strong>IP Address:</strong> " . htmlspecialchars($ip) . "</p>";
        $html .= "<p><strong>Device:</strong> " . htmlspecialchars(substr($user_agent, 0, 100)) . "</p>";
        
        if ($is_new_device) {
            $html .= "<p><strong style='color: red;'>This login is from a new device.</strong></p>";
            $html .= "<p>If this wasn't you, please change your password immediately.</p>";
        } else {
            $html .= "<p>This login is from a recognized device.</p>";
        }
        
        return self::sendEmail($email, $subject, $html, true);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($email, $reset_token, $username) {
        $reset_url = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.html?token=" . urlencode($reset_token);
        
        $subject = "Password Reset Request";
        $html = "<h2>Password Reset Request</h2>";
        $html .= "<p>Hi " . htmlspecialchars($username) . ",</p>";
        $html .= "<p>You requested to reset your password. Click the link below to proceed:</p>";
        $html .= "<p><a href='" . htmlspecialchars($reset_url) . "' style='background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>";
        $html .= "<p>This link will expire in 1 hour.</p>";
        $html .= "<p>If you didn't request this, please ignore this email.</p>";
        
        return self::sendEmail($email, $subject, $html, true);
    }
    
    /**
     * Verify email address
     */
    public static function sendVerificationEmail($email, $token, $username) {
        $verify_url = "https://" . $_SERVER['HTTP_HOST'] . "/verify-email.html?token=" . urlencode($token);
        
        $subject = "Verify Your Email Address";
        $html = "<h2>Verify Your Email</h2>";
        $html .= "<p>Hi " . htmlspecialchars($username) . ",</p>";
        $html .= "<p>Please verify your email address by clicking the link below:</p>";
        $html .= "<p><a href='" . htmlspecialchars($verify_url) . "' style='background-color: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verify Email</a></p>";
        $html .= "<p>This link will expire in 24 hours.</p>";
        
        return self::sendEmail($email, $subject, $html, true);
    }
}

// Enable CORS for development (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>

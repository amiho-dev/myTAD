<?php
/**
 * Secure Login Endpoint
 * 
 * Endpoint: POST /php/login.php
 * 
 * Request body:
 * {
 *   "username": "username",
 *   "password": "securePassword123",
 *   "remember_me": true,
 *   "csrf_token": "token_if_needed"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Login successful",
 *   "requires_2fa": false,
 *   "user_id": 1,
 *   "username": "username",
 *   "email": "email@example.com",
 *   "token": "unique_session_token"
 * }
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get client IP and user agent
$client_ip = SecurityManager::getClientIP();
$user_agent = SecurityManager::getUserAgent();
$device_fingerprint = SecurityManager::getDeviceFingerprint();

// Get JSON request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    SecurityManager::logLoginAttempt($conn ?? null, $client_ip, $input['username'] ?? 'unknown', 0, 'Missing credentials');
    echo json_encode(['error' => 'Missing username or password']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];
$remember_me = isset($input['remember_me']) ? (bool)$input['remember_me'] : false;

try {
    $conn = getDBConnection();
    
    // Check rate limiting (brute force protection)
    if (SecurityManager::checkRateLimit($conn, $client_ip, 5, 15)) {
        http_response_code(429);
        SecurityManager::logLoginAttempt($conn, $client_ip, $username, 0, 'Rate limit exceeded');
        echo json_encode(['error' => 'Too many login attempts. Please try again later.']);
        $conn->close();
        exit;
    }
    
    // Check if device is banned
    $device_ban = SecurityManager::isDeviceBanned($conn, $device_fingerprint, $client_ip);
    if ($device_ban) {
        // Device is banned, don't reveal which user they're trying to log in as
        http_response_code(403);
        
        // Determine if ban is permanent
        $is_permanent = (bool)$device_ban['is_permanent'];
        $banned_until = $device_ban['banned_until'];
        
        // Set a cookie to remember this device is banned
        $cookie_expires = $is_permanent ? time() + (365 * 24 * 60 * 60) : strtotime($banned_until);
        setcookie(
            'mytad_device_banned',
            '1',
            $cookie_expires,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true  // HttpOnly
        );
        
        setcookie(
            'mytad_device_fingerprint',
            $device_fingerprint,
            $cookie_expires,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true  // HttpOnly
        );
        
        $response = [
            'error' => 'banned_device',
            'message' => 'Your access has been restricted',
            'is_permanent' => $is_permanent
        ];
        
        if (!$is_permanent && $banned_until) {
            $response['banned_until'] = $banned_until;
            $response['banned_until_formatted'] = date('F j, Y \a\t g:i A', strtotime($banned_until));
        }
        
        SecurityManager::logLoginAttempt($conn, $client_ip, $username ?? 'unknown', 0, 'Device banned');
        echo json_encode($response);
        $conn->close();
        exit;
    }
    
    // Find user by username
    $stmt = $conn->prepare("
        SELECT id, username, email, password_hash, is_active, is_email_verified, two_factor_enabled, account_locked_until
        FROM users 
        WHERE username = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        // Generic message for security (don't reveal if user exists)
        http_response_code(401);
        SecurityManager::logLoginAttempt($conn, $client_ip, $username, 0, 'User not found');
        echo json_encode(['error' => 'Invalid username or password']);
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Check if user is active
    if (!$user['is_active']) {
        http_response_code(403);
        SecurityManager::logLoginAttempt($conn, $client_ip, $username, 0, 'Account disabled');
        
        // Check if there's a temporary ban
        $is_permanent = false;
        $ban_expires_at = null;
        
        if ($user['account_locked_until']) {
            $lock_time = strtotime($user['account_locked_until']);
            $now = time();
            
            if ($lock_time > $now) {
                // Temporary ban (account locked)
                $is_permanent = false;
                $ban_expires_at = $user['account_locked_until'];
            } else {
                // Lock has expired but account is still inactive, so it's a permanent ban
                $is_permanent = true;
            }
        } else {
            // No unlock time set, so it's a permanent ban
            $is_permanent = true;
        }
        
        $response = [
            'error' => 'account_banned',
            'message' => 'Your access has been restricted until further notice',
            'is_permanent' => $is_permanent
        ];
        
        if (!$is_permanent && $ban_expires_at) {
            $response['banned_until'] = $ban_expires_at;
            $response['banned_until_formatted'] = date('F j, Y \a\t g:i A', strtotime($ban_expires_at));
        }
        
        echo json_encode($response);
        $conn->close();
        exit;
    }
    
    // Check if account is locked
    if (SecurityManager::isAccountLocked($conn, $user['id'])) {
        http_response_code(429);
        SecurityManager::logLoginAttempt($conn, $client_ip, $username, 0, 'Account locked');
        echo json_encode(['error' => 'Account is temporarily locked. Try again later.']);
        $conn->close();
        exit;
    }
    
    // Verify password
    if (!SecurityManager::verifyPassword($password, $user['password_hash'])) {
        // Increment failed attempts
        $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();
        
        // Log failed attempt
        SecurityManager::logLoginAttempt($conn, $client_ip, $username, 0, 'Invalid password');
        
        // Check if we should lock the account
        $stmt = $conn->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['failed_login_attempts'] >= 5) {
            SecurityManager::lockUserAccount($conn, $user['id'], 30);
            SecurityManager::logAction($conn, $user['id'], 'ACCOUNT_LOCKED', 'Account locked due to failed login attempts', $client_ip, $user_agent);
        }
        
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        $conn->close();
        exit;
    }
    
    // Password is correct - clear failed attempts
    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
    
    // Log successful login attempt
    SecurityManager::logLoginAttempt($conn, $client_ip, $username, 1, 'Successful login');
    SecurityManager::logAction($conn, $user['id'], 'LOGIN', 'User login', $client_ip, $user_agent);
    
    // Check if this is a new device
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM sessions 
        WHERE user_id = ? AND ip_address = ?
    ");
    
    $stmt->bind_param("is", $user['id'], $client_ip);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $is_new_device = $result['count'] === 0;
    
    // Send login notification email
    SecurityManager::sendLoginNotificationEmail($user['email'], $client_ip, $user_agent, $is_new_device);
    
    // Check if 2FA is enabled
    if ($user['two_factor_enabled']) {
        // Store temporary session for 2FA verification
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_username'] = $user['username'];
        $_SESSION['pending_email'] = $user['email'];
        $_SESSION['pending_created'] = time();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'requires_2fa' => true,
            'message' => 'Please complete two-factor authentication'
        ]);
        
        $conn->close();
        exit;
    }
    
    // Generate session token
    $session_token = SecurityManager::generateToken(32);
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    // Store session in database
    $stmt = $conn->prepare("
        INSERT INTO sessions (user_id, token, ip_address, user_agent, created_at, expires_at, last_activity)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("issss", $user['id'], $session_token, $client_ip, $user_agent, $expires_at);
    $stmt->execute();
    $stmt->close();
    
    // Store session data in PHP session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['token'] = $session_token;
    $_SESSION['created'] = time();
    $_SESSION['ip_address'] = $client_ip;
    
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'requires_2fa' => false,
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'token' => $session_token,
        'is_email_verified' => (bool)$user['is_email_verified']
    ];
    
    // Handle "Remember Me" cookie
    if ($remember_me) {
        $cookie_token = SecurityManager::generateToken(32);
        $cookie_expires = time() + REMEMBER_ME_DURATION;
        
        // Store remember-me token in database
        $stmt = $conn->prepare("
            INSERT INTO sessions (user_id, token, ip_address, user_agent, created_at, expires_at, last_activity)
            VALUES (?, ?, ?, ?, NOW(), ?, NOW())
        ");
        
        $expires_at_cookie = date('Y-m-d H:i:s', $cookie_expires);
        $stmt->bind_param("issss", $user['id'], $cookie_token, $client_ip, $user_agent, $expires_at_cookie);
        $stmt->execute();
        $stmt->close();
        
        // Set HttpOnly cookies for security (not accessible via JavaScript)
        setcookie(
            'mytad_session',
            $session_token,
            $cookie_expires,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true  // HttpOnly - not accessible via JavaScript for security
        );
        
        setcookie(
            'mytad_user',
            base64_encode($user['id'] . ':' . $user['username']),
            $cookie_expires,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        
        $response['remember_me'] = true;
    }
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    SecurityManager::logAction($conn ?? null, $_SESSION['user_id'] ?? null, 'LOGIN_ERROR', $e->getMessage(), $client_ip, $user_agent);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}
?>

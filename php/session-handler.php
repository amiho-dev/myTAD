<?php
/**
 * Session Management Handler
 * Handles session creation, validation, and refresh
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get current session (requires Authorization header or cookie)
 * GET /php/session-handler.php?action=get
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = null;
    
    // Check for Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) === 2 && $parts[0] === 'Bearer') {
            $token = $parts[1];
        }
    }
    
    // Check for session token in session
    if (!$token && isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token found']);
        exit;
    }
    
    try {
        $conn = getDBConnection();
        
        // Validate session token
        $stmt = $conn->prepare("
            SELECT s.user_id, s.expires_at, u.username, u.email, u.is_email_verified
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? AND s.is_active = 1
        ");
        
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired session']);
            $conn->close();
            exit;
        }
        
        $session = $result->fetch_assoc();
        
        // Check if session has expired
        if (strtotime($session['expires_at']) < time()) {
            // Invalidate session
            $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            
            http_response_code(401);
            echo json_encode(['error' => 'Session expired']);
            $conn->close();
            exit;
        }
        
        // Update last activity
        $stmt = $conn->prepare("UPDATE sessions SET last_activity = NOW() WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'user_id' => $session['user_id'],
            'username' => $session['username'],
            'email' => $session['email'],
            'is_email_verified' => (bool)$session['is_email_verified']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    
    exit;
}

/**
 * Refresh session token
 * POST /php/session-handler.php?action=refresh
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'refresh') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = null;
    
    // Check for Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) === 2 && $parts[0] === 'Bearer') {
            $token = $parts[1];
        }
    }
    
    // Check for session token in session
    if (!$token && isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token found']);
        exit;
    }
    
    try {
        $conn = getDBConnection();
        
        // Validate existing session
        $stmt = $conn->prepare("
            SELECT user_id FROM sessions
            WHERE token = ? AND is_active = 1
        ");
        
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid session']);
            $conn->close();
            exit;
        }
        
        $session = $result->fetch_assoc();
        $user_id = $session['user_id'];
        
        // Generate new token
        $new_token = SecurityManager::generateToken(32);
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        $client_ip = SecurityManager::getClientIP();
        $user_agent = SecurityManager::getUserAgent();
        
        // Insert new session
        $stmt = $conn->prepare("
            INSERT INTO sessions (user_id, token, ip_address, user_agent, created_at, expires_at, last_activity)
            VALUES (?, ?, ?, ?, NOW(), ?, NOW())
        ");
        
        $stmt->bind_param("issss", $user_id, $new_token, $client_ip, $user_agent, $expires_at);
        $stmt->execute();
        $stmt->close();
        
        // Optionally invalidate old token
        $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'token' => $new_token,
            'message' => 'Token refreshed'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    
    exit;
}

/**
 * Logout - invalidate session
 * POST /php/session-handler.php?action=logout
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = null;
    
    // Check for Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) === 2 && $parts[0] === 'Bearer') {
            $token = $parts[1];
        }
    }
    
    // Check for session token in session
    if (!$token && isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    
    try {
        if ($token) {
            $conn = getDBConnection();
            
            // Invalidate session
            $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            
            if (isset($_SESSION['user_id'])) {
                SecurityManager::logAction($conn, $_SESSION['user_id'], 'LOGOUT', 'User logout');
            }
            
            $conn->close();
        }
        
        // Clear session
        $_SESSION = [];
        session_destroy();
        
        // Clear cookies
        setcookie('mytad_session', '', time() - 3600, '/');
        setcookie('mytad_user', '', time() - 3600, '/');
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    
    exit;
}

/**
 * Get all active sessions for current user
 * GET /php/session-handler.php?action=list-sessions
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list-sessions') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = null;
    
    // Check for Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) === 2 && $parts[0] === 'Bearer') {
            $token = $parts[1];
        }
    }
    
    if (!$token && isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token']);
        exit;
    }
    
    try {
        $conn = getDBConnection();
        
        // Get user ID from token
        $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND is_active = 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid session']);
            $conn->close();
            exit;
        }
        
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Get all active sessions
        $stmt = $conn->prepare("
            SELECT token, ip_address, user_agent, created_at, last_activity, expires_at
            FROM sessions
            WHERE user_id = ? AND is_active = 1
            ORDER BY last_activity DESC
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'sessions' => $sessions
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    
    exit;
}

/**
 * Terminate a specific session
 * POST /php/session-handler.php?action=terminate-session
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'terminate-session') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $target_token = $input['token'] ?? null;
    $current_token = null;
    
    // Check for Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) === 2 && $parts[0] === 'Bearer') {
            $current_token = $parts[1];
        }
    }
    
    if (!$current_token && isset($_SESSION['token'])) {
        $current_token = $_SESSION['token'];
    }
    
    if (!$current_token || !$target_token) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing token']);
        exit;
    }
    
    try {
        $conn = getDBConnection();
        
        // Verify current session belongs to user
        $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND is_active = 1");
        $stmt->bind_param("s", $current_token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid session']);
            $conn->close();
            exit;
        }
        
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Verify target session belongs to same user
        $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND user_id = ?");
        $stmt->bind_param("si", $target_token, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            $conn->close();
            exit;
        }
        
        // Terminate target session
        $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE token = ?");
        $stmt->bind_param("s", $target_token);
        $stmt->execute();
        $stmt->close();
        
        SecurityManager::logAction($conn, $user_id, 'TERMINATE_SESSION', 'User terminated a session');
        
        $conn->close();
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Session terminated']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>

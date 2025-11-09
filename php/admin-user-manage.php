<?php
/**
 * Admin Security - Disable/Enable User
 * POST /php/admin-user-manage.php
 * 
 * Request:
 * {
 *   "action": "disable|enable|lock|unlock",
 *   "user_id": 123
 * }
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = null;

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
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? '');
$target_user_id = intval($input['user_id'] ?? 0);

if (!$action || !in_array($action, ['disable', 'enable', 'lock', 'unlock']) || !$target_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action or user_id']);
    exit;
}

try {
    $conn = getDBConnection();
    $client_ip = SecurityManager::getClientIP();
    $user_agent = SecurityManager::getUserAgent();
    
    // Get admin user from token
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
    
    $admin_user_id = $result->fetch_assoc()['user_id'];
    
    // Verify admin has permission (add is_admin check in production)
    
    // Get target user
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        $conn->close();
        exit;
    }
    
    $target_user = $result->fetch_assoc();
    
    // Prevent admin from managing their own account
    if ($admin_user_id === $target_user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot manage your own account']);
        $conn->close();
        exit;
    }
    
    switch ($action) {
        case 'disable':
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            // Invalidate all sessions
            $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            SecurityManager::logAction($conn, $admin_user_id, 'USER_DISABLED', "User {$target_user['username']} disabled", $client_ip, $user_agent);
            SecurityManager::logAction($conn, $target_user_id, 'ACCOUNT_DISABLED', "Account disabled by admin", $client_ip, $user_agent);
            
            $message = 'User disabled successfully';
            break;
            
        case 'enable':
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            SecurityManager::logAction($conn, $admin_user_id, 'USER_ENABLED', "User {$target_user['username']} enabled", $client_ip, $user_agent);
            
            $message = 'User enabled successfully';
            break;
            
        case 'lock':
            SecurityManager::lockUserAccount($conn, $target_user_id, 1440); // 24 hours
            SecurityManager::logAction($conn, $admin_user_id, 'USER_LOCKED', "User {$target_user['username']} locked", $client_ip, $user_agent);
            
            $message = 'User account locked for 24 hours';
            break;
            
        case 'unlock':
            SecurityManager::unlockUserAccount($conn, $target_user_id);
            SecurityManager::logAction($conn, $admin_user_id, 'USER_UNLOCKED', "User {$target_user['username']} unlocked", $client_ip, $user_agent);
            
            $message = 'User account unlocked';
            break;
    }
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'action' => $action,
        'user_id' => $target_user_id,
        'username' => $target_user['username']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

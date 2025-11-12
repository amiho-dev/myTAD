<?php
/**
 * Admin Management - Grant/Revoke Admin Roles
 * POST /php/manage-admin-role.php
 * 
 * Request:
 * {
 *   "action": "grant|revoke",
 *   "user_id": 123
 * }
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

if (!$action || !in_array($action, ['grant', 'revoke']) || !$target_user_id) {
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
    
    // Check if user is admin
    if (!SecurityManager::isUserAdmin($conn, $admin_user_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        $conn->close();
        exit;
    }
    
    // Get target user
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
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
    
    if ($action === 'grant') {
        SecurityManager::grantAdminRole($conn, $target_user_id, $admin_user_id);
        SecurityManager::logAction($conn, $admin_user_id, 'GRANT_ADMIN', "Granted admin role to {$target_user['username']}", $client_ip, $user_agent);
        $message = 'Admin privileges granted';
    } else {
        SecurityManager::revokeAdminRole($conn, $target_user_id);
        SecurityManager::logAction($conn, $admin_user_id, 'REVOKE_ADMIN', "Revoked admin role from {$target_user['username']}", $client_ip, $user_agent);
        $message = 'Admin privileges revoked';
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
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

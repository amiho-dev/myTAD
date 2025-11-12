<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db-config.php';
require_once 'security.php';

try {
    // Get the Bearer token from Authorization header
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid token']);
        exit;
    }
    
    $token = $matches[1];
    
    // Verify token in database
    $stmt = $conn->prepare("
        SELECT user_id FROM sessions 
        WHERE token = ? AND expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $token_result = $stmt->get_result();
    $stmt->close();
    
    if ($token_result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit;
    }
    
    $token_user = $token_result->fetch_assoc();
    $user_id = $token_user['user_id'];
    
    // Check if user is admin
    if (!SecurityManager::isUserAdmin($conn, $user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        exit;
    }
    
    // Handle GET request (list exclusions)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $exclusions = SecurityManager::getBanExclusionList($conn);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'exclusions' => $exclusions,
            'total' => count($exclusions)
        ]);
        exit;
    }
    
    // Handle POST request (add/remove)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = isset($data['action']) ? $data['action'] : '';
        $target_user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $reason = isset($data['reason']) ? trim($data['reason']) : '';
        
        if (!$action || !$target_user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }
        
        // Prevent self-exclusion
        if ($target_user_id === $user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot exclude yourself']);
            exit;
        }
        
        // Verify target user exists
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $stmt->close();
        
        if ($user_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        
        $target_user = $user_result->fetch_assoc();
        
        if ($action === 'add') {
            if (!$reason) {
                $reason = 'Added to protection list';
            }
            
            SecurityManager::addBanExclusion($conn, $target_user_id, $reason, $user_id);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $target_user['username'] . ' is now protected from bans',
                'action' => 'add',
                'user_id' => $target_user_id,
                'username' => $target_user['username']
            ]);
            exit;
        }
        
        if ($action === 'remove') {
            SecurityManager::removeBanExclusion($conn, $target_user_id);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $target_user['username'] . ' is no longer protected',
                'action' => 'remove',
                'user_id' => $target_user_id,
                'username' => $target_user['username']
            ]);
            exit;
        }
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    error_log($e->getMessage());
}

$conn->close();
?>

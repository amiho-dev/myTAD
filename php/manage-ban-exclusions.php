<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once 'db-config.php';
require_once 'security.php';

try {
    // Debug: Log all headers received
    error_log('=== REQUEST HEADERS ===');
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            error_log($key . ': ' . substr($value, 0, 50));
        }
    }
    error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
    
    // Get the Bearer token from Authorization header or custom header
    $auth_header = '';
    
    // Try multiple ways to get token (handles proxies/Cloudflare)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        error_log('Token from HTTP_AUTHORIZATION');
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        error_log('Token from REDIRECT_HTTP_AUTHORIZATION');
    } elseif (isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        // Custom header workaround for Cloudflare/proxies
        $auth_header = 'Bearer ' . $_SERVER['HTTP_X_AUTH_TOKEN'];
        error_log('Token from HTTP_X_AUTH_TOKEN (custom header)');
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
            error_log('Token from getallheaders() Authorization');
        } elseif (isset($headers['authorization'])) {
            $auth_header = $headers['authorization'];
            error_log('Token from getallheaders() authorization (lowercase)');
        } elseif (isset($headers['X-Auth-Token'])) {
            $auth_header = 'Bearer ' . $headers['X-Auth-Token'];
            error_log('Token from getallheaders() X-Auth-Token');
        }
    }
    
    error_log('Auth header value: ' . substr($auth_header, 0, 50));
    
    if (!preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
        http_response_code(401);
        error_log('ERROR: No bearer token found in header');
        error_log('Auth header: ' . $auth_header);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid token']);
        exit;
    }
    
    $token = trim($matches[1]);
    error_log('TOKEN FOUND: ' . substr($token, 0, 20) . '...');
    
    // Verify token in database
    $stmt = $conn->prepare("
        SELECT user_id FROM sessions 
        WHERE token = ? AND expires_at > NOW() 
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $token_result = $stmt->get_result();
    $stmt->close();
    
    error_log('Token lookup returned ' . $token_result->num_rows . ' rows');
    
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
        
        // Prevent adding admins to ban exclusions (admins cannot be banned)
        if ($action === 'add' && SecurityManager::isUserAdmin($conn, $target_user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Admins cannot be added to ban exclusion list']);
            exit;
        }
        
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

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config and security
require_once 'db-config.php';
require_once 'security.php';

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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get user from token
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        $conn->close();
        exit;
    }
    
    $user_id = $result->fetch_assoc()['user_id'];
    
    // Check if user is admin
    if (!SecurityManager::isUserAdmin($conn, $user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        $conn->close();
        exit;
    }
    
    // Get search parameter if provided
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if (!empty($search)) {
        // Search for users by username or email
        $search_term = '%' . $conn->real_escape_string($search) . '%';
        
        $users_stmt = $conn->prepare(
            "SELECT id, username, email, created_at, last_login, is_active 
             FROM users 
             WHERE username LIKE ? OR email LIKE ? 
             ORDER BY created_at DESC 
             LIMIT 50"
        );
        
        if (!$users_stmt) {
            throw new Exception($conn->error);
        }
        
        $users_stmt->bind_param("ss", $search_term, $search_term);
    } else {
        // Fetch all users
        $users_stmt = $conn->prepare(
            "SELECT id, username, email, created_at, last_login, is_active 
             FROM users 
             ORDER BY created_at DESC 
             LIMIT 100"
        );
        
        if (!$users_stmt) {
            throw new Exception($conn->error);
        }
    }
    
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();

    $users = [];
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
    $users_stmt->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    error_log($e->getMessage());
}

if (isset($conn)) {
    $conn->close();
}
?>

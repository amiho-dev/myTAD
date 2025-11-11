<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Include database config and security
require_once 'db-config.php';
require_once 'security.php';

// Get token from Authorization header or session
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$new_password = isset($data['new_password']) ? $data['new_password'] : '';

if (!$target_user_id || !$new_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
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

    // Prevent admin from resetting own password this way
    if ($target_user_id === $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot reset your own password via admin panel']);
        $conn->close();
        exit;
    }

    // Validate new password
    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        $conn->close();
        exit;
    }

    // Verify target user exists
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $user_check->bind_param("i", $target_user_id);
    $user_check->execute();
    $user_check_result = $user_check->get_result();
    $user_check->close();
    
    if ($user_check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $conn->close();
        exit;
    }

    // Hash new password
    $password_hash = SecurityManager::hashPassword($new_password);

    // Update password
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update_stmt->bind_param("si", $password_hash, $target_user_id);

    if ($update_stmt->execute()) {
        // Log the action
        SecurityManager::logAction($conn, $user_id, 'ADMIN_RESET_PASSWORD', "Reset password for user ID $target_user_id");
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'user_id' => $target_user_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to reset password: ' . $conn->error]);
    }
    $update_stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
?>

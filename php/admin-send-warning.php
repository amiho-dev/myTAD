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
$warning_message = isset($data['warning']) ? trim($data['warning']) : '';

if (!$target_user_id || !$warning_message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (strlen($warning_message) < 5 || strlen($warning_message) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Warning message must be 5-500 characters']);
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

    // Prevent self-warning
    if ($target_user_id === $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot send warning to yourself']);
        $conn->close();
        exit;
    }

    // Get user info
    $user_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $target_user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $user_stmt->close();
        $conn->close();
        exit;
    }

    $user = $user_result->fetch_assoc();
    $user_stmt->close();

    // Log warning (in production, store in a warnings table)
    $timestamp = date('Y-m-d H:i:s');
    error_log("ADMIN WARNING: User ID $target_user_id (${user['username']}) - Message: $warning_message - Timestamp: $timestamp");
    
    // Store warning in notifications table
    $notification_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, admin_id, type, title, message)
        VALUES (?, ?, 'warning', 'Admin Warning', ?)
    ");
    $notification_stmt->bind_param("iis", $target_user_id, $user_id, $warning_message);
    
    if (!$notification_stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to store warning: ' . $conn->error]);
        $notification_stmt->close();
        $conn->close();
        exit;
    }
    $notification_stmt->close();
    
    // Log the action
    SecurityManager::logAction($conn, $user_id, 'ADMIN_WARNING', "Warning issued to user ID $target_user_id: $warning_message");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Warning issued successfully',
        'user_id' => $target_user_id,
        'username' => $user['username'],
        'email' => $user['email'],
        'timestamp' => $timestamp
    ]);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
?>

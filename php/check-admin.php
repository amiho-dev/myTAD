<?php
/**
 * Check Admin Status
 * GET /php/check-admin.php
 * 
 * Returns whether the current user has admin privileges
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

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
    http_response_code(200);
    echo json_encode([
        'is_admin' => false,
        'authenticated' => false
    ]);
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
        http_response_code(200);
        echo json_encode([
            'is_admin' => false,
            'authenticated' => false
        ]);
        $conn->close();
        exit;
    }
    
    $user_id = $result->fetch_assoc()['user_id'];
    
    // Check if user is admin
    $is_admin = SecurityManager::isUserAdmin($conn, $user_id);
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'is_admin' => $is_admin,
        'authenticated' => true,
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

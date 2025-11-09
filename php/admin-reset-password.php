<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Include database config
require_once 'db-config.php';

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
    // Check if requester is admin (only owner thatoneamiho)
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND username = 'thatoneamiho'");
    $admin_stmt->bind_param("i", $_SESSION['user_id']);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($admin_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        $admin_stmt->close();
        exit;
    }
    $admin_stmt->close();

    // Prevent admin from resetting own password this way
    if ($target_user_id === $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot reset your own password via admin panel']);
        exit;
    }

    // Validate new password
    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        exit;
    }

    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update password
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update_stmt->bind_param("si", $password_hash, $target_user_id);

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'user_id' => $target_user_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to reset password']);
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

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

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Include database config
require_once 'db-config.php';

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$current_password = isset($data['current_password']) ? $data['current_password'] : '';
$new_password = isset($data['new_password']) ? $data['new_password'] : '';

// Validate inputs
if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password fields cannot be empty']);
    exit;
}

if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
    exit;
}

if ($current_password === $new_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
    exit;
}

try {
    // Get user's current password hash
    $user_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $user_stmt->close();
        exit;
    }

    $user = $user_result->fetch_assoc();
    $user_stmt->close();

    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update password
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_password_hash, $_SESSION['user_id']);

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update password']);
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

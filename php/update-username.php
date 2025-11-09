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
$new_username = isset($data['username']) ? trim($data['username']) : '';

// Validate username
if (empty($new_username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username cannot be empty']);
    exit;
}

if (strlen($new_username) < 3 || strlen($new_username) > 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username must be 3-30 characters']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $new_username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username can only contain letters, numbers, hyphens, and underscores']);
    exit;
}

try {
    // Check if new username is already taken
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->bind_param("si", $new_username, $_SESSION['user_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Username already taken']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Update username
    $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_username, $_SESSION['user_id']);

    if ($update_stmt->execute()) {
        // Update session username
        $_SESSION['username'] = $new_username;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Username updated successfully',
            'username' => $new_username
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update username']);
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

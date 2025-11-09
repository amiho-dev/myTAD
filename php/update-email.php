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
$new_email = isset($data['email']) ? trim($data['email']) : '';

// Validate email
if (empty($new_email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email cannot be empty']);
    exit;
}

// RFC 5322 email validation
if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    // Check if new email is already taken
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $new_email, $_SESSION['user_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already registered']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Update email
    $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_email, $_SESSION['user_id']);

    if ($update_stmt->execute()) {
        // Update session email
        $_SESSION['email'] = $new_email;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Email updated successfully',
            'email' => $new_email
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update email']);
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

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

    // Prevent self-warning
    if ($target_user_id === $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot send warning to yourself']);
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
        exit;
    }

    $user = $user_result->fetch_assoc();
    $user_stmt->close();

    // Log warning (in production, store in a warnings table)
    $timestamp = date('Y-m-d H:i:s');
    error_log("ADMIN WARNING: User ID $target_user_id (${user['username']}) - Message: $warning_message - Timestamp: $timestamp");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Warning issued successfully',
        'user_id' => $target_user_id,
        'username' => $user['username'],
        'email' => $user['email'],
        'timestamp' => $timestamp
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Start session
session_start();

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Include database config
require_once 'db-config.php';

try {
    // Check if user is admin (only owner thatoneamiho)
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

    // Fetch all users
    $users_stmt = $conn->prepare("SELECT id, username, email, created_at, last_login, is_active FROM users ORDER BY created_at DESC");
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
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

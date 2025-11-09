<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Start session
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Include database config
require_once 'db-config.php';

try {
    // Get account stats
    $stats_stmt = $conn->prepare("
        SELECT 
            id,
            created_at,
            last_login,
            COALESCE((SELECT COUNT(*) FROM users WHERE id = ?), 0) as login_count
        FROM users 
        WHERE id = ?
    ");
    $stats_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();

    if ($stats_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $stats_stmt->close();
        exit;
    }

    $user = $stats_result->fetch_assoc();
    $stats_stmt->close();

    // Calculate login count from session history (simple increment on login)
    // For now, we'll just return basic info. In production, you might track login history
    $login_count = isset($_SESSION['login_count']) ? $_SESSION['login_count'] : 1;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login'] ?: $user['created_at'],
        'login_count' => $login_count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

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
$action = isset($data['action']) ? $data['action'] : ''; // ban, unban, mute, unmute
$reason = isset($data['reason']) ? trim($data['reason']) : 'No reason provided';

if (!$target_user_id || !in_array($action, ['ban', 'unban', 'mute', 'unmute'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
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

    // Prevent admin from banning themselves
    if ($target_user_id === $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot perform this action on yourself']);
        exit;
    }

    // Check if we need to add columns for bans/mutes (simplified - using is_active flag)
    if ($action === 'ban') {
        $update_stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $update_stmt->bind_param("i", $target_user_id);
        $message = 'User banned successfully';
    } else if ($action === 'unban') {
        $update_stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $target_user_id);
        $message = 'User unbanned successfully';
    } else if ($action === 'mute') {
        // For mute, we would need to add a 'is_muted' column
        // For now, just return success (implementation depends on your game logic)
        $message = 'User muted successfully (game-side implementation needed)';
        $update_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $update_stmt->bind_param("i", $target_user_id);
    } else { // unmute
        $message = 'User unmuted successfully (game-side implementation needed)';
        $update_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $update_stmt->bind_param("i", $target_user_id);
    }

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'user_id' => $target_user_id,
            'action' => $action,
            'reason' => $reason
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to perform action']);
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log($e->getMessage());
}

$conn->close();
?>

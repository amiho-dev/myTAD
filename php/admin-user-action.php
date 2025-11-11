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
$action = isset($data['action']) ? $data['action'] : ''; // ban, unban, mute, unmute
$reason = isset($data['reason']) ? trim($data['reason']) : 'No reason provided';

if (!$target_user_id || !in_array($action, ['ban', 'unban', 'mute', 'unmute'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
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

    // Prevent admin from banning themselves
    if ($target_user_id === $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot perform this action on yourself']);
        $conn->close();
        exit;
    }

    // Get target username to check for owner protection
    $target_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $target_stmt->bind_param("i", $target_user_id);
    $target_stmt->execute();
    $target_result = $target_stmt->get_result();
    $target_user = $target_result->fetch_assoc();
    $target_stmt->close();

    // Prevent regular admins from banning/muting the owner (thatoneamiho)
    if ($target_user['username'] === 'thatoneamiho' && in_array($action, ['ban', 'mute'])) {
        // Check if the current user is the owner
        $current_user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $current_user_stmt->bind_param("i", $user_id);
        $current_user_stmt->execute();
        $current_user_result = $current_user_stmt->get_result();
        $current_user = $current_user_result->fetch_assoc();
        $current_user_stmt->close();

        if ($current_user['username'] !== 'thatoneamiho') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to perform this action on this user']);
            $conn->close();
            exit;
        }
    }

    // Verify target user exists
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $user_check->bind_param("i", $target_user_id);
    $user_check->execute();
    $user_check_result = $user_check->get_result();
    $user_check->close();
    
    if ($user_check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $conn->close();
        exit;
    }

    // Perform the action
    if ($action === 'ban') {
        // Parse ban duration if provided
        $locked_until = null;
        if (isset($data['ban_hours']) && is_numeric($data['ban_hours']) && $data['ban_hours'] > 0) {
            $ban_hours = intval($data['ban_hours']);
            $locked_until = date('Y-m-d H:i:s', strtotime("+$ban_hours hours"));
        }
        
        if ($locked_until) {
            $update_stmt = $conn->prepare("UPDATE users SET is_active = 0, account_locked_until = ? WHERE id = ?");
            $update_stmt->bind_param("si", $locked_until, $target_user_id);
            $message = "User banned until " . date('H:i d.m.Y', strtotime($locked_until));
        } else {
            // Permanent ban
            $update_stmt = $conn->prepare("UPDATE users SET is_active = 0, account_locked_until = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $message = 'User permanently banned';
        }
    } else if ($action === 'unban') {
        $update_stmt = $conn->prepare("UPDATE users SET is_active = 1, account_locked_until = NULL WHERE id = ?");
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
        // Log the action
        SecurityManager::logAction($conn, $user_id, strtoupper($action), "Action $action on user ID $target_user_id. Reason: $reason");
        
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
        echo json_encode(['success' => false, 'error' => 'Failed to perform action: ' . $conn->error]);
    }
    $update_stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
?>

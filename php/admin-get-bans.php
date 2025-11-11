<?php
/**
 * Admin Get Bans Endpoint
 * Returns list of banned or temporarily locked users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once 'db-config.php';
require_once 'security.php';

// Get token from Authorization header
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

    // Get all banned or temporarily locked users
    $stmt = $conn->prepare("
        SELECT 
            id,
            username,
            email,
            is_active,
            account_locked_until,
            created_at,
            last_login,
            CASE 
                WHEN is_active = 0 AND account_locked_until IS NULL THEN 'Permanently Banned'
                WHEN is_active = 0 AND account_locked_until > NOW() THEN 'Temporarily Banned'
                WHEN is_active = 0 AND account_locked_until <= NOW() THEN 'Ban Expired'
                WHEN account_locked_until > NOW() THEN 'Temporarily Locked'
                ELSE 'None'
            END as ban_status,
            account_locked_until as locked_until_raw
        FROM users 
        WHERE is_active = 0 OR account_locked_until IS NOT NULL
        ORDER BY account_locked_until DESC NULLS LAST, created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bans = [];
    while ($row = $result->fetch_assoc()) {
        $bans[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'is_active' => (bool)$row['is_active'],
            'ban_status' => $row['ban_status'],
            'locked_until' => $row['locked_until_raw'],
            'locked_until_formatted' => $row['locked_until_raw'] ? date('H:i d.m.Y', strtotime($row['locked_until_raw'])) : null,
            'created_at' => $row['created_at'],
            'last_login' => $row['last_login']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'bans' => $bans,
        'total' => count($bans)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
?>

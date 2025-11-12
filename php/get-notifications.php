<?php
/**
 * Get Notifications for Current User
 * GET /php/get-notifications.php
 * 
 * Returns all notifications for the authenticated user
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    
    // Get query parameters
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $unread_only = isset($_GET['unread']) ? (bool)$_GET['unread'] : false;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Build query
    $where = "n.user_id = ?";
    $params = array($user_id);
    $types = "i";
    
    if (!empty($type)) {
        $where .= " AND n.type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    if ($unread_only) {
        $where .= " AND n.is_read = 0";
    }
    
    // Get notifications
    $query = "
        SELECT 
            n.id,
            n.type,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            u.username as admin_username
        FROM notifications n
        LEFT JOIN users u ON n.admin_id = u.id
        WHERE $where
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Build bind_param call dynamically
    $bind_params = array($types);
    foreach ($params as &$param) {
        $bind_params[] = &$param;
    }
    
    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = array();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM notifications n WHERE $where";
    $count_stmt = $conn->prepare($count_query);
    if (!$count_stmt) {
        throw new Exception($conn->error);
    }
    
    // Rebuild params without limit/offset for count
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_bind_params = array($count_types);
        foreach ($count_params as &$param) {
            $count_bind_params[] = &$param;
        }
        call_user_func_array(array($count_stmt, 'bind_param'), $count_bind_params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();
    
    // Get unread count
    $unread_query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result()->fetch_assoc();
    $unread_stmt->close();
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => $count_result['total'],
        'unread_count' => $unread_result['unread'],
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
?>

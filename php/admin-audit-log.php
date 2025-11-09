<?php
/**
 * Admin Security - View Audit Log
 * GET /php/admin-audit-log.php
 * 
 * Query parameters:
 * - user_id: Filter by user ID (optional)
 * - action: Filter by action type (optional)
 * - limit: Number of results (default 100)
 * - offset: Pagination offset (default 0)
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get session token
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
    echo json_encode(['error' => 'Authentication required']);
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
        echo json_encode(['error' => 'Invalid session']);
        $conn->close();
        exit;
    }
    
    $admin_user_id = $result->fetch_assoc()['user_id'];
    
    // Check if user is admin (you'll need to add is_admin field to users table)
    // For now, we'll assume any authenticated user can view audit logs
    // In production, implement proper role-based access control
    
    $filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $filter_action = isset($_GET['action']) ? trim($_GET['action']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $limit = min($limit, 1000); // Max 1000 results
    $limit = max($limit, 1); // Min 1 result
    
    // Build query
    $query = "SELECT al.id, al.user_id, u.username, al.action, al.description, al.ip_address, al.created_at
              FROM audit_log al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE 1=1";
    
    $params = [];
    $param_types = "";
    
    if ($filter_user_id) {
        $query .= " AND al.user_id = ?";
        $params[] = $filter_user_id;
        $param_types .= "i";
    }
    
    if ($filter_action) {
        $query .= " AND al.action = ?";
        $params[] = $filter_action;
        $param_types .= "s";
    }
    
    $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    $stmt = $conn->prepare($query);
    
    if ($params) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($logs)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

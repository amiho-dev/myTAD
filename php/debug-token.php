<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db-config.php';
require_once 'security.php';

$result = [
    'status' => 'debug',
    'timestamp' => date('Y-m-d H:i:s'),
    'token_info' => [],
    'database_info' => []
];

// Try to extract token from multiple sources
$auth_header = '';

// Try 1: Standard Authorization header
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    $result['token_info']['source'] = 'HTTP_AUTHORIZATION';
}

// Try 2: Redirect Authorization
if (empty($auth_header) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    $result['token_info']['source'] = 'REDIRECT_HTTP_AUTHORIZATION';
}

// Try 3: Custom header (workaround for Cloudflare/proxies)
if (empty($auth_header) && isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
    $auth_header = 'Bearer ' . $_SERVER['HTTP_X_AUTH_TOKEN'];
    $result['token_info']['source'] = 'HTTP_X_AUTH_TOKEN (custom header)';
}

// Try 4: getallheaders
if (empty($auth_header) && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        $result['token_info']['source'] = 'getallheaders (Authorization)';
    } elseif (isset($headers['X-Auth-Token'])) {
        $auth_header = 'Bearer ' . $headers['X-Auth-Token'];
        $result['token_info']['source'] = 'getallheaders (X-Auth-Token)';
    }
}

$result['token_info']['header_value'] = $auth_header ? substr($auth_header, 0, 30) . '...' : 'NOT FOUND';

if (!preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
    $result['token_info']['parsed'] = 'FAILED';
    echo json_encode($result);
    exit;
}

$token = trim($matches[1]);
$result['token_info']['parsed'] = 'SUCCESS';
$result['token_info']['token_preview'] = substr($token, 0, 20) . '...';
$result['token_info']['token_length'] = strlen($token);

// Check database
try {
    $stmt = $conn->prepare("
        SELECT s.user_id, s.expires_at, u.username, u.email 
        FROM sessions s 
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.token = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        $result['database_info']['error'] = 'Prepare failed: ' . $conn->error;
    } else {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $token_result = $stmt->get_result();
        
        if ($token_result->num_rows === 0) {
            $result['database_info']['found'] = false;
            $result['database_info']['message'] = 'Token not found in sessions table';
        } else {
            $row = $token_result->fetch_assoc();
            $result['database_info']['found'] = true;
            $result['database_info']['user_id'] = $row['user_id'];
            $result['database_info']['username'] = $row['username'];
            $result['database_info']['email'] = $row['email'];
            
            // Check expiration
            $expires = strtotime($row['expires_at']);
            $now = time();
            $result['database_info']['expires_at'] = $row['expires_at'];
            $result['database_info']['expired'] = $expires < $now;
            $result['database_info']['expires_in_seconds'] = $expires - $now;
            
            // Check admin status
            $admin_stmt = $conn->prepare("SELECT role FROM admins WHERE user_id = ? LIMIT 1");
            if ($admin_stmt) {
                $admin_stmt->bind_param("i", $row['user_id']);
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                $result['database_info']['is_admin'] = $admin_result->num_rows > 0;
                if ($admin_result->num_rows > 0) {
                    $admin_row = $admin_result->fetch_assoc();
                    $result['database_info']['admin_role'] = $admin_row['role'];
                }
                $admin_stmt->close();
            }
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    $result['database_info']['error'] = $e->getMessage();
}

http_response_code(200);
echo json_encode($result, JSON_PRETTY_PRINT);
?>

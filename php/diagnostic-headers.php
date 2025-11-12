<?php
/**
 * DIAGNOSTIC ENDPOINT - Detailed Authorization Header Debug
 * 
 * This endpoint shows EXACTLY what's happening with the Authorization header
 * and helps us understand why it's not being received.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$diagnostic = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'server_protocol' => $_SERVER['SERVER_PROTOCOL'],
    'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'NOT SET',
    'remote_addr' => $_SERVER['REMOTE_ADDR'],
];

// Log EVERY header
$diagnostic['all_headers'] = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0 || $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
        $diagnostic['all_headers'][$key] = $value;
    }
}

// Check Authorization specifically
$diagnostic['authorization_check'] = [
    'HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'FOUND: ' . substr($_SERVER['HTTP_AUTHORIZATION'], 0, 50) : 'NOT FOUND',
    'REDIRECT_HTTP_AUTHORIZATION' => isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? 'FOUND: ' . substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 0, 50) : 'NOT FOUND',
];

// Try getallheaders if available
if (function_exists('getallheaders')) {
    $all_headers = getallheaders();
    $diagnostic['getallheaders_result'] = [];
    foreach ($all_headers as $key => $value) {
        $diagnostic['getallheaders_result'][$key] = $value;
    }
}

// Check if running through Apache modules
$diagnostic['server_info'] = [
    'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'UNKNOWN',
    'PHP_SAPI' => php_sapi_name(),
];

// Check if headers might be in raw input
$diagnostic['raw_input'] = [
    'php://input length' => strlen(file_get_contents('php://input')),
];

http_response_code(200);
echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

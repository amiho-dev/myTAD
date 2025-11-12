<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$result = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => [],
    'all_server_keys' => []
];

// Log all HTTP_* headers
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $result['headers'][$key] = $value;
    }
}

// Check specific Authorization headers
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $result['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
}
if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $result['REDIRECT_HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

// Try getallheaders
if (function_exists('getallheaders')) {
    $all_headers = getallheaders();
    $result['getallheaders'] = $all_headers;
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>

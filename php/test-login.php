<?php
/**
 * Login Test - Debug login process
 * Upload to your server and visit it in your browser
 * e.g., https://yourdomain.com/php/test-login.php
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

$results = [];

// Test 1: Check if SecurityManager methods exist
$results['methods_check'] = [
    'getDeviceFingerprint' => method_exists('SecurityManager', 'getDeviceFingerprint') ? 'YES' : 'NO',
    'isDeviceBanned' => method_exists('SecurityManager', 'isDeviceBanned') ? 'YES' : 'NO',
    'getClientIP' => method_exists('SecurityManager', 'getClientIP') ? 'YES' : 'NO',
    'getUserAgent' => method_exists('SecurityManager', 'getUserAgent') ? 'YES' : 'NO',
    'checkRateLimit' => method_exists('SecurityManager', 'checkRateLimit') ? 'YES' : 'NO'
];

// Test 2: Get device info
$results['device_info'] = [
    'client_ip' => SecurityManager::getClientIP(),
    'user_agent' => SecurityManager::getUserAgent(),
    'device_fingerprint' => SecurityManager::getDeviceFingerprint()
];

// Test 3: Check device ban status
try {
    $conn = getDBConnection();
    $device_fingerprint = SecurityManager::getDeviceFingerprint();
    $client_ip = SecurityManager::getClientIP();
    
    $device_ban = SecurityManager::isDeviceBanned($conn, $device_fingerprint, $client_ip);
    
    $results['device_ban_check'] = [
        'status' => $device_ban ? 'BANNED' : 'NOT_BANNED',
        'details' => $device_ban ? $device_ban : 'Device is not banned'
    ];
    
    // Test 4: Check rate limiting
    $is_rate_limited = SecurityManager::checkRateLimit($conn, $client_ip, 5, 15);
    
    $results['rate_limit_check'] = [
        'status' => $is_rate_limited ? 'RATE_LIMITED' : 'OK',
        'message' => $is_rate_limited ? 'Too many attempts' : 'OK to login'
    ];
    
    // Test 5: Check if test user exists
    $stmt = $conn->prepare("SELECT id, username FROM users LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $results['test_user'] = [
            'exists' => 'YES',
            'id' => $user['id'],
            'username' => $user['username'],
            'message' => 'You can try logging in with this username'
        ];
    } else {
        $results['test_user'] = [
            'exists' => 'NO',
            'message' => 'No users found. Register a new account first.'
        ];
    }
    
    $conn->close();
    
    $results['status'] = 'SUCCESS - Login system should now work!';
    
} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

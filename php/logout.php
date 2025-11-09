<?php
/**
 * Iron Dominion - User Logout Endpoint
 * 
 * Endpoint: POST /php/logout.php
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Logout successful"
 * }
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session data
session_destroy();
$_SESSION = [];

// Clear cookies
$cookie_params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $cookie_params['path'],
    $cookie_params['domain'],
    $cookie_params['secure'],
    $cookie_params['httponly']
);

// Clear remember me cookies
setcookie('iron_dominion_remember', '', time() - 42000, '/');
setcookie('iron_dominion_user', '', time() - 42000, '/');

header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Logout successful'
]);
?>

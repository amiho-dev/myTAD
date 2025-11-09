<?php
/**
 * Iron Dominion - Authentication Check Endpoint
 * 
 * Endpoint: GET /php/check-auth.php
 * 
 * Checks if user is authenticated via session or remember-me cookie
 * 
 * Response (authenticated):
 * {
 *   "authenticated": true,
 *   "user_id": 1,
 *   "username": "player_name",
 *   "email": "email@example.com"
 * }
 * 
 * Response (not authenticated):
 * {
 *   "authenticated": false
 * }
 */

require_once 'db-config.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated via session
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode([
        'authenticated' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email']
    ]);
    exit;
}

// Check remember-me cookies
if (isset($_COOKIE['iron_dominion_remember']) && isset($_COOKIE['iron_dominion_user'])) {
    try {
        $user_data = base64_decode($_COOKIE['iron_dominion_user']);
        list($user_id, $username) = explode(':', $user_data);
        
        $conn = getDBConnection();
        
        // Verify user exists and is active
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Re-establish session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['token'] = bin2hex(random_bytes(32));
            $_SESSION['created'] = time();
            
            $stmt->close();
            $conn->close();
            
            http_response_code(200);
            echo json_encode([
                'authenticated' => true,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'via_remember' => true
            ]);
            exit;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        // Invalid remember-me cookie - clear it
        setcookie('iron_dominion_remember', '', time() - 42000, '/');
        setcookie('iron_dominion_user', '', time() - 42000, '/');
    }
}

// Not authenticated
http_response_code(401);
echo json_encode(['authenticated' => false]);
?>

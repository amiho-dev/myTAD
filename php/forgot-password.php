<?php
/**
 * Forgot Password - Request password reset token
 * POST /php/forgot-password.php
 * 
 * Request:
 * {
 *   "email": "user@example.com"
 * }
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !SecurityManager::isValidEmail($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

try {
    $conn = getDBConnection();
    $client_ip = SecurityManager::getClientIP();
    
    // Check rate limiting for password resets (3 per hour)
    $cutoff_time = date('Y-m-d H:i:s', time() - 3600);
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM password_resets
        WHERE ip_address = ? AND created_at > ?
    ");
    
    $stmt->bind_param("ss", $client_ip, $cutoff_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['count'] >= 3) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many password reset requests. Please try again later.']);
        $conn->close();
        exit;
    }
    
    // Find user by email
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    // Always return success message for security (don't reveal if email exists)
    if ($result->num_rows === 0) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'If an account exists with this email, you will receive a password reset link.']);
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Generate reset token
    $reset_token = SecurityManager::generateToken(32);
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Clear previous reset tokens
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
    
    // Store reset token
    $stmt = $conn->prepare("
        INSERT INTO password_resets (user_id, token, expires_at, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->bind_param("isss", $user['id'], $reset_token, $expires_at, $client_ip);
    $stmt->execute();
    $stmt->close();
    
    // Send reset email
    SecurityManager::sendPasswordResetEmail($email, $reset_token, $user['username']);
    SecurityManager::logAction($conn, $user['id'], 'PASSWORD_RESET_REQUESTED', 'Password reset requested', $client_ip);
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'If an account exists with this email, you will receive a password reset link.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}
?>

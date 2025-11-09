<?php
/**
 * Reset Password - Verify token and update password
 * POST /php/reset-password-confirm.php
 * 
 * Request:
 * {
 *   "token": "reset_token_from_email",
 *   "password": "NewSecurePassword123!"
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
$reset_token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if (!$reset_token || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or password']);
    exit;
}

// Validate password strength
$password_errors = SecurityManager::validatePasswordStrength($password);
if (!empty($password_errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Password is not strong enough', 'details' => $password_errors]);
    exit;
}

try {
    $conn = getDBConnection();
    $client_ip = SecurityManager::getClientIP();
    
    // Find valid reset token
    $stmt = $conn->prepare("
        SELECT pr.user_id, pr.expires_at, u.email, u.username
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used_at IS NULL
    ");
    
    $stmt->bind_param("s", $reset_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired reset token']);
        $conn->close();
        exit;
    }
    
    $reset = $result->fetch_assoc();
    
    // Check if token has expired
    if (strtotime($reset['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Reset token has expired. Please request a new one.']);
        $conn->close();
        exit;
    }
    
    // Hash new password
    $password_hash = SecurityManager::hashPassword($password);
    
    // Update password
    $stmt = $conn->prepare("
        UPDATE users
        SET password_hash = ?, last_password_change = NOW(), failed_login_attempts = 0
        WHERE id = ?
    ");
    
    $stmt->bind_param("si", $password_hash, $reset['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Mark reset token as used
    $stmt = $conn->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE token = ?
    ");
    
    $stmt->bind_param("s", $reset_token);
    $stmt->execute();
    $stmt->close();
    
    // Invalidate all sessions for security
    $stmt = $conn->prepare("UPDATE sessions SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $reset['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Log action
    SecurityManager::logAction($conn, $reset['user_id'], 'PASSWORD_RESET', 'Password reset completed', $client_ip);
    
    // Send notification email
    $subject = "Your Password Has Been Reset";
    $html = "<h2>Password Reset Successful</h2>";
    $html .= "<p>Hi " . htmlspecialchars($reset['username']) . ",</p>";
    $html .= "<p>Your password has been successfully reset.</p>";
    $html .= "<p>If this wasn't you, please contact support immediately.</p>";
    $html .= "<p>All existing sessions have been invalidated for your security. You'll need to log in again.</p>";
    
    SecurityManager::sendEmail($reset['email'], $subject, $html, true);
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Password reset successfully. Please log in with your new password.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}
?>

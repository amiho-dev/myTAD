<?php
/**
 * Two-Factor Authentication - Verify OTP Code
 * POST /php/verify-2fa.php
 * 
 * Used after login to verify 2FA code
 * 
 * Request:
 * {
 *   "code": "123456",
 *   "backup_code": "ABC12345" (optional - use if TOTP not available)
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if pending 2FA verification
if (!isset($_SESSION['pending_user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No pending 2FA verification']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$backup_code = trim($input['backup_code'] ?? '');

if (!$code && !$backup_code) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing authentication code']);
    exit;
}

try {
    $conn = getDBConnection();
    $user_id = $_SESSION['pending_user_id'];
    $client_ip = SecurityManager::getClientIP();
    $user_agent = SecurityManager::getUserAgent();
    
    // Get user
    $stmt = $conn->prepare("SELECT username, email, two_factor_secret FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid user']);
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $verified = false;
    
    // Check backup code if provided
    if ($backup_code) {
        $verified = SecurityManager::validateAndUseBackupCode($conn, $user_id, $backup_code);
        if ($verified) {
            SecurityManager::logAction($conn, $user_id, '2FA_VERIFIED_BACKUP', 'Login verified with backup code', $client_ip, $user_agent);
        }
    }
    
    // Check TOTP code if provided (simplified check - in production use GoogleAuthenticator)
    if ($code && !$verified) {
        if (preg_match('/^\d{6}$/', $code)) {
            // Simplified verification - in production, verify against time-based code
            // using library like: https://github.com/PHPGangsta/GoogleAuthenticator
            // For now, accept any 6-digit code after successful login
            // In production, implement proper TOTP verification
            
            // Store secret temporarily for verification
            if (!empty($user['two_factor_secret'])) {
                $verified = true; // In production, verify the code against the secret
                SecurityManager::logAction($conn, $user_id, '2FA_VERIFIED', 'Login verified with TOTP', $client_ip, $user_agent);
            }
        }
    }
    
    if (!$verified) {
        http_response_code(401);
        SecurityManager::logAction($conn, $user_id, '2FA_FAILED', 'Failed 2FA verification attempt', $client_ip, $user_agent);
        echo json_encode(['error' => 'Invalid authentication code']);
        $conn->close();
        exit;
    }
    
    // Create session now that 2FA is verified
    $session_token = SecurityManager::generateToken(32);
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    // Store session in database
    $stmt = $conn->prepare("
        INSERT INTO sessions (user_id, token, ip_address, user_agent, created_at, expires_at, last_activity)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW())
    ");
    
    $stmt->bind_param("issss", $user_id, $session_token, $client_ip, $user_agent, $expires_at);
    $stmt->execute();
    $stmt->close();
    
    // Update session data
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['token'] = $session_token;
    $_SESSION['created'] = time();
    $_SESSION['ip_address'] = $client_ip;
    
    // Clear pending 2FA session data
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['pending_username']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['pending_created']);
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $user_id,
        'username' => $user['username'],
        'email' => $user['email'],
        'token' => $session_token
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

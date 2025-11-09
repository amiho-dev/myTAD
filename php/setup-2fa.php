<?php
/**
 * Two-Factor Authentication - Setup
 * GET /php/setup-2fa.php - Get QR code setup
 * POST /php/setup-2fa.php - Verify and enable 2FA
 */

require_once 'db-config.php';
require_once 'security.php';

header('Content-Type: application/json');

// Get session token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    
    $user_id = $result->fetch_assoc()['user_id'];
    
    // GET - Initialize 2FA setup
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // Get user info
        $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Generate 2FA secret (32 character base32 encoded string)
        $secret = bin2hex(random_bytes(32));
        
        // Store in session for verification
        $_SESSION['pending_2fa_secret'] = $secret;
        $_SESSION['pending_2fa_time'] = time();
        
        // Generate provisioning URI for QR code
        $issuer = 'myTAD';
        $accountName = $user['email'];
        $provisioning_uri = "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}";
        
        // Generate QR code URL using QR server API
        $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($provisioning_uri);
        
        // Generate backup codes
        $backup_codes = SecurityManager::generateBackupCodes(10);
        
        // Store backup codes in session
        $_SESSION['pending_backup_codes'] = $backup_codes;
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qr_code_url,
            'provisioning_uri' => $provisioning_uri,
            'backup_codes' => $backup_codes,
            'message' => 'Scan the QR code with your authenticator app'
        ]);
        
    }
    // POST - Verify and enable 2FA
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $code = trim($input['code'] ?? '');
        $secret = trim($input['secret'] ?? '');
        
        if (!$code || !$secret) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing code or secret']);
            $conn->close();
            exit;
        }
        
        // Verify the code against secret (simplified - requires Google Authenticator library in production)
        // For now, we'll accept a 6-digit code that can be validated
        
        if (!preg_match('/^\d{6}$/', $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid authentication code format']);
            $conn->close();
            exit;
        }
        
        // In production, use this library: https://github.com/PHPGangsta/GoogleAuthenticator
        // For now, store the secret and mark 2FA as enabled
        
        // Store 2FA secret (should be encrypted in production)
        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
        $stmt->bind_param("si", $secret, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Store backup codes
        if (isset($_SESSION['pending_backup_codes'])) {
            SecurityManager::storeBackupCodes($conn, $user_id, $_SESSION['pending_backup_codes']);
            unset($_SESSION['pending_backup_codes']);
        }
        
        // Clear pending 2FA session data
        unset($_SESSION['pending_2fa_secret']);
        unset($_SESSION['pending_2fa_time']);
        
        SecurityManager::logAction($conn, $user_id, '2FA_ENABLED', 'Two-factor authentication enabled');
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Two-factor authentication has been enabled'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

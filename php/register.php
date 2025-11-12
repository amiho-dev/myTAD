<?php
/**
 * Iron Dominion - User Registration Endpoint
 * 
 * Endpoint: POST /php/register.php
 * 
 * Request body:
 * {
 *   "username": "player_name",
 *   "email": "email@example.com",
 *   "password": "securePassword123"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Account created successfully",
 *   "user_id": 1,
 *   "username": "player_name"
 * }
 */

require_once 'db-config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get client info for device ban checking
require_once 'security.php';
$client_ip = SecurityManager::getClientIP();
$user_agent = SecurityManager::getUserAgent();
$device_fingerprint = SecurityManager::getDeviceFingerprint();

// Get JSON request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: username, email, password']);
    exit;
}

$username = trim($input['username']);
$email = trim($input['email']);
$password = $input['password'];

// Validation rules
$errors = [];

if (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters';
}

if (strlen($username) > 30) {
    $errors[] = 'Username must not exceed 30 characters';
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, underscores, and hyphens';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}

if (strlen($password) > 100) {
    $errors[] = 'Password is too long';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(', ', $errors)]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if device is banned - prevent registration from banned devices
    $device_ban = SecurityManager::isDeviceBanned($conn, $device_fingerprint, $client_ip);
    if ($device_ban) {
        http_response_code(403);
        $is_permanent = (bool)$device_ban['is_permanent'];
        $banned_until = $device_ban['banned_until'];
        
        $response = [
            'error' => 'device_banned',
            'message' => 'This device is restricted from accessing myTAD',
            'is_permanent' => $is_permanent
        ];
        
        if (!$is_permanent && $banned_until) {
            $response['banned_until'] = $banned_until;
            $response['banned_until_formatted'] = date('F j, Y \a\t g:i A', strtotime($banned_until));
        }
        
        echo json_encode($response);
        $conn->close();
        exit;
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already exists']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
    
    // Hash password using bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password_hash);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // AUTO-LOGIN: Create session token for new user
        $token = SecurityManager::generateToken();
        $expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
        
        $session_stmt = $conn->prepare("
            INSERT INTO sessions (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $session_stmt->bind_param("iss", $user_id, $token, $expires_at);
        
        if ($session_stmt->execute()) {
            // Log the registration/login action
            SecurityManager::logAction($conn, $user_id, 'ACCOUNT_CREATED', 'User registered and auto-logged in', $client_ip);
            
            $session_stmt->close();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully and logged in',
                'user_id' => $user_id,
                'username' => $username,
                'token' => $token,
                'authenticated' => true
            ]);
        } else {
            // Session creation failed, but account was created
            throw new Exception('Account created but session creation failed: ' . $session_stmt->error);
        }
    } else {
        throw new Exception('Failed to create account: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

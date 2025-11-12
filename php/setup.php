<?php
/**
 * MyTAD Setup - Database Configuration
 * 
 * Located in: php/setup.php
 * Usage: https://yourdomain.com/php/setup.php
 */

session_start();

$config_exists = file_exists(__DIR__ . '/config.local.php');
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'configure') {
        $db_host = trim($_POST['db_host'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_name = trim($_POST['db_name'] ?? '');
        
        $errors = [];
        if (empty($db_host)) $errors[] = 'Database host required';
        if (empty($db_user)) $errors[] = 'Database user required';
        if (empty($db_pass)) $errors[] = 'Database password required';
        if (empty($db_name)) $errors[] = 'Database name required';
        
        if (!empty($errors)) {
            $message = implode(' | ', $errors);
            $message_type = 'error';
        } else {
            $test_conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
            
            if (!$test_conn) {
                $message = 'Connection failed: ' . mysqli_connect_error();
                $message_type = 'error';
            } else {
                $config_content = "<?php\n";
                $config_content .= "// MyTAD Database Configuration\n";
                $config_content .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
                $config_content .= "// DO NOT COMMIT TO GIT\n\n";
                $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
                $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
                $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
                $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
                $config_content .= "\n?>";
                
                if (file_put_contents(__DIR__ . '/config.local.php', $config_content)) {
                    @chmod(__DIR__ . '/config.local.php', 0600);
                    mysqli_close($test_conn);
                    $message = 'Configuration saved to config.local.php';
                    $message_type = 'success';
                    $config_exists = true;
                } else {
                    $message = 'Failed to write config file. Check permissions.';
                    $message_type = 'error';
                    mysqli_close($test_conn);
                }
            }
        }
    } elseif ($_POST['action'] === 'reset') {
        if (file_exists(__DIR__ . '/config.local.php')) {
            if (unlink(__DIR__ . '/config.local.php')) {
                $message = 'Configuration reset.';
                $message_type = 'success';
                $config_exists = false;
            } else {
                $message = 'Failed to delete config file.';
                $message_type = 'error';
            }
        }
    }
}

$db_host = '';
$db_user = '';
$db_pass = '';
$db_name = '';

if ($config_exists && file_exists(__DIR__ . '/config.local.php')) {
    $content = file_get_contents(__DIR__ . '/config.local.php');
    if (preg_match("/define\('DB_HOST',\s*'([^']*)'\)/", $content, $m)) $db_host = $m[1];
    if (preg_match("/define\('DB_USER',\s*'([^']*)'\)/", $content, $m)) $db_user = $m[1];
    if (preg_match("/define\('DB_PASS',\s*'([^']*)'\)/", $content, $m)) $db_pass = $m[1];
    if (preg_match("/define\('DB_NAME',\s*'([^']*)'\)/", $content, $m)) $db_name = $m[1];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyTAD Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .status.ok { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; }
        .button-group { display: flex; gap: 10px; }
        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102,126,234,0.3); }
        .btn-danger { background: #f44336; color: white; }
        .btn-danger:hover { background: #d32f2f; }
        .info { background: #f5f5f5; border-left: 4px solid #667eea; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; line-height: 1.6; color: #555; }
        .security { background: #fff3cd; border-left: 4px solid #ff9800; padding: 12px; border-radius: 4px; margin-top: 20px; font-size: 13px; color: #856404; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 MyTAD Setup</h1>
        <p class="subtitle">Database Configuration</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($config_exists): ?>
            <div class="status ok">✅ Database configured and ready</div>
        <?php else: ?>
            <div class="status warning">⚠️ Database not configured</div>
        <?php endif; ?>
        
        <div class="info">
            <strong>ℹ️ How it works:</strong><br>
            Your credentials will be saved to <code>config.local.php</code> with restricted permissions. This file is in .gitignore.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" placeholder="localhost" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database User</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" placeholder="username" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>" placeholder="••••••••" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" placeholder="database_name" required>
            </div>
            
            <div class="button-group">
                <button type="submit" name="action" value="configure" class="btn-primary">
                    <?php echo $config_exists ? '📝 Update' : '✅ Save Configuration'; ?>
                </button>
                <?php if ($config_exists): ?>
                    <button type="submit" name="action" value="reset" class="btn-danger" onclick="return confirm('Reset configuration?')">
                        🔄 Reset
                    </button>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="security">
            <strong>🔐 Security:</strong><br>
            • Password NOT sent anywhere - only used to test connection<br>
            • Stored in local file with restricted permissions (600)<br>
            • Automatically ignored by git<br>
            • Delete setup.php after configuration
        </div>
    </div>
</body>
</html>

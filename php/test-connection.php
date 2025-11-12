<?php
/**
 * Database Connection Test
 * Use this file to diagnose database connection issues
 * Upload to your server and visit it in your browser
 * e.g., https://yourdomain.com/php/test-connection.php
 */

require_once 'db-config.php';

header('Content-Type: application/json');

$test_results = [];

// Test 1: Check if constants are defined
$test_results['constants_defined'] = [
    'DB_HOST' => defined('DB_HOST') ? 'YES' : 'NO',
    'DB_USER' => defined('DB_USER') ? 'YES' : 'NO',
    'DB_PASS' => defined('DB_PASS') ? 'YES (hidden)' : 'NO',
    'DB_NAME' => defined('DB_NAME') ? 'YES' : 'NO'
];

// Test 2: Try to connect
$test_results['connection_attempt'] = [
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER
];

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        $test_results['connection_result'] = [
            'status' => 'FAILED',
            'error' => $conn->connect_error,
            'error_code' => $conn->connect_errno
        ];
    } else {
        $test_results['connection_result'] = [
            'status' => 'SUCCESS',
            'message' => 'Database connection successful!'
        ];
        
        // Test 3: Check if tables exist
        $tables_check = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
        
        if ($tables_check) {
            $tables = [];
            while ($row = $tables_check->fetch_assoc()) {
                $tables[] = $row['TABLE_NAME'];
            }
            $test_results['tables_found'] = $tables;
            $test_results['table_count'] = count($tables);
        }
        
        // Test 4: Check users table structure
        $users_check = $conn->query("DESCRIBE users");
        if ($users_check) {
            $columns = [];
            while ($row = $users_check->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $test_results['users_table_columns'] = $columns;
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $test_results['connection_result'] = [
        'status' => 'EXCEPTION',
        'error' => $e->getMessage()
    ];
}

echo json_encode($test_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

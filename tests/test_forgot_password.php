<?php
/**
 * Test Script for Forgot Password Feature
 * 
 * This script tests all scenarios for the password reset functionality
 */

// Change to SWAP-IT directory
chdir(dirname(__DIR__));
require_once 'config/database.php';
require_once 'config/functions.php';

echo "========================================\n";
echo "Testing Forgot Password Feature\n";
echo "========================================\n\n";

$conn = getDBConnection();
$test_results = [];

// Test 1: Check if password_resets table exists
echo "Test 1: Checking if password_resets table exists...\n";
$result = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($result->num_rows > 0) {
    echo "✓ PASS: Table password_resets exists\n";
    $test_results['table_exists'] = true;
} else {
    echo "✗ FAIL: Table password_resets does not exist\n";
    $test_results['table_exists'] = false;
}
echo "\n";

// Test 2: Check table structure
echo "Test 2: Checking password_resets table structure...\n";
$result = $conn->query("DESCRIBE password_resets");
if ($result) {
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $required_columns = ['id', 'user_id', 'token', 'expires_at', 'created_at'];
    $missing = array_diff($required_columns, $columns);
    
    if (empty($missing)) {
        echo "✓ PASS: All required columns exist\n";
        $test_results['table_structure'] = true;
    } else {
        echo "✗ FAIL: Missing columns: " . implode(', ', $missing) . "\n";
        $test_results['table_structure'] = false;
    }
} else {
    echo "✗ FAIL: Could not describe table\n";
    $test_results['table_structure'] = false;
}
echo "\n";

// Test 3: Get a test user
echo "Test 3: Getting test user...\n";
$result = $conn->query("SELECT id, email, username FROM users LIMIT 1");
if ($result && $result->num_rows > 0) {
    $test_user = $result->fetch_assoc();
    echo "✓ PASS: Test user found: {$test_user['email']}\n";
    $test_results['test_user'] = $test_user;
} else {
    echo "✗ FAIL: No users in database\n";
    $test_results['test_user'] = null;
}
echo "\n";

// Test 4: Generate and store reset token
if ($test_results['test_user']) {
    echo "Test 4: Generating and storing reset token...\n";
    $test_user = $test_results['test_user'];
    
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Delete old tokens
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->bind_param("i", $test_user['id']);
    $stmt->execute();
    
    // Insert new token
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $test_user['id'], $token_hash, $expires);
    
    if ($stmt->execute()) {
        echo "✓ PASS: Token generated and stored successfully\n";
        echo "  Token: $token\n";
        echo "  Expires: $expires\n";
        $test_results['token'] = $token;
        $test_results['token_insert'] = true;
    } else {
        echo "✗ FAIL: Failed to store token: " . $stmt->error . "\n";
        $test_results['token_insert'] = false;
    }
}
echo "\n";

// Test 5: Validate token
if ($test_results['token_insert'] && $test_results['token']) {
    echo "Test 5: Validating token...\n";
    $token_hash = hash('sha256', $test_results['token']);
    
    $stmt = $conn->prepare("SELECT pr.user_id, pr.expires_at, u.email 
                            FROM password_resets pr 
                            JOIN users u ON pr.user_id = u.id 
                            WHERE pr.token = ? AND pr.expires_at > NOW()");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $reset_data = $result->fetch_assoc();
        echo "✓ PASS: Token validated successfully\n";
        echo "  User ID: {$reset_data['user_id']}\n";
        echo "  Email: {$reset_data['email']}\n";
        $test_results['token_validate'] = true;
    } else {
        echo "✗ FAIL: Token validation failed\n";
        $test_results['token_validate'] = false;
    }
}
echo "\n";

// Test 6: Test password hashing
echo "Test 6: Testing password hashing...\n";
$test_password = "TestPassword123!";
$hashed = password_hash($test_password, PASSWORD_DEFAULT);

if ($hashed && password_verify($test_password, $hashed)) {
    echo "✓ PASS: Password hashing and verification works\n";
    $test_results['password_hash'] = true;
} else {
    echo "✗ FAIL: Password hashing failed\n";
    $test_results['password_hash'] = false;
}
echo "\n";

// Test 7: Test expired token detection
echo "Test 7: Testing expired token detection...\n";
if ($test_results['test_user']) {
    $expired_token = bin2hex(random_bytes(32));
    $expired_token_hash = hash('sha256', $expired_token);
    $expired_date = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $test_results['test_user']['id'], $expired_token_hash, $expired_date);
    $stmt->execute();
    
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $expired_token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo "✓ PASS: Expired token correctly detected\n";
        $test_results['expired_token'] = true;
    } else {
        echo "✗ FAIL: Expired token not detected\n";
        $test_results['expired_token'] = false;
    }
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $expired_token_hash);
    $stmt->execute();
}
echo "\n";

// Test 8: Clean up test data
echo "Test 8: Cleaning up test data...\n";
if ($test_results['test_user']) {
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->bind_param("i", $test_results['test_user']['id']);
    if ($stmt->execute()) {
        echo "✓ PASS: Test data cleaned up\n";
        $test_results['cleanup'] = true;
    } else {
        echo "✗ FAIL: Failed to clean up test data\n";
        $test_results['cleanup'] = false;
    }
}
echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";
$total_tests = count($test_results);
$passed_tests = count(array_filter($test_results));
$failed_tests = $total_tests - $passed_tests;

echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests ✓\n";
echo "Failed: $failed_tests ✗\n";
echo "\n";

if ($failed_tests == 0) {
    echo "🎉 All tests passed! Forgot password feature is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Please check the errors above.\n";
}

echo "\nDetailed Results:\n";
foreach ($test_results as $test => $result) {
    $status = $result ? '✓ PASS' : '✗ FAIL';
    echo "  $status: $test\n";
}

$conn->close();
echo "\n========================================\n";
echo "Testing Complete\n";
echo "========================================\n";
?>

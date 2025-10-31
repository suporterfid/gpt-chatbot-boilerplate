#!/usr/bin/env php
<?php
/**
 * Admin API Authentication Test
 * Tests the token-based authentication for admin-api.php
 */

echo "=== Admin API Authentication Test ===\n\n";

// Start PHP built-in server
$port = 9998;
$serverLog = '/tmp/test_admin_auth_server.log';
$cmd = "php -S localhost:$port -t " . __DIR__ . "/.. > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(2); // Wait for server to start

echo "Started test server (PID: $pid)\n\n";

$baseUrl = "http://localhost:$port";
$validToken = "test_admin_token_for_phase1_testing_min32chars";
$invalidToken = "wrong_token";

$testsPassed = 0;
$testsFailed = 0;

function test($name, $url, $token, $expectedStatus) {
    global $testsPassed, $testsFailed;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === $expectedStatus) {
        echo "✓ PASS: $name (HTTP $httpCode)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $name (expected HTTP $expectedStatus, got HTTP $httpCode)\n";
        $testsFailed++;
    }
}

// Test 1: Request without token should fail
test(
    "List agents without token",
    "$baseUrl/admin-api.php?action=list_agents",
    null,
    403
);

// Test 2: Request with invalid token should fail
test(
    "List agents with invalid token",
    "$baseUrl/admin-api.php?action=list_agents",
    $invalidToken,
    403
);

// Test 3: Request with valid token should succeed
test(
    "List agents with valid token",
    "$baseUrl/admin-api.php?action=list_agents",
    $validToken,
    200
);

// Test 4: Create with valid token should succeed
$uniqueName = 'Auth Test Agent ' . time();
$ch = curl_init("$baseUrl/admin-api.php?action=create_agent");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $uniqueName,
    'api_type' => 'chat'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $validToken",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201) {
    echo "✓ PASS: Create agent with valid token (HTTP $httpCode)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Create agent with valid token (expected HTTP 201, got HTTP $httpCode)\n";
    echo "Response: $response\n";
    $testsFailed++;
}

// Cleanup
posix_kill($pid, SIGTERM);
sleep(1);
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n❌ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All authentication tests passed!\n";
    exit(0);
}

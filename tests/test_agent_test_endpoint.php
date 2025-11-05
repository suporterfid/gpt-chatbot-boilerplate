#!/usr/bin/env php
<?php
/**
 * Test that the test_agent endpoint works with GET requests and token parameter
 */

echo "=== Test Agent Endpoint Test ===\n\n";

// Set up .env file for testing
$envContent = "ADMIN_TOKEN=test_admin_token_for_phase1_testing_min32chars\n";
$envContent .= "OPENAI_API_KEY=test_key\n";
$envPath = __DIR__ . '/../.env';
$envBackupPath = __DIR__ . '/../.env.backup.test';

// Backup existing .env if it exists
if (file_exists($envPath)) {
    copy($envPath, $envBackupPath);
    echo "Backed up existing .env\n";
}

// Write test .env
file_put_contents($envPath, $envContent);
echo "Created test .env with ADMIN_TOKEN\n\n";

// Start PHP built-in server
$port = 9998;
$serverLog = '/tmp/test_agent_endpoint_server.log';
$baseDir = escapeshellarg(__DIR__ . "/..");
$cmd = "php -S localhost:$port -t $baseDir > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(3); // Wait for server to start

echo "Started test server (PID: $pid) on port $port\n\n";

$baseUrl = "http://localhost:$port";
$testToken = "test_admin_token_for_phase1_testing_min32chars";

$testsPassed = 0;
$testsFailed = 0;

function testRequest($name, $url, $method = 'GET', $headers = [], $body = null) {
    global $testsPassed, $testsFailed;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Split headers and body
    $headerSize = strpos($response, "\r\n\r\n");
    if ($headerSize === false) {
        $headers = '';
        $body = $response;
    } else {
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize + 4);
    }
    
    echo "Test: $name\n";
    echo "  HTTP Status: $httpCode\n";
    
    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body
    ];
}

// First, create a test agent
// Set ADMIN_TOKEN environment variable for testing
$testToken = "test_admin_token_for_phase1_testing_min32chars";
putenv("ADMIN_TOKEN=$testToken");
$_ENV['ADMIN_TOKEN'] = $testToken;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

$dbConfig = [
    'database_url' => null,
    'database_path' => __DIR__ . '/../data/chatbot.db'
];

$db = new DB($dbConfig);

// Run migrations
try {
    $db->runMigrations(__DIR__ . '/../db/migrations');
} catch (Exception $e) {
    // Migrations might already be run, continue
}

// Verify the token is set in config
if (!isset($config['admin']['token']) || $config['admin']['token'] !== $testToken) {
    echo "Warning: ADMIN_TOKEN not properly set in config\n";
    $config['admin']['token'] = $testToken;
}

$agentService = new AgentService($db);

// Create a test agent
$testAgent = $agentService->createAgent([
    'name' => 'Test Agent for Endpoint',
    'description' => 'Test agent for endpoint test',
    'api_type' => 'chat',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'system_prompt' => 'You are a helpful assistant.',
    'is_default' => false
]);

$agentId = $testAgent['id'];
echo "Created test agent: $agentId\n\n";

// Test 1: GET request with token parameter (should work now)
echo "--- Test 1: GET request with token parameter ---\n";
$url1 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId&token=" . urlencode($testToken);
$result1 = testRequest('GET with token param', $url1);

if ($result1['code'] === 200 || strpos($result1['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: GET request with token parameter works (got SSE response)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request with token parameter failed (HTTP {$result1['code']})\n";
    echo "  Response body: " . substr($result1['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Test 2: GET request with Authorization header (should also work)
echo "\n--- Test 2: GET request with Authorization header ---\n";
$url2 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId";
$result2 = testRequest('GET with Auth header', $url2, 'GET', ["Authorization: Bearer $testToken"]);

if ($result2['code'] === 200 || strpos($result2['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: GET request with Authorization header works\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request with Authorization header failed (HTTP {$result2['code']})\n";
    echo "  Response body: " . substr($result2['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Test 3: GET request with admin_token parameter (legacy, should still work)
echo "\n--- Test 3: GET request with admin_token parameter ---\n";
$url3 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId&admin_token=" . urlencode($testToken);
$result3 = testRequest('GET with admin_token param', $url3);

if ($result3['code'] === 200 || strpos($result3['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: GET request with admin_token parameter works (legacy support)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request with admin_token parameter failed (HTTP {$result3['code']})\n";
    echo "  Response body: " . substr($result3['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Test 4: GET request without authentication (should fail with 403)
echo "\n--- Test 4: GET request without authentication ---\n";
$url4 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId";
$result4 = testRequest('GET without auth', $url4);

if ($result4['code'] === 403) {
    echo "✓ PASS: GET request without authentication properly rejected (403)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request without authentication should return 403, got {$result4['code']}\n";
    $testsFailed++;
}

// Test 5: POST request with JSON body (should still work for backward compatibility)
echo "\n--- Test 5: POST request with JSON body ---\n";
$url5 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId";
$postData = json_encode(['message' => 'Test message from POST']);
$result5 = testRequest('POST with JSON', $url5, 'POST', [
    "Authorization: Bearer $testToken",
    "Content-Type: application/json"
], $postData);

if ($result5['code'] === 200 || strpos($result5['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: POST request with JSON body works (backward compatibility)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: POST request with JSON body failed (HTTP {$result5['code']})\n";
    echo "  Response body: " . substr($result5['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Clean up test agent
$agentService->deleteAgent($agentId);
echo "\nDeleted test agent: $agentId\n";

// Stop server
shell_exec("kill $pid 2>/dev/null");
echo "\nStopped test server (PID: $pid)\n";

// Restore .env
if (file_exists($envBackupPath)) {
    rename($envBackupPath, $envPath);
    echo "Restored original .env\n";
} else {
    unlink($envPath);
    echo "Removed test .env\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total tests passed: $testsPassed\n";
echo "Total tests failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed!\n";
    exit(1);
}

#!/usr/bin/env php
<?php
/**
 * Test that the test_agent endpoint works with GET requests and token parameter
 */

echo "=== Test Agent Endpoint Test ===\n\n";

// Test credentials (generated for testing only, not for production use)
$sessionEmail = 'endpoint.super.admin@test.local';
$sessionPassword = 'EndpointTest!234';

// Set up .env file for testing
$envContent = "ADMIN_ENABLED=true\n";
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
echo "Created test .env with admin session support\n\n";

// Start PHP built-in server
$port = 9998;
$serverLog = '/tmp/test_agent_endpoint_server.log';
$baseDir = escapeshellarg(__DIR__ . "/..");
$cmd = "php -S localhost:$port -t $baseDir > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(3); // Wait for server to start

echo "Started test server (PID: $pid) on port $port\n\n";

$baseUrl = "http://localhost:$port";

$testsPassed = 0;
$testsFailed = 0;

function testRequest($name, $url, $method = 'GET', $headers = [], $body = null, $cookie = null) {
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

    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

$dbConfig = [
    'database_url' => null,
    'database_path' => __DIR__ . '/../data/chatbot.db'
];

$db = new DB($dbConfig);
$adminAuth = new AdminAuth($db, $config);

// Run migrations
try {
    $db->runMigrations(__DIR__ . '/../db/migrations');
} catch (Exception $e) {
    // Migrations might already be run, continue
}

// Create a dedicated session test user
try {
    $db->execute('DELETE FROM admin_sessions WHERE user_id IN (SELECT id FROM admin_users WHERE email = ?)', [$sessionEmail]);
    $db->execute('DELETE FROM admin_users WHERE email = ?', [$sessionEmail]);
} catch (Exception $e) {
    // Ignore cleanup issues
}

$sessionUser = $adminAuth->createUser($sessionEmail, $sessionPassword, AdminAuth::ROLE_SUPER_ADMIN);
$apiKey = $adminAuth->generateApiKey($sessionUser['id'], 'Endpoint test key');

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

// Test 1: Login with credentials to receive session cookie
echo "--- Test 1: Session login flow ---\n";
$loginPayload = json_encode(['email' => $sessionEmail, 'password' => $sessionPassword]);
$loginResult = testRequest('Login with credentials', "$baseUrl/admin-api.php?action=login", 'POST', [
    'Content-Type: application/json'
], $loginPayload);

$sessionCookie = null;
if ($loginResult['code'] === 200) {
    foreach (explode("\r\n", $loginResult['headers']) as $headerLine) {
        if (stripos($headerLine, 'Set-Cookie:') === 0) {
            $rawCookie = trim(substr($headerLine, strlen('Set-Cookie:')));
            $sessionCookie = explode(';', $rawCookie)[0];
            break;
        }
    }
}

if ($sessionCookie) {
    echo "✓ PASS: Login succeeded and session cookie issued ($sessionCookie)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Login failed or session cookie missing (HTTP {$loginResult['code']})\n";
    $testsFailed++;
}

// Test 2: GET request with session cookie should stream SSE
echo "\n--- Test 2: GET request with session cookie ---\n";
$urlSession = "$baseUrl/admin-api.php?action=test_agent&id=$agentId";
$resultSession = testRequest('GET with session cookie', $urlSession, 'GET', [], null, $sessionCookie);

if ($resultSession['code'] === 200 || strpos($resultSession['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: GET request with session cookie works\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request with session cookie failed (HTTP {$resultSession['code']})\n";
    echo "  Response body: " . substr($resultSession['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Test 3: GET request with Authorization header for API key compatibility
echo "\n--- Test 3: GET request with Authorization header ---\n";
$resultApiKey = testRequest('GET with Authorization header', $urlSession, 'GET', ["Authorization: Bearer " . $apiKey['key']]);

if ($resultApiKey['code'] === 200 || strpos($resultApiKey['headers'], 'text/event-stream') !== false) {
    echo "✓ PASS: GET request with Authorization header works\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: GET request with Authorization header failed (HTTP {$resultApiKey['code']})\n";
    echo "  Response body: " . substr($resultApiKey['body'], 0, 200) . "\n";
    $testsFailed++;
}

// Test 4: Query parameter token should be rejected
echo "\n--- Test 4: GET request with token query parameter (deprecated) ---\n";
$urlToken = "$baseUrl/admin-api.php?action=test_agent&id=$agentId&token=" . urlencode($apiKey['key']);
$resultToken = testRequest('GET with token param (deprecated)', $urlToken);

if ($resultToken['code'] === 403) {
    echo "✓ PASS: Query token rejected with 403 as expected\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Query token should be rejected with 403, got {$resultToken['code']}\n";
    $testsFailed++;
}

// Test 5: Logout should revoke session cookie
echo "\n--- Test 5: Logout invalidates session ---\n";
$logoutResult = testRequest('Logout with session cookie', "$baseUrl/admin-api.php?action=logout", 'POST', [], null, $sessionCookie);

if ($logoutResult['code'] === 200) {
    echo "✓ PASS: Logout endpoint responded with 200\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Logout expected HTTP 200, got {$logoutResult['code']}\n";
    $testsFailed++;
}

$postLogout = testRequest('GET after logout', $urlSession, 'GET', [], null, $sessionCookie);
if ($postLogout['code'] === 403) {
    echo "✓ PASS: Session cookie no longer valid after logout\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Session should be invalid after logout (expected 403, got {$postLogout['code']})\n";
    $testsFailed++;
}

$currentUserResult = testRequest('Current user after logout', "$baseUrl/admin-api.php?action=current_user", 'GET', [], null, $sessionCookie);
if ($currentUserResult['code'] === 401) {
    echo "✓ PASS: current_user requires active session (401 after logout)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: current_user should return 401 after logout, got {$currentUserResult['code']}\n";
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

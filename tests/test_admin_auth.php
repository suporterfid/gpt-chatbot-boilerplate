#!/usr/bin/env php
<?php
/**
 * Admin API Authentication Test
 * Tests API key and session authentication for admin-api.php
 */

echo "=== Admin API Authentication Test ===\n\n";

// Prepare authentication credentials before server boots
$validEmail = 'auth.super.admin@test.local';
$validPassword = 'AuthTestPass!456';
$invalidKey = 'wrong_key';

// Configure .env for admin session support
$envContent = "ADMIN_ENABLED=true\n";
$envContent .= "OPENAI_API_KEY=test_key\n";
$envPath = __DIR__ . '/../.env';
$envBackupPath = __DIR__ . '/../.env.backup.adminauth';

if (file_exists($envPath)) {
    copy($envPath, $envBackupPath);
}

file_put_contents($envPath, $envContent);

// Start PHP built-in server
$port = 9998;
$serverLog = '/tmp/test_admin_auth_server.log';
$cmd = "php -S localhost:$port -t " . __DIR__ . "/.. > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(2); // Wait for server to start

echo "Started test server (PID: $pid)\n\n";

$baseUrl = "http://localhost:$port";

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

$dbConfig = [
    'database_url' => null,
    'database_path' => __DIR__ . '/../data/chatbot.db'
];

$db = new DB($dbConfig);
try {
    $db->runMigrations(__DIR__ . '/../db/migrations');
} catch (Exception $e) {
    // ignore - migrations may have already run
}

$adminAuth = new AdminAuth($db, $config);
$sessionEmail = 'auth.session@test.local';
$sessionPassword = 'Sup3rSecure!';
$apiKey = null;

try {
    $db->execute('DELETE FROM admin_sessions WHERE user_id IN (SELECT id FROM admin_users WHERE email = ?)', [$sessionEmail]);
    $db->execute('DELETE FROM admin_users WHERE email = ?', [$sessionEmail]);
    $db->execute('DELETE FROM admin_sessions WHERE user_id IN (SELECT id FROM admin_users WHERE email = ?)', [$validEmail]);
    $db->execute('DELETE FROM admin_users WHERE email = ?', [$validEmail]);
} catch (Exception $e) {
    // ignore cleanup issues
}

$adminAuth->createUser($sessionEmail, $sessionPassword, AdminAuth::ROLE_SUPER_ADMIN);
$adminUser = $adminAuth->createUser($validEmail, $validPassword, AdminAuth::ROLE_SUPER_ADMIN);
$apiKey = $adminAuth->generateApiKey($adminUser['id'], 'Admin auth test key');

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

// Test 2: Request with invalid API key should fail
test(
    "List agents with invalid API key",
    "$baseUrl/admin-api.php?action=list_agents",
    $invalidKey,
    403
);

// Test 3: Request with valid API key should succeed
test(
    "List agents with valid API key",
    "$baseUrl/admin-api.php?action=list_agents",
    $apiKey['key'],
    200
);

// Test 4: Create with valid API key should succeed
$uniqueName = 'Auth Test Agent ' . time();
$ch = curl_init("$baseUrl/admin-api.php?action=create_agent");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $uniqueName,
    'api_type' => 'chat'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey['key']}",
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

// Session-based login/logout flow
echo "\n--- Session login/logout flow ---\n";
$loginCh = curl_init("$baseUrl/admin-api.php?action=login");
curl_setopt($loginCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($loginCh, CURLOPT_HEADER, true);
curl_setopt($loginCh, CURLOPT_POST, true);
curl_setopt($loginCh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($loginCh, CURLOPT_POSTFIELDS, json_encode([
    'email' => $sessionEmail,
    'password' => $sessionPassword,
]));
$loginResponse = curl_exec($loginCh);
$loginCode = curl_getinfo($loginCh, CURLINFO_HTTP_CODE);
curl_close($loginCh);

$sessionCookie = null;
if (preg_match('/Set-Cookie:\s*([^\r\n]+)/i', $loginResponse, $matches)) {
    $sessionCookie = explode(';', trim($matches[1]))[0];
}

if ($loginCode === 200 && $sessionCookie) {
    echo "✓ PASS: Login returned 200 and issued session cookie\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Login failed (HTTP $loginCode, cookie: " . ($sessionCookie ?? 'none') . ")\n";
    $testsFailed++;
}

if ($sessionCookie) {
    $currentCh = curl_init("$baseUrl/admin-api.php?action=current_user");
    curl_setopt($currentCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($currentCh, CURLOPT_HEADER, false);
    curl_setopt($currentCh, CURLOPT_COOKIE, $sessionCookie);
    $currentResponse = curl_exec($currentCh);
    $currentCode = curl_getinfo($currentCh, CURLINFO_HTTP_CODE);
    curl_close($currentCh);

    if ($currentCode === 200) {
        echo "✓ PASS: current_user returns 200 with valid session\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: current_user expected 200 with session, got HTTP $currentCode\n";
        $testsFailed++;
    }

    $logoutCh = curl_init("$baseUrl/admin-api.php?action=logout");
    curl_setopt($logoutCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($logoutCh, CURLOPT_POST, true);
    curl_setopt($logoutCh, CURLOPT_COOKIE, $sessionCookie);
    $logoutResponse = curl_exec($logoutCh);
    $logoutCode = curl_getinfo($logoutCh, CURLINFO_HTTP_CODE);
    curl_close($logoutCh);

    if ($logoutCode === 200) {
        echo "✓ PASS: Logout endpoint returned 200\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Logout expected 200, got HTTP $logoutCode\n";
        $testsFailed++;
    }

    $postLogoutCh = curl_init("$baseUrl/admin-api.php?action=current_user");
    curl_setopt($postLogoutCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($postLogoutCh, CURLOPT_COOKIE, $sessionCookie);
    $postLogoutResponse = curl_exec($postLogoutCh);
    $postLogoutCode = curl_getinfo($postLogoutCh, CURLINFO_HTTP_CODE);
    curl_close($postLogoutCh);

    if ($postLogoutCode === 401) {
        echo "✓ PASS: Session cookie invalidated after logout (401)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Session should be invalid after logout (expected 401, got $postLogoutCode)\n";
        $testsFailed++;
    }
}

// Cleanup
posix_kill($pid, SIGTERM);
sleep(1);
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if (file_exists($envBackupPath)) {
    rename($envBackupPath, $envPath);
} else {
    @unlink($envPath);
}

if ($testsFailed > 0) {
    echo "\n❌ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All authentication tests passed!\n";
    exit(0);
}

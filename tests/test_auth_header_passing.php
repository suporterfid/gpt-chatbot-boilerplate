#!/usr/bin/env php
<?php
/**
 * Test Authorization Header Passing
 * Verifies that the Authorization header is properly passed to PHP scripts
 */

echo "=== Authorization Header Passing Test ===\n\n";

// Start PHP built-in server
$port = 9997;
$serverLog = '/tmp/test_auth_header_server.log';
$baseDir = escapeshellarg(__DIR__ . "/..");
$cmd = "php -S localhost:$port -t $baseDir > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(2); // Wait for server to start

echo "Started test server (PID: $pid) on port $port\n\n";

$baseUrl = "http://localhost:$port";
$testToken = "test_token_for_auth_header_passing_verification";

$testsPassed = 0;
$testsFailed = 0;

function testAuthHeader($name, $url, $token, $expectedStatus, $checkAuthReceived = true) {
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
    
    // Split headers and body
    $headerSize = strpos($response, "\r\n\r\n");
    if ($headerSize === false) {
        // Malformed response, treat entire response as body
        $headers = '';
        $body = $response;
    } else {
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize + 4);
    }
    
    $passed = true;
    $messages = [];
    
    // Check HTTP status
    if ($httpCode !== $expectedStatus) {
        $passed = false;
        $messages[] = "Expected HTTP $expectedStatus, got HTTP $httpCode";
    }
    
    // If we expect authorization to be received, check the error message
    if ($checkAuthReceived && $httpCode === 403) {
        $bodyData = json_decode($body, true);
        if (isset($bodyData['error']['message'])) {
            $errorMsg = $bodyData['error']['message'];
            if (strpos($errorMsg, 'Authorization header required') !== false) {
                $passed = false;
                $messages[] = "Authorization header was NOT received by PHP (got 'Authorization header required' error)";
            } else if (strpos($errorMsg, 'Invalid') !== false) {
                // This is good - header was received but token was invalid
                $messages[] = "Authorization header WAS received by PHP (token validation failed as expected)";
            }
        }
    }
    
    if ($passed) {
        echo "✓ PASS: $name";
        if (!empty($messages)) {
            echo " (" . implode(', ', $messages) . ")";
        }
        echo "\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $name\n";
        foreach ($messages as $msg) {
            echo "  - $msg\n";
        }
        if (strlen($body) < 500) {
            echo "  Response body: $body\n";
        }
        $testsFailed++;
    }
    
    return $passed;
}

echo "Testing Authorization header passing to admin-api.php:\n\n";

// Test 1: Request without Authorization header should get "Authorization header required"
echo "Test 1: Verify proper error when no Authorization header is sent\n";
$result = testAuthHeader(
    "Request without Authorization header",
    "$baseUrl/admin-api.php?action=health",
    null,
    403,
    false  // We expect 403 but don't check if header was received (it shouldn't be)
);

// Test 2: Request with Authorization header should NOT get "Authorization header required"
// It should get "Invalid authentication token" instead, proving the header was received
echo "\nTest 2: Verify Authorization header is received by PHP\n";
$ch = curl_init("$baseUrl/admin-api.php?action=health");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $testToken"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$bodyData = json_decode($response, true);
if ($httpCode === 403 && isset($bodyData['error']['message'])) {
    $errorMsg = $bodyData['error']['message'];
    if (strpos($errorMsg, 'Authorization header required') !== false) {
        echo "✗ FAIL: Authorization header was NOT passed to PHP\n";
        echo "  Got error: $errorMsg\n";
        echo "  This means Apache is not forwarding the Authorization header to PHP.\n";
        $testsFailed++;
    } else if (strpos($errorMsg, 'Invalid') !== false || strpos($errorMsg, 'token') !== false) {
        echo "✓ PASS: Authorization header WAS received by PHP\n";
        echo "  Got expected error: $errorMsg\n";
        echo "  This proves the header is being forwarded correctly.\n";
        $testsPassed++;
    } else {
        echo "? UNEXPECTED: Got error: $errorMsg\n";
        $testsFailed++;
    }
} else {
    echo "? UNEXPECTED: HTTP $httpCode - $response\n";
    $testsFailed++;
}

echo "\n";

// Cleanup
echo "Cleaning up...\n";
if (function_exists('posix_kill')) {
    posix_kill($pid, SIGTERM);
} else {
    // Fallback for systems without POSIX extension
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("taskkill /F /PID $pid");
    } else {
        exec("kill -15 $pid");
    }
}
sleep(1);

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n⚠ IMPORTANT: If you see 'Authorization header required' errors, the fix is not working.\n";
    echo "The Authorization header is not being passed to PHP by Apache.\n";
    echo "\nTo fix this issue:\n";
    echo "1. Ensure CGIPassAuth On is in both Dockerfile and .htaccess\n";
    echo "2. Rebuild the Docker container: docker-compose build --no-cache\n";
    echo "3. Restart the container: docker-compose up -d\n";
    exit(1);
} else {
    echo "\n✓ All tests passed! Authorization header is being properly passed to PHP.\n";
    exit(0);
}

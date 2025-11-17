<?php
/**
 * Test suite for WebhookSecurityService
 * 
 * Tests centralized webhook security validation per SPEC_WEBHOOK.md §6
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/WebhookSecurityService.php';

function testWebhookSecurityService(): void {
    echo "\n=== Testing WebhookSecurityService ===\n\n";

    $allTestsPassed = true;

    // Test 1: Signature validation - valid signature
    echo "Test 1: Valid HMAC signature...\n";
    $config = ['webhooks' => ['timestamp_tolerance' => 300]];
    $service = new WebhookSecurityService($config);
    
    $secret = 'test-secret-key';
    $body = '{"event":"test.event","timestamp":1234567890,"data":{"key":"value"}}';
    $validSignature = 'sha256=' . hash_hmac('sha256', $body, $secret);
    
    $result = $service->validateSignature($validSignature, $body, $secret);
    if ($result === true) {
        echo "  ✓ Valid signature accepted\n";
    } else {
        echo "  ✗ Valid signature rejected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 2: Signature validation - invalid signature
    echo "\nTest 2: Invalid HMAC signature...\n";
    $invalidSignature = 'sha256=' . hash_hmac('sha256', $body, 'wrong-secret');
    
    $result = $service->validateSignature($invalidSignature, $body, $secret);
    if ($result === false) {
        echo "  ✓ Invalid signature rejected\n";
    } else {
        echo "  ✗ Invalid signature accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 3: Signature validation - malformed signature
    echo "\nTest 3: Malformed signature format...\n";
    $malformedSignature = 'invalid-format-12345';
    
    $result = $service->validateSignature($malformedSignature, $body, $secret);
    if ($result === false) {
        echo "  ✓ Malformed signature rejected\n";
    } else {
        echo "  ✗ Malformed signature accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 4: Signature validation - empty signature
    echo "\nTest 4: Empty signature...\n";
    $result = $service->validateSignature('', $body, $secret);
    if ($result === false) {
        echo "  ✓ Empty signature rejected\n";
    } else {
        echo "  ✗ Empty signature accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 5: Clock skew - valid timestamp
    echo "\nTest 5: Valid timestamp within tolerance...\n";
    $currentTime = time();
    $result = $service->enforceClockSkew($currentTime, 300);
    if ($result === true) {
        echo "  ✓ Current timestamp accepted\n";
    } else {
        echo "  ✗ Current timestamp rejected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 6: Clock skew - timestamp at edge of tolerance
    echo "\nTest 6: Timestamp at edge of tolerance (5 minutes ago)...\n";
    $pastTime = time() - 299; // Just within 300s tolerance
    $result = $service->enforceClockSkew($pastTime, 300);
    if ($result === true) {
        echo "  ✓ Timestamp within tolerance accepted\n";
    } else {
        echo "  ✗ Timestamp within tolerance rejected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 7: Clock skew - timestamp outside tolerance
    echo "\nTest 7: Timestamp outside tolerance (10 minutes ago)...\n";
    $oldTime = time() - 601; // Outside 300s tolerance
    $result = $service->enforceClockSkew($oldTime, 300);
    if ($result === false) {
        echo "  ✓ Old timestamp rejected\n";
    } else {
        echo "  ✗ Old timestamp accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 8: Clock skew - future timestamp outside tolerance
    echo "\nTest 8: Future timestamp outside tolerance...\n";
    $futureTime = time() + 601;
    $result = $service->enforceClockSkew($futureTime, 300);
    if ($result === false) {
        echo "  ✓ Future timestamp rejected\n";
    } else {
        echo "  ✗ Future timestamp accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 9: Clock skew - tolerance disabled (0)
    echo "\nTest 9: Timestamp validation disabled (tolerance = 0)...\n";
    $veryOldTime = time() - 86400; // 1 day ago
    $result = $service->enforceClockSkew($veryOldTime, 0);
    if ($result === true) {
        echo "  ✓ Timestamp accepted when validation disabled\n";
    } else {
        echo "  ✗ Timestamp rejected when validation disabled (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 10: IP whitelist - exact match
    echo "\nTest 10: IP whitelist - exact match...\n";
    $whitelist = ['192.168.1.1', '10.0.0.1'];
    $result = $service->checkWhitelist('192.168.1.1', $whitelist);
    if ($result === true) {
        echo "  ✓ Exact IP match accepted\n";
    } else {
        echo "  ✗ Exact IP match rejected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 11: IP whitelist - not in list
    echo "\nTest 11: IP whitelist - IP not in list...\n";
    $result = $service->checkWhitelist('192.168.1.100', $whitelist);
    if ($result === false) {
        echo "  ✓ Unlisted IP rejected\n";
    } else {
        echo "  ✗ Unlisted IP accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 12: IP whitelist - CIDR range match
    echo "\nTest 12: IP whitelist - CIDR range match...\n";
    $whitelist = ['192.168.1.0/24', '10.0.0.0/8'];
    $result = $service->checkWhitelist('192.168.1.50', $whitelist);
    if ($result === true) {
        echo "  ✓ IP in CIDR range accepted\n";
    } else {
        echo "  ✗ IP in CIDR range rejected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 13: IP whitelist - CIDR range no match
    echo "\nTest 13: IP whitelist - IP outside CIDR range...\n";
    $result = $service->checkWhitelist('192.168.2.50', $whitelist);
    if ($result === false) {
        echo "  ✓ IP outside CIDR range rejected\n";
    } else {
        echo "  ✗ IP outside CIDR range accepted (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 14: IP whitelist - empty whitelist (disabled)
    echo "\nTest 14: IP whitelist - empty whitelist (disabled)...\n";
    $result = $service->checkWhitelist('1.2.3.4', []);
    if ($result === true) {
        echo "  ✓ Any IP accepted when whitelist is empty\n";
    } else {
        echo "  ✗ IP rejected when whitelist is empty (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 15: IP whitelist - invalid IP format
    echo "\nTest 15: IP whitelist - invalid IP format...\n";
    try {
        $result = $service->checkWhitelist('not-an-ip', $whitelist);
        echo "  ✗ Invalid IP format should throw exception (FAILED)\n";
        $allTestsPassed = false;
    } catch (InvalidArgumentException $e) {
        echo "  ✓ Invalid IP format throws exception\n";
    }

    // Test 16: validateAll - all checks pass
    echo "\nTest 16: validateAll - all security checks pass...\n";
    $currentTime = time();
    $body = '{"event":"test","timestamp":' . $currentTime . ',"data":{}}';
    $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
    
    $params = [
        'signature_header' => $signature,
        'body' => $body,
        'secret' => $secret,
        'timestamp' => $currentTime,
        'ip' => '192.168.1.1',
        'whitelist' => ['192.168.1.0/24'],
    ];
    
    $result = $service->validateAll($params);
    if ($result['valid'] === true && 
        $result['checks']['signature'] === true &&
        $result['checks']['timestamp'] === true &&
        $result['checks']['whitelist'] === true) {
        echo "  ✓ All security checks passed\n";
    } else {
        echo "  ✗ Security checks failed: " . json_encode($result['errors']) . " (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 17: validateAll - signature fails
    echo "\nTest 17: validateAll - signature check fails...\n";
    $params['signature_header'] = 'sha256=invalid';
    
    $result = $service->validateAll($params);
    if ($result['valid'] === false && $result['checks']['signature'] === false) {
        echo "  ✓ Invalid signature detected correctly\n";
    } else {
        echo "  ✗ Invalid signature not detected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 18: validateAll - timestamp fails
    echo "\nTest 18: validateAll - timestamp check fails...\n";
    $params['signature_header'] = $signature;
    $params['timestamp'] = time() - 1000; // Outside tolerance
    
    $result = $service->validateAll($params);
    if ($result['valid'] === false && $result['checks']['timestamp'] === false) {
        echo "  ✓ Invalid timestamp detected correctly\n";
    } else {
        echo "  ✗ Invalid timestamp not detected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 19: validateAll - whitelist fails
    echo "\nTest 19: validateAll - whitelist check fails...\n";
    $params['timestamp'] = $currentTime;
    $params['ip'] = '10.0.0.1'; // Not in whitelist
    
    $result = $service->validateAll($params);
    if ($result['valid'] === false && $result['checks']['whitelist'] === false) {
        echo "  ✓ IP not in whitelist detected correctly\n";
    } else {
        echo "  ✗ IP not in whitelist not detected (FAILED)\n";
        $allTestsPassed = false;
    }

    // Test 20: Config integration - tolerance from config
    echo "\nTest 20: Config integration - timestamp tolerance from config...\n";
    $config = ['webhooks' => ['timestamp_tolerance' => 600]];
    $service = new WebhookSecurityService($config);
    
    $pastTime = time() - 500; // Within 600s but outside 300s
    $result = $service->enforceClockSkew($pastTime); // Should use config value
    if ($result === true) {
        echo "  ✓ Timestamp tolerance from config works correctly\n";
    } else {
        echo "  ✗ Timestamp tolerance from config not working (FAILED)\n";
        $allTestsPassed = false;
    }

    // Summary
    echo "\n" . str_repeat('=', 60) . "\n";
    if ($allTestsPassed) {
        echo "✓ All WebhookSecurityService tests passed!\n";
    } else {
        echo "✗ Some tests failed. Please review the output above.\n";
    }
    echo str_repeat('=', 60) . "\n";
}

// Run tests
testWebhookSecurityService();

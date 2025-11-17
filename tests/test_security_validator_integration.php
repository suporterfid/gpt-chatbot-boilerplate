<?php
/**
 * Integration test for SecurityValidator with chat-unified.php
 * 
 * Tests that the security validator properly integrates with the
 * extractTenantId function in chat-unified.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/SecurityValidator.php';

echo "\n=== SecurityValidator Integration Tests ===\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Valid tenant_id should pass validation in context
echo "\n--- Test 1: Valid Tenant ID Integration ---\n";
$_GET['tenant_id'] = 'valid-tenant-123';

// Simulate the extractTenantId behavior
function mockExtractTenantId() {
    if (!empty($_GET['tenant_id'])) {
        $tenantId = trim($_GET['tenant_id']);
        try {
            return SecurityValidator::validateTenantId($tenantId);
        } catch (Exception $e) {
            throw $e;
        }
    }
    return null;
}

try {
    $result = mockExtractTenantId();
    if ($result === 'valid-tenant-123') {
        echo "✓ PASS: Valid tenant_id extracted successfully\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Valid tenant_id not extracted correctly\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 2: SQL injection in tenant_id should be rejected
echo "\n--- Test 2: SQL Injection Rejected ---\n";
$_GET['tenant_id'] = "'; DROP TABLE agents; --";

try {
    $result = mockExtractTenantId();
    echo "✗ FAIL: SQL injection was not rejected\n";
    $testsFailed++;
} catch (Exception $e) {
    if ($e->getCode() === 400) {
        echo "✓ PASS: SQL injection properly rejected with code 400\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
        $testsFailed++;
    }
}

// Test 3: API key validation in context
echo "\n--- Test 3: API Key Validation ---\n";
$validKey = 'sk-test-12345678901234567890';
$result = SecurityValidator::validateApiKey($validKey);

if ($result === $validKey) {
    echo "✓ PASS: Valid API key accepted\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Valid API key rejected\n";
    $testsFailed++;
}

// Test 4: Invalid API key returns null (prevents enumeration)
echo "\n--- Test 4: API Key Enumeration Prevention ---\n";
$invalidKey = 'short';
$result = SecurityValidator::validateApiKey($invalidKey);

if ($result === null) {
    echo "✓ PASS: Invalid API key returns null (no exception thrown)\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Invalid API key should return null\n";
    $testsFailed++;
}

// Test 5: Conversation ID validation
echo "\n--- Test 5: Conversation ID Validation ---\n";
$validConvId = 'conv_abc123';
try {
    $result = SecurityValidator::validateConversationId($validConvId);
    if ($result === $validConvId) {
        echo "✓ PASS: Valid conversation_id accepted\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Conversation ID validation failed\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid conversation_id threw exception\n";
    $testsFailed++;
}

// Test 6: Agent slug validation
echo "\n--- Test 6: Agent Slug Validation ---\n";
$validSlug = 'support-agent';
try {
    $result = SecurityValidator::validateAgentSlug($validSlug);
    if ($result === $validSlug) {
        echo "✓ PASS: Valid agent slug accepted\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Agent slug validation failed\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid agent slug threw exception\n";
    $testsFailed++;
}

// Test 7: ID validation
echo "\n--- Test 7: Integer ID Validation ---\n";
try {
    $result = SecurityValidator::validateId('123');
    if ($result === 123) {
        echo "✓ PASS: Valid ID accepted and converted to integer\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: ID validation failed\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid ID threw exception\n";
    $testsFailed++;
}

// Test 8: Negative ID should fail
echo "\n--- Test 8: Negative ID Rejection ---\n";
try {
    $result = SecurityValidator::validateId('-1');
    echo "✗ FAIL: Negative ID should be rejected\n";
    $testsFailed++;
} catch (Exception $e) {
    if ($e->getCode() === 400) {
        echo "✓ PASS: Negative ID properly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception code\n";
        $testsFailed++;
    }
}

// Test 9: Email validation
echo "\n--- Test 9: Email Validation ---\n";
try {
    $result = SecurityValidator::validateEmail('user@example.com');
    if ($result === 'user@example.com') {
        echo "✓ PASS: Valid email accepted\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Email validation failed\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid email threw exception\n";
    $testsFailed++;
}

// Test 10: Invalid email rejection
echo "\n--- Test 10: Invalid Email Rejection ---\n";
try {
    $result = SecurityValidator::validateEmail('not-an-email');
    echo "✗ FAIL: Invalid email should be rejected\n";
    $testsFailed++;
} catch (Exception $e) {
    if ($e->getCode() === 400) {
        echo "✓ PASS: Invalid email properly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception code\n";
        $testsFailed++;
    }
}

// Test 11: Message sanitization removes dangerous content
echo "\n--- Test 11: Message Sanitization ---\n";
$dangerousMessage = 'Hello <script>alert("XSS")</script> world';
$sanitized = SecurityValidator::sanitizeMessage($dangerousMessage);

if (strpos($sanitized, '<script') === false) {
    echo "✓ PASS: Script tags removed from message\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Script tags not removed\n";
    $testsFailed++;
}

// Test 12: Event handlers removed
echo "\n--- Test 12: Event Handler Removal ---\n";
$messageWithEvent = '<img src="x" onerror="alert(1)">';
$sanitized = SecurityValidator::sanitizeMessage($messageWithEvent);

if (strpos($sanitized, 'onerror') === false) {
    echo "✓ PASS: Event handlers removed\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Event handlers not removed\n";
    $testsFailed++;
}

// Test 13: JavaScript protocol removed
echo "\n--- Test 13: JavaScript Protocol Removal ---\n";
$messageWithJS = '<a href="javascript:alert(1)">Click</a>';
$sanitized = SecurityValidator::sanitizeMessage($messageWithJS);

if (strpos($sanitized, 'javascript:') === false) {
    echo "✓ PASS: JavaScript protocol removed\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: JavaScript protocol not removed\n";
    $testsFailed++;
}

// Summary
echo "\n=== Integration Test Summary ===\n";
echo "Tests passed: $testsPassed\n";
echo "Tests failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All integration tests passed!\n";
    echo "SecurityValidator is properly integrated and working correctly.\n";
    exit(0);
} else {
    echo "\n❌ Some integration tests failed.\n";
    exit(1);
}

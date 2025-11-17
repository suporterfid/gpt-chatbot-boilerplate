<?php
/**
 * Test SQL Injection Prevention - Issue 002
 * 
 * Tests the SecurityValidator class and extractTenantId function
 * to ensure SQL injection attacks are prevented.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/SecurityValidator.php';

// Simulate extractTenantId function for testing
function testExtractTenantId($tenantId, $source = 'GET') {
    if ($source === 'GET') {
        $_GET['tenant_id'] = $tenantId;
    } elseif ($source === 'POST') {
        $_POST['tenant_id'] = $tenantId;
    } elseif ($source === 'HEADER') {
        $_SERVER['HTTP_X_TENANT_ID'] = $tenantId;
    }
    
    try {
        $validated = SecurityValidator::validateTenantId($tenantId);
        return $validated;
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => $e->getCode()];
    }
}

echo "\n=== SQL Injection Prevention Tests (Issue 002) ===\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Valid tenant IDs should pass
echo "\n--- Test 1: Valid Tenant IDs ---\n";
$validIds = [
    'tenant1',
    'tenant-123',
    'tenant_abc',
    'ABC123',
    'a',
    str_repeat('x', 64), // Maximum length
];

foreach ($validIds as $id) {
    try {
        $result = SecurityValidator::validateTenantId($id);
        if ($result === $id) {
            echo "✓ PASS: Valid tenant_id accepted: '$id'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Valid tenant_id rejected: '$id'\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "✗ FAIL: Valid tenant_id threw exception: '$id' - " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

// Test 2: SQL Injection attempts should be rejected
echo "\n--- Test 2: SQL Injection Attempts ---\n";
$maliciousIds = [
    "'; DROP TABLE agents; --",
    "' OR '1'='1",
    "1' UNION SELECT * FROM admin_users--",
    "admin'--",
    "'; DELETE FROM agents WHERE '1'='1",
    "' OR 1=1--",
    "1'; UPDATE agents SET name='hacked'--",
];

foreach ($maliciousIds as $id) {
    try {
        $result = SecurityValidator::validateTenantId($id);
        echo "✗ FAIL: SQL injection attempt accepted: '" . substr($id, 0, 30) . "...'\n";
        $testsFailed++;
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            echo "✓ PASS: SQL injection rejected: '" . substr($id, 0, 30) . "...'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
            $testsFailed++;
        }
    }
}

// Test 3: Path traversal attempts should be rejected
echo "\n--- Test 3: Path Traversal Attempts ---\n";
$pathTraversals = [
    "../../../etc/passwd",
    "..\\..\\windows\\system32",
    "./../../config",
    "tenant/../admin",
];

foreach ($pathTraversals as $id) {
    try {
        $result = SecurityValidator::validateTenantId($id);
        echo "✗ FAIL: Path traversal accepted: '$id'\n";
        $testsFailed++;
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            echo "✓ PASS: Path traversal rejected: '$id'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
            $testsFailed++;
        }
    }
}

// Test 4: Special characters should be rejected
echo "\n--- Test 4: Special Characters ---\n";
$specialChars = [
    "tenant<script>",
    "tenant>alert",
    "tenant\0null",
    "tenant\nalert",
    "tenant;command",
    "tenant|command",
    "tenant&command",
    "tenant`command`",
    'tenant$var',  // Use single quotes to prevent variable interpolation
    "tenant%27",
];

foreach ($specialChars as $id) {
    try {
        $result = SecurityValidator::validateTenantId($id);
        echo "✗ FAIL: Special character accepted: '$id'\n";
        $testsFailed++;
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            echo "✓ PASS: Special character rejected: '$id'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
            $testsFailed++;
        }
    }
}

// Test 5: Length validation
echo "\n--- Test 5: Length Validation ---\n";
$tooLong = str_repeat('x', 65);
try {
    $result = SecurityValidator::validateTenantId($tooLong);
    echo "✗ FAIL: Too long tenant_id accepted (65 chars)\n";
    $testsFailed++;
} catch (Exception $e) {
    if ($e->getCode() === 400) {
        echo "✓ PASS: Too long tenant_id rejected (65 chars)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
        $testsFailed++;
    }
}

// Test 6: Empty and null handling
echo "\n--- Test 6: Empty and Null Handling ---\n";
$emptyValues = [null, '', '   ', "\t", "\n"];
foreach ($emptyValues as $val) {
    $result = SecurityValidator::validateTenantId($val);
    if ($result === null) {
        echo "✓ PASS: Empty/null value returns null\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Empty/null value didn't return null\n";
        $testsFailed++;
    }
}

// Test 7: API Key validation
echo "\n--- Test 7: API Key Validation ---\n";
$validApiKeys = [
    str_repeat('a', 20), // Minimum length
    str_repeat('b', 128), // Maximum length
    'sk-abc123def456ghi789',
    'api_key_12345678901234567890',
];

foreach ($validApiKeys as $key) {
    $result = SecurityValidator::validateApiKey($key);
    if ($result === $key) {
        echo "✓ PASS: Valid API key accepted (length: " . strlen($key) . ")\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Valid API key rejected (length: " . strlen($key) . ")\n";
        $testsFailed++;
    }
}

// Test 8: Invalid API keys (should return null, not throw)
echo "\n--- Test 8: Invalid API Keys ---\n";
$invalidApiKeys = [
    'short', // Too short
    str_repeat('x', 129), // Too long
    'key with spaces',
    'key<script>',
    'key;drop',
];

foreach ($invalidApiKeys as $key) {
    $result = SecurityValidator::validateApiKey($key);
    if ($result === null) {
        echo "✓ PASS: Invalid API key returned null (prevents enumeration)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Invalid API key didn't return null\n";
        $testsFailed++;
    }
}

// Test 9: Filename validation
echo "\n--- Test 9: Filename Validation ---\n";
$validFilenames = [
    'document.pdf',
    'image.jpg',
    'report-2024.xlsx',
    'data_file.txt',
];

foreach ($validFilenames as $filename) {
    try {
        $result = SecurityValidator::validateFilename($filename);
        if ($result === $filename) {
            echo "✓ PASS: Valid filename accepted: '$filename'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Valid filename rejected: '$filename'\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "✗ FAIL: Valid filename threw exception: '$filename'\n";
        $testsFailed++;
    }
}

// Test 10: Malicious filenames
echo "\n--- Test 10: Malicious Filenames ---\n";
$maliciousFilenames = [
    '../../../etc/passwd',
    'file.pdf.php', // Double extension
    'file<script>.pdf',
    'file\0.pdf', // Null byte
    '..',
    '.',
];

foreach ($maliciousFilenames as $filename) {
    try {
        $result = SecurityValidator::validateFilename($filename);
        echo "✗ FAIL: Malicious filename accepted: '$filename'\n";
        $testsFailed++;
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            echo "✓ PASS: Malicious filename rejected: '$filename'\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Wrong exception code for filename: " . $e->getCode() . "\n";
            $testsFailed++;
        }
    }
}

// Test 11: Conversation ID validation
echo "\n--- Test 11: Conversation ID Validation ---\n";
$validConvIds = ['conv_123', 'session-abc-def', 'chat_12345'];
foreach ($validConvIds as $id) {
    try {
        $result = SecurityValidator::validateConversationId($id);
        if ($result === $id) {
            echo "✓ PASS: Valid conversation_id accepted: '$id'\n";
            $testsPassed++;
        }
    } catch (Exception $e) {
        echo "✗ FAIL: Valid conversation_id rejected: '$id'\n";
        $testsFailed++;
    }
}

// Test 12: Message sanitization
echo "\n--- Test 12: Message Sanitization ---\n";
$testMessage = '<script>alert("XSS")</script>Hello <img src=x onerror="alert(1)"> world';
$sanitized = SecurityValidator::sanitizeMessage($testMessage);

if (strpos($sanitized, '<script') === false && strpos($sanitized, 'onerror') === false) {
    echo "✓ PASS: Malicious content removed from message\n";
    $testsPassed++;
} else {
    echo "✗ FAIL: Malicious content not properly removed\n";
    $testsFailed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Tests passed: $testsPassed\n";
echo "Tests failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All SQL injection prevention tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
    exit(1);
}

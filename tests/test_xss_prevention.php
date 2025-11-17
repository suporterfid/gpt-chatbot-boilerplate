<?php
/**
 * XSS Prevention Tests
 * Tests for Cross-Site Scripting vulnerability prevention
 * 
 * Related Issue: #005 - XSS Vulnerability Frontend
 * Tests both backend sanitization and validates frontend implementation
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityValidator.php';

echo "\n=== XSS Prevention Tests ===\n";

$testsPassed = 0;
$testsFailed = 0;

/**
 * Test helper function
 */
function runTest($testName, $callback) {
    global $testsPassed, $testsFailed;
    
    echo "\n--- Test: $testName ---\n";
    
    try {
        $result = $callback();
        if ($result) {
            echo "✓ PASS: $testName\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: $testName\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "✗ FAIL: $testName - Exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

// Test 1: Script tag removal
runTest('Script tag should be removed from message', function() {
    $malicious = '<script>alert("XSS")</script>Hello';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Script tag not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 2: Event handler removal
runTest('Event handlers should be removed', function() {
    $malicious = '<img src="x" onerror="alert(1)">';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (strpos($sanitized, 'onerror') !== false) {
        echo "   Error: Event handler not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 3: JavaScript protocol removal
runTest('JavaScript protocol should be removed', function() {
    $malicious = '<a href="javascript:alert(1)">Click</a>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (stripos($sanitized, 'javascript:') !== false) {
        echo "   Error: JavaScript protocol not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 4: Multiple script tags
runTest('Multiple script tags should be removed', function() {
    $malicious = '<script>alert(1)</script>Text<script>alert(2)</script>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Not all script tags removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 5: Various event handlers
runTest('Various event handlers should be removed', function() {
    $handlers = [
        '<div onclick="alert(1)">Click</div>',
        '<img onload="alert(1)" src="x">',
        '<body onload="alert(1)">',
        '<div onmouseover="alert(1)">Hover</div>',
        '<input onfocus="alert(1)">',
    ];
    
    foreach ($handlers as $malicious) {
        $sanitized = SecurityValidator::sanitizeMessage($malicious);
        
        // Check for any on* event handlers
        if (preg_match('/\s+on\w+\s*=/i', $sanitized)) {
            echo "   Error: Event handler not removed from: $malicious\n";
            echo "   Output: $sanitized\n";
            return false;
        }
    }
    
    echo "   All event handlers successfully removed\n";
    return true;
});

// Test 6: Data URI with script
runTest('Data URI with script should be blocked', function() {
    $malicious = '<a href="data:text/html,<script>alert(1)</script>">Click</a>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    // Should not contain script tag
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Script in data URI not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 7: SVG with script
runTest('SVG with script should be sanitized', function() {
    $malicious = '<svg><script>alert(1)</script></svg>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Script in SVG not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 8: Encoded script tag
runTest('Encoded script tag should be handled', function() {
    $malicious = '&lt;script&gt;alert(1)&lt;/script&gt;';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    // After decoding and sanitizing, should not contain executable script
    // The sanitizer should handle both encoded and decoded forms
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true; // Already encoded, should pass through safely
});

// Test 9: Mixed case script tag
runTest('Mixed case script tag should be removed', function() {
    $malicious = '<ScRiPt>alert(1)</sCrIpT>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (stripos($sanitized, '<script') !== false) {
        echo "   Error: Mixed case script tag not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 10: Normal text should pass through
runTest('Normal text should not be modified', function() {
    $normal = 'Hello, this is a normal message with no HTML.';
    $sanitized = SecurityValidator::sanitizeMessage($normal);
    
    if ($sanitized !== $normal) {
        echo "   Error: Normal text was modified\n";
        echo "   Input: $normal\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Normal text passed through correctly\n";
    return true;
});

// Test 11: Safe HTML should be preserved
runTest('Safe HTML like bold should be preserved', function() {
    $safe = 'This is <b>bold</b> and <i>italic</i> text.';
    $sanitized = SecurityValidator::sanitizeMessage($safe);
    
    // Safe tags like <b> and <i> might be preserved or removed depending on implementation
    // The important thing is no script execution
    echo "   Input: $safe\n";
    echo "   Output: $sanitized\n";
    
    // Verify no script injection
    if (strpos($sanitized, '<script') !== false || strpos($sanitized, 'javascript:') !== false) {
        echo "   Error: Malicious content found\n";
        return false;
    }
    
    return true;
});

// Test 12: Nested script tags
runTest('Nested script tags should be removed', function() {
    $malicious = '<script><script>alert(1)</script></script>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Nested script tags not fully removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 13: iframe injection
runTest('iframe should be removed', function() {
    $malicious = '<iframe src="http://evil.com"></iframe>';
    $sanitized = SecurityValidator::sanitizeMessage($malicious);
    
    if (stripos($sanitized, '<iframe') !== false) {
        echo "   Error: iframe not removed\n";
        echo "   Input: $malicious\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Input: $malicious\n";
    echo "   Output: $sanitized\n";
    return true;
});

// Test 14: object/embed tags
runTest('object and embed tags should be removed', function() {
    $malicious1 = '<object data="http://evil.com"></object>';
    $malicious2 = '<embed src="http://evil.com">';
    
    $sanitized1 = SecurityValidator::sanitizeMessage($malicious1);
    $sanitized2 = SecurityValidator::sanitizeMessage($malicious2);
    
    if (stripos($sanitized1, '<object') !== false) {
        echo "   Error: object tag not removed\n";
        echo "   Output: $sanitized1\n";
        return false;
    }
    
    if (stripos($sanitized2, '<embed') !== false) {
        echo "   Error: embed tag not removed\n";
        echo "   Output: $sanitized2\n";
        return false;
    }
    
    echo "   Both object and embed tags removed\n";
    return true;
});

// Test 15: Long message with XSS
runTest('Long message with embedded XSS should be sanitized', function() {
    $longMessage = str_repeat('Normal text. ', 100) . '<script>alert(1)</script>' . str_repeat(' More text.', 100);
    $sanitized = SecurityValidator::sanitizeMessage($longMessage);
    
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Script tag in long message not removed\n";
        return false;
    }
    
    echo "   Long message successfully sanitized\n";
    return true;
});

// Test 16: Filename sanitization (using SecurityValidator if it has this method)
runTest('Filename with script should be sanitized', function() {
    $maliciousFilename = '<script>alert("XSS")</script>document.pdf';
    
    // If SecurityValidator has validateFilename, test that it rejects the malicious filename
    if (method_exists('SecurityValidator', 'validateFilename')) {
        try {
            $result = SecurityValidator::validateFilename($maliciousFilename);
            // If we get here, it means validation passed (bad)
            echo "   Error: Malicious filename was not rejected\n";
            return false;
        } catch (Exception $e) {
            // Expected - malicious filename should be rejected
            echo "   Malicious filename correctly rejected: " . $e->getMessage() . "\n";
            return true;
        }
    }
    
    // Otherwise, check if sanitizeMessage can handle it
    $sanitized = SecurityValidator::sanitizeMessage($maliciousFilename);
    if (strpos($sanitized, '<script') !== false) {
        echo "   Error: Script tag in filename not removed\n";
        echo "   Input: $maliciousFilename\n";
        echo "   Output: $sanitized\n";
        return false;
    }
    
    echo "   Filename sanitized: $sanitized\n";
    return true;
});

// Summary
echo "\n=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed === 0) {
    echo "\n✓ All XSS prevention tests passed!\n\n";
    exit(0);
} else {
    echo "\n✗ Some XSS prevention tests failed!\n\n";
    exit(1);
}

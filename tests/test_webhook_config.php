<?php
/**
 * Test suite for Webhook Configuration (WH-007a, WH-007b)
 *
 * Validates that webhook configuration is properly loaded from environment variables
 * and available in the config array.
 */

declare(strict_types=1);

function testWebhookConfiguration(): void {
    echo "\n=== Testing Webhook Configuration (WH-007a, WH-007b) ===\n\n";
    
    $allPassed = true;
    
    // Test 1: Load config and verify webhook section exists
    echo "Test 1: Verify webhook configuration section exists...\n";
    $config = require __DIR__ . '/../config.php';
    if (isset($config['webhooks'])) {
        echo "  ✓ Webhooks configuration section found\n";
    } else {
        echo "  ✗ Webhooks configuration section missing\n";
        $allPassed = false;
        exit(1);
    }
    
    // Test 2: Verify inbound configuration structure
    echo "\nTest 2: Verify inbound webhook configuration...\n";
    if (isset($config['webhooks']['inbound']) && is_array($config['webhooks']['inbound'])) {
        echo "  ✓ Inbound configuration section exists\n";
        
        $requiredInboundKeys = ['enabled', 'path', 'validate_signature', 'max_clock_skew', 'ip_whitelist'];
        foreach ($requiredInboundKeys as $key) {
            if (array_key_exists($key, $config['webhooks']['inbound'])) {
                echo "  ✓ Inbound key '$key' exists\n";
            } else {
                echo "  ✗ Inbound key '$key' missing\n";
                $allPassed = false;
            }
        }
    } else {
        echo "  ✗ Inbound configuration section missing or invalid\n";
        $allPassed = false;
    }
    
    // Test 3: Verify outbound configuration structure
    echo "\nTest 3: Verify outbound webhook configuration...\n";
    if (isset($config['webhooks']['outbound']) && is_array($config['webhooks']['outbound'])) {
        echo "  ✓ Outbound configuration section exists\n";
        
        $requiredOutboundKeys = ['enabled', 'max_attempts', 'timeout', 'concurrency'];
        foreach ($requiredOutboundKeys as $key) {
            if (array_key_exists($key, $config['webhooks']['outbound'])) {
                echo "  ✓ Outbound key '$key' exists\n";
            } else {
                echo "  ✗ Outbound key '$key' missing\n";
                $allPassed = false;
            }
        }
    } else {
        echo "  ✗ Outbound configuration section missing or invalid\n";
        $allPassed = false;
    }
    
    // Test 4: Verify default values
    echo "\nTest 4: Verify default configuration values...\n";
    $expectedDefaults = [
        ['webhooks', 'inbound', 'enabled', true, 'boolean'],
        ['webhooks', 'inbound', 'path', '/webhook/inbound', 'string'],
        ['webhooks', 'inbound', 'validate_signature', true, 'boolean'],
        ['webhooks', 'inbound', 'max_clock_skew', 120, 'integer'],
        ['webhooks', 'inbound', 'ip_whitelist', [], 'array'],
        ['webhooks', 'outbound', 'enabled', true, 'boolean'],
        ['webhooks', 'outbound', 'max_attempts', 6, 'integer'],
        ['webhooks', 'outbound', 'timeout', 5, 'integer'],
        ['webhooks', 'outbound', 'concurrency', 10, 'integer'],
    ];
    
    foreach ($expectedDefaults as [$section, $subsection, $key, $expectedValue, $expectedType]) {
        $actualValue = $config[$section][$subsection][$key];
        $actualType = gettype($actualValue);
        
        if ($actualType === $expectedType && $actualValue === $expectedValue) {
            echo "  ✓ $section.$subsection.$key = $expectedValue ($expectedType)\n";
        } else {
            echo "  ✗ $section.$subsection.$key: expected $expectedValue ($expectedType), got " . 
                 json_encode($actualValue) . " ($actualType)\n";
            $allPassed = false;
        }
    }
    
    // Test 5: Test with custom environment variables
    echo "\nTest 5: Test configuration with custom environment variables...\n";
    putenv('WEBHOOK_INBOUND_ENABLED=false');
    putenv('WEBHOOK_MAX_CLOCK_SKEW=60');
    putenv('WEBHOOK_MAX_ATTEMPTS=3');
    putenv('WEBHOOK_TIMEOUT=10');
    putenv('WEBHOOK_CONCURRENCY=5');
    
    // Reload config
    $customConfig = require __DIR__ . '/../config.php';
    
    $customTests = [
        ['webhooks', 'inbound', 'enabled', false],
        ['webhooks', 'inbound', 'max_clock_skew', 60],
        ['webhooks', 'outbound', 'max_attempts', 3],
        ['webhooks', 'outbound', 'timeout', 10],
        ['webhooks', 'outbound', 'concurrency', 5],
    ];
    
    foreach ($customTests as [$section, $subsection, $key, $expectedValue]) {
        $actualValue = $customConfig[$section][$subsection][$key];
        if ($actualValue === $expectedValue) {
            echo "  ✓ Custom $section.$subsection.$key = $expectedValue\n";
        } else {
            echo "  ✗ Custom $section.$subsection.$key: expected $expectedValue, got " . 
                 json_encode($actualValue) . "\n";
            $allPassed = false;
        }
    }
    
    // Clean up environment
    putenv('WEBHOOK_INBOUND_ENABLED');
    putenv('WEBHOOK_MAX_CLOCK_SKEW');
    putenv('WEBHOOK_MAX_ATTEMPTS');
    putenv('WEBHOOK_TIMEOUT');
    putenv('WEBHOOK_CONCURRENCY');
    
    // Test 6: Verify backward compatibility with existing webhook config
    echo "\nTest 6: Verify backward compatibility with existing webhook config...\n";
    $legacyKeys = ['gateway_secret', 'timestamp_tolerance', 'log_payloads', 'openai_signing_secret', 'ip_whitelist'];
    foreach ($legacyKeys as $key) {
        if (array_key_exists($key, $config['webhooks'])) {
            echo "  ✓ Legacy key '$key' still exists\n";
        } else {
            echo "  ✗ Legacy key '$key' missing (backward compatibility broken)\n";
            $allPassed = false;
        }
    }
    
    // Summary
    echo "\n" . str_repeat('=', 60) . "\n";
    if ($allPassed) {
        echo "✓ All webhook configuration tests PASSED\n";
    } else {
        echo "✗ Some tests FAILED\n";
        exit(1);
    }
    echo str_repeat('=', 60) . "\n";
}

// Run tests
testWebhookConfiguration();

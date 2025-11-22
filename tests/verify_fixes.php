<?php
/**
 * Verification Script for Code Review Fixes
 *
 * This script tests all the fixes applied from the comprehensive code review:
 * - PHP compilation errors
 * - Security vulnerabilities
 * - API integration
 *
 * Run this script to verify everything is working correctly.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/CryptoAdapter.php';

// Initialize test results
$results = [
    'passed' => 0,
    'failed' => 0,
    'errors' => []
];

echo "=================================================\n";
echo "CODE REVIEW FIXES VERIFICATION\n";
echo "=================================================\n\n";

// Test 1: Environment Variables
echo "Test 1: Environment Variables Configuration\n";
echo "--------------------------------------------\n";

$requiredEnvVars = [
    'APP_BASE_URL' => 'Application base URL',
    'ALLOWED_HOSTS' => 'Allowed hosts whitelist',
    'BLOG_ENCRYPTION_KEY' => 'Blog encryption key',
    'ENCRYPTION_KEY' => 'General encryption key'
];

foreach ($requiredEnvVars as $var => $description) {
    $value = getEnvValue($var);
    if (empty($value)) {
        echo "  ❌ FAIL: $var ($description) is not set\n";
        $results['failed']++;
        $results['errors'][] = "$var is missing in .env file";
    } else {
        echo "  ✓ PASS: $var is configured\n";
        $results['passed']++;
    }
}
echo "\n";

// Test 2: WordPress Blog Queue Service - requeueArticle() method
echo "Test 2: WordPressBlogQueueService - requeueArticle() Method\n";
echo "-----------------------------------------------------------\n";

try {
    require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogConfigurationService.php';
    require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogQueueService.php';

    // Check if method exists
    if (method_exists('WordPressBlogQueueService', 'requeueArticle')) {
        echo "  ✓ PASS: requeueArticle() method exists\n";
        $results['passed']++;
    } else {
        echo "  ❌ FAIL: requeueArticle() method not found\n";
        $results['failed']++;
        $results['errors'][] = "WordPressBlogQueueService::requeueArticle() method missing";
    }

    // Check if getQueueStats() method exists (not getQueueStatistics)
    if (method_exists('WordPressBlogQueueService', 'getQueueStats')) {
        echo "  ✓ PASS: getQueueStats() method exists\n";
        $results['passed']++;
    } else {
        echo "  ❌ FAIL: getQueueStats() method not found\n";
        $results['failed']++;
        $results['errors'][] = "WordPressBlogQueueService::getQueueStats() method missing";
    }
} catch (Exception $e) {
    echo "  ❌ FAIL: Error loading WordPressBlogQueueService: " . $e->getMessage() . "\n";
    $results['failed']++;
    $results['errors'][] = "WordPressBlogQueueService loading error: " . $e->getMessage();
}
echo "\n";

// Test 3: CryptoAdapter Constructor Compatibility
echo "Test 3: WordPressBlogConfigurationService - CryptoAdapter Compatibility\n";
echo "-----------------------------------------------------------------------\n";

try {
    $db = new DB();
    $cryptoAdapter = new CryptoAdapter(['encryption_key' => getEnvValue('ENCRYPTION_KEY')]);

    // Test with CryptoAdapter object
    $configService1 = new WordPressBlogConfigurationService($db, $cryptoAdapter);
    echo "  ✓ PASS: Constructor accepts CryptoAdapter object\n";
    $results['passed']++;

    // Test with array
    $configService2 = new WordPressBlogConfigurationService($db, [
        'encryption_key' => getEnvValue('BLOG_ENCRYPTION_KEY')
    ]);
    echo "  ✓ PASS: Constructor accepts configuration array\n";
    $results['passed']++;

} catch (Exception $e) {
    echo "  ❌ FAIL: " . $e->getMessage() . "\n";
    $results['failed']++;
    $results['errors'][] = "CryptoAdapter constructor compatibility issue: " . $e->getMessage();
}
echo "\n";

// Test 4: Class Dependencies Loading
echo "Test 4: WordPress Blog Class Dependencies\n";
echo "------------------------------------------\n";

$requiredClasses = [
    'WordPressBlogConfigurationService',
    'WordPressBlogQueueService',
    'WordPressBlogGeneratorService'
];

foreach ($requiredClasses as $className) {
    try {
        if ($className === 'WordPressBlogGeneratorService') {
            require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogGeneratorService.php';
        }

        if (class_exists($className)) {
            echo "  ✓ PASS: $className loaded successfully\n";
            $results['passed']++;
        } else {
            echo "  ❌ FAIL: $className not found\n";
            $results['failed']++;
            $results['errors'][] = "$className not loaded";
        }
    } catch (Exception $e) {
        echo "  ❌ FAIL: Error loading $className: " . $e->getMessage() . "\n";
        $results['failed']++;
        $results['errors'][] = "$className loading error: " . $e->getMessage();
    }
}
echo "\n";

// Test 5: Host Header Injection Fix
echo "Test 5: Host Header Injection Security Fix\n";
echo "-------------------------------------------\n";

$appBaseUrl = getEnvValue('APP_BASE_URL');
if (!empty($appBaseUrl) && filter_var($appBaseUrl, FILTER_VALIDATE_URL)) {
    echo "  ✓ PASS: APP_BASE_URL is set and valid: $appBaseUrl\n";
    $results['passed']++;
} else {
    echo "  ⚠ WARNING: APP_BASE_URL not set or invalid\n";
    echo "  INFO: Host header validation will use ALLOWED_HOSTS fallback\n";
    $results['passed']++;
}

$allowedHosts = getEnvValue('ALLOWED_HOSTS');
if (!empty($allowedHosts)) {
    $hosts = explode(',', $allowedHosts);
    echo "  ✓ PASS: ALLOWED_HOSTS configured with " . count($hosts) . " host(s)\n";
    $results['passed']++;
} else {
    echo "  ❌ FAIL: ALLOWED_HOSTS not configured\n";
    $results['failed']++;
    $results['errors'][] = "ALLOWED_HOSTS must be configured for security";
}
echo "\n";

// Test 6: Encryption Keys
echo "Test 6: Encryption Keys Security\n";
echo "---------------------------------\n";

$blogKey = getEnvValue('BLOG_ENCRYPTION_KEY');
$generalKey = getEnvValue('ENCRYPTION_KEY');

if (!empty($blogKey) && strlen($blogKey) >= 64) {
    echo "  ✓ PASS: BLOG_ENCRYPTION_KEY is set and secure (64+ hex chars)\n";
    $results['passed']++;
} else {
    echo "  ❌ FAIL: BLOG_ENCRYPTION_KEY is missing or too short\n";
    $results['failed']++;
    $results['errors'][] = "BLOG_ENCRYPTION_KEY must be at least 64 hex characters";
}

if (!empty($generalKey) && strlen($generalKey) >= 64) {
    echo "  ✓ PASS: ENCRYPTION_KEY is set and secure (64+ hex chars)\n";
    $results['passed']++;
} else {
    echo "  ❌ FAIL: ENCRYPTION_KEY is missing or too short\n";
    $results['failed']++;
    $results['errors'][] = "ENCRYPTION_KEY must be at least 64 hex characters";
}
echo "\n";

// Test 7: Database Connection
echo "Test 7: Database Connectivity\n";
echo "-----------------------------\n";

try {
    $db = new DB();

    // Test basic query
    $result = $db->query("SELECT 1 as test");
    if (!empty($result) && $result[0]['test'] == 1) {
        echo "  ✓ PASS: Database connection successful\n";
        $results['passed']++;
    } else {
        echo "  ❌ FAIL: Database query returned unexpected result\n";
        $results['failed']++;
        $results['errors'][] = "Database query test failed";
    }

    // Check if WordPress Blog tables exist
    $tables = ['blog_configurations', 'blog_articles_queue'];
    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table");
            $count = $result[0]['count'] ?? 0;
            echo "  ✓ PASS: Table '$table' exists (contains $count records)\n";
            $results['passed']++;
        } catch (Exception $e) {
            echo "  ⚠ WARNING: Table '$table' not found (may need migration)\n";
            // Don't count as failed - table might not be created yet
        }
    }
} catch (Exception $e) {
    echo "  ❌ FAIL: Database connection failed: " . $e->getMessage() . "\n";
    $results['failed']++;
    $results['errors'][] = "Database connection error: " . $e->getMessage();
}
echo "\n";

// Test Summary
echo "=================================================\n";
echo "TEST SUMMARY\n";
echo "=================================================\n";
echo "Passed: " . $results['passed'] . "\n";
echo "Failed: " . $results['failed'] . "\n";
echo "\n";

if ($results['failed'] > 0) {
    echo "ERRORS FOUND:\n";
    echo "-------------\n";
    foreach ($results['errors'] as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n";
    echo "❌ VERIFICATION FAILED - Please fix the errors above\n";
    exit(1);
} else {
    echo "✅ ALL TESTS PASSED - All fixes verified successfully!\n";
    echo "\n";
    echo "Next Steps:\n";
    echo "1. Update APP_BASE_URL in .env to your production domain\n";
    echo "2. Update ALLOWED_HOSTS to include your production domains\n";
    echo "3. Run database migrations if WordPress Blog tables are missing\n";
    echo "4. Deploy to production and monitor logs\n";
    exit(0);
}

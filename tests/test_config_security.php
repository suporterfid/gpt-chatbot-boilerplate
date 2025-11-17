<?php
/**
 * Tests for Configuration Security (Issue #008)
 * 
 * Tests ConfigValidator, SecretsManager, and ErrorHandler
 */

require_once __DIR__ . '/../includes/ConfigValidator.php';
require_once __DIR__ . '/../includes/SecretsManager.php';
require_once __DIR__ . '/../includes/ErrorHandler.php';

echo "\n=== Configuration Security Tests ===\n";

$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;

/**
 * Test helper function
 */
function runTest(string $testName, callable $testFunction): void {
    global $testsRun, $testsPassed, $testsFailed;
    
    echo "\n--- $testName ---\n";
    $testsRun++;
    
    try {
        $testFunction();
        echo "✓ PASS: $testName\n";
        $testsPassed++;
    } catch (Exception $e) {
        echo "✗ FAIL: $testName\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

// =============================================================================
// ConfigValidator Tests
// =============================================================================

runTest('ConfigValidator: Missing required key', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            // api_key missing
            'base_url' => 'https://api.openai.com/v1'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== false) {
        throw new Exception('Should fail validation with missing api_key');
    }
    
    $errors = $validator->getErrors();
    if (empty($errors)) {
        throw new Exception('Should have validation errors');
    }
    
    $hasApiKeyError = false;
    foreach ($errors as $error) {
        if (str_contains($error, 'OPENAI_API_KEY')) {
            $hasApiKeyError = true;
            break;
        }
    }
    
    if (!$hasApiKeyError) {
        throw new Exception('Should mention OPENAI_API_KEY in errors');
    }
});

runTest('ConfigValidator: Invalid API key format', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            'api_key' => 'invalid_key',
            'base_url' => 'https://api.openai.com/v1'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== false) {
        throw new Exception('Should fail with invalid API key format');
    }
    
    $errors = $validator->getErrors();
    $hasFormatError = false;
    foreach ($errors as $error) {
        if (str_contains($error, 'invalid format')) {
            $hasFormatError = true;
            break;
        }
    }
    
    if (!$hasFormatError) {
        throw new Exception('Should mention invalid format');
    }
});

runTest('ConfigValidator: Valid configuration', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            'api_key' => 'sk-' . str_repeat('a', 40),
            'base_url' => 'https://api.openai.com/v1'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== true) {
        $errors = $validator->getErrors();
        throw new Exception('Should pass validation: ' . implode(', ', $errors));
    }
});

runTest('ConfigValidator: Invalid URL format', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            'api_key' => 'sk-' . str_repeat('a', 40),
            'base_url' => 'not_a_url'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== false) {
        throw new Exception('Should fail with invalid URL');
    }
    
    $errors = $validator->getErrors();
    $hasUrlError = false;
    foreach ($errors as $error) {
        if (str_contains($error, 'valid URL')) {
            $hasUrlError = true;
            break;
        }
    }
    
    if (!$hasUrlError) {
        throw new Exception('Should mention invalid URL');
    }
});

runTest('ConfigValidator: HTTP URL warning for production', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            'api_key' => 'sk-' . str_repeat('a', 40),
            'base_url' => 'http://api.example.com/v1'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== false) {
        throw new Exception('Should warn about HTTP in production');
    }
    
    $errors = $validator->getErrors();
    $hasHttpsError = false;
    foreach ($errors as $error) {
        if (str_contains($error, 'HTTPS')) {
            $hasHttpsError = true;
            break;
        }
    }
    
    if (!$hasHttpsError) {
        throw new Exception('Should mention HTTPS requirement');
    }
});

runTest('ConfigValidator: Allow localhost HTTP', function() {
    $validator = new ConfigValidator();
    $config = [
        'openai' => [
            'api_key' => 'sk-' . str_repeat('a', 40),
            'base_url' => 'http://localhost:8080/v1'
        ]
    ];
    
    $isValid = $validator->validate($config);
    
    if ($isValid !== true) {
        $errors = $validator->getErrors();
        throw new Exception('Should allow HTTP for localhost: ' . implode(', ', $errors));
    }
});

// =============================================================================
// SecretsManager Tests
// =============================================================================

runTest('SecretsManager: Load from environment', function() {
    // Set test environment variables
    putenv('OPENAI_API_KEY=sk-test123456789012345678901234567890');
    $_ENV['OPENAI_API_KEY'] = 'sk-test123456789012345678901234567890';
    
    $manager = new SecretsManager('env');
    
    $apiKey = $manager->get('openai.api_key');
    if ($apiKey !== 'sk-test123456789012345678901234567890') {
        throw new Exception('Should load API key from environment');
    }
    
    // Cleanup
    putenv('OPENAI_API_KEY');
    unset($_ENV['OPENAI_API_KEY']);
});

runTest('SecretsManager: Get redacted secret', function() {
    $manager = new SecretsManager('env');
    $manager->set('test.secret', 'supersecret123456');
    
    $redacted = $manager->getRedacted('test.secret');
    
    if (str_contains($redacted, 'supersecret123456')) {
        throw new Exception('Redacted value should not contain full secret');
    }
    
    if (!str_contains($redacted, 'supe') || !str_contains($redacted, '3456')) {
        throw new Exception('Redacted value should show first and last 4 chars');
    }
    
    if (!str_contains($redacted, '*')) {
        throw new Exception('Redacted value should contain asterisks');
    }
});

runTest('SecretsManager: Short secrets fully redacted', function() {
    $manager = new SecretsManager('env');
    $manager->set('test.short', '1234');
    
    $redacted = $manager->getRedacted('test.short');
    
    if ($redacted !== '****') {
        throw new Exception("Short secrets should be fully redacted, got: $redacted");
    }
});

runTest('SecretsManager: Has and get methods', function() {
    $manager = new SecretsManager('env');
    $manager->set('test.key', 'value123');
    
    if (!$manager->has('test.key')) {
        throw new Exception('Has() should return true for existing key');
    }
    
    if ($manager->has('nonexistent.key')) {
        throw new Exception('Has() should return false for non-existent key');
    }
    
    if ($manager->get('test.key') !== 'value123') {
        throw new Exception('Get() should return correct value');
    }
    
    if ($manager->get('nonexistent.key', 'default') !== 'default') {
        throw new Exception('Get() should return default for non-existent key');
    }
});

runTest('SecretsManager: Unsupported source throws exception', function() {
    try {
        $manager = new SecretsManager('invalid-source');
        throw new Exception('Should throw exception for invalid source');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'Unknown secrets source')) {
            throw new Exception('Should mention unknown source in error');
        }
    }
});

// =============================================================================
// ErrorHandler Tests
// =============================================================================

runTest('ErrorHandler: Sanitize API keys', function() {
    $message = 'API error with key: sk-abc123xyz789012345678901234567890';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, 'sk-abc123xyz789')) {
        throw new Exception('Should redact API key');
    }
    
    if (!str_contains($sanitized, '[API_KEY_REDACTED]')) {
        throw new Exception('Should show redaction message');
    }
});

runTest('ErrorHandler: Sanitize passwords', function() {
    $message = 'Database connection failed: password=secret123';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, 'secret123')) {
        throw new Exception('Should redact password');
    }
    
    if (!str_contains($sanitized, '[REDACTED]')) {
        throw new Exception('Should show redaction message');
    }
});

runTest('ErrorHandler: Sanitize file paths', function() {
    $message = 'File not found: /var/www/html/config/secrets.php';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, '/var/www/html')) {
        throw new Exception('Should redact file path');
    }
    
    if (!str_contains($sanitized, '[PATH_REDACTED]')) {
        throw new Exception('Should show redaction message');
    }
});

runTest('ErrorHandler: Sanitize Bearer tokens', function() {
    $message = 'Auth failed: Bearer abc123xyz789';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, 'abc123xyz789')) {
        throw new Exception('Should redact Bearer token');
    }
    
    if (!str_contains($sanitized, 'Bearer [REDACTED]')) {
        throw new Exception('Should show bearer redaction');
    }
});

runTest('ErrorHandler: Sanitize email addresses', function() {
    $message = 'User not found: user@example.com';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, 'user@example.com')) {
        throw new Exception('Should redact email');
    }
    
    if (!str_contains($sanitized, '[EMAIL_REDACTED]')) {
        throw new Exception('Should show email redaction');
    }
});

runTest('ErrorHandler: Sanitize IP addresses', function() {
    $message = 'Connection from 192.168.1.100';
    $sanitized = ErrorHandler::sanitize($message);
    
    if (str_contains($sanitized, '192.168.1.100')) {
        throw new Exception('Should redact IP address');
    }
    
    if (!str_contains($sanitized, '[IP_REDACTED]')) {
        throw new Exception('Should show IP redaction');
    }
});

runTest('ErrorHandler: Sanitize context array', function() {
    $context = [
        'username' => 'john',
        'password' => 'secret123',
        'api_key' => 'sk-test123',
        'user_id' => 42,
        'nested' => [
            'token' => 'abc123',
            'data' => 'normal'
        ]
    ];
    
    $sanitized = ErrorHandler::sanitizeContext($context);
    
    if ($sanitized['username'] !== 'john') {
        throw new Exception('Should keep non-sensitive values');
    }
    
    if (str_contains($sanitized['password'], 'secret123')) {
        throw new Exception('Should redact password in context');
    }
    
    if (str_contains($sanitized['api_key'], 'sk-test123')) {
        throw new Exception('Should redact API key in context');
    }
    
    if (str_contains($sanitized['nested']['token'], 'abc123')) {
        throw new Exception('Should redact nested sensitive values');
    }
    
    if ($sanitized['nested']['data'] !== 'normal') {
        throw new Exception('Should keep nested non-sensitive values');
    }
});

runTest('ErrorHandler: Get user message for production', function() {
    $message = 'Database error: password=secret at /var/www/html/db.php:123';
    $userMessage = ErrorHandler::getUserMessage($message, true);
    
    if (str_contains($userMessage, 'password') || str_contains($userMessage, 'secret') || str_contains($userMessage, '/var/www')) {
        throw new Exception('User message should not contain technical details in production');
    }
    
    if (!str_contains($userMessage, 'error occurred')) {
        throw new Exception('User message should be generic and friendly');
    }
});

runTest('ErrorHandler: Get user message for development', function() {
    $message = 'Database error: password=secret at /var/www/html/db.php:123';
    $userMessage = ErrorHandler::getUserMessage($message, false);
    
    // In development, should be sanitized but more detailed
    if (str_contains($userMessage, 'password=secret')) {
        throw new Exception('Should still sanitize in development');
    }
    
    if (!str_contains($userMessage, '[REDACTED]') && !str_contains($userMessage, '[PATH_REDACTED]')) {
        throw new Exception('Should show sanitized details in development');
    }
});

runTest('ErrorHandler: Format exception', function() {
    $exception = new Exception('Database connection failed: password=secret123');
    $formatted = ErrorHandler::formatException($exception, false);
    
    if (str_contains($formatted, 'secret123')) {
        throw new Exception('Should sanitize exception message');
    }
    
    if (!str_contains($formatted, '[REDACTED]')) {
        throw new Exception('Should show redaction in exception');
    }
});

// =============================================================================
// Integration Tests
// =============================================================================

runTest('Integration: Validate config with SecretsManager', function() {
    // Set up environment
    putenv('OPENAI_API_KEY=sk-' . str_repeat('a', 40));
    $_ENV['OPENAI_API_KEY'] = 'sk-' . str_repeat('a', 40);
    
    // Load secrets
    $secretsManager = new SecretsManager('env');
    
    // Build config
    $config = [
        'openai' => [
            'api_key' => $secretsManager->get('openai.api_key'),
            'base_url' => 'https://api.openai.com/v1'
        ]
    ];
    
    // Validate
    $validator = new ConfigValidator();
    if (!$validator->validate($config)) {
        $errors = $validator->getErrors();
        throw new Exception('Validation should pass: ' . implode(', ', $errors));
    }
    
    // Get redacted version for logging
    $redacted = $secretsManager->getRedacted('openai.api_key');
    if (str_contains($redacted, str_repeat('a', 40))) {
        throw new Exception('Redacted version should not contain full key');
    }
    
    // Cleanup
    putenv('OPENAI_API_KEY');
    unset($_ENV['OPENAI_API_KEY']);
});

runTest('Integration: Error logging with sanitization', function() {
    $message = 'Failed to connect with API key: sk-abc123xyz789012345678901234567890';
    $context = [
        'api_key' => 'sk-test123',
        'endpoint' => 'https://api.openai.com/v1/chat'
    ];
    
    // Test sanitization directly without trying to capture error_log
    $sanitizedMessage = ErrorHandler::sanitize($message);
    $sanitizedContext = ErrorHandler::sanitizeContext($context);
    
    if (str_contains($sanitizedMessage, 'sk-abc123xyz789')) {
        throw new Exception('Sanitized message should not contain full API key');
    }
    
    if (!str_contains($sanitizedMessage, '[API_KEY_REDACTED]')) {
        throw new Exception('Sanitized message should show API key redaction');
    }
    
    if (str_contains(json_encode($sanitizedContext), 'sk-test123')) {
        throw new Exception('Sanitized context should not contain full API key');
    }
    
    if (!str_contains($sanitizedContext['api_key'], '[REDACTED]') && 
        !str_contains($sanitizedContext['api_key'], 'sk-t')) {
        throw new Exception('Sanitized context API key should be redacted');
    }
    
    // Actually call logError to ensure it doesn't crash
    ErrorHandler::logError($message, $context);
});

// =============================================================================
// Test Summary
// =============================================================================

echo "\n=== Test Summary ===\n";
echo "Tests run: $testsRun\n";
echo "Tests passed: $testsPassed\n";
echo "Tests failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n✗ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All configuration security tests passed!\n";
    exit(0);
}

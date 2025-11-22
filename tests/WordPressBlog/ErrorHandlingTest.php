<?php
/**
 * Error Handling Tests
 *
 * Comprehensive tests for:
 * - Exception hierarchy
 * - Error handler retry logic
 * - Backoff calculation
 * - Credential encryption/decryption
 * - Validation engine
 *
 * @package WordPressBlog\Tests
 */

use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase {
    private $db;
    private $cryptoAdapter;
    private $validationEngine;
    private $errorHandler;
    private $credentialManager;

    protected function setUp(): void {
        // Initialize in-memory database
        $this->db = new DB([
            'db_type' => 'sqlite',
            'db_path' => ':memory:'
        ]);

        // Initialize crypto adapter
        $this->cryptoAdapter = new CryptoAdapter([
            'encryption_key' => 'test-key-32-chars-for-testing!'
        ]);

        // Initialize components
        $this->validationEngine = new WordPressBlogValidationEngine();
        $this->errorHandler = new WordPressBlogErrorHandler();
        $this->credentialManager = new BlogCredentialManager($this->cryptoAdapter, $this->db);
    }

    protected function tearDown(): void {
        $this->db = null;
    }

    // ====================================================================
    // Exception Hierarchy Tests
    // ====================================================================

    public function testBaseExceptionCreation() {
        $exception = new WordPressBlogException('Test error', 100);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
        $this->assertFalse($exception->isRetryable());
        $this->assertEmpty($exception->getContext());
    }

    public function testExceptionContext() {
        $exception = new WordPressBlogException('Test error', 0, null, [
            'article_id' => 'abc123',
            'attempt' => 2
        ]);

        $context = $exception->getContext();
        $this->assertArrayHasKey('article_id', $context);
        $this->assertEquals('abc123', $context['article_id']);
        $this->assertEquals(2, $context['attempt']);

        $exception->addContext('new_key', 'new_value');
        $this->assertEquals('new_value', $exception->getContext()['new_key']);
    }

    public function testExceptionRetryable() {
        $exception = new WordPressBlogException('Test error');
        $this->assertFalse($exception->isRetryable());

        $exception->setRetryable(true);
        $this->assertTrue($exception->isRetryable());
    }

    public function testExceptionHttpStatusCode() {
        $exception = new WordPressBlogException('Test error');
        $this->assertNull($exception->getHttpStatusCode());

        $exception->setHttpStatusCode(429);
        $this->assertEquals(429, $exception->getHttpStatusCode());
    }

    public function testExceptionToArray() {
        $exception = new WordPressBlogException('Test error', 100, null, ['key' => 'value']);
        $exception->setRetryable(true);
        $exception->setHttpStatusCode(500);

        $array = $exception->toArray();

        $this->assertArrayHasKey('exception', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('retryable', $array);
        $this->assertArrayHasKey('http_status_code', $array);
        $this->assertTrue($array['retryable']);
        $this->assertEquals(500, $array['http_status_code']);
    }

    public function testConfigurationException() {
        $exception = new ConfigurationException('Invalid config');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertFalse($exception->isRetryable());
        $this->assertStringContainsString('Configuration error', $exception->getUserMessage());
    }

    public function testQueueException() {
        $exception = new QueueException('Article not found');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertFalse($exception->isRetryable());
    }

    public function testContentGenerationException() {
        $exception = new ContentGenerationException('API error');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertTrue($exception->isRetryable());

        // Test error type setting
        $exception->setErrorType('rate_limit');
        $this->assertEquals('rate_limit', $exception->getContext()['error_type']);

        // Test non-retryable error types
        $exception2 = new ContentGenerationException('Invalid key');
        $exception2->setErrorType('invalid_api_key');
        $this->assertFalse($exception2->isRetryable());
    }

    public function testImageGenerationException() {
        $exception = new ImageGenerationException('Image generation failed');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertTrue($exception->isRetryable());

        $exception->setImageContext('featured', null);
        $this->assertEquals('featured', $exception->getContext()['image_type']);

        $exception->setImageContext('chapter', 3);
        $this->assertEquals(3, $exception->getContext()['chapter_number']);
    }

    public function testWordPressPublishException() {
        $exception = new WordPressPublishException('Publish failed');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertTrue($exception->isRetryable());

        // Test authentication error (not retryable)
        $exception->setWordPressContext(null, 401);
        $this->assertFalse($exception->isRetryable());
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }

    public function testStorageException() {
        $exception = new StorageException('Upload failed');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertTrue($exception->isRetryable());

        $exception->setStorageContext('upload', '/path/to/file.png');
        $this->assertEquals('upload', $exception->getContext()['operation']);
        $this->assertEquals('/path/to/file.png', $exception->getContext()['path']);
    }

    public function testCredentialException() {
        $exception = new CredentialException('Invalid API key');

        $this->assertInstanceOf(WordPressBlogException::class, $exception);
        $this->assertFalse($exception->isRetryable());

        $exception->setCredentialType('openai_api_key');
        $this->assertEquals('openai_api_key', $exception->getContext()['credential_type']);
    }

    // ====================================================================
    // Error Handler Retry Logic Tests
    // ====================================================================

    public function testBackoffCalculation() {
        $handler = new WordPressBlogErrorHandler(3, 2);

        $this->assertEquals(2, $handler->calculateBackoff(1));  // 2 * 2^0 = 2
        $this->assertEquals(4, $handler->calculateBackoff(2));  // 2 * 2^1 = 4
        $this->assertEquals(8, $handler->calculateBackoff(3));  // 2 * 2^2 = 8
        $this->assertEquals(16, $handler->calculateBackoff(4)); // 2 * 2^3 = 16
        $this->assertEquals(32, $handler->calculateBackoff(5)); // 2 * 2^4 = 32
        $this->assertEquals(60, $handler->calculateBackoff(6)); // 2 * 2^5 = 64, capped at 60
    }

    public function testSuccessfulExecutionWithoutRetry() {
        $callCount = 0;
        $callable = function() use (&$callCount) {
            $callCount++;
            return 'success';
        };

        $result = $this->errorHandler->executeWithRetry($callable, [], 'test_operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $callCount);
    }

    public function testRetryableErrorSucceedsOnSecondAttempt() {
        $callCount = 0;
        $callable = function() use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                $exception = new ContentGenerationException('Temporary error');
                $exception->setRetryable(true);
                throw $exception;
            }
            return 'success';
        };

        // Use fast delays for testing
        $handler = new WordPressBlogErrorHandler(3, 0);
        $result = $handler->executeWithRetry($callable, [], 'test_operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(2, $callCount);
    }

    public function testNonRetryableErrorThrowsImmediately() {
        $callCount = 0;
        $callable = function() use (&$callCount) {
            $callCount++;
            throw new ConfigurationException('Non-retryable error');
        };

        $this->expectException(ConfigurationException::class);
        $this->errorHandler->executeWithRetry($callable, [], 'test_operation');

        $this->assertEquals(1, $callCount);
    }

    public function testExhaustsAllRetries() {
        $callCount = 0;
        $callable = function() use (&$callCount) {
            $callCount++;
            $exception = new ContentGenerationException('Always fails');
            $exception->setRetryable(true);
            throw $exception;
        };

        $handler = new WordPressBlogErrorHandler(3, 0);

        $this->expectException(ContentGenerationException::class);
        $handler->executeWithRetry($callable, [], 'test_operation');

        $this->assertEquals(3, $callCount);
    }

    public function testHandleException() {
        $exception = new ContentGenerationException('Test error');
        $exception->setRetryable(true);

        $result = $this->errorHandler->handleException($exception);

        $this->assertArrayHasKey('retryable', $result);
        $this->assertArrayHasKey('error_type', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertTrue($result['retryable']);
    }

    public function testRateLimitDetection() {
        $exception = new ContentGenerationException('Rate limit exceeded');
        $exception->setHttpStatusCode(429);

        $result = $this->errorHandler->handleException($exception);

        $this->assertTrue($result['retryable']);
        $this->assertEquals('rate_limit', $result['error_type']);
        $this->assertEquals(60, $result['suggested_delay']);
    }

    // ====================================================================
    // Credential Manager Tests
    // ====================================================================

    public function testEncryptDecryptCredential() {
        $original = 'sk-proj-test-api-key-12345';

        $encrypted = $this->credentialManager->encryptCredential($original, 'openai_api_key');

        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('nonce', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);

        $decrypted = $this->credentialManager->decryptCredential(
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['tag'],
            'openai_api_key'
        );

        $this->assertEquals($original, $decrypted);
    }

    public function testEncryptEmptyCredentialThrowsException() {
        $this->expectException(CredentialException::class);
        $this->credentialManager->encryptCredential('', 'test');
    }

    public function testDecryptWithMissingDataThrowsException() {
        $this->expectException(CredentialException::class);
        $this->credentialManager->decryptCredential('', '', '', 'test');
    }

    public function testBatchEncryptDecrypt() {
        $credentials = [
            'openai_api_key' => 'sk-test-123',
            'wordpress_api_key' => 'admin:pass123'
        ];

        $encrypted = $this->credentialManager->encryptBatch($credentials);

        $this->assertArrayHasKey('openai_api_key', $encrypted);
        $this->assertArrayHasKey('wordpress_api_key', $encrypted);

        $decrypted = $this->credentialManager->decryptBatch($encrypted);

        $this->assertEquals('sk-test-123', $decrypted['openai_api_key']);
        $this->assertEquals('admin:pass123', $decrypted['wordpress_api_key']);
    }

    public function testValidateOpenAIKey() {
        $result1 = $this->credentialManager->validateCredential('sk-proj-abc123', 'openai_api_key');
        $this->assertTrue($result1['valid']);

        $result2 = $this->credentialManager->validateCredential('invalid-key', 'openai_api_key');
        $this->assertFalse($result2['valid']);
    }

    public function testValidateWordPressKey() {
        $result1 = $this->credentialManager->validateCredential('admin:' . str_repeat('a', 20), 'wordpress_api_key');
        $this->assertTrue($result1['valid']);

        $result2 = $this->credentialManager->validateCredential('invalid', 'wordpress_api_key');
        $this->assertFalse($result2['valid']);

        $result3 = $this->credentialManager->validateCredential('admin:short', 'wordpress_api_key');
        $this->assertFalse($result3['valid']);
    }

    public function testMaskCredential() {
        $credential = 'sk-proj-abcdefghijklmnop';
        $masked = $this->credentialManager->maskCredential($credential);

        $this->assertStringStartsWith('sk-p', $masked);
        $this->assertStringEndsWith('mnop', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function testAuditLogging() {
        $this->credentialManager->encryptCredential('test-key', 'test_type');

        $auditLog = $this->credentialManager->getAuditLog();
        $this->assertCount(1, $auditLog);

        $entry = $auditLog[0];
        $this->assertEquals('encrypt', $entry['operation']);
        $this->assertEquals('test_type', $entry['credential_type']);
        $this->assertTrue($entry['success']);
    }

    // ====================================================================
    // Validation Engine Tests
    // ====================================================================

    public function testValidateConfigurationSuccess() {
        $config = [
            'config_name' => 'Test Config',
            'wordpress_site_url' => 'https://example.com',
            'wordpress_api_key' => 'admin:pass',
            'openai_api_key' => 'sk-test',
            'target_word_count' => 2000,
            'image_quality' => 'standard',
            'auto_publish' => true
        ];

        $result = $this->validationEngine->validateConfiguration($config);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateConfigurationMissingRequired() {
        $config = [
            'config_name' => 'Test Config'
            // Missing required fields
        ];

        $result = $this->validationEngine->validateConfiguration($config);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertGreaterThanOrEqual(3, count($result['errors']));
    }

    public function testValidateConfigurationInvalidURL() {
        $config = [
            'config_name' => 'Test',
            'wordpress_site_url' => 'not-a-valid-url',
            'wordpress_api_key' => 'key',
            'openai_api_key' => 'key'
        ];

        $result = $this->validationEngine->validateConfiguration($config);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('URL', implode(' ', $result['errors']));
    }

    public function testValidateConfigurationWordCountRange() {
        $config1 = [
            'config_name' => 'Test',
            'wordpress_site_url' => 'https://example.com',
            'wordpress_api_key' => 'key',
            'openai_api_key' => 'key',
            'target_word_count' => 100 // Too low
        ];

        $result1 = $this->validationEngine->validateConfiguration($config1);
        $this->assertFalse($result1['valid']);

        $config2 = $config1;
        $config2['target_word_count'] = 15000; // Too high

        $result2 = $this->validationEngine->validateConfiguration($config2);
        $this->assertFalse($result2['valid']);
    }

    public function testValidateArticleStructure() {
        $structure = [
            'metadata' => [
                'title' => 'Test Article Title',
                'slug' => 'test-article',
                'meta_description' => 'This is a test meta description with sufficient length for SEO purposes.'
            ],
            'chapters' => [
                ['chapter_title' => 'Chapter 1', 'chapter_outline' => 'Outline 1'],
                ['chapter_title' => 'Chapter 2', 'chapter_outline' => 'Outline 2'],
                ['chapter_title' => 'Chapter 3', 'chapter_outline' => 'Outline 3']
            ],
            'introduction' => 'Introduction text',
            'conclusion' => 'Conclusion text'
        ];

        $result = $this->validationEngine->validateArticleStructure($structure);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateContentWordCount() {
        $content = [
            'introduction' => [
                'content' => str_repeat('word ', 200) // ~200 words
            ],
            'chapters' => [
                ['content' => str_repeat('word ', 800)], // ~800 words
                ['content' => str_repeat('word ', 800)]  // ~800 words
            ],
            'conclusion' => [
                'content' => str_repeat('word ', 200) // ~200 words
            ]
        ];

        $result = $this->validationEngine->validateContent($content, 2000);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertGreaterThan(1800, $result['word_count']);
        $this->assertLessThan(2200, $result['word_count']);
    }

    public function testDetectProhibitedContent() {
        $cleanContent = 'This is clean article content about technology.';
        $result1 = $this->validationEngine->detectProhibitedContent($cleanContent);
        $this->assertFalse($result1['has_prohibited']);

        $spamContent = 'Buy casino chips and viagra here!';
        $result2 = $this->validationEngine->detectProhibitedContent($spamContent);
        $this->assertTrue($result2['has_prohibited']);
        $this->assertContains('spam', $result2['categories']);
    }

    // ====================================================================
    // Integration Tests
    // ====================================================================

    public function testFullErrorHandlingWorkflow() {
        // Simulate operation that fails once then succeeds
        $attempt = 0;
        $operation = function() use (&$attempt) {
            $attempt++;
            if ($attempt === 1) {
                throw (new ContentGenerationException('Temporary OpenAI error'))
                    ->setRetryable(true)
                    ->setErrorType('timeout');
            }
            return ['success' => true, 'attempts' => $attempt];
        };

        $handler = new WordPressBlogErrorHandler(3, 0);
        $result = $handler->executeWithRetry($operation, [], 'content_generation');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['attempts']);
    }

    public function testCredentialValidationAndEncryption() {
        $apiKey = 'sk-proj-test-key-12345';

        // Validate
        $validation = $this->credentialManager->validateCredential($apiKey, 'openai_api_key');
        $this->assertTrue($validation['valid']);

        // Encrypt
        $encrypted = $this->credentialManager->encryptCredential($apiKey, 'openai_api_key');
        $this->assertNotEquals($apiKey, $encrypted['ciphertext']);

        // Decrypt
        $decrypted = $this->credentialManager->decryptCredential(
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['tag'],
            'openai_api_key'
        );
        $this->assertEquals($apiKey, $decrypted);

        // Audit
        $audit = $this->credentialManager->getAuditLog();
        $this->assertCount(2, $audit); // encrypt + decrypt
    }
}

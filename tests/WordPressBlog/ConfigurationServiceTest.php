<?php
/**
 * Unit Tests for WordPressBlogConfigurationService
 *
 * Tests CRUD operations, credential encryption, validation, and internal links management
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/DB.php';
require_once __DIR__ . '/../../includes/WordPressBlog/Services/WordPressBlogConfigurationService.php';

final class ConfigurationServiceTest extends TestCase
{
    private $db;
    private $service;
    private $testDbPath;

    protected function setUp(): void
    {
        // Use in-memory SQLite database for testing
        $this->testDbPath = ':memory:';

        $this->db = new DB([
            'database_url' => 'sqlite::memory:',
            'app_env' => 'testing'
        ]);

        // Run migration to create tables
        $this->runMigration();

        // Initialize service with test encryption key
        $this->service = new WordPressBlogConfigurationService($this->db, [
            'encryption_key' => 'test-encryption-key-for-unit-tests-12345'
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up is automatic with in-memory database
        $this->db = null;
        $this->service = null;
    }

    /**
     * Run database migration for blog tables
     */
    private function runMigration(): void
    {
        $migrationSql = file_get_contents(__DIR__ . '/../../db/migrations/048_add_wordpress_blog_tables.sql');

        // Execute migration
        $reflection = new ReflectionClass($this->db);
        $property = $reflection->getProperty('pdo');
        $property->setAccessible(true);
        $pdo = $property->getValue($this->db);
        $pdo->exec($migrationSql);
    }

    /**
     * Helper: Create a valid test configuration
     */
    private function createTestConfig(array $overrides = []): array
    {
        return array_merge([
            'config_name' => 'Test Blog Config',
            'website_url' => 'https://test-blog.com',
            'wordpress_api_url' => 'https://test-blog.com/wp-json',
            'wordpress_api_key' => 'test_wordpress_api_key_12345',
            'openai_api_key' => 'sk-test-openai-key-12345',
            'number_of_chapters' => 5,
            'max_word_count' => 3000,
            'introduction_length' => 300,
            'conclusion_length' => 200,
            'default_publish_status' => 'draft'
        ], $overrides);
    }

    // ========================================
    // CRUD Operation Tests
    // ========================================

    public function testCreateConfiguration(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->assertNotEmpty($configId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $configId);
    }

    public function testGetConfiguration(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $retrieved = $this->service->getConfiguration($configId);

        $this->assertNotNull($retrieved);
        $this->assertEquals($config['config_name'], $retrieved['config_name']);
        $this->assertEquals($config['website_url'], $retrieved['website_url']);
        $this->assertEquals($config['number_of_chapters'], $retrieved['number_of_chapters']);

        // Credentials should NOT be included by default
        $this->assertArrayNotHasKey('wordpress_api_key', $retrieved);
        $this->assertArrayNotHasKey('openai_api_key', $retrieved);
        $this->assertArrayNotHasKey('wordpress_api_key_encrypted', $retrieved);
        $this->assertArrayNotHasKey('openai_api_key_encrypted', $retrieved);
    }

    public function testGetConfigurationWithCredentials(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $retrieved = $this->service->getConfiguration($configId, true);

        $this->assertNotNull($retrieved);
        $this->assertEquals($config['wordpress_api_key'], $retrieved['wordpress_api_key']);
        $this->assertEquals($config['openai_api_key'], $retrieved['openai_api_key']);
    }

    public function testGetNonExistentConfiguration(): void
    {
        $retrieved = $this->service->getConfiguration('non-existent-uuid');

        $this->assertNull($retrieved);
    }

    public function testUpdateConfiguration(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $success = $this->service->updateConfiguration($configId, [
            'config_name' => 'Updated Config Name',
            'number_of_chapters' => 7,
            'max_word_count' => 4000
        ]);

        $this->assertTrue($success);

        $updated = $this->service->getConfiguration($configId);
        $this->assertEquals('Updated Config Name', $updated['config_name']);
        $this->assertEquals(7, $updated['number_of_chapters']);
        $this->assertEquals(4000, $updated['max_word_count']);
    }

    public function testUpdateConfigurationCredentials(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $newApiKey = 'sk-new-openai-key-67890';
        $success = $this->service->updateConfiguration($configId, [
            'openai_api_key' => $newApiKey
        ]);

        $this->assertTrue($success);

        $updated = $this->service->getConfiguration($configId, true);
        $this->assertEquals($newApiKey, $updated['openai_api_key']);
    }

    public function testUpdateNonExistentConfiguration(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configuration not found');

        $this->service->updateConfiguration('non-existent-uuid', [
            'config_name' => 'Test'
        ]);
    }

    public function testDeleteConfiguration(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $success = $this->service->deleteConfiguration($configId);
        $this->assertTrue($success);

        $retrieved = $this->service->getConfiguration($configId);
        $this->assertNull($retrieved);
    }

    public function testListConfigurations(): void
    {
        // Create multiple configurations
        $this->service->createConfiguration($this->createTestConfig(['config_name' => 'Config 1']));
        $this->service->createConfiguration($this->createTestConfig(['config_name' => 'Config 2']));
        $this->service->createConfiguration($this->createTestConfig(['config_name' => 'Config 3']));

        $list = $this->service->listConfigurations(10, 0);

        $this->assertCount(3, $list);
        $this->assertArrayHasKey('config_name', $list[0]);
        $this->assertArrayHasKey('website_url', $list[0]);
    }

    public function testListConfigurationsPagination(): void
    {
        // Create 5 configurations
        for ($i = 1; $i <= 5; $i++) {
            $this->service->createConfiguration($this->createTestConfig(['config_name' => "Config $i"]));
        }

        $page1 = $this->service->listConfigurations(2, 0);
        $page2 = $this->service->listConfigurations(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotEquals($page1[0]['config_name'], $page2[0]['config_name']);
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function testCreateConfigurationWithMissingRequiredFields(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Validation failed');

        $this->service->createConfiguration([
            'config_name' => 'Incomplete Config'
            // Missing required fields
        ]);
    }

    public function testValidationInvalidUrl(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid website_url format');

        $config = $this->createTestConfig(['website_url' => 'not-a-valid-url']);
        $this->service->createConfiguration($config);
    }

    public function testValidationNumberOfChaptersOutOfRange(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('number_of_chapters must be between 1 and 20');

        $config = $this->createTestConfig(['number_of_chapters' => 25]);
        $this->service->createConfiguration($config);
    }

    public function testValidationMaxWordCountOutOfRange(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('max_word_count must be between 500 and 10000');

        $config = $this->createTestConfig(['max_word_count' => 100]);
        $this->service->createConfiguration($config);
    }

    public function testValidationIntroductionLengthOutOfRange(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('introduction_length must be between 100 and 1000');

        $config = $this->createTestConfig(['introduction_length' => 50]);
        $this->service->createConfiguration($config);
    }

    public function testValidationConclusionLengthOutOfRange(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('conclusion_length must be between 100 and 1000');

        $config = $this->createTestConfig(['conclusion_length' => 1500]);
        $this->service->createConfiguration($config);
    }

    public function testValidationWordPressApiKeyTooShort(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wordpress_api_key must be at least 20 characters');

        $config = $this->createTestConfig(['wordpress_api_key' => 'short']);
        $this->service->createConfiguration($config);
    }

    public function testValidationOpenAIKeyInvalidPrefix(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("openai_api_key must start with 'sk-'");

        $config = $this->createTestConfig(['openai_api_key' => 'invalid-key-format']);
        $this->service->createConfiguration($config);
    }

    public function testValidationInvalidPublishStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("default_publish_status must be 'draft', 'publish', or 'pending'");

        $config = $this->createTestConfig(['default_publish_status' => 'invalid']);
        $this->service->createConfiguration($config);
    }

    public function testIsConfigurationComplete(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $isComplete = $this->service->isConfigurationComplete($configId);

        $this->assertTrue($isComplete);
    }

    public function testIsConfigurationCompleteReturnsFalseForNonExistent(): void
    {
        $isComplete = $this->service->isConfigurationComplete('non-existent-uuid');

        $this->assertFalse($isComplete);
    }

    // ========================================
    // Credential Encryption Tests
    // ========================================

    public function testCredentialsAreEncrypted(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        // Query database directly to check encrypted storage
        $sql = "SELECT wordpress_api_key_encrypted, openai_api_key_encrypted
                FROM blog_configurations WHERE configuration_id = :id";
        $result = $this->db->query($sql, ['id' => $configId]);

        $this->assertNotEmpty($result);

        $encrypted = $result[0];

        // Encrypted values should be JSON and not match plaintext
        $this->assertNotEquals($config['wordpress_api_key'], $encrypted['wordpress_api_key_encrypted']);
        $this->assertNotEquals($config['openai_api_key'], $encrypted['openai_api_key_encrypted']);

        // Should be valid JSON
        $decoded = json_decode($encrypted['wordpress_api_key_encrypted'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('c', $decoded); // ciphertext
        $this->assertArrayHasKey('n', $decoded); // nonce
        $this->assertArrayHasKey('t', $decoded); // tag
    }

    public function testCredentialsAreDecryptedCorrectly(): void
    {
        $config = $this->createTestConfig();
        $originalWordPressKey = $config['wordpress_api_key'];
        $originalOpenAIKey = $config['openai_api_key'];

        $configId = $this->service->createConfiguration($config);

        $retrieved = $this->service->getConfiguration($configId, true);

        $this->assertEquals($originalWordPressKey, $retrieved['wordpress_api_key']);
        $this->assertEquals($originalOpenAIKey, $retrieved['openai_api_key']);
    }

    // ========================================
    // Internal Links Tests
    // ========================================

    public function testAddInternalLink(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $linkId = $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1',
            'anchor_text' => 'Read more about this topic',
            'relevance_keywords' => ['keyword1', 'keyword2'],
            'priority' => 8
        ]);

        $this->assertNotEmpty($linkId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $linkId);
    }

    public function testAddInternalLinkWithMissingFields(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('URL and anchor_text are required');

        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1'
            // Missing anchor_text
        ]);
    }

    public function testAddInternalLinkWithInvalidUrl(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid URL format');

        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'not-a-valid-url',
            'anchor_text' => 'Test'
        ]);
    }

    public function testGetInternalLinks(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1',
            'anchor_text' => 'Article 1',
            'relevance_keywords' => ['keyword1']
        ]);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-2',
            'anchor_text' => 'Article 2',
            'relevance_keywords' => ['keyword2']
        ]);

        $links = $this->service->getInternalLinks($configId);

        $this->assertCount(2, $links);
        $this->assertIsArray($links[0]['relevance_keywords']); // Should be parsed from JSON
    }

    public function testGetInternalLinksActiveOnly(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $linkId1 = $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1',
            'anchor_text' => 'Active Link',
            'is_active' => true
        ]);

        $linkId2 = $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-2',
            'anchor_text' => 'Inactive Link',
            'is_active' => false
        ]);

        $activeLinks = $this->service->getInternalLinks($configId, true);
        $allLinks = $this->service->getInternalLinks($configId, false);

        $this->assertCount(1, $activeLinks);
        $this->assertCount(2, $allLinks);
    }

    public function testUpdateInternalLink(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $linkId = $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1',
            'anchor_text' => 'Original Text',
            'priority' => 5
        ]);

        $success = $this->service->updateInternalLink($linkId, [
            'anchor_text' => 'Updated Text',
            'priority' => 9
        ]);

        $this->assertTrue($success);

        $links = $this->service->getInternalLinks($configId, false);
        $updatedLink = array_filter($links, fn($l) => $l['link_id'] === $linkId)[0];

        $this->assertEquals('Updated Text', $updatedLink['anchor_text']);
        $this->assertEquals(9, $updatedLink['priority']);
    }

    public function testDeleteInternalLink(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $linkId = $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article-1',
            'anchor_text' => 'Test Link'
        ]);

        $success = $this->service->deleteInternalLink($linkId);
        $this->assertTrue($success);

        $links = $this->service->getInternalLinks($configId, false);
        $this->assertCount(0, $links);
    }

    public function testFindRelevantLinks(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/seo-guide',
            'anchor_text' => 'SEO Guide',
            'relevance_keywords' => ['seo', 'optimization', 'ranking'],
            'priority' => 8
        ]);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/content-marketing',
            'anchor_text' => 'Content Marketing',
            'relevance_keywords' => ['content', 'marketing', 'strategy'],
            'priority' => 6
        ]);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/seo-tools',
            'anchor_text' => 'SEO Tools',
            'relevance_keywords' => ['seo', 'tools', 'analysis'],
            'priority' => 7
        ]);

        $relevantLinks = $this->service->findRelevantLinks($configId, ['seo', 'optimization'], 2);

        $this->assertCount(2, $relevantLinks);
        $this->assertArrayHasKey('relevance_score', $relevantLinks[0]);

        // Should be sorted by relevance score (highest first)
        $this->assertGreaterThanOrEqual($relevantLinks[1]['relevance_score'], $relevantLinks[0]['relevance_score']);
    }

    public function testFindRelevantLinksWithNoMatches(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article',
            'anchor_text' => 'Article',
            'relevance_keywords' => ['keyword1']
        ]);

        $relevantLinks = $this->service->findRelevantLinks($configId, ['unrelated', 'keywords']);

        $this->assertCount(0, $relevantLinks);
    }

    // ========================================
    // Edge Cases and Security Tests
    // ========================================

    public function testSQLInjectionPrevention(): void
    {
        // Attempt SQL injection in config_name
        $config = $this->createTestConfig([
            'config_name' => "'; DROP TABLE blog_configurations; --"
        ]);

        $configId = $this->service->createConfiguration($config);

        $retrieved = $this->service->getConfiguration($configId);

        $this->assertNotNull($retrieved);
        $this->assertEquals("'; DROP TABLE blog_configurations; --", $retrieved['config_name']);

        // Verify table still exists
        $list = $this->service->listConfigurations();
        $this->assertNotEmpty($list);
    }

    public function testEmptyStringHandling(): void
    {
        $config = $this->createTestConfig([
            'cta_message' => '',
            'cta_url' => null,
            'company_offering' => ''
        ]);

        $configId = $this->service->createConfiguration($config);
        $retrieved = $this->service->getConfiguration($configId);

        $this->assertNotNull($retrieved);
    }

    public function testSpecialCharactersInFields(): void
    {
        $config = $this->createTestConfig([
            'config_name' => 'Test "Config" with <special> & characters',
            'cta_message' => "Test message with\nnewlines\tand\ttabs"
        ]);

        $configId = $this->service->createConfiguration($config);
        $retrieved = $this->service->getConfiguration($configId);

        $this->assertEquals($config['config_name'], $retrieved['config_name']);
        $this->assertEquals($config['cta_message'], $retrieved['cta_message']);
    }

    public function testCascadeDeleteWithInternalLinks(): void
    {
        $config = $this->createTestConfig();
        $configId = $this->service->createConfiguration($config);

        $this->service->addInternalLink($configId, [
            'url' => 'https://test-blog.com/article',
            'anchor_text' => 'Test'
        ]);

        // Delete configuration (should cascade delete links)
        $this->service->deleteConfiguration($configId);

        // Verify links are gone
        $links = $this->service->getInternalLinks($configId, false);
        $this->assertCount(0, $links);
    }
}

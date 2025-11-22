<?php
/**
 * WordPress Blog Schema Validation Script
 *
 * Validates that all WordPress blog tables, columns, indexes, and foreign keys
 * are correctly created after migration 048.
 *
 * Usage: php db/validate_blog_schema.php
 * Exit codes: 0 = success, 1 = validation failed
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

// ANSI color codes for terminal output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

class BlogSchemaValidator {
    private $db;
    private $errors = [];
    private $warnings = [];
    private $passed = 0;
    private $failed = 0;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Run all validation checks
     */
    public function validate(): bool {
        echo COLOR_BLUE . "WordPress Blog Schema Validation\n";
        echo str_repeat("=", 50) . COLOR_RESET . "\n\n";

        $this->validateTables();
        $this->validateBlogConfigurationsSchema();
        $this->validateBlogArticlesQueueSchema();
        $this->validateBlogArticleCategoriesSchema();
        $this->validateBlogArticleTagsSchema();
        $this->validateBlogInternalLinksSchema();
        $this->validateIndexes();
        $this->validateTriggers();
        $this->validateForeignKeys();
        $this->validateConstraints();

        $this->printSummary();

        return count($this->errors) === 0;
    }

    /**
     * Validate all required tables exist
     */
    private function validateTables() {
        echo "Checking tables...\n";

        $requiredTables = [
            'blog_configurations',
            'blog_articles_queue',
            'blog_article_categories',
            'blog_article_tags',
            'blog_internal_links'
        ];

        $existingTables = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'blog_%'"
        );
        $existingTableNames = array_column($existingTables, 'name');

        foreach ($requiredTables as $table) {
            if (in_array($table, $existingTableNames)) {
                $this->pass("Table '$table' exists");
            } else {
                $this->fail("Table '$table' does NOT exist");
            }
        }

        echo "\n";
    }

    /**
     * Validate blog_configurations table schema
     */
    private function validateBlogConfigurationsSchema() {
        echo "Checking blog_configurations schema...\n";

        $expectedColumns = [
            'configuration_id' => 'TEXT',
            'config_name' => 'TEXT',
            'website_url' => 'TEXT',
            'number_of_chapters' => 'INTEGER',
            'max_word_count' => 'INTEGER',
            'introduction_length' => 'INTEGER',
            'conclusion_length' => 'INTEGER',
            'cta_message' => 'TEXT',
            'cta_url' => 'TEXT',
            'company_offering' => 'TEXT',
            'wordpress_api_url' => 'TEXT',
            'wordpress_api_key_encrypted' => 'TEXT',
            'openai_api_key_encrypted' => 'TEXT',
            'default_publish_status' => 'TEXT',
            'google_drive_folder_id' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ];

        $this->validateTableSchema('blog_configurations', $expectedColumns);
        echo "\n";
    }

    /**
     * Validate blog_articles_queue table schema
     */
    private function validateBlogArticlesQueueSchema() {
        echo "Checking blog_articles_queue schema...\n";

        $expectedColumns = [
            'article_id' => 'TEXT',
            'configuration_id' => 'TEXT',
            'status' => 'TEXT',
            'seed_keyword' => 'TEXT',
            'target_audience' => 'TEXT',
            'writing_style' => 'TEXT',
            'publication_date' => 'TEXT',
            'scheduled_date' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
            'processing_started_at' => 'TEXT',
            'processing_completed_at' => 'TEXT',
            'execution_log_url' => 'TEXT',
            'wordpress_post_id' => 'INTEGER',
            'wordpress_post_url' => 'TEXT',
            'error_message' => 'TEXT',
            'retry_count' => 'INTEGER'
        ];

        $this->validateTableSchema('blog_articles_queue', $expectedColumns);
        echo "\n";
    }

    /**
     * Validate blog_article_categories table schema
     */
    private function validateBlogArticleCategoriesSchema() {
        echo "Checking blog_article_categories schema...\n";

        $expectedColumns = [
            'id' => 'INTEGER',
            'article_id' => 'TEXT',
            'category_id' => 'INTEGER',
            'category_name' => 'TEXT',
            'created_at' => 'TEXT'
        ];

        $this->validateTableSchema('blog_article_categories', $expectedColumns);
        echo "\n";
    }

    /**
     * Validate blog_article_tags table schema
     */
    private function validateBlogArticleTagsSchema() {
        echo "Checking blog_article_tags schema...\n";

        $expectedColumns = [
            'id' => 'INTEGER',
            'article_id' => 'TEXT',
            'tag_id' => 'INTEGER',
            'tag_name' => 'TEXT',
            'created_at' => 'TEXT'
        ];

        $this->validateTableSchema('blog_article_tags', $expectedColumns);
        echo "\n";
    }

    /**
     * Validate blog_internal_links table schema
     */
    private function validateBlogInternalLinksSchema() {
        echo "Checking blog_internal_links schema...\n";

        $expectedColumns = [
            'link_id' => 'TEXT',
            'configuration_id' => 'TEXT',
            'url' => 'TEXT',
            'anchor_text' => 'TEXT',
            'relevance_keywords' => 'TEXT',
            'priority' => 'INTEGER',
            'is_active' => 'INTEGER',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ];

        $this->validateTableSchema('blog_internal_links', $expectedColumns);
        echo "\n";
    }

    /**
     * Validate table schema matches expected columns
     */
    private function validateTableSchema(string $tableName, array $expectedColumns) {
        try {
            $columns = $this->db->query("PRAGMA table_info($tableName)");
            $actualColumns = [];

            foreach ($columns as $col) {
                $actualColumns[$col['name']] = $col['type'];
            }

            foreach ($expectedColumns as $colName => $colType) {
                if (!isset($actualColumns[$colName])) {
                    $this->fail("Column '$tableName.$colName' is missing");
                } elseif (strtoupper($actualColumns[$colName]) !== strtoupper($colType)) {
                    $this->fail("Column '$tableName.$colName' has wrong type: expected $colType, got {$actualColumns[$colName]}");
                } else {
                    $this->pass("Column '$tableName.$colName' is correct");
                }
            }

        } catch (Exception $e) {
            $this->fail("Failed to validate $tableName schema: " . $e->getMessage());
        }
    }

    /**
     * Validate indexes exist
     */
    private function validateIndexes() {
        echo "Checking indexes...\n";

        $requiredIndexes = [
            'idx_blog_configs_config_name',
            'idx_blog_configs_created_at',
            'idx_blog_articles_status',
            'idx_blog_articles_configuration_id',
            'idx_blog_articles_scheduled_date',
            'idx_blog_articles_created_at',
            'idx_blog_articles_queue_polling',
            'idx_blog_categories_article_id',
            'idx_blog_categories_category_name',
            'idx_blog_tags_article_id',
            'idx_blog_tags_tag_name',
            'idx_blog_links_configuration_id',
            'idx_blog_links_is_active',
            'idx_blog_links_priority',
            'idx_blog_links_config_active'
        ];

        $existingIndexes = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_blog_%'"
        );
        $existingIndexNames = array_column($existingIndexes, 'name');

        foreach ($requiredIndexes as $index) {
            if (in_array($index, $existingIndexNames)) {
                $this->pass("Index '$index' exists");
            } else {
                $this->fail("Index '$index' does NOT exist");
            }
        }

        echo "\n";
    }

    /**
     * Validate triggers exist
     */
    private function validateTriggers() {
        echo "Checking triggers...\n";

        $requiredTriggers = [
            'update_blog_configurations_timestamp',
            'update_blog_articles_queue_timestamp',
            'update_blog_internal_links_timestamp'
        ];

        $existingTriggers = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='trigger' AND name LIKE '%blog%'"
        );
        $existingTriggerNames = array_column($existingTriggers, 'name');

        foreach ($requiredTriggers as $trigger) {
            if (in_array($trigger, $existingTriggerNames)) {
                $this->pass("Trigger '$trigger' exists");
            } else {
                $this->fail("Trigger '$trigger' does NOT exist");
            }
        }

        echo "\n";
    }

    /**
     * Validate foreign keys
     */
    private function validateForeignKeys() {
        echo "Checking foreign keys...\n";

        // Check if foreign keys are enabled
        $fkEnabled = $this->db->query("PRAGMA foreign_keys");
        if ($fkEnabled[0]['foreign_keys'] == 1) {
            $this->pass("Foreign keys are enabled");
        } else {
            $this->warn("Foreign keys are NOT enabled");
        }

        // Check blog_articles_queue foreign key
        $fk = $this->db->query("PRAGMA foreign_key_list(blog_articles_queue)");
        if (!empty($fk)) {
            $found = false;
            foreach ($fk as $key) {
                if ($key['table'] === 'blog_configurations') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->pass("blog_articles_queue has foreign key to blog_configurations");
            } else {
                $this->fail("blog_articles_queue missing foreign key to blog_configurations");
            }
        } else {
            $this->fail("blog_articles_queue has no foreign keys");
        }

        // Check blog_internal_links foreign key
        $fk = $this->db->query("PRAGMA foreign_key_list(blog_internal_links)");
        if (!empty($fk)) {
            $found = false;
            foreach ($fk as $key) {
                if ($key['table'] === 'blog_configurations') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->pass("blog_internal_links has foreign key to blog_configurations");
            } else {
                $this->fail("blog_internal_links missing foreign key to blog_configurations");
            }
        } else {
            $this->fail("blog_internal_links has no foreign keys");
        }

        // Check blog_article_categories foreign key
        $fk = $this->db->query("PRAGMA foreign_key_list(blog_article_categories)");
        if (!empty($fk)) {
            $found = false;
            foreach ($fk as $key) {
                if ($key['table'] === 'blog_articles_queue') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->pass("blog_article_categories has foreign key to blog_articles_queue");
            } else {
                $this->fail("blog_article_categories missing foreign key to blog_articles_queue");
            }
        } else {
            $this->fail("blog_article_categories has no foreign keys");
        }

        // Check blog_article_tags foreign key
        $fk = $this->db->query("PRAGMA foreign_key_list(blog_article_tags)");
        if (!empty($fk)) {
            $found = false;
            foreach ($fk as $key) {
                if ($key['table'] === 'blog_articles_queue') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->pass("blog_article_tags has foreign key to blog_articles_queue");
            } else {
                $this->fail("blog_article_tags missing foreign key to blog_articles_queue");
            }
        } else {
            $this->fail("blog_article_tags has no foreign keys");
        }

        // Run foreign key check
        try {
            $fkCheck = $this->db->query("PRAGMA foreign_key_check");
            if (empty($fkCheck)) {
                $this->pass("Foreign key integrity check passed");
            } else {
                $this->fail("Foreign key integrity violations found: " . json_encode($fkCheck));
            }
        } catch (Exception $e) {
            $this->warn("Could not run foreign key check: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Validate check constraints
     */
    private function validateConstraints() {
        echo "Checking constraints...\n";

        // Test number_of_chapters constraint (should be between 1 and 20)
        try {
            $this->db->execute(
                "INSERT INTO blog_configurations
                (config_name, website_url, number_of_chapters, wordpress_api_url, wordpress_api_key_encrypted, openai_api_key_encrypted)
                VALUES (?, ?, ?, ?, ?, ?)",
                ['Test Invalid', 'https://test.com', 0, 'https://test.com', 'enc1', 'enc2']
            );
            $this->fail("number_of_chapters constraint NOT working (accepted 0)");
            // Cleanup
            $this->db->execute("DELETE FROM blog_configurations WHERE config_name = 'Test Invalid'");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'constraint') !== false) {
                $this->pass("number_of_chapters constraint is working");
            } else {
                $this->warn("Could not test number_of_chapters constraint: " . $e->getMessage());
            }
        }

        // Test default_publish_status constraint
        try {
            $this->db->execute(
                "INSERT INTO blog_configurations
                (config_name, website_url, default_publish_status, wordpress_api_url, wordpress_api_key_encrypted, openai_api_key_encrypted)
                VALUES (?, ?, ?, ?, ?, ?)",
                ['Test Invalid Status', 'https://test.com', 'invalid', 'https://test.com', 'enc1', 'enc2']
            );
            $this->fail("default_publish_status constraint NOT working (accepted 'invalid')");
            // Cleanup
            $this->db->execute("DELETE FROM blog_configurations WHERE config_name = 'Test Invalid Status'");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'constraint') !== false) {
                $this->pass("default_publish_status constraint is working");
            } else {
                $this->warn("Could not test default_publish_status constraint: " . $e->getMessage());
            }
        }

        // Test article status constraint
        try {
            // First create a valid configuration
            $this->db->execute(
                "INSERT INTO blog_configurations
                (configuration_id, config_name, website_url, wordpress_api_url, wordpress_api_key_encrypted, openai_api_key_encrypted)
                VALUES (?, ?, ?, ?, ?, ?)",
                ['test-config-123', 'Test Config', 'https://test.com', 'https://test.com', 'enc1', 'enc2']
            );

            // Try to insert article with invalid status
            $this->db->execute(
                "INSERT INTO blog_articles_queue (configuration_id, seed_keyword, status)
                VALUES (?, ?, ?)",
                ['test-config-123', 'test keyword', 'invalid']
            );
            $this->fail("article status constraint NOT working (accepted 'invalid')");

            // Cleanup
            $this->db->execute("DELETE FROM blog_configurations WHERE configuration_id = 'test-config-123'");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'constraint') !== false) {
                $this->pass("article status constraint is working");
            } else {
                $this->warn("Could not test article status constraint: " . $e->getMessage());
            }

            // Cleanup anyway
            try {
                $this->db->execute("DELETE FROM blog_configurations WHERE configuration_id = 'test-config-123'");
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }

        echo "\n";
    }

    /**
     * Mark a check as passed
     */
    private function pass(string $message) {
        echo COLOR_GREEN . "✓ " . COLOR_RESET . $message . "\n";
        $this->passed++;
    }

    /**
     * Mark a check as failed
     */
    private function fail(string $message) {
        echo COLOR_RED . "✗ " . COLOR_RESET . $message . "\n";
        $this->errors[] = $message;
        $this->failed++;
    }

    /**
     * Add a warning
     */
    private function warn(string $message) {
        echo COLOR_YELLOW . "⚠ " . COLOR_RESET . $message . "\n";
        $this->warnings[] = $message;
    }

    /**
     * Print validation summary
     */
    private function printSummary() {
        echo str_repeat("=", 50) . "\n";
        echo COLOR_BLUE . "Validation Summary\n" . COLOR_RESET;
        echo str_repeat("=", 50) . "\n";

        $total = $this->passed + $this->failed;
        $successRate = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "Total checks: $total\n";
        echo COLOR_GREEN . "Passed: {$this->passed}\n" . COLOR_RESET;
        echo COLOR_RED . "Failed: {$this->failed}\n" . COLOR_RESET;
        echo COLOR_YELLOW . "Warnings: " . count($this->warnings) . "\n" . COLOR_RESET;
        echo "Success rate: {$successRate}%\n\n";

        if (count($this->errors) > 0) {
            echo COLOR_RED . "VALIDATION FAILED\n" . COLOR_RESET;
            echo "The following issues were found:\n";
            foreach ($this->errors as $error) {
                echo "  • $error\n";
            }
            echo "\n";
        } else {
            echo COLOR_GREEN . "✓ ALL VALIDATIONS PASSED!\n" . COLOR_RESET;
            echo "The WordPress blog schema is correctly installed.\n\n";
        }

        if (count($this->warnings) > 0) {
            echo COLOR_YELLOW . "Warnings:\n" . COLOR_RESET;
            foreach ($this->warnings as $warning) {
                echo "  • $warning\n";
            }
            echo "\n";
        }
    }
}

// Main execution
try {
    // Initialize database connection
    $config = [
        'database_path' => __DIR__ . '/../data/chatbot.db',
        'app_env' => getEnvValue('APP_ENV') ?? 'development'
    ];

    $db = new DB($config);

    // Run validation
    $validator = new BlogSchemaValidator($db);
    $success = $validator->validate();

    // Exit with appropriate code
    exit($success ? 0 : 1);

} catch (Exception $e) {
    echo COLOR_RED . "Error: " . $e->getMessage() . COLOR_RESET . "\n";
    exit(1);
}

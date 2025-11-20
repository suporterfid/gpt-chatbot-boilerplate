<?php
/**
 * Unit Tests for SpecializedAgentService
 *
 * Tests configuration management, validation, and encryption
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentRegistry.php';
require_once __DIR__ . '/../includes/SpecializedAgentService.php';

class SpecializedAgentServiceTest
{
    private $db;
    private $registry;
    private $service;
    private $passed = 0;
    private $failed = 0;

    public function __construct()
    {
        $this->db = new DB(':memory:');
        $this->setupDatabase();

        $testAgentsPath = __DIR__ . '/../agents';
        $this->registry = new AgentRegistry($this->db, $testAgentsPath, null, []);
        $this->registry->discoverAgents();

        $config = [
            'specialized_agents' => [
                'encryption_key' => 'test-encryption-key-32-chars-xx'
            ]
        ];
        $this->service = new SpecializedAgentService($this->db, $this->registry, null, $config);
    }

    private function setupDatabase()
    {
        // Create necessary tables
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS agents (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                agent_type TEXT DEFAULT 'generic',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->db->execute("
            CREATE TABLE IF NOT EXISTS specialized_agent_configs (
                id TEXT PRIMARY KEY,
                agent_id TEXT NOT NULL,
                agent_type TEXT NOT NULL,
                config_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(agent_id)
            )
        ");

        $this->db->execute("
            CREATE TABLE IF NOT EXISTS agent_type_metadata (
                agent_type TEXT PRIMARY KEY,
                display_name TEXT NOT NULL,
                description TEXT,
                version TEXT,
                enabled INTEGER DEFAULT 1,
                config_schema_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Insert test agent
        $this->db->insert("INSERT INTO agents (id, name, agent_type) VALUES (?, ?, ?)", [
            'test-agent-123',
            'Test Agent',
            'generic'
        ]);
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            $this->passed++;
            echo "✓ {$message}\n";
        } else {
            $this->failed++;
            echo "✗ {$message}\n";
        }
    }

    private function assertEquals($expected, $actual, $message)
    {
        $this->assert($expected === $actual, $message);
    }

    public function testSaveConfig()
    {
        echo "\n=== Testing saveConfig ===\n";

        $config = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'testuser',
            'wp_app_password' => 'test-password-1234567890',
            'default_status' => 'draft'
        ];

        try {
            $result = $this->service->saveConfig('test-agent-123', 'wordpress', $config);
            $this->assert($result !== null, "saveConfig should return result");
            $this->assertEquals('test-agent-123', $result['agent_id'], "Result should have correct agent_id");
            $this->assertEquals('wordpress', $result['agent_type'], "Result should have correct agent_type");
        } catch (Exception $e) {
            $this->assert(false, "saveConfig should not throw exception: " . $e->getMessage());
        }
    }

    public function testGetConfig()
    {
        echo "\n=== Testing getConfig ===\n";

        // First save a config
        $config = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'testuser',
            'wp_app_password' => 'test-password-1234567890'
        ];
        $this->service->saveConfig('test-agent-123', 'wordpress', $config);

        // Then retrieve it
        $retrieved = $this->service->getConfig('test-agent-123', true);
        $this->assert($retrieved !== null, "getConfig should return config");
        $this->assertEquals('test-agent-123', $retrieved['agent_id'], "Config should have correct agent_id");
        $this->assertEquals('wordpress', $retrieved['agent_type'], "Config should have correct agent_type");
        $this->assert(isset($retrieved['config']), "Config should have config data");
        $this->assertEquals('https://example.com', $retrieved['config']['wp_site_url'], "Config values should match");
    }

    public function testDeleteConfig()
    {
        echo "\n=== Testing deleteConfig ===\n";

        // First save a config
        $config = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'testuser',
            'wp_app_password' => 'test-password-1234567890'
        ];
        $this->service->saveConfig('test-agent-123', 'wordpress', $config);

        // Then delete it
        $deleted = $this->service->deleteConfig('test-agent-123');
        $this->assert($deleted, "deleteConfig should return true");

        // Verify it's gone
        $retrieved = $this->service->getConfig('test-agent-123');
        $this->assert($retrieved === null, "Config should be deleted");

        // Verify agent_type was reset to generic
        $agent = $this->db->query("SELECT agent_type FROM agents WHERE id = ?", ['test-agent-123']);
        $this->assertEquals('generic', $agent[0]['agent_type'], "Agent type should be reset to generic");
    }

    public function testConfigValidation()
    {
        echo "\n=== Testing Config Validation ===\n";

        // Try to save invalid config (missing required fields)
        $invalidConfig = [
            'wp_site_url' => 'https://example.com'
            // Missing wp_username and wp_app_password
        ];

        try {
            $this->service->saveConfig('test-agent-123', 'wordpress', $invalidConfig);
            $this->assert(false, "Should throw exception for invalid config");
        } catch (ChatbotBoilerplate\Exceptions\AgentConfigurationException $e) {
            $this->assert(true, "Should throw AgentConfigurationException for invalid config");
        } catch (Exception $e) {
            $this->assert(false, "Wrong exception type: " . get_class($e));
        }
    }

    public function testNonexistentAgentType()
    {
        echo "\n=== Testing Nonexistent Agent Type ===\n";

        $config = ['test' => 'value'];

        try {
            $this->service->saveConfig('test-agent-123', 'nonexistent', $config);
            $this->assert(false, "Should throw exception for nonexistent agent type");
        } catch (ChatbotBoilerplate\Exceptions\AgentNotFoundException $e) {
            $this->assert(true, "Should throw AgentNotFoundException for nonexistent type");
        } catch (Exception $e) {
            $this->assert(false, "Wrong exception type: " . get_class($e));
        }
    }

    public function testConfigUpdate()
    {
        echo "\n=== Testing Config Update ===\n";

        // Save initial config
        $config1 = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'user1',
            'wp_app_password' => 'password-1234567890'
        ];
        $this->service->saveConfig('test-agent-123', 'wordpress', $config1);

        // Update config
        $config2 = [
            'wp_site_url' => 'https://newsite.com',
            'wp_username' => 'user2',
            'wp_app_password' => 'newpassword-1234567890'
        ];
        $result = $this->service->saveConfig('test-agent-123', 'wordpress', $config2);

        // Verify update
        $retrieved = $this->service->getConfig('test-agent-123', true);
        $this->assertEquals('https://newsite.com', $retrieved['config']['wp_site_url'], "Config should be updated");
        $this->assertEquals('user2', $retrieved['config']['wp_username'], "Config should be updated");
    }

    public function testGetAgentConfigWithSpecialization()
    {
        echo "\n=== Testing getAgentConfigWithSpecialization ===\n";

        // Save config
        $config = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'testuser',
            'wp_app_password' => 'test-password-1234567890'
        ];
        $this->service->saveConfig('test-agent-123', 'wordpress', $config);

        // Get config with specialization
        $result = $this->service->getAgentConfigWithSpecialization('test-agent-123');
        $this->assert(isset($result['specialized_config']), "Should have specialized_config");
        $this->assert(isset($result['agent_type']), "Should have agent_type");
        $this->assertEquals('wordpress', $result['agent_type'], "Agent type should be wordpress");
        $this->assertEquals('https://example.com', $result['specialized_config']['wp_site_url'], "Should have config values");
    }

    public function testEnvironmentVariableResolution()
    {
        echo "\n=== Testing Environment Variable Resolution ===\n";

        // Set test environment variable
        putenv('TEST_WP_PASSWORD=my-secret-password');

        $config = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'testuser',
            'wp_app_password' => '${TEST_WP_PASSWORD}'
        ];

        $this->service->saveConfig('test-agent-123', 'wordpress', $config);
        $retrieved = $this->service->getConfig('test-agent-123', true);

        $this->assertEquals('my-secret-password', $retrieved['config']['wp_app_password'], "Environment variable should be resolved");

        // Cleanup
        putenv('TEST_WP_PASSWORD');
    }

    public function runAll()
    {
        echo "\n╔═══════════════════════════════════════════╗\n";
        echo "║   SpecializedAgentService Unit Tests    ║\n";
        echo "╚═══════════════════════════════════════════╝\n";

        try {
            $this->testSaveConfig();
            $this->testGetConfig();
            $this->testDeleteConfig();
            $this->testConfigValidation();
            $this->testNonexistentAgentType();
            $this->testConfigUpdate();
            $this->testGetAgentConfigWithSpecialization();
            $this->testEnvironmentVariableResolution();

            echo "\n╔═══════════════════════════════════════════╗\n";
            echo "║   Test Results                           ║\n";
            echo "╠═══════════════════════════════════════════╣\n";
            echo "║   Passed: " . str_pad($this->passed, 30, ' ', STR_PAD_LEFT) . "   ║\n";
            echo "║   Failed: " . str_pad($this->failed, 30, ' ', STR_PAD_LEFT) . "   ║\n";
            echo "╚═══════════════════════════════════════════╝\n";

            return $this->failed === 0;

        } catch (Exception $e) {
            echo "\n✗ Test suite failed with exception: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            return false;
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new SpecializedAgentServiceTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}

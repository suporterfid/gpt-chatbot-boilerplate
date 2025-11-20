<?php
/**
 * Unit Tests for AgentRegistry
 *
 * Tests agent discovery, registration, and metadata management
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentRegistry.php';

// Simple test framework
class AgentRegistryTest
{
    private $db;
    private $registry;
    private $testAgentsPath;
    private $passed = 0;
    private $failed = 0;

    public function __construct()
    {
        // Use in-memory SQLite for testing
        $this->db = new DB(':memory:');
        $this->setupDatabase();

        $this->testAgentsPath = __DIR__ . '/../agents';
    }

    private function setupDatabase()
    {
        // Create necessary tables
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
        $this->assert($expected === $actual, $message . " (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
    }

    public function testRegistryInitialization()
    {
        echo "\n=== Testing Registry Initialization ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $this->assert($registry !== null, "Registry should initialize successfully");
    }

    public function testAgentDiscovery()
    {
        echo "\n=== Testing Agent Discovery ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $types = $registry->listAvailableTypes();
        $this->assert(is_array($types), "listAvailableTypes should return an array");
        $this->assert(count($types) > 0, "Should discover at least one agent type");

        // Check if WordPress agent was discovered
        $hasWordPress = false;
        foreach ($types as $type) {
            if ($type['agent_type'] === 'wordpress') {
                $hasWordPress = true;
                break;
            }
        }
        $this->assert($hasWordPress, "Should discover WordPress agent");
    }

    public function testHasAgentType()
    {
        echo "\n=== Testing hasAgentType ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $this->assert($registry->hasAgentType('wordpress'), "Should have wordpress agent type");
        $this->assert(!$registry->hasAgentType('nonexistent'), "Should not have nonexistent agent type");
    }

    public function testGetAgentMetadata()
    {
        echo "\n=== Testing getAgentMetadata ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $metadata = $registry->getAgentMetadata('wordpress');
        $this->assert($metadata !== null, "Should get metadata for wordpress agent");
        $this->assertEquals('wordpress', $metadata['agent_type'], "Metadata should have correct agent_type");
        $this->assert(isset($metadata['display_name']), "Metadata should have display_name");
        $this->assert(isset($metadata['description']), "Metadata should have description");
        $this->assert(isset($metadata['version']), "Metadata should have version");
        $this->assert(isset($metadata['config_schema']), "Metadata should have config_schema");
    }

    public function testGetAgent()
    {
        echo "\n=== Testing getAgent ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $agent = $registry->getAgent('wordpress');
        $this->assert($agent !== null, "Should get wordpress agent instance");
        $this->assert($agent instanceof ChatbotBoilerplate\Interfaces\SpecializedAgentInterface, "Agent should implement SpecializedAgentInterface");

        // Test caching - should return same instance
        $agent2 = $registry->getAgent('wordpress');
        $this->assert($agent === $agent2, "Should return cached agent instance");
    }

    public function testValidateConfig()
    {
        echo "\n=== Testing validateConfig ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        // Valid configuration
        $validConfig = [
            'wp_site_url' => 'https://example.com',
            'wp_username' => 'admin',
            'wp_app_password' => 'test-password-1234567890'
        ];
        $errors = $registry->validateConfig('wordpress', $validConfig);
        $this->assert(empty($errors), "Valid config should have no errors");

        // Invalid configuration (missing required fields)
        $invalidConfig = [
            'wp_site_url' => 'https://example.com'
        ];
        $errors = $registry->validateConfig('wordpress', $invalidConfig);
        $this->assert(!empty($errors), "Invalid config should have errors");
        $this->assert(isset($errors['wp_username']) || isset($errors['wp_app_password']), "Should report missing required fields");
    }

    public function testMetadataSyncToDatabase()
    {
        echo "\n=== Testing Metadata Sync to Database ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        // Check if metadata was synced to database
        $result = $this->db->query("SELECT * FROM agent_type_metadata WHERE agent_type = ?", ['wordpress']);
        $this->assert(!empty($result), "Metadata should be synced to database");

        if (!empty($result)) {
            $metadata = $result[0];
            $this->assertEquals('wordpress', $metadata['agent_type'], "Database should have correct agent_type");
            $this->assert(!empty($metadata['display_name']), "Database should have display_name");
            $this->assert(!empty($metadata['version']), "Database should have version");
        }
    }

    public function testNonexistentAgent()
    {
        echo "\n=== Testing Nonexistent Agent ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $agent = $registry->getAgent('nonexistent');
        $this->assert($agent === null, "Should return null for nonexistent agent type");

        $metadata = $registry->getAgentMetadata('nonexistent');
        $this->assert($metadata === null, "Should return null metadata for nonexistent agent type");
    }

    public function testForceRediscovery()
    {
        echo "\n=== Testing Force Rediscovery ===\n";

        $registry = new AgentRegistry($this->db, $this->testAgentsPath, null, []);
        $registry->discoverAgents();

        $types1 = $registry->listAvailableTypes();
        $count1 = count($types1);

        // Force rediscovery
        $registry->discoverAgents(true);

        $types2 = $registry->listAvailableTypes();
        $count2 = count($types2);

        $this->assertEquals($count1, $count2, "Rediscovery should find same number of agents");
    }

    public function runAll()
    {
        echo "\n╔═══════════════════════════════════════════╗\n";
        echo "║   AgentRegistry Unit Tests               ║\n";
        echo "╚═══════════════════════════════════════════╝\n";

        try {
            $this->testRegistryInitialization();
            $this->testAgentDiscovery();
            $this->testHasAgentType();
            $this->testGetAgentMetadata();
            $this->testGetAgent();
            $this->testValidateConfig();
            $this->testMetadataSyncToDatabase();
            $this->testNonexistentAgent();
            $this->testForceRediscovery();

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
    $test = new AgentRegistryTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}

<?php
/**
 * Template Agent Test
 *
 * Example test file for specialized agents.
 * Copy and customize this for your agent's tests.
 */

require_once __DIR__ . '/../TemplateAgent.php';

// Mock classes for testing
class MockDB {
    public function query($sql, $params = []) { return []; }
    public function getOne($sql, $params = []) { return null; }
}

class MockLogger {
    public function info($message, $context = []) {}
    public function error($message, $context = []) {}
    public function warning($message, $context = []) {}
    public function debug($message, $context = []) {}
}

/**
 * Template Agent Test Suite
 *
 * Tests for TemplateAgent functionality
 */
class TemplateAgentTest
{
    /**
     * Test agent metadata
     */
    public function testGetAgentType()
    {
        $agent = new TemplateAgent();
        $type = $agent->getAgentType();

        assert($type === 'template', "Agent type should be 'template'");
        echo "✓ testGetAgentType passed\n";
    }

    public function testGetDisplayName()
    {
        $agent = new TemplateAgent();
        $name = $agent->getDisplayName();

        assert(!empty($name), "Display name should not be empty");
        assert($name === 'Template Agent', "Display name should be 'Template Agent'");
        echo "✓ testGetDisplayName passed\n";
    }

    public function testGetDescription()
    {
        $agent = new TemplateAgent();
        $description = $agent->getDescription();

        assert(!empty($description), "Description should not be empty");
        echo "✓ testGetDescription passed\n";
    }

    public function testGetVersion()
    {
        $agent = new TemplateAgent();
        $version = $agent->getVersion();

        assert(!empty($version), "Version should not be empty");
        assert(preg_match('/^\d+\.\d+\.\d+$/', $version), "Version should follow semver format");
        echo "✓ testGetVersion passed\n";
    }

    /**
     * Test configuration schema
     */
    public function testGetConfigSchema()
    {
        $agent = new TemplateAgent();
        $schema = $agent->getConfigSchema();

        assert(is_array($schema), "Config schema should be an array");
        assert(isset($schema['type']), "Schema should have 'type' field");
        assert($schema['type'] === 'object', "Schema type should be 'object'");
        assert(isset($schema['properties']), "Schema should have 'properties' field");

        echo "✓ testGetConfigSchema passed\n";
    }

    /**
     * Test initialization
     */
    public function testInitialize()
    {
        $agent = new TemplateAgent();
        $dependencies = [
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ];

        $agent->initialize($dependencies);

        echo "✓ testInitialize passed\n";
    }

    /**
     * Test context building
     */
    public function testBuildContext()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];

        $agentConfig = [
            'id' => 'agent-123',
            'tenant_id' => 'tenant-456',
            'conversation_id' => 'conv-789'
        ];

        $context = $agent->buildContext($messages, $agentConfig);

        assert(is_array($context), "Context should be an array");
        assert($context['agent_id'] === 'agent-123', "Context should contain agent_id");
        assert($context['agent_type'] === 'template', "Context should contain agent_type");
        assert($context['message_count'] === 1, "Context should contain message_count");

        echo "✓ testBuildContext passed\n";
    }

    /**
     * Test input validation
     */
    public function testValidateInput()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message']
        ];

        $context = ['agent_id' => '123'];

        $validatedMessages = $agent->validateInput($messages, $context);

        assert(is_array($validatedMessages), "Validated messages should be an array");
        assert(count($validatedMessages) === 1, "Should have 1 message");

        echo "✓ testValidateInput passed\n";
    }

    public function testValidateInputThrowsExceptionForEmptyMessages()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [];
        $context = [];

        try {
            $agent->validateInput($messages, $context);
            assert(false, "Should have thrown AgentValidationException");
        } catch (Exception $e) {
            assert(true, "Exception thrown as expected");
        }

        echo "✓ testValidateInputThrowsExceptionForEmptyMessages passed\n";
    }

    /**
     * Test processing
     */
    public function testProcess()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Create something new']
        ];

        $context = [
            'agent_id' => '123',
            'specialized_config' => []
        ];

        $result = $agent->process($messages, $context);

        assert(is_array($result), "Process result should be an array");
        assert(isset($result['messages']), "Result should contain messages");
        assert(isset($result['custom_data']), "Result should contain custom_data");

        echo "✓ testProcess passed\n";
    }

    /**
     * Test LLM requirement
     */
    public function testRequiresLLM()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $processedData = ['messages' => []];
        $context = [];

        $requiresLLM = $agent->requiresLLM($processedData, $context);

        assert(is_bool($requiresLLM), "requiresLLM should return a boolean");

        echo "✓ testRequiresLLM passed\n";
    }

    /**
     * Test LLM message preparation
     */
    public function testPrepareLLMMessages()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $processedData = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ];

        $context = [
            'agent_config' => [
                'system_message' => 'You are a helpful assistant.'
            ]
        ];

        $llmMessages = $agent->prepareLLMMessages($processedData, $context);

        assert(is_array($llmMessages), "LLM messages should be an array");
        assert(count($llmMessages) > 0, "LLM messages should not be empty");

        echo "✓ testPrepareLLMMessages passed\n";
    }

    /**
     * Test output formatting
     */
    public function testFormatOutput()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $processedData = [
            'messages' => [],
            'llm_response' => [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Test response'
                        ]
                    ]
                ]
            ]
        ];

        $context = [];

        $output = $agent->formatOutput($processedData, $context);

        assert(is_array($output), "Output should be an array");
        assert(isset($output['message']), "Output should contain message");
        assert(isset($output['metadata']), "Output should contain metadata");

        echo "✓ testFormatOutput passed\n";
    }

    /**
     * Test custom tools
     */
    public function testGetCustomTools()
    {
        $agent = new TemplateAgent();
        $tools = $agent->getCustomTools();

        assert(is_array($tools), "Custom tools should be an array");

        echo "✓ testGetCustomTools passed\n";
    }

    /**
     * Test cleanup
     */
    public function testCleanup()
    {
        $agent = new TemplateAgent();
        $agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $agent->cleanup();

        echo "✓ testCleanup passed\n";
    }

    /**
     * Run all tests
     */
    public static function runAll()
    {
        echo "\n=== Running TemplateAgent Tests ===\n\n";

        $test = new self();

        try {
            $test->testGetAgentType();
            $test->testGetDisplayName();
            $test->testGetDescription();
            $test->testGetVersion();
            $test->testGetConfigSchema();
            $test->testInitialize();
            $test->testBuildContext();
            $test->testValidateInput();
            $test->testValidateInputThrowsExceptionForEmptyMessages();
            $test->testProcess();
            $test->testRequiresLLM();
            $test->testPrepareLLMMessages();
            $test->testFormatOutput();
            $test->testGetCustomTools();
            $test->testCleanup();

            echo "\n=== All Tests Passed! ===\n\n";
            return true;

        } catch (Exception $e) {
            echo "\n✗ Test failed: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            return false;
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    TemplateAgentTest::runAll();
}

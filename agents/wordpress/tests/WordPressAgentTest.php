<?php
/**
 * Unit Tests for WordPressAgent
 *
 * Tests WordPress agent functionality, intent detection, and tools
 */

require_once __DIR__ . '/../../../includes/Interfaces/SpecializedAgentInterface.php';
require_once __DIR__ . '/../../../includes/Exceptions/AgentException.php';
require_once __DIR__ . '/../WordPressAgent.php';

// Mock classes
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

class MockQueueService {
    public $statusCalls = [];

    public function updateStatus($queueId, $status, $details = []) {
        $this->statusCalls[] = [
            'queueId' => $queueId,
            'status' => $status,
            'details' => $details
        ];

        return [
            'queue_id' => $queueId,
            'status' => $status,
            'details' => $details
        ];
    }
}

class MockExecutionLogger {
    public $logCalls = [];

    public function logPhase($queueId, $articleId, $phase, $status, $payload) {
        $this->logCalls[] = compact('queueId', 'articleId', 'phase', 'status', 'payload');

        return 'https://logs.example.com/' . $queueId . '/' . $phase;
    }

    public function append($queueId, $articleId, $phase, $status, $payload) {
        return $this->logPhase($queueId, $articleId, $phase, $status, $payload);
    }

    public function appendLog($queueId, $articleId, $phase, $status, $payload) {
        return $this->logPhase($queueId, $articleId, $phase, $status, $payload);
    }

    public function record($queueId, $articleId, $phase, $status, $payload) {
        return $this->logPhase($queueId, $articleId, $phase, $status, $payload);
    }

    public function recordPhase($queueId, $articleId, $phase, $status, $payload) {
        return $this->logPhase($queueId, $articleId, $phase, $status, $payload);
    }

    public function write($queueId, $articleId, $phase, $status, $payload) {
        return $this->logPhase($queueId, $articleId, $phase, $status, $payload);
    }
}

class WordPressAgentTest
{
    private $agent;
    private $passed = 0;
    private $failed = 0;

    public function __construct()
    {
        $this->agent = new WordPressAgent();
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

    public function testMetadata()
    {
        echo "\n=== Testing Agent Metadata ===\n";

        $this->assertEquals('wordpress', $this->agent->getAgentType(), "Agent type should be 'wordpress'");
        $this->assertEquals('WordPress Content Manager', $this->agent->getDisplayName(), "Display name should match");
        $this->assert(!empty($this->agent->getDescription()), "Description should not be empty");
        $this->assert(preg_match('/^\d+\.\d+\.\d+$/', $this->agent->getVersion()), "Version should follow semver");
    }

    public function testConfigSchema()
    {
        echo "\n=== Testing Config Schema ===\n";

        $schema = $this->agent->getConfigSchema();
        $this->assert(is_array($schema), "Config schema should be an array");
        $this->assertEquals('object', $schema['type'], "Schema type should be 'object'");
        $this->assert(isset($schema['required']), "Schema should have required fields");
        $this->assert(isset($schema['properties']), "Schema should have properties");

        // Check required fields
        $required = $schema['required'];
        $this->assert(in_array('wp_site_url', $required), "wp_site_url should be required");
        $this->assert(in_array('wp_username', $required), "wp_username should be required");
        $this->assert(in_array('wp_app_password', $required), "wp_app_password should be required");

        // Check sensitive fields
        $this->assert($schema['properties']['wp_app_password']['sensitive'] === true, "wp_app_password should be marked as sensitive");
    }

    public function testInitialization()
    {
        echo "\n=== Testing Initialization ===\n";

        $dependencies = [
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ];

        try {
            $this->agent->initialize($dependencies);
            $this->assert(true, "Agent should initialize without errors");
        } catch (Exception $e) {
            $this->assert(false, "Initialization failed: " . $e->getMessage());
        }
    }

    public function testBuildContext()
    {
        echo "\n=== Testing buildContext ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Create a blog post about AI']
        ];

        $agentConfig = [
            'id' => 'agent-123',
            'tenant_id' => 'tenant-456'
        ];

        $context = $this->agent->buildContext($messages, $agentConfig);

        $this->assert(is_array($context), "Context should be an array");
        $this->assertEquals('agent-123', $context['agent_id'], "Context should have agent_id");
        $this->assertEquals('wordpress', $context['agent_type'], "Context should have agent_type");
        $this->assert(isset($context['user_intent']), "Context should have user_intent");
        $this->assert(isset($context['user_message']), "Context should have user_message");
    }

    public function testValidateInput()
    {
        echo "\n=== Testing validateInput ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message']
        ];

        $context = [
            'specialized_config' => [
                'wp_site_url' => 'https://example.com',
                'wp_username' => 'admin',
                'wp_app_password' => 'test-password',
                'configuration_id' => 'config-123',
                'article_queue_id' => 'queue-123',
                'workflow_phases' => ['generate_assets' => false],
                'image_preferences' => ['enabled' => false],
                'enable_execution_logging' => false
            ],
            'blog_workflow' => [
                'configuration_id' => 'config-123',
                'queue_id' => 'queue-123',
                'configuration' => ['website_url' => 'https://example.com'],
                'queue_entry' => ['article_id' => 'article-abc', 'status' => 'queued'],
                'execution_log' => 'https://logs.example.com/queue-123',
                'metadata' => ['article_id' => 'article-abc', 'last_status' => 'queued', 'retry_count' => 0]
            ]
        ];

        try {
            $validated = $this->agent->validateInput($messages, $context);
            $this->assert(is_array($validated), "Validated input should be an array");
        } catch (Exception $e) {
            $this->assert(false, "Validation failed: " . $e->getMessage());
        }
    }

    public function testValidateInputMissingConfig()
    {
        echo "\n=== Testing validateInput with Missing Config ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message']
        ];

        $context = [
            'specialized_config' => []
        ];

        try {
            $this->agent->validateInput($messages, $context);
            $this->assert(false, "Should throw exception for missing config");
        } catch (ChatbotBoilerplate\Exceptions\AgentValidationException $e) {
            $this->assert(true, "Should throw AgentValidationException for missing config");
        }
    }

    public function testCustomTools()
    {
        echo "\n=== Testing Custom Tools ===\n";

        $tools = $this->agent->getCustomTools();
        $this->assert(is_array($tools), "Custom tools should be an array");
        $this->assert(count($tools) > 0, "Should have at least one custom tool");

        // Check for specific tools
        $toolNames = array_map(function($tool) {
            return $tool['function']['name'];
        }, $tools);

        $expectedTools = [
            'queue_article_request',
            'update_article_brief',
            'run_generation_phase',
            'submit_required_action_output',
            'fetch_execution_log',
            'list_internal_links'
        ];

        foreach ($expectedTools as $toolName) {
            $this->assert(in_array($toolName, $toolNames), "Should expose {$toolName} tool");
        }
    }

    public function testIntentDetection()
    {
        echo "\n=== Testing Intent Detection ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        // Test create intent
        $messages1 = [
            ['role' => 'user', 'content' => 'Create a blog post about AI']
        ];
        $context1 = $this->agent->buildContext($messages1, ['id' => 'agent-123']);
        $this->assertEquals('create_post', $context1['user_intent']['action'], "Should detect create_post intent");

        // Test search intent
        $messages2 = [
            ['role' => 'user', 'content' => 'Find all posts about machine learning']
        ];
        $context2 = $this->agent->buildContext($messages2, ['id' => 'agent-123']);
        $this->assertEquals('search_posts', $context2['user_intent']['action'], "Should detect search_posts intent");

        // Test update intent
        $messages3 = [
            ['role' => 'user', 'content' => 'Update post 123 with new content']
        ];
        $context3 = $this->agent->buildContext($messages3, ['id' => 'agent-123']);
        $this->assertEquals('update_post', $context3['user_intent']['action'], "Should detect update_post intent");
    }

    public function testRequiresLLM()
    {
        echo "\n=== Testing requiresLLM ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        // Create/update actions should require LLM
        $processedData1 = [
            'intent' => ['action' => 'create_post']
        ];
        $this->assert($this->agent->requiresLLM($processedData1, []), "create_post should require LLM");

        // Search actions should NOT require LLM
        $processedData2 = [
            'intent' => ['action' => 'search_posts']
        ];
        $this->assert(!$this->agent->requiresLLM($processedData2, []), "search_posts should not require LLM");
    }

    public function testCleanup()
    {
        echo "\n=== Testing cleanup ===\n";

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => []
        ]);

        try {
            $this->agent->cleanup();
            $this->assert(true, "Cleanup should execute without errors");
        } catch (Exception $e) {
            $this->assert(false, "Cleanup failed: " . $e->getMessage());
        }
    }

    public function testQueueStatusNormalization()
    {
        echo "\n=== Testing queue status normalization ===\n";

        $queueService = new MockQueueService();
        $executionLogger = new MockExecutionLogger();

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => [],
            'wordpress_blog_queue_service' => $queueService,
            'wordpress_blog_execution_logger' => $executionLogger
        ]);

        $method = new ReflectionMethod(WordPressAgent::class, 'updateQueueStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->agent, 'queue-1', 'article-1', 'structure_ready', ['phase' => 'structure']);

        $this->assertEquals('completed', $result['status'], 'structure_ready should normalize to completed');
        $this->assert(!empty($queueService->statusCalls), 'Queue status should be written');
        $this->assertEquals('completed', $queueService->statusCalls[0]['status'], 'Queue service should receive canonical status');
        $this->assert(isset($result['details']['operator_message']), 'Operator guidance should be present');
    }

    public function testRetryTransitionSchedulesStatus()
    {
        echo "\n=== Testing retry scheduling on transient failure ===\n";

        $queueService = new MockQueueService();
        $executionLogger = new MockExecutionLogger();

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => [],
            'wordpress_blog_queue_service' => $queueService,
            'wordpress_blog_execution_logger' => $executionLogger
        ]);

        $method = new ReflectionMethod(WordPressAgent::class, 'wrapWorkflowException');
        $method->setAccessible(true);

        $context = [
            'blog_workflow' => [
                'queue_id' => 'queue-99',
                'metadata' => ['article_id' => 'article-99'],
                'execution_log' => 'https://logs.example.com/fallback'
            ]
        ];

        $exception = new Exception('Rate limit exceeded', 429);
        $result = $method->invoke($this->agent, 'generate_structure', 'structure', $exception, $context);

        $this->assert($result instanceof ChatbotBoilerplate\Exceptions\AgentProcessingException, 'Should return processing exception');
        $this->assertEquals('retry_scheduled', end($queueService->statusCalls)['status'], 'Rate limit should schedule retry');
        $this->assert(str_contains($result->getMessage(), 'retry'), 'Operator message should mention retry path');
    }

    public function testHardFailureSetsFailedStatus()
    {
        echo "\n=== Testing hard failure transition ===\n";

        $queueService = new MockQueueService();
        $executionLogger = new MockExecutionLogger();

        $this->agent->initialize([
            'db' => new MockDB(),
            'logger' => new MockLogger(),
            'config' => [],
            'wordpress_blog_queue_service' => $queueService,
            'wordpress_blog_execution_logger' => $executionLogger
        ]);

        $method = new ReflectionMethod(WordPressAgent::class, 'wrapWorkflowException');
        $method->setAccessible(true);

        $context = [
            'blog_workflow' => [
                'queue_id' => 'queue-100',
                'metadata' => ['article_id' => 'article-100'],
                'execution_log' => 'https://logs.example.com/hard-failure'
            ]
        ];

        $exception = new Exception('Invalid publish payload', 500);
        $result = $method->invoke($this->agent, 'publish_article', 'publish', $exception, $context);

        $this->assert($result instanceof ChatbotBoilerplate\Exceptions\AgentProcessingException, 'Should return processing exception');
        $this->assertEquals('failed', end($queueService->statusCalls)['status'], 'Hard failure should mark queue as failed');
        $this->assert(str_contains($result->getMessage(), 'failed'), 'Operator message should describe failure');
    }

    public function runAll()
    {
        echo "\n╔═══════════════════════════════════════════╗\n";
        echo "║   WordPress Agent Unit Tests            ║\n";
        echo "╚═══════════════════════════════════════════╝\n";

        try {
            $this->testMetadata();
            $this->testConfigSchema();
            $this->testInitialization();
            $this->testBuildContext();
            $this->testValidateInput();
            $this->testValidateInputMissingConfig();
            $this->testCustomTools();
            $this->testIntentDetection();
            $this->testRequiresLLM();
            $this->testCleanup();
            $this->testQueueStatusNormalization();
            $this->testRetryTransitionSchedulesStatus();
            $this->testHardFailureSetsFailedStatus();

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
    $test = new WordPressAgentTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}

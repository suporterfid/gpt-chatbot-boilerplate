<?php
/**
 * Template Specialized Agent
 *
 * This is a skeleton agent that developers can copy and customize.
 * Replace "Template" with your agent type name (e.g., WordPress, LinkedIn).
 *
 * INSTRUCTIONS:
 * 1. Copy this entire directory to agents/your-agent-type/
 * 2. Rename this file to YourAgentTypeAgent.php
 * 3. Update the class name to match (e.g., WordPressAgent, LinkedInAgent)
 * 4. Update getAgentType(), getDisplayName(), getDescription()
 * 5. Implement the process() method with your custom logic
 * 6. Update config.schema.json with your configuration requirements
 * 7. Add custom tools if needed
 * 8. Write tests in the tests/ directory
 *
 * @package ChatbotBoilerplate\Agents
 * @version 1.0.0
 */

require_once __DIR__ . '/../../includes/Agents/AbstractSpecializedAgent.php';

use ChatbotBoilerplate\Agents\AbstractSpecializedAgent;
use ChatbotBoilerplate\Exceptions\AgentValidationException;
use ChatbotBoilerplate\Exceptions\AgentProcessingException;

class TemplateAgent extends AbstractSpecializedAgent
{
    // ==================== REQUIRED METADATA ====================

    /**
     * Get unique agent type identifier
     *
     * CHANGE THIS: Use a unique lowercase identifier (e.g., 'wordpress', 'linkedin')
     *
     * @return string
     */
    public function getAgentType(): string
    {
        return 'template'; // CHANGE THIS
    }

    /**
     * Get human-readable agent type name
     *
     * CHANGE THIS: Provide a user-friendly display name
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return 'Template Agent'; // CHANGE THIS
    }

    /**
     * Get agent type description
     *
     * CHANGE THIS: Describe what your agent does
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'A template agent for creating new specialized agents. Copy and customize this for your needs.'; // CHANGE THIS
    }

    /**
     * Get agent version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get configuration schema
     *
     * CUSTOMIZE THIS: Define your agent's configuration requirements
     * See config.schema.json for the full schema definition
     *
     * @return array JSON Schema definition
     */
    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'example_setting' => [
                    'type' => 'string',
                    'description' => 'An example configuration setting',
                    'default' => 'default_value'
                ],
                'api_endpoint' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'API endpoint URL (optional)'
                ],
                'api_key' => [
                    'type' => 'string',
                    'description' => 'API authentication key (supports env variables: ${VAR_NAME})',
                    'minLength' => 10,
                    'sensitive' => true // Mark as sensitive for encryption
                ],
                'enable_feature_x' => [
                    'type' => 'boolean',
                    'description' => 'Enable feature X',
                    'default' => false
                ]
            ]
        ];
    }

    // ==================== INITIALIZATION ====================

    /**
     * Initialize agent with dependencies
     *
     * Override this if you need custom initialization.
     * Always call parent::initialize($dependencies) first.
     *
     * @param array $dependencies Injected dependencies (db, logger, config)
     * @return void
     */
    public function initialize(array $dependencies): void
    {
        parent::initialize($dependencies);

        // TODO: Add custom initialization logic here
        // Example:
        // $this->apiClient = new YourApiClient();
        // $this->cache = new YourCacheService();

        $this->logInfo('Agent initialized');
    }

    // ==================== CONTEXT BUILDING ====================

    /**
     * Build processing context
     *
     * Override to add custom context data that will be available
     * throughout the processing pipeline.
     *
     * @param array $messages Conversation messages
     * @param array $agentConfig Agent configuration
     * @return array Processing context
     */
    public function buildContext(array $messages, array $agentConfig): array
    {
        $context = parent::buildContext($messages, $agentConfig);

        // TODO: Add agent-specific context
        // Example:
        // $context['user_intent'] = $this->detectIntent($messages);
        // $context['session_data'] = $this->loadSessionData($context['conversation_id']);

        return $context;
    }

    // ==================== INPUT VALIDATION ====================

    /**
     * Validate input messages
     *
     * Override to add custom validation rules.
     * Throw AgentValidationException if validation fails.
     *
     * @param array $messages Input messages
     * @param array $context Processing context
     * @return array Validated messages
     * @throws AgentValidationException
     */
    public function validateInput(array $messages, array $context): array
    {
        // Basic validation from parent class
        $validatedMessages = parent::validateInput($messages, $context);

        // TODO: Add custom validation
        // Example:
        // $userMessage = $this->extractUserMessage($messages, -1);
        // if (empty($userMessage)) {
        //     throw new AgentValidationException('User message cannot be empty');
        // }

        return $validatedMessages;
    }

    // ==================== CORE PROCESSING ====================

    /**
     * Core processing logic
     *
     * IMPLEMENT THIS: Your agent's main processing logic.
     *
     * This is where you implement your agent's unique behavior:
     * - Extract information from user input
     * - Call external APIs
     * - Transform data
     * - Prepare data for LLM (if needed)
     *
     * @param array $input Validated input messages
     * @param array $context Processing context
     * @return array Processed data
     */
    public function process(array $input, array $context): array
    {
        $this->logInfo('Processing started', ['message_count' => count($input)]);

        // TODO: Implement your agent's processing logic here

        // Example implementation:
        // 1. Extract user intent or action
        $userMessage = $this->extractUserMessage($input, -1);
        $intent = $this->detectIntent($userMessage, [
            'create' => '/create|make|generate/i',
            'update' => '/update|modify|change/i',
            'query' => '/what|how|when|where|why/i'
        ]);

        $this->logInfo('Detected intent', ['intent' => $intent['intent'] ?? 'unknown']);

        // 2. Execute action based on intent
        $result = null;
        if ($intent) {
            switch ($intent['intent']) {
                case 'create':
                    $result = $this->handleCreate($userMessage, $context);
                    break;
                case 'update':
                    $result = $this->handleUpdate($userMessage, $context);
                    break;
                case 'query':
                    $result = $this->handleQuery($userMessage, $context);
                    break;
            }
        }

        // 3. Return processed data
        return [
            'messages' => $input,
            'intent' => $intent,
            'result' => $result,
            'custom_data' => [
                'processed_at' => date('c'),
                'agent_version' => $this->getVersion()
            ]
        ];
    }

    // ==================== LLM INTEGRATION ====================

    /**
     * Determine if LLM interaction is required
     *
     * Override if your agent has conditional LLM usage.
     * Return false if you can respond without LLM (e.g., using cached data).
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return bool
     */
    public function requiresLLM(array $processedData, array $context): bool
    {
        // TODO: Add custom logic
        // Example:
        // if (isset($processedData['result']['cached'])) {
        //     return false; // Use cached response
        // }

        return true; // Default: always use LLM
    }

    /**
     * Prepare messages for LLM
     *
     * Override to customize messages sent to the LLM.
     * Add system messages, inject context, format user input, etc.
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return array Messages for OpenAI API
     */
    public function prepareLLMMessages(array $processedData, array $context): array
    {
        $messages = $processedData['messages'];

        // TODO: Customize messages sent to LLM
        // Example: Add custom system message with context
        $systemMessage = $context['agent_config']['system_message'] ?? 'You are a helpful assistant.';

        // Enhance system message with agent-specific instructions
        $systemMessage .= "\n\nYou are a specialized " . $this->getDisplayName() . ".";

        // Add intent context if available
        if (isset($processedData['intent']['intent'])) {
            $systemMessage .= "\nThe user wants to: " . $processedData['intent']['intent'];
        }

        // Prepend system message
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemMessage
        ]);

        return $messages;
    }

    /**
     * Format output for response
     *
     * Override to customize response formatting.
     * Transform LLM response, add metadata, inject data, etc.
     *
     * @param array $processedData Output from process() (may include llm_response)
     * @param array $context Processing context
     * @return array Formatted response
     */
    public function formatOutput(array $processedData, array $context): array
    {
        $output = parent::formatOutput($processedData, $context);

        // TODO: Add custom formatting
        // Example: Add custom metadata
        $output['metadata']['intent'] = $processedData['intent']['intent'] ?? 'unknown';
        $output['metadata']['custom_data'] = $processedData['custom_data'] ?? [];

        return $output;
    }

    // ==================== CUSTOM TOOLS (OPTIONAL) ====================

    /**
     * Get custom tools for this agent
     *
     * Override to add agent-specific tools/functions that the LLM can call.
     *
     * @return array OpenAI function definitions
     */
    public function getCustomTools(): array
    {
        // TODO: Define custom tools using OpenAI function schema
        return [
            // Example tool:
            [
                'type' => 'function',
                'function' => [
                    'name' => 'example_tool',
                    'description' => 'An example custom tool that does something useful',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'param1' => [
                                'type' => 'string',
                                'description' => 'First parameter'
                            ],
                            'param2' => [
                                'type' => 'number',
                                'description' => 'Second parameter (optional)'
                            ]
                        ],
                        'required' => ['param1']
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute custom tool
     *
     * Override to implement tool execution logic.
     * This is called when the LLM invokes one of your custom tools.
     *
     * @param string $toolName Tool name from function call
     * @param array $arguments Tool arguments from LLM
     * @param array $context Processing context
     * @return array Tool execution result
     */
    public function executeCustomTool(string $toolName, array $arguments, array $context): array
    {
        // TODO: Implement tool execution

        switch ($toolName) {
            case 'example_tool':
                return $this->handleExampleTool($arguments, $context);

            default:
                // Fall back to parent (which throws exception)
                return parent::executeCustomTool($toolName, $arguments, $context);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Handle create action
     *
     * Example helper method for processing "create" intent
     *
     * @param string $userMessage User message
     * @param array $context Processing context
     * @return array Result data
     */
    private function handleCreate(string $userMessage, array $context): array
    {
        // TODO: Implement create logic
        $this->logInfo('Handling create action');

        return [
            'action' => 'create',
            'status' => 'pending',
            'message' => 'Create action initiated'
        ];
    }

    /**
     * Handle update action
     *
     * Example helper method for processing "update" intent
     *
     * @param string $userMessage User message
     * @param array $context Processing context
     * @return array Result data
     */
    private function handleUpdate(string $userMessage, array $context): array
    {
        // TODO: Implement update logic
        $this->logInfo('Handling update action');

        return [
            'action' => 'update',
            'status' => 'pending',
            'message' => 'Update action initiated'
        ];
    }

    /**
     * Handle query action
     *
     * Example helper method for processing "query" intent
     *
     * @param string $userMessage User message
     * @param array $context Processing context
     * @return array Result data
     */
    private function handleQuery(string $userMessage, array $context): array
    {
        // TODO: Implement query logic
        $this->logInfo('Handling query action');

        return [
            'action' => 'query',
            'status' => 'completed',
            'message' => 'Query processed'
        ];
    }

    /**
     * Example tool handler
     *
     * @param array $arguments Tool arguments
     * @param array $context Processing context
     * @return array Tool result
     */
    private function handleExampleTool(array $arguments, array $context): array
    {
        // TODO: Implement tool logic

        $param1 = $arguments['param1'] ?? null;
        $param2 = $arguments['param2'] ?? 0;

        $this->logInfo('Example tool executed', ['param1' => $param1, 'param2' => $param2]);

        return [
            'success' => true,
            'result' => "Tool executed successfully with param1={$param1}, param2={$param2}",
            'data' => [
                'param1' => $param1,
                'param2' => $param2
            ]
        ];
    }

    /**
     * Cleanup resources
     *
     * Override if you need to release resources (connections, temp files, etc.)
     * Called at the end of request processing.
     *
     * @return void
     */
    public function cleanup(): void
    {
        parent::cleanup();

        // TODO: Add cleanup logic
        // Example:
        // $this->apiClient->disconnect();
        // $this->cache->flush();

        $this->logDebug('Cleanup completed');
    }
}

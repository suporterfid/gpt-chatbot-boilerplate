<?php
/**
 * Abstract Specialized Agent
 *
 * Provides common functionality for specialized agents.
 * Agents can extend this class instead of implementing the interface directly.
 *
 * This base class provides:
 * - Default implementations of interface methods
 * - Helper methods for common operations
 * - Logging utilities
 * - Error handling patterns
 *
 * @package ChatbotBoilerplate\Agents
 * @version 1.0.0
 */

namespace ChatbotBoilerplate\Agents;

use ChatbotBoilerplate\Interfaces\SpecializedAgentInterface;
use ChatbotBoilerplate\Exceptions\AgentValidationException;

abstract class AbstractSpecializedAgent implements SpecializedAgentInterface
{
    /**
     * Database instance
     * @var \DB
     */
    protected $db;

    /**
     * Logger instance (PSR-3 compatible)
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    /**
     * Global configuration
     * @var array
     */
    protected $config;

    // ==================== INITIALIZATION ====================

    /**
     * Initialize agent with dependencies
     *
     * Override this method if you need custom initialization,
     * but remember to call parent::initialize($dependencies).
     *
     * @param array $dependencies Injected dependencies
     * @return void
     */
    public function initialize(array $dependencies): void
    {
        $this->db = $dependencies['db'] ?? null;
        $this->logger = $dependencies['logger'] ?? null;
        $this->config = $dependencies['config'] ?? [];
    }

    // ==================== CONTEXT & VALIDATION ====================

    /**
     * Build processing context from conversation messages
     *
     * Default implementation extracts basic context.
     * Override to add agent-specific context data.
     *
     * @param array $messages Conversation history
     * @param array $agentConfig Agent configuration from database
     * @return array Processing context
     */
    public function buildContext(array $messages, array $agentConfig): array
    {
        return [
            'conversation_id' => $agentConfig['conversation_id'] ?? null,
            'tenant_id' => $agentConfig['tenant_id'] ?? null,
            'agent_id' => $agentConfig['id'] ?? null,
            'agent_type' => $this->getAgentType(),
            'message_count' => count($messages),
            'timestamp' => time(),
            'agent_config' => $agentConfig,
            'specialized_config' => $agentConfig['specialized_config'] ?? []
        ];
    }

    /**
     * Validate and transform input messages
     *
     * Default implementation performs basic validation.
     * Override for custom validation logic.
     *
     * @param array $messages Input messages
     * @param array $context Processing context
     * @return array Validated messages
     * @throws AgentValidationException if validation fails
     */
    public function validateInput(array $messages, array $context): array
    {
        if (empty($messages)) {
            throw new AgentValidationException('Messages array cannot be empty', [
                'field' => 'messages',
                'error' => 'required'
            ]);
        }

        // Validate message structure
        foreach ($messages as $index => $message) {
            if (!isset($message['role'])) {
                throw new AgentValidationException(
                    "Message at index {$index} missing required 'role' field",
                    ['field' => "messages[{$index}].role", 'error' => 'required']
                );
            }

            if (!isset($message['content']) && !isset($message['tool_calls'])) {
                throw new AgentValidationException(
                    "Message at index {$index} must have 'content' or 'tool_calls'",
                    ['field' => "messages[{$index}]", 'error' => 'missing_content']
                );
            }
        }

        return $messages;
    }

    /**
     * Validate output before sending to user
     *
     * Default implementation performs basic validation.
     * Override for custom validation logic.
     *
     * @param array $output Formatted output
     * @param array $context Processing context
     * @return array Validated output
     * @throws AgentValidationException if validation fails
     */
    public function validateOutput(array $output, array $context): array
    {
        if (!isset($output['message'])) {
            throw new AgentValidationException(
                'Output must contain message field',
                ['field' => 'message', 'error' => 'required']
            );
        }

        $message = $output['message'];

        if (!isset($message['role'])) {
            throw new AgentValidationException(
                'Output message must have role field',
                ['field' => 'message.role', 'error' => 'required']
            );
        }

        if (!isset($message['content']) && !isset($message['tool_calls'])) {
            throw new AgentValidationException(
                'Output message must have content or tool_calls',
                ['field' => 'message.content', 'error' => 'required']
            );
        }

        return $output;
    }

    // ==================== LLM INTERACTION ====================

    /**
     * Determine if LLM interaction is required
     *
     * Default: always use LLM.
     * Override to implement conditional LLM usage.
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return bool True if LLM call needed
     */
    public function requiresLLM(array $processedData, array $context): bool
    {
        return true;
    }

    /**
     * Prepare messages for LLM API call
     *
     * Default implementation passes through original messages.
     * Override to customize messages sent to LLM.
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return array Messages for OpenAI API
     */
    public function prepareLLMMessages(array $processedData, array $context): array
    {
        return $processedData['messages'] ?? [];
    }

    /**
     * Format output for response
     *
     * Default implementation extracts LLM response.
     * Override to customize response formatting.
     *
     * @param array $processedData Output from process() (may include llm_response)
     * @param array $context Processing context
     * @return array Formatted response
     */
    public function formatOutput(array $processedData, array $context): array
    {
        $output = [
            'message' => [
                'role' => 'assistant',
                'content' => ''
            ],
            'metadata' => [
                'agent_type' => $this->getAgentType(),
                'processed_at' => date('c')
            ]
        ];

        // Extract LLM response if available
        if (isset($processedData['llm_response']['choices'][0]['message'])) {
            $output['message'] = $processedData['llm_response']['choices'][0]['message'];
        }

        // Include custom data if present
        if (isset($processedData['custom_data'])) {
            $output['metadata']['custom_data'] = $processedData['custom_data'];
        }

        return $output;
    }

    // ==================== ERROR HANDLING ====================

    /**
     * Handle errors during processing
     *
     * Default implementation logs error and returns generic message.
     * Override to provide custom error handling.
     *
     * @param \Throwable $error Exception that occurred
     * @param array $context Processing context
     * @return array Error response
     */
    public function handleError(\Throwable $error, array $context): array
    {
        $this->logError('Agent processing error: ' . $error->getMessage(), [
            'exception' => get_class($error),
            'message' => $error->getMessage(),
            'context' => $context,
            'trace' => $error->getTraceAsString()
        ]);

        return [
            'error' => true,
            'message' => [
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again.'
            ],
            'metadata' => [
                'agent_type' => $this->getAgentType(),
                'error_type' => get_class($error),
                'error_code' => $error->getCode()
            ]
        ];
    }

    // ==================== CUSTOM TOOLS ====================

    /**
     * Get custom tools for this agent
     *
     * Default: no custom tools.
     * Override to add agent-specific tools.
     *
     * @return array OpenAI function definitions
     */
    public function getCustomTools(): array
    {
        return [];
    }

    /**
     * Execute custom tool
     *
     * Default implementation throws exception.
     * Override to implement tool execution logic.
     *
     * @param string $toolName Tool name
     * @param array $arguments Tool arguments
     * @param array $context Processing context
     * @return array Tool result
     * @throws \BadMethodCallException
     */
    public function executeCustomTool(string $toolName, array $arguments, array $context): array
    {
        throw new \BadMethodCallException("Tool '{$toolName}' not implemented by " . $this->getAgentType());
    }

    // ==================== LIFECYCLE ====================

    /**
     * Cleanup resources
     *
     * Default: no cleanup needed.
     * Override if you need to release resources.
     *
     * @return void
     */
    public function cleanup(): void
    {
        // Default: no cleanup needed
    }

    // ==================== HELPER METHODS ====================

    /**
     * Log info message with agent context
     *
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    protected function logInfo(string $message, array $data = []): void
    {
        if ($this->logger) {
            $this->logger->info("[{$this->getAgentType()}] {$message}", $data);
        }
    }

    /**
     * Log error message with agent context
     *
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    protected function logError(string $message, array $data = []): void
    {
        if ($this->logger) {
            $this->logger->error("[{$this->getAgentType()}] {$message}", $data);
        }
    }

    /**
     * Log warning message with agent context
     *
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    protected function logWarning(string $message, array $data = []): void
    {
        if ($this->logger) {
            $this->logger->warning("[{$this->getAgentType()}] {$message}", $data);
        }
    }

    /**
     * Log debug message with agent context
     *
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    protected function logDebug(string $message, array $data = []): void
    {
        if ($this->logger) {
            $this->logger->debug("[{$this->getAgentType()}] {$message}", $data);
        }
    }

    /**
     * Get specialized configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @param array $context Context containing specialized_config
     * @return mixed Configuration value
     */
    protected function getConfig(string $key, $default = null, array $context = [])
    {
        $specializedConfig = $context['specialized_config'] ?? [];
        return $specializedConfig[$key] ?? $default;
    }

    /**
     * Extract user message content from messages array
     *
     * @param array $messages Message array
     * @param int $index Index (negative values count from end, -1 = last)
     * @return string Message content
     */
    protected function extractUserMessage(array $messages, int $index = -1): string
    {
        if ($index < 0) {
            $index = count($messages) + $index;
        }

        if (!isset($messages[$index])) {
            return '';
        }

        $message = $messages[$index];

        if ($message['role'] !== 'user') {
            return '';
        }

        return $message['content'] ?? '';
    }

    /**
     * Detect intent or action from user message
     *
     * Helper method to extract keywords or patterns from user input.
     *
     * @param string $message User message
     * @param array $patterns Regex patterns to match
     * @return array|null Matches or null if no match
     */
    protected function detectIntent(string $message, array $patterns): ?array
    {
        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return [
                    'intent' => $intent,
                    'matches' => $matches
                ];
            }
        }

        return null;
    }
}

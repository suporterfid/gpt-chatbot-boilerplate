<?php
/**
 * Specialized Agent Interface
 *
 * All specialized agent types must implement this interface.
 * Defines the contract for custom agent behavior and processing logic.
 *
 * @package ChatbotBoilerplate\Interfaces
 * @version 1.0.0
 */

namespace ChatbotBoilerplate\Interfaces;

use ChatbotBoilerplate\Exceptions\AgentValidationException;

interface SpecializedAgentInterface
{
    // ==================== METADATA METHODS ====================

    /**
     * Get unique agent type identifier
     *
     * This identifier is used for registration and lookup.
     * Must be unique across all agents, lowercase, no spaces.
     *
     * @return string Unique identifier (e.g., 'wordpress', 'linkedin', 'biblical-writer')
     */
    public function getAgentType(): string;

    /**
     * Get human-readable agent type name
     *
     * @return string Display name (e.g., 'WordPress Content Manager', 'LinkedIn Assistant')
     */
    public function getDisplayName(): string;

    /**
     * Get agent type description
     *
     * @return string Brief description of agent capabilities
     */
    public function getDescription(): string;

    /**
     * Get agent version
     *
     * @return string Semantic version (e.g., '1.0.0')
     */
    public function getVersion(): string;

    /**
     * Get configuration schema
     *
     * Returns a JSON Schema definition that describes the structure
     * and validation rules for agent-specific configuration.
     *
     * @return array JSON Schema definition for agent-specific configuration
     */
    public function getConfigSchema(): array;

    // ==================== INITIALIZATION ====================

    /**
     * Initialize agent with dependencies
     *
     * Called once when agent is first instantiated by the registry.
     * Use this to set up any required services, connections, or state.
     *
     * Expected dependencies:
     * - 'db': Database instance
     * - 'logger': PSR-3 compatible logger
     * - 'config': Global configuration array
     *
     * @param array $dependencies Injected dependencies
     * @return void
     */
    public function initialize(array $dependencies): void;

    // ==================== PROCESSING PIPELINE ====================

    /**
     * Build processing context from conversation messages
     *
     * Creates a context object that flows through the entire processing pipeline.
     * Use this to extract metadata, prepare state, or gather additional data.
     *
     * @param array $messages Conversation history (OpenAI message format)
     * @param array $agentConfig Agent configuration from database
     * @return array Context object for downstream processing
     */
    public function buildContext(array $messages, array $agentConfig): array;

    /**
     * Validate and transform input messages
     *
     * Validates incoming messages and performs any necessary transformations.
     * Throw AgentValidationException if input is invalid.
     *
     * @param array $messages Input messages
     * @param array $context Processing context from buildContext()
     * @return array Validated and transformed messages
     * @throws AgentValidationException if input is invalid
     */
    public function validateInput(array $messages, array $context): array;

    /**
     * Core processing logic (agent-specific behavior)
     *
     * This is where your agent's custom logic lives. Process the input,
     * call external APIs, transform data, or perform any specialized operations.
     *
     * The returned array structure is flexible and determined by your agent's needs.
     * It will be passed to subsequent pipeline methods.
     *
     * @param array $input Validated input from validateInput()
     * @param array $context Processing context
     * @return array Processed data (structure determined by agent)
     */
    public function process(array $input, array $context): array;

    /**
     * Determine if LLM interaction is required
     *
     * Return false if your agent can respond without calling the LLM
     * (e.g., using cached data, templates, or deterministic logic).
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return bool True if LLM call needed, false otherwise
     */
    public function requiresLLM(array $processedData, array $context): bool;

    /**
     * Prepare messages for LLM API call
     *
     * Transform processed data into messages suitable for the OpenAI API.
     * Add system messages, inject context, or format user input.
     *
     * Only called if requiresLLM() returns true.
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return array Messages array for OpenAI API (message format: {role, content})
     */
    public function prepareLLMMessages(array $processedData, array $context): array;

    /**
     * Format output for response
     *
     * Transform processed data (potentially including LLM response) into
     * the final response format.
     *
     * @param array $processedData Output from process() (may include llm_response key)
     * @param array $context Processing context
     * @return array Formatted response data
     */
    public function formatOutput(array $processedData, array $context): array;

    /**
     * Validate output before sending to user
     *
     * Perform final validation on the formatted output.
     * Throw AgentValidationException if output is invalid.
     *
     * @param array $output Formatted output from formatOutput()
     * @param array $context Processing context
     * @return array Validated output
     * @throws AgentValidationException if output is invalid
     */
    public function validateOutput(array $output, array $context): array;

    // ==================== ERROR HANDLING ====================

    /**
     * Handle errors during processing
     *
     * Called when an exception occurs during agent processing.
     * Return a user-friendly error response.
     *
     * @param \Throwable $error Exception/error that occurred
     * @param array $context Processing context
     * @return array Error response to send to user (should include 'message' key)
     */
    public function handleError(\Throwable $error, array $context): array;

    // ==================== CUSTOM TOOLS ====================

    /**
     * Get custom tools/functions for this agent type
     *
     * Return an array of OpenAI function definitions that will be
     * available to the LLM during processing.
     *
     * @return array OpenAI function definitions (empty array if no custom tools)
     */
    public function getCustomTools(): array;

    /**
     * Execute custom tool call
     *
     * Called when the LLM invokes one of the custom tools.
     * Implement the tool's logic and return the result.
     *
     * @param string $toolName Tool name from function call
     * @param array $arguments Tool arguments from LLM
     * @param array $context Processing context
     * @return array Tool execution result
     * @throws \BadMethodCallException if tool is not implemented
     */
    public function executeCustomTool(string $toolName, array $arguments, array $context): array;

    // ==================== LIFECYCLE ====================

    /**
     * Cleanup resources
     *
     * Called at the end of request processing to release resources
     * (connections, temp files, etc.).
     *
     * @return void
     */
    public function cleanup(): void;
}

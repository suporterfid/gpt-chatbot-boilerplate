<?php
/**
 * Agent Exception Classes
 *
 * Custom exceptions for the specialized agent system.
 * Provides granular error handling for different failure scenarios.
 */

namespace ChatbotBoilerplate\Exceptions;

/**
 * Base exception for all agent-related errors
 */
class AgentException extends \Exception
{
    protected $context = [];

    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get exception context data
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

/**
 * Thrown when an agent type is not found in the registry
 */
class AgentNotFoundException extends AgentException
{
    public function __construct(string $agentType, int $code = 404, \Throwable $previous = null)
    {
        $message = "Agent type '{$agentType}' not found in registry";
        parent::__construct($message, $code, $previous, ['agent_type' => $agentType]);
    }
}

/**
 * Thrown when agent configuration is invalid
 */
class AgentConfigurationException extends AgentException
{
    public function __construct(string $message, array $errors = [], int $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, ['validation_errors' => $errors]);
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->context['validation_errors'] ?? [];
    }
}

/**
 * Thrown when agent input validation fails
 */
class AgentValidationException extends AgentException
{
    public function __construct(string $message, array $validationErrors = [], int $code = 422, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, ['validation_errors' => $validationErrors]);
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->context['validation_errors'] ?? [];
    }
}

/**
 * Thrown when agent processing fails
 */
class AgentProcessingException extends AgentException
{
    public function __construct(string $message, string $agentType = '', int $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, ['agent_type' => $agentType]);
    }
}

/**
 * Thrown when agent initialization fails
 */
class AgentInitializationException extends AgentException
{
    public function __construct(string $message, string $agentType = '', int $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, ['agent_type' => $agentType]);
    }
}

/**
 * Thrown when a custom tool execution fails
 */
class AgentToolException extends AgentException
{
    public function __construct(string $toolName, string $message, int $code = 500, \Throwable $previous = null)
    {
        $fullMessage = "Tool '{$toolName}' execution failed: {$message}";
        parent::__construct($fullMessage, $code, $previous, ['tool_name' => $toolName]);
    }
}

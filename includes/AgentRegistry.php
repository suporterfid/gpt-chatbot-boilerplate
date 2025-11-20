<?php
/**
 * Agent Registry Service
 *
 * Discovers, registers, and manages specialized agent types.
 * Uses auto-discovery to find agent classes in the /agents/ directory.
 *
 * Features:
 * - Automatic agent discovery and registration
 * - Lazy instantiation of agent instances
 * - Dependency injection
 * - Configuration validation
 * - Caching of agent metadata
 *
 * @package ChatbotBoilerplate
 * @version 1.0.0
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Interfaces/SpecializedAgentInterface.php';
require_once __DIR__ . '/Exceptions/AgentException.php';

use ChatbotBoilerplate\Interfaces\SpecializedAgentInterface;
use ChatbotBoilerplate\Exceptions\AgentNotFoundException;
use ChatbotBoilerplate\Exceptions\AgentConfigurationException;
use ChatbotBoilerplate\Exceptions\AgentInitializationException;

class AgentRegistry
{
    /**
     * Registered agent types (type => class name)
     * @var array
     */
    private $registeredTypes = [];

    /**
     * Instantiated agent instances (type => instance)
     * @var array
     */
    private $instances = [];

    /**
     * Agent metadata cache (type => metadata)
     * @var array
     */
    private $metadata = [];

    /**
     * Database instance
     * @var DB
     */
    private $db;

    /**
     * Path to agents directory
     * @var string
     */
    private $agentsPath;

    /**
     * Logger instance
     * @var mixed
     */
    private $logger;

    /**
     * Global configuration
     * @var array
     */
    private $config;

    /**
     * Discovery complete flag
     * @var bool
     */
    private $discoveryComplete = false;

    /**
     * Constructor
     *
     * @param DB $db Database instance
     * @param string $agentsPath Path to agents directory
     * @param mixed $logger Logger instance (PSR-3 compatible)
     * @param array $config Global configuration
     */
    public function __construct(DB $db, string $agentsPath, $logger = null, array $config = [])
    {
        $this->db = $db;
        $this->agentsPath = rtrim($agentsPath, '/\\');
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Discover and register all available agent types
     *
     * Scans the agents directory for PHP files implementing SpecializedAgentInterface.
     * Automatically registers found agents.
     *
     * @param bool $force Force re-discovery even if already completed
     * @return void
     */
    public function discoverAgents(bool $force = false): void
    {
        if ($this->discoveryComplete && !$force) {
            return;
        }

        $this->logInfo('Starting agent discovery', ['path' => $this->agentsPath]);

        if (!is_dir($this->agentsPath)) {
            $this->logWarning('Agents directory not found', ['path' => $this->agentsPath]);
            $this->discoveryComplete = true;
            return;
        }

        $discoveredCount = 0;

        // Scan agent directories
        $directories = glob($this->agentsPath . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $agentType = basename($dir);

            // Skip template directory
            if ($agentType === '_template') {
                continue;
            }

            // Look for agent class file (e.g., WordPressAgent.php)
            $expectedClassName = $this->getExpectedClassName($agentType);
            $expectedFile = $dir . '/' . $expectedClassName . '.php';

            if (!file_exists($expectedFile)) {
                $this->logWarning("Agent file not found for type '{$agentType}'", [
                    'expected_file' => $expectedFile
                ]);
                continue;
            }

            try {
                // Include the agent file
                require_once $expectedFile;

                // Check if class exists
                $className = $expectedClassName;

                // Try with namespace
                if (!class_exists($className)) {
                    $className = "ChatbotBoilerplate\\Agents\\{$expectedClassName}";
                }

                if (!class_exists($className)) {
                    $this->logWarning("Class not found for agent type '{$agentType}'", [
                        'expected_class' => $expectedClassName,
                        'file' => $expectedFile
                    ]);
                    continue;
                }

                // Verify class implements interface
                $reflection = new ReflectionClass($className);

                if (!$reflection->implementsInterface(SpecializedAgentInterface::class)) {
                    $this->logWarning("Class does not implement SpecializedAgentInterface", [
                        'class' => $className,
                        'type' => $agentType
                    ]);
                    continue;
                }

                // Register the agent type
                $this->registerType($agentType, $className);
                $discoveredCount++;

                $this->logInfo("Registered agent type '{$agentType}'", [
                    'class' => $className
                ]);

            } catch (Exception $e) {
                $this->logError("Failed to register agent type '{$agentType}'", [
                    'error' => $e->getMessage(),
                    'file' => $expectedFile
                ]);
            }
        }

        $this->discoveryComplete = true;

        $this->logInfo('Agent discovery complete', [
            'discovered_count' => $discoveredCount,
            'total_registered' => count($this->registeredTypes)
        ]);

        // Sync metadata to database
        $this->syncMetadataToDatabase();
    }

    /**
     * Register a specific agent type
     *
     * @param string $agentType Unique identifier (e.g., 'wordpress')
     * @param string $className Fully qualified class name
     * @return void
     * @throws AgentConfigurationException if agent type already registered
     */
    public function registerType(string $agentType, string $className): void
    {
        if (isset($this->registeredTypes[$agentType])) {
            throw new AgentConfigurationException(
                "Agent type '{$agentType}' is already registered",
                ['agent_type' => $agentType, 'existing_class' => $this->registeredTypes[$agentType]]
            );
        }

        $this->registeredTypes[$agentType] = $className;

        // Clear cached metadata for this type
        unset($this->metadata[$agentType]);
    }

    /**
     * Get instance of specialized agent for a given type
     *
     * Lazy instantiation: creates instance on first request and caches it.
     *
     * @param string $agentType Agent type identifier
     * @return SpecializedAgentInterface|null Agent instance or null if not found
     * @throws AgentInitializationException if agent initialization fails
     */
    public function getAgent(string $agentType): ?SpecializedAgentInterface
    {
        // Ensure discovery has run
        if (!$this->discoveryComplete) {
            $this->discoverAgents();
        }

        // Check if type is registered
        if (!$this->hasAgentType($agentType)) {
            return null;
        }

        // Return cached instance if available
        if (isset($this->instances[$agentType])) {
            return $this->instances[$agentType];
        }

        // Instantiate agent
        try {
            $className = $this->registeredTypes[$agentType];
            $agent = new $className();

            if (!($agent instanceof SpecializedAgentInterface)) {
                throw new AgentInitializationException(
                    "Agent class does not implement SpecializedAgentInterface",
                    $agentType
                );
            }

            // Initialize agent with dependencies
            $dependencies = [
                'db' => $this->db,
                'logger' => $this->logger,
                'config' => $this->config
            ];

            $agent->initialize($dependencies);

            // Cache instance
            $this->instances[$agentType] = $agent;

            $this->logInfo("Instantiated agent '{$agentType}'", [
                'class' => $className
            ]);

            return $agent;

        } catch (Exception $e) {
            throw new AgentInitializationException(
                "Failed to instantiate agent '{$agentType}': " . $e->getMessage(),
                $agentType,
                500,
                $e
            );
        }
    }

    /**
     * Check if agent type is registered
     *
     * @param string $agentType Agent type identifier
     * @return bool True if registered
     */
    public function hasAgentType(string $agentType): bool
    {
        // Ensure discovery has run
        if (!$this->discoveryComplete) {
            $this->discoverAgents();
        }

        return isset($this->registeredTypes[$agentType]);
    }

    /**
     * Get all registered agent types with metadata
     *
     * @return array Array of agent type metadata
     */
    public function listAvailableTypes(): array
    {
        // Ensure discovery has run
        if (!$this->discoveryComplete) {
            $this->discoverAgents();
        }

        $types = [];

        foreach ($this->registeredTypes as $agentType => $className) {
            $types[] = $this->getAgentMetadata($agentType);
        }

        return $types;
    }

    /**
     * Get metadata for a specific agent type
     *
     * @param string $agentType Agent type identifier
     * @return array|null Metadata or null if not found
     */
    public function getAgentMetadata(string $agentType): ?array
    {
        if (!$this->hasAgentType($agentType)) {
            return null;
        }

        // Return cached metadata if available
        if (isset($this->metadata[$agentType])) {
            return $this->metadata[$agentType];
        }

        try {
            // Get agent instance to extract metadata
            $agent = $this->getAgent($agentType);

            if (!$agent) {
                return null;
            }

            $metadata = [
                'agent_type' => $agent->getAgentType(),
                'display_name' => $agent->getDisplayName(),
                'description' => $agent->getDescription(),
                'version' => $agent->getVersion(),
                'config_schema' => $agent->getConfigSchema(),
                'custom_tools' => $agent->getCustomTools(),
                'class' => $this->registeredTypes[$agentType]
            ];

            // Cache metadata
            $this->metadata[$agentType] = $metadata;

            return $metadata;

        } catch (Exception $e) {
            $this->logError("Failed to get metadata for agent '{$agentType}'", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate agent configuration against schema
     *
     * @param string $agentType Agent type identifier
     * @param array $config Configuration to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateConfig(string $agentType, array $config): array
    {
        $metadata = $this->getAgentMetadata($agentType);

        if (!$metadata) {
            return ['agent_type' => "Agent type '{$agentType}' not found"];
        }

        $schema = $metadata['config_schema'] ?? [];

        // Basic validation (simplified - in production, use a proper JSON Schema validator)
        $errors = [];

        // Check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!isset($config[$requiredField])) {
                    $errors[$requiredField] = "Required field '{$requiredField}' is missing";
                }
            }
        }

        // Check property types (basic validation)
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($config as $key => $value) {
                if (!isset($schema['properties'][$key])) {
                    // Property not in schema
                    if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                        $errors[$key] = "Property '{$key}' is not allowed";
                    }
                    continue;
                }

                $propertySchema = $schema['properties'][$key];
                $expectedType = $propertySchema['type'] ?? null;

                if ($expectedType && !$this->validateType($value, $expectedType)) {
                    $errors[$key] = "Property '{$key}' must be of type '{$expectedType}'";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate value type
     *
     * @param mixed $value Value to validate
     * @param string $expectedType Expected type
     * @return bool True if valid
     */
    private function validateType($value, string $expectedType): bool
    {
        switch ($expectedType) {
            case 'string':
                return is_string($value);
            case 'number':
            case 'integer':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_array($value) || is_object($value);
            default:
                return true;
        }
    }

    /**
     * Sync agent metadata to database
     *
     * Updates the agent_type_metadata table with discovered agents.
     *
     * @return void
     */
    private function syncMetadataToDatabase(): void
    {
        try {
            foreach ($this->registeredTypes as $agentType => $className) {
                $metadata = $this->getAgentMetadata($agentType);

                if (!$metadata) {
                    continue;
                }

                // Check if metadata exists
                $existing = $this->db->query(
                    "SELECT agent_type FROM agent_type_metadata WHERE agent_type = ?",
                    [$agentType]
                );

                $configSchemaJson = json_encode($metadata['config_schema']);

                if (empty($existing)) {
                    // Insert new metadata
                    $this->db->insert(
                        "INSERT INTO agent_type_metadata (agent_type, display_name, description, version, config_schema_json, enabled)
                         VALUES (?, ?, ?, ?, ?, 1)",
                        [
                            $metadata['agent_type'],
                            $metadata['display_name'],
                            $metadata['description'],
                            $metadata['version'],
                            $configSchemaJson
                        ]
                    );
                } else {
                    // Update existing metadata
                    $this->db->execute(
                        "UPDATE agent_type_metadata
                         SET display_name = ?, description = ?, version = ?, config_schema_json = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE agent_type = ?",
                        [
                            $metadata['display_name'],
                            $metadata['description'],
                            $metadata['version'],
                            $configSchemaJson,
                            $metadata['agent_type']
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            $this->logError('Failed to sync metadata to database', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get expected class name from agent type
     *
     * Converts 'wordpress' to 'WordPressAgent'
     * Converts 'biblical-writer' to 'BiblicalWriterAgent'
     *
     * @param string $agentType Agent type identifier
     * @return string Expected class name
     */
    private function getExpectedClassName(string $agentType): string
    {
        // Split by hyphen and capitalize each part
        $parts = explode('-', $agentType);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts) . 'Agent';
    }

    /**
     * Cleanup all agent instances
     *
     * Calls cleanup() on all instantiated agents.
     *
     * @return void
     */
    public function cleanupAll(): void
    {
        foreach ($this->instances as $agentType => $agent) {
            try {
                $agent->cleanup();
                $this->logDebug("Cleaned up agent '{$agentType}'");
            } catch (Exception $e) {
                $this->logError("Failed to cleanup agent '{$agentType}'", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    // ==================== LOGGING HELPERS ====================

    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info("[AgentRegistry] {$message}", $context);
        }
    }

    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'warning')) {
            $this->logger->warning("[AgentRegistry] {$message}", $context);
        }
    }

    private function logError(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'error')) {
            $this->logger->error("[AgentRegistry] {$message}", $context);
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'debug')) {
            $this->logger->debug("[AgentRegistry] {$message}", $context);
        }
    }
}

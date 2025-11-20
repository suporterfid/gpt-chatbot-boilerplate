<?php
/**
 * Specialized Agent Service
 *
 * Manages specialized agent configurations and provides utilities
 * for working with agent-specific settings.
 *
 * Features:
 * - Configuration CRUD operations
 * - Environment variable substitution
 * - Sensitive data encryption
 * - Configuration validation
 *
 * @package ChatbotBoilerplate
 * @version 1.0.0
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/AgentRegistry.php';
require_once __DIR__ . '/Exceptions/AgentException.php';

use ChatbotBoilerplate\Exceptions\AgentConfigurationException;
use ChatbotBoilerplate\Exceptions\AgentNotFoundException;

class SpecializedAgentService
{
    /**
     * Database instance
     * @var DB
     */
    private $db;

    /**
     * Agent registry
     * @var AgentRegistry
     */
    private $registry;

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
     * Encryption key for sensitive data
     * @var string|null
     */
    private $encryptionKey;

    /**
     * Constructor
     *
     * @param DB $db Database instance
     * @param AgentRegistry $registry Agent registry
     * @param mixed $logger Logger instance
     * @param array $config Global configuration
     */
    public function __construct(DB $db, AgentRegistry $registry, $logger = null, array $config = [])
    {
        $this->db = $db;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->config = $config;
        $this->encryptionKey = $config['specialized_agents']['encryption_key'] ?? null;
    }

    /**
     * Save specialized agent configuration
     *
     * @param string $agentId Agent ID
     * @param string $agentType Agent type
     * @param array $config Configuration data
     * @return array Saved configuration record
     * @throws AgentNotFoundException if agent type not found
     * @throws AgentConfigurationException if configuration is invalid
     */
    public function saveConfig(string $agentId, string $agentType, array $config): array
    {
        // Validate agent type exists
        if (!$this->registry->hasAgentType($agentType)) {
            throw new AgentNotFoundException($agentType);
        }

        // Validate configuration against schema
        $errors = $this->registry->validateConfig($agentType, $config);

        if (!empty($errors)) {
            throw new AgentConfigurationException(
                'Invalid configuration for agent type: ' . $agentType,
                $errors
            );
        }

        // Resolve environment variables
        $resolvedConfig = $this->resolveEnvironmentVariables($config);

        // Encrypt sensitive fields
        $metadata = $this->registry->getAgentMetadata($agentType);
        $schema = $metadata['config_schema'] ?? [];
        $encryptedConfig = $this->encryptSensitiveFields($resolvedConfig, $schema);

        // Convert to JSON
        $configJson = json_encode($encryptedConfig);

        // Check if configuration exists
        $existing = $this->db->query(
            "SELECT id FROM specialized_agent_configs WHERE agent_id = ?",
            [$agentId]
        );

        $id = null;

        if (empty($existing)) {
            // Insert new configuration
            $id = $this->generateUUID();

            $this->db->insert(
                "INSERT INTO specialized_agent_configs (id, agent_id, agent_type, config_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [$id, $agentId, $agentType, $configJson]
            );

            $this->logInfo("Created configuration for agent {$agentId}", [
                'agent_id' => $agentId,
                'agent_type' => $agentType
            ]);
        } else {
            // Update existing configuration
            $id = $existing[0]['id'];

            $this->db->execute(
                "UPDATE specialized_agent_configs
                 SET agent_type = ?, config_json = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$agentType, $configJson, $id]
            );

            $this->logInfo("Updated configuration for agent {$agentId}", [
                'agent_id' => $agentId,
                'agent_type' => $agentType
            ]);
        }

        // Update agent's agent_type in agents table
        $this->db->execute(
            "UPDATE agents SET agent_type = ? WHERE id = ?",
            [$agentType, $agentId]
        );

        return $this->getConfig($agentId);
    }

    /**
     * Get specialized agent configuration
     *
     * @param string $agentId Agent ID
     * @param bool $decrypt Whether to decrypt sensitive fields
     * @return array|null Configuration or null if not found
     */
    public function getConfig(string $agentId, bool $decrypt = true): ?array
    {
        $result = $this->db->query(
            "SELECT * FROM specialized_agent_configs WHERE agent_id = ?",
            [$agentId]
        );

        if (empty($result)) {
            return null;
        }

        $record = $result[0];
        $config = json_decode($record['config_json'], true) ?? [];

        // Decrypt sensitive fields if requested
        if ($decrypt) {
            $metadata = $this->registry->getAgentMetadata($record['agent_type']);
            $schema = $metadata['config_schema'] ?? [];
            $config = $this->decryptSensitiveFields($config, $schema);
        }

        return [
            'id' => $record['id'],
            'agent_id' => $record['agent_id'],
            'agent_type' => $record['agent_type'],
            'config' => $config,
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at']
        ];
    }

    /**
     * Delete specialized agent configuration
     *
     * @param string $agentId Agent ID
     * @return bool True if deleted
     */
    public function deleteConfig(string $agentId): bool
    {
        $result = $this->db->execute(
            "DELETE FROM specialized_agent_configs WHERE agent_id = ?",
            [$agentId]
        );

        // Reset agent_type to generic
        $this->db->execute(
            "UPDATE agents SET agent_type = 'generic' WHERE id = ?",
            [$agentId]
        );

        $this->logInfo("Deleted configuration for agent {$agentId}");

        return $result > 0;
    }

    /**
     * Get configuration for agent with decryption
     *
     * Helper method that combines agent lookup with config retrieval.
     *
     * @param string $agentId Agent ID
     * @return array Configuration array ready for agent use
     */
    public function getAgentConfigWithSpecialization(string $agentId): array
    {
        $specializedConfig = $this->getConfig($agentId, true);

        return [
            'specialized_config' => $specializedConfig['config'] ?? [],
            'agent_type' => $specializedConfig['agent_type'] ?? 'generic'
        ];
    }

    /**
     * Resolve environment variables in configuration
     *
     * Replaces ${VAR_NAME} placeholders with environment variable values.
     *
     * @param array $config Configuration array
     * @return array Resolved configuration
     */
    private function resolveEnvironmentVariables(array $config): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_string($value) && preg_match('/^\$\{([A-Z_][A-Z0-9_]*)\}$/', $value, $matches)) {
                // Replace with environment variable
                $envVar = $matches[1];
                $envValue = getenv($envVar);

                if ($envValue === false) {
                    $this->logWarning("Environment variable not found: {$envVar}");
                    $resolved[$key] = $value; // Keep placeholder
                } else {
                    $resolved[$key] = $envValue;
                }
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveEnvironmentVariables($value);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Encrypt sensitive configuration fields
     *
     * @param array $config Configuration array
     * @param array $schema JSON Schema
     * @return array Configuration with encrypted sensitive fields
     */
    private function encryptSensitiveFields(array $config, array $schema): array
    {
        if (!$this->encryptionKey) {
            return $config; // No encryption if key not set
        }

        $encrypted = [];
        $properties = $schema['properties'] ?? [];

        foreach ($config as $key => $value) {
            $propertySchema = $properties[$key] ?? [];
            $isSensitive = $propertySchema['sensitive'] ?? false;

            if ($isSensitive && is_string($value)) {
                // Encrypt the value
                $encrypted[$key] = $this->encrypt($value);
            } elseif (is_array($value) && isset($propertySchema['properties'])) {
                // Recursively encrypt nested objects
                $encrypted[$key] = $this->encryptSensitiveFields($value, $propertySchema);
            } else {
                $encrypted[$key] = $value;
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt sensitive configuration fields
     *
     * @param array $config Configuration array
     * @param array $schema JSON Schema
     * @return array Configuration with decrypted sensitive fields
     */
    private function decryptSensitiveFields(array $config, array $schema): array
    {
        if (!$this->encryptionKey) {
            return $config; // No decryption if key not set
        }

        $decrypted = [];
        $properties = $schema['properties'] ?? [];

        foreach ($config as $key => $value) {
            $propertySchema = $properties[$key] ?? [];
            $isSensitive = $propertySchema['sensitive'] ?? false;

            if ($isSensitive && is_string($value)) {
                // Decrypt the value
                $decrypted[$key] = $this->decrypt($value);
            } elseif (is_array($value) && isset($propertySchema['properties'])) {
                // Recursively decrypt nested objects
                $decrypted[$key] = $this->decryptSensitiveFields($value, $propertySchema);
            } else {
                $decrypted[$key] = $value;
            }
        }

        return $decrypted;
    }

    /**
     * Encrypt a value
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value (base64 encoded)
     */
    private function encrypt(string $value): string
    {
        if (!$this->encryptionKey) {
            return $value;
        }

        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($value, $cipher, $this->encryptionKey, 0, $iv);

        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value
     *
     * @param string $encryptedValue Encrypted value (base64 encoded)
     * @return string Decrypted value
     */
    private function decrypt(string $encryptedValue): string
    {
        if (!$this->encryptionKey) {
            return $encryptedValue;
        }

        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);

        $data = base64_decode($encryptedValue);

        if ($data === false) {
            return $encryptedValue; // Not encrypted or invalid
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $cipher, $this->encryptionKey, 0, $iv);

        return $decrypted !== false ? $decrypted : $encryptedValue;
    }

    /**
     * Generate UUID for records
     *
     * @return string UUID
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info("[SpecializedAgentService] {$message}", $context);
        }
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'warning')) {
            $this->logger->warning("[SpecializedAgentService] {$message}", $context);
        }
    }
}

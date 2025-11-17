<?php
declare(strict_types=1);

/**
 * AgentConfigResolver
 * 
 * Resolves and merges agent configuration from database with default configuration.
 * Extracted from ChatHandler to follow Single Responsibility Principle.
 * 
 * @package GPT_Chatbot
 */
class AgentConfigResolver
{
    private array $config;
    private $agentService;

    /**
     * Constructor
     * 
     * @param array $config Application configuration
     * @param object|null $agentService Agent service for database lookups
     */
    public function __construct(array $config, $agentService = null)
    {
        $this->config = $config;
        $this->agentService = $agentService;
    }

    /**
     * Resolve agent configuration overrides from database
     * 
     * @param string|null $agentId Agent ID to resolve (null for default agent)
     * @return array Agent configuration overrides
     */
    public function resolveAgentOverrides($agentId): array
    {
        if (!$this->agentService) {
            return [];
        }
        
        try {
            $agent = null;
            
            // If agent_id provided, try to load it
            if ($agentId) {
                $agent = $this->agentService->getAgent($agentId);
                if (!$agent) {
                    error_log("Agent not found: $agentId, falling back to default");
                }
            }
            
            // If no agent_id provided or agent not found, try default agent
            if (!$agent) {
                $agent = $this->agentService->getDefaultAgent();
                if ($agent) {
                    error_log("Using default agent: " . ($agent['name'] ?? 'unknown'));
                }
            }
            
            // If no agent found, return empty array (fall back to config.php)
            if (!$agent) {
                return [];
            }
            
            $overrides = [];
            
            // Copy all relevant agent configuration fields
            $fields = [
                'api_type', 'prompt_id', 'prompt_version', 'model', 'temperature',
                'top_p', 'max_output_tokens', 'tools', 'vector_store_ids',
                'max_num_results', 'system_message', 'response_format'
            ];
            
            foreach ($fields as $field) {
                if (isset($agent[$field])) {
                    $overrides[$field] = $agent[$field];
                }
            }
            
            // Load active Prompt Builder specification if available
            if (isset($agent['active_prompt_version']) && $agent['active_prompt_version'] !== null) {
                $generatedPrompt = $this->loadActivePromptSpec($agent['id'], $agent['active_prompt_version']);
                if ($generatedPrompt !== null) {
                    // Inject generated prompt as system_message (overrides manual system_message)
                    $overrides['system_message'] = $generatedPrompt;
                    $overrides['_prompt_builder_active'] = true;
                    error_log("Using Prompt Builder specification v{$agent['active_prompt_version']} for agent {$agent['id']}");
                }
            }
            
            return $overrides;
        } catch (Exception $e) {
            error_log("Error resolving agent $agentId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Merge agent overrides with default configuration
     * 
     * @param array $agentOverrides Agent-specific overrides
     * @param array $defaults Default configuration
     * @return array Merged configuration
     */
    public function mergeWithDefaults(array $agentOverrides, array $defaults): array
    {
        return array_merge($defaults, $agentOverrides);
    }

    /**
     * Load active Prompt Builder specification for an agent
     * 
     * @param string $agentId Agent ID
     * @param int $version Version number
     * @return string|null Generated prompt content or null if not found
     */
    private function loadActivePromptSpec($agentId, $version): ?string
    {
        try {
            require_once __DIR__ . '/PromptBuilder/PromptSpecRepository.php';
            require_once __DIR__ . '/DB.php';
            
            // Get database connection
            $dbConfig = [
                'database_url' => $this->config['admin']['database_url'] ?? null,
                'database_path' => $this->config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
            ];
            $db = new DB($dbConfig);
            
            // Load repository
            $repo = new PromptSpecRepository($db->getPdo(), $this->config['prompt_builder'] ?? []);
            
            // Get the version
            $promptData = $repo->getVersion($agentId, $version);
            
            if ($promptData === null) {
                error_log("Prompt Builder version {$version} not found for agent {$agentId}");
                return null;
            }
            
            return $promptData['prompt_md'];
        } catch (Exception $e) {
            error_log("Failed to load Prompt Builder spec for agent {$agentId} v{$version}: " . $e->getMessage());
            return null;
        }
    }
}

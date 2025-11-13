<?php
declare(strict_types=1);

/**
 * Admin Prompt Builder Controller
 * Handles API routes for Prompt Builder feature
 * 
 * Routes:
 * - POST   /api/v1/admin/agents/{agent_id}/prompt-builder/generate
 * - GET    /api/v1/admin/agents/{agent_id}/prompts
 * - GET    /api/v1/admin/agents/{agent_id}/prompts/{version}
 * - POST   /api/v1/admin/agents/{agent_id}/prompts/{version}/activate
 * - POST   /api/v1/admin/agents/{agent_id}/prompts/manual
 * - DELETE /api/v1/admin/agents/{agent_id}/prompts/{version}
 */

require_once __DIR__ . '/../DB.php';
require_once __DIR__ . '/../OpenAIClient.php';
require_once __DIR__ . '/../PIIRedactor.php';
require_once __DIR__ . '/../AuditService.php';
require_once __DIR__ . '/PromptBuilderService.php';
require_once __DIR__ . '/GuardrailLoader.php';
require_once __DIR__ . '/PromptSpecRepository.php';

class AdminPromptBuilderController
{
    private PDO $pdo;
    private array $config;
    private ?AuditService $audit;
    private array $rateLimitStore = [];

    public function __construct(PDO $pdo, array $config, ?AuditService $audit = null)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->audit = $audit;
    }

    /**
     * Route request to appropriate handler
     * 
     * @param string $method HTTP method
     * @param array $pathParts URL path parts
     * @param array $data Request data
     * @param string|null $userId User ID from auth
     * @return array Response data
     */
    public function route(string $method, array $pathParts, array $data, ?string $userId = null): array
    {
        // Expected path format: /api/v1/admin/agents/{agent_id}/...
        // pathParts after /api/v1/admin/agents/ are passed here
        
        if (count($pathParts) < 1) {
            throw new Exception('Invalid path: agent_id required', 400);
        }
        
        $agentId = $pathParts[0];

        $isCatalogRequest = count($pathParts) >= 3
            && $pathParts[1] === 'prompt-builder'
            && $pathParts[2] === 'catalog'
            && $method === 'GET';

        if (!$isCatalogRequest) {
            // Validate agent exists for agent-scoped routes
            $this->validateAgentExists($agentId);

            // Check rate limit scoped to agent operations
            $this->checkRateLimit($userId ?? 'anonymous');
        } else {
            // Apply rate limiting for global catalog requests as well
            $this->checkRateLimit(($userId ?? 'anonymous') . ':catalog');
        }
        
        // Route based on remaining path
        if (count($pathParts) === 2 && $pathParts[1] === 'prompt-builder' && $method === 'POST') {
            // POST /agents/{agent_id}/prompt-builder (shorthand for /prompt-builder/generate)
            return $this->generate($agentId, $data, $userId);
        }
        
        if (count($pathParts) >= 2 && $pathParts[1] === 'prompt-builder') {
            if (count($pathParts) === 3 && $pathParts[2] === 'generate' && $method === 'POST') {
                // POST /agents/{agent_id}/prompt-builder/generate
                return $this->generate($agentId, $data, $userId);
            }
            if ($isCatalogRequest) {
                // GET /agents/{agent_id}/prompt-builder/catalog
                return $this->catalog();
            }
        }
        
        if (count($pathParts) >= 2 && $pathParts[1] === 'prompts') {
            if (count($pathParts) === 2 && $method === 'GET') {
                // GET /agents/{agent_id}/prompts
                return $this->listVersions($agentId);
            }
            if (count($pathParts) === 3 && $pathParts[2] === 'manual' && $method === 'POST') {
                // POST /agents/{agent_id}/prompts/manual
                return $this->saveManual($agentId, $data, $userId);
            }
            if (count($pathParts) === 3 && is_numeric($pathParts[2])) {
                $version = (int)$pathParts[2];
                
                if ($method === 'GET') {
                    // GET /agents/{agent_id}/prompts/{version}
                    return $this->getVersion($agentId, $version);
                }
                if ($method === 'DELETE') {
                    // DELETE /agents/{agent_id}/prompts/{version}
                    return $this->deleteVersion($agentId, $version);
                }
            }
            if (count($pathParts) === 4 && is_numeric($pathParts[2]) && $pathParts[3] === 'activate' && $method === 'POST') {
                // POST /agents/{agent_id}/prompts/{version}/activate
                $version = (int)$pathParts[2];
                return $this->activate($agentId, $version);
            }
            if (count($pathParts) === 3 && $pathParts[2] === 'deactivate' && $method === 'POST') {
                // POST /agents/{agent_id}/prompts/deactivate
                return $this->deactivate($agentId);
            }
        }
        
        throw new Exception('Not found', 404);
    }

    /**
     * Generate a new prompt from an idea
     * 
     * POST /agents/{agent_id}/prompt-builder/generate
     * Body: {idea_text, guardrails?, language?, variables?}
     */
    private function generate(string $agentId, array $data, ?string $userId): array
    {
        // Validate input
        if (empty($data['idea_text'])) {
            throw new InvalidArgumentException('idea_text is required', 400);
        }
        
        $ideaText = trim($data['idea_text']);
        $guardrails = $data['guardrails'] ?? [];
        $language = $data['language'] ?? 'en';
        $variables = $data['variables'] ?? [];
        
        // Create service
        $service = $this->createService();
        
        // Generate
        $startTime = microtime(true);
        $result = $service->generate($agentId, $ideaText, $guardrails, [
            'language' => $language,
            'variables' => $variables,
            'created_by' => $userId,
        ]);
        
        // Audit
        $this->auditEvent('prompt_builder.generated', $agentId, [
            'version' => $result['version'],
            'guardrails' => $result['applied_guardrails'],
            'latency_ms' => $result['latency_ms'],
            'usage' => $result['usage'],
        ], $userId);
        
        return [
            'success' => true,
            'data' => $result,
        ];
    }

    /**
     * List all versions for an agent
     * 
     * GET /agents/{agent_id}/prompts
     */
    private function listVersions(string $agentId): array
    {
        $repo = $this->createRepository();
        $versions = $repo->listVersions($agentId);
        $activeVersion = $repo->getActiveVersionNumber($agentId);
        
        return [
            'success' => true,
            'data' => [
                'versions' => $versions,
                'active_version' => $activeVersion,
            ],
        ];
    }

    /**
     * Get a specific version
     * 
     * GET /agents/{agent_id}/prompts/{version}
     */
    private function getVersion(string $agentId, int $version): array
    {
        $repo = $this->createRepository();
        $data = $repo->getVersion($agentId, $version);
        
        if ($data === null) {
            throw new Exception('Version not found', 404);
        }
        
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Activate a specific version
     * 
     * POST /agents/{agent_id}/prompts/{version}/activate
     */
    private function activate(string $agentId, int $version): array
    {
        $repo = $this->createRepository();
        $repo->activateVersion($agentId, $version);
        
        // Audit
        $this->auditEvent('prompt_builder.activated', $agentId, [
            'version' => $version,
        ]);
        
        return [
            'success' => true,
            'message' => "Version {$version} activated for agent {$agentId}",
        ];
    }

    /**
     * Deactivate prompt for an agent
     * 
     * POST /agents/{agent_id}/prompts/deactivate
     */
    private function deactivate(string $agentId): array
    {
        $repo = $this->createRepository();
        $repo->deactivateVersion($agentId);
        
        // Audit
        $this->auditEvent('prompt_builder.deactivated', $agentId, []);
        
        return [
            'success' => true,
            'message' => "Prompt deactivated for agent {$agentId}",
        ];
    }

    /**
     * Save manually edited prompt as new version
     * 
     * POST /agents/{agent_id}/prompts/manual
     * Body: {prompt_md, guardrails?}
     */
    private function saveManual(string $agentId, array $data, ?string $userId): array
    {
        if (empty($data['prompt_md'])) {
            throw new Exception('prompt_md is required', 400);
        }
        
        $promptMd = $data['prompt_md'];
        $guardrails = $data['guardrails'] ?? [];
        
        // Apply PII redaction if configured
        $redactor = $this->createRedactor();
        if ($redactor) {
            $promptMd = $redactor->redact($promptMd);
        }
        
        $repo = $this->createRepository();
        $version = $repo->createVersion($agentId, $promptMd, $guardrails, $userId);
        
        // Audit
        $this->auditEvent('prompt_builder.manual_save', $agentId, [
            'version' => $version,
        ], $userId);
        
        return [
            'success' => true,
            'data' => [
                'version' => $version,
                'prompt_md' => $promptMd,
            ],
        ];
    }

    /**
     * Delete a specific version
     * 
     * DELETE /agents/{agent_id}/prompts/{version}
     */
    private function deleteVersion(string $agentId, int $version): array
    {
        $repo = $this->createRepository();
        $deleted = $repo->deleteVersion($agentId, $version);
        
        if (!$deleted) {
            throw new Exception('Version not found or could not be deleted', 404);
        }
        
        // Audit
        $this->auditEvent('prompt_builder.deleted', $agentId, [
            'version' => $version,
        ]);
        
        return [
            'success' => true,
            'message' => "Version {$version} deleted",
        ];
    }

    /**
     * Get guardrails catalog
     * 
     * GET /agents/{agent_id}/prompt-builder/catalog
     */
    private function catalog(): array
    {
        $loader = $this->createGuardrailLoader();
        $catalog = $loader->catalog();
        
        return [
            'success' => true,
            'data' => [
                'guardrails' => $catalog,
            ],
        ];
    }

    /**
     * Validate agent exists
     */
    private function validateAgentExists(string $agentId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Agent not found', 404);
        }
    }

    /**
     * Check rate limit
     */
    private function checkRateLimit(string $identifier): void
    {
        $limit = $this->config['prompt_builder']['rate_limit_per_min'] ?? 10;
        $windowSeconds = 60;
        
        $key = 'prompt_builder:' . $identifier;
        $now = time();
        
        // Simple in-memory rate limiting (for production, use Redis or similar)
        if (!isset($this->rateLimitStore[$key])) {
            $this->rateLimitStore[$key] = [];
        }
        
        // Remove old timestamps
        $this->rateLimitStore[$key] = array_filter(
            $this->rateLimitStore[$key],
            fn($ts) => $ts > $now - $windowSeconds
        );
        
        // Check limit
        if (count($this->rateLimitStore[$key]) >= $limit) {
            throw new Exception('Rate limit exceeded. Please try again later.', 429);
        }
        
        // Add current timestamp
        $this->rateLimitStore[$key][] = $now;
    }

    /**
     * Create PromptBuilderService instance
     */
    private function createService(): PromptBuilderService
    {
        $promptBuilderConfig = $this->config['prompt_builder'] ?? [];

        if (empty($promptBuilderConfig['enabled'])) {
            throw new Exception('Prompt Builder is disabled by configuration', 409);
        }

        $openaiConfig = [
            'api_key' => $this->config['openai']['api_key'],
            'organization' => $this->config['openai']['organization'] ?? '',
            'base_url' => $this->config['openai']['base_url'],
        ];

        if (empty($openaiConfig['api_key'])) {
            throw new Exception(
                'Prompt Builder requires an OpenAI API key. Set OPENAI_API_KEY before generating prompts.',
                412
            );
        }

        $client = new OpenAIClient($openaiConfig);
        $loader = $this->createGuardrailLoader();
        $repo = $this->createRepository();
        $redactor = $this->createRedactor();

        return new PromptBuilderService(
            $client,
            $loader,
            $repo,
            $redactor,
            $promptBuilderConfig
        );
    }

    /**
     * Create GuardrailLoader instance
     */
    private function createGuardrailLoader(): GuardrailLoader
    {
        $templatesPath = $this->config['prompt_builder']['templates_path'] 
            ?? __DIR__ . '/templates/guardrails';
        
        return new GuardrailLoader($templatesPath);
    }

    /**
     * Create PromptSpecRepository instance
     */
    private function createRepository(): PromptSpecRepository
    {
        return new PromptSpecRepository($this->pdo, $this->config['prompt_builder'] ?? []);
    }

    /**
     * Create PIIRedactor instance if enabled
     */
    private function createRedactor(): ?PIIRedactor
    {
        // Check if PII redaction is enabled globally or for prompt builder
        $enabled = $this->config['prompt_builder']['pii_redaction'] 
            ?? $this->config['auditing']['pii_redaction_enabled'] 
            ?? false;
        
        if (!$enabled) {
            return null;
        }
        
        $patterns = $this->config['auditing']['pii_redaction_patterns'] ?? '';
        
        return new PIIRedactor(['pii_redaction_patterns' => $patterns]);
    }

    /**
     * Audit an event
     */
    private function auditEvent(string $event, string $agentId, array $metadata = [], ?string $userId = null): void
    {
        if (!$this->audit || empty($this->config['prompt_builder']['audit_enabled'])) {
            return;
        }
        
        try {
            $this->audit->logEvent($event, array_merge($metadata, [
                'agent_id' => $agentId,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]));
        } catch (Exception $e) {
            error_log("Failed to audit event {$event}: " . $e->getMessage());
        }
    }
}

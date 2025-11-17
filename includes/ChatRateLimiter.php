<?php
declare(strict_types=1);

/**
 * ChatRateLimiter
 * 
 * Handles rate limiting and quota management for chat requests.
 * Consolidates legacy IP-based and tenant-based rate limiting.
 * Extracted from ChatHandler to follow Single Responsibility Principle.
 * 
 * @package GPT_Chatbot
 */
class ChatRateLimiter
{
    private array $config;
    private $rateLimitService;
    private $quotaService;

    /**
     * Constructor
     * 
     * @param array $config Application configuration
     * @param object|null $rateLimitService Tenant-based rate limit service
     * @param object|null $quotaService Quota management service
     */
    public function __construct(array $config, $rateLimitService = null, $quotaService = null)
    {
        $this->config = $config;
        $this->rateLimitService = $rateLimitService;
        $this->quotaService = $quotaService;
    }

    /**
     * Check rate limit using legacy method (IP-based)
     * Kept for backwards compatibility with whitelabel integrations
     * 
     * @param array|null $agentConfig Agent configuration
     * @throws Exception if rate limit exceeded
     */
    public function checkRateLimitLegacy($agentConfig = null): void
    {
        // For whitelabel agents without tenant context, use IP + agent as fallback
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Construct a pseudo-tenant ID from IP and agent for backwards compatibility
        if ($agentConfig && isset($agentConfig['agent_public_id'])) {
            $pseudoTenantId = 'whitelabel_' . $agentConfig['agent_public_id'] . '_' . md5($clientIP);
            
            // Get custom rate limits from whitelabel config
            $limit = isset($agentConfig['wl_rate_limit_requests']) && $agentConfig['wl_rate_limit_requests'] > 0
                ? $agentConfig['wl_rate_limit_requests']
                : ($this->config['chat_config']['rate_limit_requests'] ?? 60);
            
            $window = isset($agentConfig['wl_rate_limit_window_seconds']) && $agentConfig['wl_rate_limit_window_seconds'] > 0
                ? $agentConfig['wl_rate_limit_window_seconds']
                : ($this->config['chat_config']['rate_limit_window'] ?? 3600);
        } else {
            // No tenant context, use IP-based fallback
            $pseudoTenantId = 'ip_' . md5($clientIP);
            $limit = $this->config['chat_config']['rate_limit_requests'] ?? 60;
            $window = $this->config['chat_config']['rate_limit_window'] ?? 3600;
        }
        
        // Use TenantRateLimitService if available, otherwise fall back to file-based
        if ($this->rateLimitService) {
            try {
                $this->rateLimitService->enforceRateLimit($pseudoTenantId, 'api_call', $limit, $window);
                return;
            } catch (Exception $e) {
                if ($e->getCode() == 429) {
                    throw $e;
                }
                error_log("Rate limit service error: " . $e->getMessage());
                // Fall through to file-based fallback
            }
        }
        
        // File-based fallback for backwards compatibility
        $this->checkRateLimitFile($pseudoTenantId, $limit, $window);
    }

    /**
     * Check tenant-based rate limit
     * 
     * @param string $tenantId The tenant identifier
     * @param string $resourceType The type of resource (e.g., 'api_call')
     * @param int|null $limit Optional custom limit (uses config default if not provided)
     * @param int|null $window Optional custom window in seconds (uses config default if not provided)
     * @throws Exception if rate limit exceeded
     */
    public function checkRateLimitTenant(string $tenantId, string $resourceType = 'api_call', ?int $limit = null, ?int $window = null): void
    {
        if (!$this->rateLimitService) {
            return; // No rate limit service configured
        }

        $limit = $limit ?? ($this->config['chat_config']['rate_limit_requests'] ?? 60);
        $window = $window ?? ($this->config['chat_config']['rate_limit_window'] ?? 3600);

        try {
            $this->rateLimitService->enforceRateLimit($tenantId, $resourceType, $limit, $window);
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                throw $e;
            }
            error_log("Tenant rate limit error for {$tenantId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check quota for tenant
     * 
     * @param string $tenantId The tenant identifier
     * @param string $resourceType The type of resource (e.g., 'api_calls', 'tokens')
     * @param int $quantity The quantity to check/consume
     * @throws Exception if quota exceeded
     */
    public function checkQuota(string $tenantId, string $resourceType, int $quantity = 1): void
    {
        if (!$this->quotaService) {
            return; // No quota service configured
        }

        try {
            $this->quotaService->checkQuota($tenantId, $resourceType, $quantity);
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                throw $e;
            }
            error_log("Quota check error for {$tenantId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * File-based rate limiting (fallback)
     * 
     * @param string $identifier Unique identifier for rate limiting
     * @param int $limit Maximum number of requests
     * @param int $window Time window in seconds
     * @throws Exception if rate limit exceeded
     */
    private function checkRateLimitFile(string $identifier, int $limit, int $window): void
    {
        $requestsFile = sys_get_temp_dir() . '/chatbot_requests_' . md5($identifier);
        $currentTime = time();
        
        // Read existing requests
        $requests = [];
        if (file_exists($requestsFile)) {
            $content = file_get_contents($requestsFile);
            $requests = json_decode($content, true) ?: [];
        }
        
        // Remove old requests outside the window
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $window) {
            return $currentTime - $timestamp < $window;
        });
        
        // Check if rate limit exceeded
        if (count($requests) >= $limit) {
            throw new Exception('Rate limit exceeded. Please wait before sending another message.', 429);
        }
        
        // Add current request
        $requests[] = $currentTime;
        file_put_contents($requestsFile, json_encode($requests));
    }
}

<?php
/**
 * Unified Chat Handler for Chat Completions and Responses API
 */

class ChatHandler {
    private $config;
    private $openAIClient;
    private $agentService;
    private $auditService;
    private $leadSenseService;
    private $observability;
    private $usageTrackingService;
    private $quotaService;
    private $rateLimitService;
    private $tenantUsageService;

    public function __construct($config, $agentService = null, $auditService = null, $observability = null, $usageTrackingService = null, $quotaService = null, $rateLimitService = null, $tenantUsageService = null) {
        $this->config = $config;
        $this->observability = $observability;
        $this->openAIClient = new OpenAIClient($config['openai'], $auditService, $observability);
        $this->agentService = $agentService;
        $this->auditService = $auditService;
        $this->usageTrackingService = $usageTrackingService;
        $this->quotaService = $quotaService;
        $this->rateLimitService = $rateLimitService;
        $this->tenantUsageService = $tenantUsageService;
        
        // Initialize LeadSense if enabled
        if (isset($config['leadsense']) && ($config['leadsense']['enabled'] ?? false)) {
            require_once __DIR__ . '/LeadSense/LeadSenseService.php';
            $this->leadSenseService = new LeadSenseService($config['leadsense']);
        }
    }
    
    /**
     * Resolve agent configuration overrides
     * 
     * @param string|null $agentId
     * @return array Agent configuration or empty array if not found
     */
    private function resolveAgentOverrides($agentId) {
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
            
            if (isset($agent['api_type'])) {
                $overrides['api_type'] = $agent['api_type'];
            }
            if (isset($agent['prompt_id'])) {
                $overrides['prompt_id'] = $agent['prompt_id'];
            }
            if (isset($agent['prompt_version'])) {
                $overrides['prompt_version'] = $agent['prompt_version'];
            }
            if (isset($agent['model'])) {
                $overrides['model'] = $agent['model'];
            }
            if (isset($agent['temperature'])) {
                $overrides['temperature'] = $agent['temperature'];
            }
            if (isset($agent['top_p'])) {
                $overrides['top_p'] = $agent['top_p'];
            }
            if (isset($agent['max_output_tokens'])) {
                $overrides['max_output_tokens'] = $agent['max_output_tokens'];
            }
            if (isset($agent['tools'])) {
                $overrides['tools'] = $agent['tools'];
            }
            if (isset($agent['vector_store_ids'])) {
                $overrides['vector_store_ids'] = $agent['vector_store_ids'];
            }
            if (isset($agent['max_num_results'])) {
                $overrides['max_num_results'] = $agent['max_num_results'];
            }
            if (isset($agent['system_message'])) {
                $overrides['system_message'] = $agent['system_message'];
            }
            if (isset($agent['response_format'])) {
                $overrides['response_format'] = $agent['response_format'];
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
     * Load active Prompt Builder specification for an agent
     * 
     * @param string $agentId Agent ID
     * @param int $version Version number
     * @return string|null Generated prompt content or null if not found
     */
    private function loadActivePromptSpec($agentId, $version) {
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

    public function validateRequest($message, $conversationId, $fileData = null, $agentConfig = null) {
        // Rate limiting - use legacy method for whitelabel backwards compatibility
        // The proper tenant-based rate limiting is handled in the individual handler methods
        $this->checkRateLimitLegacy($agentConfig);

        // Validate message
        if (empty(trim($message))) {
            throw new Exception('Message cannot be empty', 400);
        }

        if (strlen($message) > $this->config['security']['max_message_length']) {
            throw new Exception('Message too long', 400);
        }

        // Sanitize input if enabled
        if ($this->config['security']['sanitize_input']) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        // Validate conversation ID format
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $conversationId)) {
            throw new Exception('Invalid conversation ID format', 400);
        }

        // Validate file data if provided
        if ($fileData && !$this->config['chat_config']['enable_file_upload']) {
            throw new Exception('File upload not enabled', 400);
        }

        if ($fileData) {
            $this->validateFileData($fileData);
        }
    }

    public function handleChatCompletion($message, $conversationId, $agentId = null, $tenantId = null) {
        // Get tenant ID if not provided
        if (!$tenantId) {
            $tenantId = $this->getTenantId($conversationId);
        }
        
        // Check tenant-based rate limits
        try {
            $this->checkRateLimitTenant($tenantId, 'api_call');
            $this->checkRateLimitTenant($tenantId, 'message');
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                sendSSEEvent('error', [
                    'code' => 429,
                    'message' => $e->getMessage(),
                    'type' => 'rate_limit_exceeded'
                ]);
                return;
            }
            throw $e;
        }
        
        // Check quotas
        try {
            $this->checkQuota($tenantId, 'message', 'daily');
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                sendSSEEvent('error', [
                    'code' => 429,
                    'message' => $e->getMessage(),
                    'type' => 'quota_exceeded'
                ]);
                return;
            }
            throw $e;
        }
        
        // Resolve agent overrides
        $agentOverrides = $this->resolveAgentOverrides($agentId);
        
        // Get conversation history
        $messages = $this->getConversationHistory($conversationId);

        // Apply system message with agent override priority
        $systemMessage = $agentOverrides['system_message'] ?? $this->config['chat']['system_message'];
        if (!empty($systemMessage) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);
        }

        // Add user message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        $charCount = mb_strlen($message ?? '', 'UTF-8');
        $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_MESSAGE, [
            'quantity' => 1,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'api_type' => 'chat',
            'streaming' => true,
            'characters' => $charCount,
            'estimated_tokens' => $this->estimateTokenCount($message),
            'model' => $agentOverrides['model'] ?? $this->config['chat']['model']
        ]);

        // Audit: Record user message
        if ($this->auditService && $this->auditService->isEnabled()) {
            $this->auditService->appendMessage($conversationId, [
                'role' => 'user',
                'content' => $message,
                'request_meta' => [
                    'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
                    'temperature' => $agentOverrides['temperature'] ?? $this->config['chat']['temperature'],
                    'agent_id' => $agentId,
                    'tenant_id' => $tenantId
                ]
            ]);
            
            $this->auditService->recordEvent($conversationId, 'request_start', [
                'api_type' => 'chat',
                'streaming' => true,
                'tenant_id' => $tenantId
            ]);
        }

        // Stream response from OpenAI with agent overrides applied
        $this->streamChatCompletion($messages, $conversationId, $agentOverrides, $tenantId, $agentId);
    }

    public function handleChatCompletionSync($message, $conversationId, $agentId = null, $tenantId = null) {
        // Get tenant ID if not provided
        if (!$tenantId) {
            $tenantId = $this->getTenantId($conversationId);
        }
        
        // Check tenant-based rate limits
        if ($tenantId) {
            try {
                $this->checkRateLimitTenant($tenantId, 'api_call');
                $this->checkRateLimitTenant($tenantId, 'message');
            } catch (Exception $e) {
                if ($e->getCode() == 429) {
                    throw $e;
                }
            }
            
            // Check quotas
            try {
                $this->checkQuota($tenantId, 'message', 'daily');
            } catch (Exception $e) {
                if ($e->getCode() == 429) {
                    throw $e;
                }
            }
        }
        
        // Resolve agent overrides
        $agentOverrides = $this->resolveAgentOverrides($agentId);
        
        $messages = $this->getConversationHistory($conversationId);

        // Apply system message with agent override priority
        $systemMessage = $agentOverrides['system_message'] ?? $this->config['chat']['system_message'];
        if (!empty($systemMessage) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        $charCount = mb_strlen($message ?? '', 'UTF-8');
        $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_MESSAGE, [
            'quantity' => 1,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'api_type' => 'chat',
            'streaming' => false,
            'characters' => $charCount,
            'estimated_tokens' => $this->estimateTokenCount($message),
            'model' => $agentOverrides['model'] ?? $this->config['chat']['model']
        ]);

        // Audit: Record user message
        $startTime = microtime(true);
        if ($this->auditService && $this->auditService->isEnabled()) {
            $this->auditService->appendMessage($conversationId, [
                'role' => 'user',
                'content' => $message,
                'request_meta' => [
                    'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
                    'temperature' => $agentOverrides['temperature'] ?? $this->config['chat']['temperature'],
                    'agent_id' => $agentId
                ]
            ]);
            
            $this->auditService->recordEvent($conversationId, 'request_start', [
                'api_type' => 'chat',
                'streaming' => false
            ]);
        }

        $payload = [
            'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
            'messages' => $messages,
            'temperature' => $agentOverrides['temperature'] ?? $this->config['chat']['temperature'],
            'max_tokens' => $this->config['chat']['max_tokens'],
            'top_p' => $agentOverrides['top_p'] ?? $this->config['chat']['top_p'],
            'frequency_penalty' => $this->config['chat']['frequency_penalty'],
            'presence_penalty' => $this->config['chat']['presence_penalty'],
            'stream' => false
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        $assistantMessage = $response['choices'][0]['message']['content'] ?? '';

        if (!empty($response['usage'])) {
            $usage = $response['usage'];
            $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_COMPLETION, [
                'quantity' => 1,
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'api_type' => 'chat',
                'streaming' => false,
                'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? null
            ]);
        }

        $messages[] = [
            'role' => 'assistant',
            'content' => $assistantMessage
        ];
        
        // Audit: Record assistant message
        if ($this->auditService && $this->auditService->isEnabled()) {
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            
            $assistantMessageId = $this->auditService->appendMessage($conversationId, [
                'role' => 'assistant',
                'content' => $assistantMessage
            ]);
            
            $this->auditService->finalizeMessage($assistantMessageId, [
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? 'stop',
                'latency_ms' => $latencyMs,
                'http_status' => 200
            ]);
            
            $this->auditService->recordEvent($conversationId, 'request_end', [
                'latency_ms' => $latencyMs
            ], $assistantMessageId);
        }

        $this->saveConversationHistory($conversationId, $messages);
        
        // Process with LeadSense
        $this->processLeadSenseTurn(
            $conversationId,
            $message,
            $assistantMessage,
            $agentId,
            [
                'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
                'api_type' => 'chat'
            ]
        );

        return [
            'response' => $assistantMessage
        ];
    }

    /**
     * Stream a Responses API chat reply over SSE.
     *
     * @param string $message
     * @param string $conversationId
     * @param array|null $fileData
     * @param string|null $promptId
     * @param string|null $promptVersion
     * @param array|null $tools Optional Responses tools configuration overrides.
     * @param array|null $responseFormat Optional response_format configuration for structured outputs.
     * @param string|null $agentId Optional agent ID to load configuration from.
     * @param string|null $tenantId Optional tenant ID for multi-tenant rate limiting and quotas.
     */
    public function handleResponsesChat($message, $conversationId, $fileData = null, $promptId = null, $promptVersion = null, $tools = null, $responseFormat = null, $agentId = null, $tenantId = null) {
        // Get tenant ID if not provided
        if (!$tenantId) {
            $tenantId = $this->getTenantId($conversationId);
        }
        
        // Check tenant-based rate limits
        try {
            $this->checkRateLimitTenant($tenantId, 'api_call');
            $this->checkRateLimitTenant($tenantId, 'message');
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                sendSSEEvent('error', [
                    'code' => 429,
                    'message' => $e->getMessage(),
                    'type' => 'rate_limit_exceeded'
                ]);
                return;
            }
            throw $e;
        }
        
        // Check quotas
        try {
            $this->checkQuota($tenantId, 'message', 'daily');
        } catch (Exception $e) {
            if ($e->getCode() == 429) {
                sendSSEEvent('error', [
                    'code' => 429,
                    'message' => $e->getMessage(),
                    'type' => 'quota_exceeded'
                ]);
                return;
            }
            throw $e;
        }
        
        // Resolve agent overrides (agent > config.php)
        $agentOverrides = $this->resolveAgentOverrides($agentId);
        
        $messages = $this->getConversationHistory($conversationId);
        $responsesConfig = $this->config['responses'];

        // Merge precedence: request > agent > config
        $promptIdOverride = $this->normalizePromptValue($promptId);
        $promptVersionOverride = $this->normalizePromptValue($promptVersion);
        $agentPromptId = isset($agentOverrides['prompt_id']) ? $this->normalizePromptValue($agentOverrides['prompt_id']) : null;
        $agentPromptVersion = isset($agentOverrides['prompt_version']) ? $this->normalizePromptValue($agentOverrides['prompt_version']) : null;
        $configuredPromptId = $this->normalizePromptValue($responsesConfig['prompt_id'] ?? null);
        $configuredPromptVersion = $this->normalizePromptValue($responsesConfig['prompt_version'] ?? null);

        $effectivePromptId = $promptIdOverride ?? $agentPromptId ?? $configuredPromptId;
        $effectivePromptVersion = $promptVersionOverride ?? $agentPromptVersion ?? $configuredPromptVersion;

        // Apply system message with precedence
        $systemMessage = $agentOverrides['system_message'] ?? $responsesConfig['system_message'];
        if (!empty($systemMessage) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);
        }

        $fileIds = [];
        if ($fileData) {
            $fileIds = $this->uploadFiles($fileData, 'user_data', $tenantId, $conversationId, $agentId);
        }

        $userMessage = [
            'role' => 'user',
            'content' => $message,
        ];

        if (!empty($fileIds)) {
            $userMessage['file_ids'] = $fileIds;
        }

        $messages[] = $userMessage;

        $fileCount = count($fileIds);
        $charCount = mb_strlen($message ?? '', 'UTF-8');
        $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_MESSAGE, [
            'quantity' => 1,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'api_type' => 'responses',
            'streaming' => true,
            'characters' => $charCount,
            'estimated_tokens' => $this->estimateTokenCount($message),
            'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
            'file_count' => $fileCount,
            'has_files' => $fileCount > 0,
            'file_ids' => $fileIds,
            'prompt_id' => $effectivePromptId,
            'prompt_version' => $effectivePromptVersion
        ]);
        
        // Audit: Record user message
        $userMessageId = null;
        $startTime = microtime(true);
        if ($this->auditService && $this->auditService->isEnabled()) {
            $userMessageId = $this->auditService->appendMessage($conversationId, [
                'role' => 'user',
                'content' => $message,
                'attachments' => !empty($fileIds) ? ['file_ids' => $fileIds] : null,
                'request_meta' => [
                    'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                    'prompt_id' => $effectivePromptId,
                    'prompt_version' => $effectivePromptVersion,
                    'temperature' => $agentOverrides['temperature'] ?? $responsesConfig['temperature'],
                    'agent_id' => $agentId
                ]
            ]);
            
            // Record request start event
            $this->auditService->recordEvent($conversationId, 'request_start', [
                'api_type' => 'responses',
                'streaming' => true
            ], $userMessageId);
        }

        $payload = [
            'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
            'input' => $this->formatMessagesForResponses($messages),
            'temperature' => $agentOverrides['temperature'] ?? $responsesConfig['temperature'],
            'top_p' => $agentOverrides['top_p'] ?? $responsesConfig['top_p'],
            'max_output_tokens' => $agentOverrides['max_output_tokens'] ?? $responsesConfig['max_output_tokens'],
            'stream' => true,
        ];

        $tooling = $this->resolveResponsesTooling();
        $configTools = $tooling['tools'];
        
        // Merge agent tools if present
        $agentTools = isset($agentOverrides['tools']) ? $this->normalizeTools($agentOverrides['tools']) : [];
        if (!empty($agentTools)) {
            $configTools = $this->mergeTools($configTools, $agentTools, false);
        }
        
        $overrideProvided = $tools !== null;

        $requestTools = [];
        if ($overrideProvided && is_array($tools)) {
            $requestTools = $this->normalizeTools($tools);
        }

        // Merge precedence: request > agent+config
        $mergedTools = $this->mergeTools($configTools, $requestTools, $overrideProvided);
        
        // Apply agent vector_store_ids if present
        $vectorStoreIds = $agentOverrides['vector_store_ids'] ?? $tooling['default_vector_store_ids'];
        $maxNumResults = $agentOverrides['max_num_results'] ?? $tooling['default_max_num_results'];
        
        $mergedTools = $this->applyFileSearchDefaults($mergedTools, $vectorStoreIds, $maxNumResults);

        if (!empty($mergedTools)) {
            $payload['tools'] = $mergedTools;
        }

        if ($effectivePromptId !== null) {
            $prompt = ['id' => $effectivePromptId];
            if ($effectivePromptVersion !== null) {
                $prompt['version'] = $effectivePromptVersion;
            }
            $payload['prompt'] = $prompt;
        }

        // Apply response_format with precedence: request > agent > config
        $effectiveResponseFormat = null;
        if ($responseFormat !== null && is_array($responseFormat)) {
            $effectiveResponseFormat = $responseFormat;
        } elseif (isset($agentOverrides['response_format']) && is_array($agentOverrides['response_format'])) {
            $effectiveResponseFormat = $agentOverrides['response_format'];
        } elseif (isset($responsesConfig['response_format']) && is_array($responsesConfig['response_format'])) {
            $effectiveResponseFormat = $responsesConfig['response_format'];
        }

        if ($effectiveResponseFormat !== null) {
            $payload['response_format'] = $effectiveResponseFormat;
        }

        $messageStarted = false;
        $fullResponse = '';
        $responseId = null;
        $toolCalls = [];
        $assistantMessageId = null;
        $auditService = $this->auditService;
        $leadSenseService = $this->leadSenseService;
        $chatHandler = $this;

        $streamFn = function($p) use (&$messages, $conversationId, &$messageStarted, &$fullResponse, &$responseId, &$toolCalls, &$assistantMessageId, $auditService, $startTime, $leadSenseService, $chatHandler, $agentId, $message, $agentOverrides, $responsesConfig, $effectivePromptId, $effectivePromptVersion, $tenantId) {
            $this->openAIClient->streamResponse($p, function($event) use (&$messages, $conversationId, &$messageStarted, &$fullResponse, &$responseId, &$toolCalls, &$assistantMessageId, $auditService, $startTime, $leadSenseService, $chatHandler, $agentId, $message, $agentOverrides, $responsesConfig, $effectivePromptId, $effectivePromptVersion, $tenantId) {
            if (!isset($event['type'])) {
                return;
            }

            $eventType = $event['type'];

            if ($eventType === 'response.created') {
                $responseId = $event['response']['id'] ?? ($event['id'] ?? $responseId);
                if (!$messageStarted) {
                    sendSSEEvent('message', [
                        'type' => 'start',
                        'response_id' => $responseId
                    ]);
                    $messageStarted = true;
                    
                    // Audit: Stream start event
                    if ($auditService && $auditService->isEnabled()) {
                        $auditService->recordEvent($conversationId, 'stream_start', [
                            'response_id' => $responseId
                        ]);
                    }
                }
                return;
            }

            if (strpos($eventType, 'response.output_text.delta') === 0 || strpos($eventType, 'response.refusal.delta') === 0) {
                $chunk = $this->extractTextDelta($event);
                if ($chunk !== '') {
                    if (!$messageStarted) {
                        $responseId = $event['response']['id'] ?? $responseId;
                        sendSSEEvent('message', [
                            'type' => 'start',
                            'response_id' => $responseId
                        ]);
                        $messageStarted = true;
                    }

                    $fullResponse .= $chunk;

                    sendSSEEvent('message', [
                        'type' => 'chunk',
                        'content' => $chunk,
                        'response_id' => $responseId
                    ]);
                }
                return;
            }

            if (strpos($eventType, 'response.output_tool_call.delta') === 0) {
                $delta = $event['delta'] ?? [];
                $callId = $delta['id'] ?? $delta['call_id'] ?? null;
                if (!$callId && isset($delta['tool_call_id'])) {
                    $callId = $delta['tool_call_id'];
                }

                if ($callId) {
                    if (!isset($toolCalls[$callId])) {
                        $toolCalls[$callId] = [
                            'tool_name' => $delta['tool_name'] ?? $delta['name'] ?? ($delta['function']['name'] ?? ''),
                            'arguments' => ''
                        ];
                    }

                    if (isset($delta['tool_name']) || isset($delta['name']) || isset($delta['function']['name'])) {
                        $toolCalls[$callId]['tool_name'] = $delta['tool_name'] ?? $delta['name'] ?? ($delta['function']['name'] ?? $toolCalls[$callId]['tool_name']);
                    }

                    if (isset($delta['arguments'])) {
                        $toolCalls[$callId]['arguments'] .= $delta['arguments'];
                    } elseif (isset($delta['function']['arguments'])) {
                        $toolCalls[$callId]['arguments'] .= $delta['function']['arguments'];
                    }

                    sendSSEEvent('message', [
                        'type' => 'tool_call',
                        'tool_name' => $toolCalls[$callId]['tool_name'],
                        'arguments' => $toolCalls[$callId]['arguments'],
                        'call_id' => $callId
                    ]);
                    
                    // Audit: Tool call event
                    if ($auditService && $auditService->isEnabled() && $assistantMessageId) {
                        $auditService->recordEvent($conversationId, 'tool_call', [
                            'tool_name' => $toolCalls[$callId]['tool_name'],
                            'arguments' => $toolCalls[$callId]['arguments'],
                            'call_id' => $callId
                        ], $assistantMessageId);
                    }
                }
                return;
            }

            if ($eventType === 'response.required_action') {
                $responseId = $event['response']['id'] ?? $responseId;
                $toolCallsData = $event['response']['required_action']['submit_tool_outputs']['tool_calls'] ?? [];

                if (!empty($toolCallsData)) {
                    $toolOutputs = [];
                    foreach ($toolCallsData as $toolCall) {
                        $result = $this->executeTool($toolCall, $tenantId, $conversationId, $agentId, $responseId);
                        $toolOutputs[] = [
                            'tool_call_id' => $toolCall['id'] ?? ($toolCall['call_id'] ?? ''),
                            'output' => is_string($result) ? $result : json_encode($result)
                        ];

                        sendSSEEvent('message', [
                            'type' => 'tool_call',
                            'tool_name' => $toolCall['name'] ?? ($toolCall['function']['name'] ?? ''),
                            'arguments' => $toolCall['arguments'] ?? ($toolCall['function']['arguments'] ?? ''),
                            'call_id' => $toolCall['id'] ?? ($toolCall['call_id'] ?? ''),
                            'status' => 'completed'
                        ]);
                    }

                    if (!empty($toolOutputs) && $responseId) {
                        try {
                            $this->openAIClient->submitResponseToolOutputs($responseId, $toolOutputs);
                        } catch (Exception $e) {
                            sendSSEEvent('error', [
                                'message' => 'Failed to submit tool outputs',
                                'code' => 'TOOL_OUTPUT_ERROR'
                            ]);
                        }
                    }
                }
                return;
            }

            if ($eventType === 'response.completed') {
                $finishReason = $event['response']['status'] ?? 'completed';
                if ($fullResponse !== '') {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $fullResponse
                    ];
                    
                    // Audit: Record assistant message
                    if ($auditService && $auditService->isEnabled()) {
                        $latencyMs = round((microtime(true) - $startTime) * 1000);
                        
                        $assistantMessageId = $auditService->appendMessage($conversationId, [
                            'role' => 'assistant',
                            'content' => $fullResponse
                        ]);
                        
                        // Finalize with response metadata
                        $auditService->finalizeMessage($assistantMessageId, [
                            'response_id' => $responseId,
                            'finish_reason' => $finishReason,
                            'latency_ms' => $latencyMs,
                            'http_status' => 200
                        ]);
                        
                        // Record stream end event
                        $auditService->recordEvent($conversationId, 'stream_end', [
                            'response_id' => $responseId,
                            'finish_reason' => $finishReason,
                            'latency_ms' => $latencyMs
                        ], $assistantMessageId);
                    }
                }

                $usage = $event['response']['usage'] ?? null;
                $chatHandler->trackUsage($tenantId, UsageTrackingService::RESOURCE_COMPLETION, [
                    'quantity' => 1,
                    'conversation_id' => $conversationId,
                    'agent_id' => $agentId,
                    'api_type' => 'responses',
                    'streaming' => true,
                    'response_id' => $responseId,
                    'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                    'total_tokens' => $usage['total_tokens'] ?? null,
                    'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                    'completion_tokens' => $usage['completion_tokens'] ?? null,
                    'finish_reason' => $finishReason,
                    'prompt_id' => $effectivePromptId,
                    'prompt_version' => $effectivePromptVersion
                ]);

                $this->saveConversationHistory($conversationId, $messages);
                
                // Process with LeadSense after stream ends
                if ($leadSenseService && $leadSenseService->isEnabled() && $fullResponse !== '') {
                    $chatHandler->processLeadSenseTurn(
                        $conversationId,
                        $message,
                        $fullResponse,
                        $agentId,
                        [
                            'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                            'prompt_id' => $effectivePromptId,
                            'api_type' => 'responses',
                            'response_id' => $responseId
                        ]
                    );
                }

                sendSSEEvent('message', [
                    'type' => 'done',
                    'finish_reason' => $finishReason,
                    'response_id' => $responseId
                ]);

                // Reset state for potential follow-up events
                $messageStarted = false;
                $fullResponse = '';
                $responseId = null;
                $toolCalls = [];
                $assistantMessageId = null;
                return;
            }

            if ($eventType === 'response.error' || $eventType === 'response.failed') {
                $errorMessage = $event['error']['message'] ?? ($event['response']['error']['message'] ?? 'Unknown error');
                sendSSEEvent('error', [
                    'message' => $errorMessage,
                    'code' => $event['error']['code'] ?? 'RESPONSE_ERROR'
                ]);
                return;
            }
        });
        };

        try {
            $streamFn($payload);
        } catch (Exception $e) {
            $err = $e->getMessage();
            $hasPrompt = isset($payload['prompt']);
            $clientErr = (strpos($err, 'HTTP 400') !== false) || (strpos($err, 'HTTP 404') !== false) || (strpos($err, 'HTTP 422') !== false);

            // Retry without prompt if prompt reference likely invalid
            if ($hasPrompt && $clientErr) {
                sendSSEEvent('message', [ 'type' => 'notice', 'message' => 'Prompt unavailable. Retrying without prompt.' ]);
                
                // Audit: Fallback event
                if ($this->auditService && $this->auditService->isEnabled()) {
                    $this->auditService->recordEvent($conversationId, 'fallback_applied', [
                        'reason' => 'prompt_removed',
                        'original_error' => $err
                    ]);
                }
                
                unset($payload['prompt']);
                try {
                    $streamFn($payload);
                    return;
                } catch (Exception $inner) {
                    // Fall through to model fallback below
                    $err = $inner->getMessage();
                    $clientErr = (strpos($err, 'HTTP 400') !== false) || (strpos($err, 'HTTP 404') !== false) || (strpos($err, 'HTTP 422') !== false);
                }
            }

            // Retry with a safe model if model might be invalid
            if ($clientErr && isset($payload['model']) && $payload['model'] !== 'gpt-4o-mini') {
                sendSSEEvent('message', [ 'type' => 'notice', 'message' => 'Model unsupported. Falling back to gpt-4o-mini.' ]);
                
                // Audit: Fallback event
                if ($this->auditService && $this->auditService->isEnabled()) {
                    $this->auditService->recordEvent($conversationId, 'fallback_applied', [
                        'reason' => 'model_downgraded',
                        'original_model' => $payload['model'],
                        'fallback_model' => 'gpt-4o-mini',
                        'original_error' => $err
                    ]);
                }
                
                $payload['model'] = 'gpt-4o-mini';
                $streamFn($payload);
                return;
            }

            throw $e;
        }
    }

    /**
     * Execute a synchronous Responses API request and return the JSON payload.
     *
     * @param string $message
     * @param string $conversationId
     * @param array|null $fileData
     * @param string|null $promptId
     * @param string|null $promptVersion
     * @param array|null $tools Optional Responses tools configuration overrides.
     * @param array|null $responseFormat Optional response_format configuration for structured outputs.
     * @param string|null $agentId Optional agent ID to load configuration from.
     *
     * @return array
     */
    public function handleResponsesChatSync($message, $conversationId, $fileData = null, $promptId = null, $promptVersion = null, $tools = null, $responseFormat = null, $agentId = null, $tenantId = null) {
        // Get tenant ID if not provided
        if (!$tenantId) {
            $tenantId = $this->getTenantId($conversationId);
        }
        
        // Check tenant-based rate limits
        if ($tenantId) {
            try {
                $this->checkRateLimitTenant($tenantId, 'api_call');
                $this->checkRateLimitTenant($tenantId, 'message');
            } catch (Exception $e) {
                if ($e->getCode() == 429) {
                    throw $e;
                }
            }
            
            // Check quotas
            try {
                $this->checkQuota($tenantId, 'message', 'daily');
            } catch (Exception $e) {
                if ($e->getCode() == 429) {
                    throw $e;
                }
            }
        }
        
        // Resolve agent overrides (agent > config.php)
        $agentOverrides = $this->resolveAgentOverrides($agentId);
        
        $messages = $this->getConversationHistory($conversationId);
        $responsesConfig = $this->config['responses'];

        // Merge precedence: request > agent > config
        $promptIdOverride = $this->normalizePromptValue($promptId);
        $promptVersionOverride = $this->normalizePromptValue($promptVersion);
        $agentPromptId = isset($agentOverrides['prompt_id']) ? $this->normalizePromptValue($agentOverrides['prompt_id']) : null;
        $agentPromptVersion = isset($agentOverrides['prompt_version']) ? $this->normalizePromptValue($agentOverrides['prompt_version']) : null;
        $configuredPromptId = $this->normalizePromptValue($responsesConfig['prompt_id'] ?? null);
        $configuredPromptVersion = $this->normalizePromptValue($responsesConfig['prompt_version'] ?? null);

        $effectivePromptId = $promptIdOverride ?? $agentPromptId ?? $configuredPromptId;
        $effectivePromptVersion = $promptVersionOverride ?? $agentPromptVersion ?? $configuredPromptVersion;

        // Apply system message with precedence
        $systemMessage = $agentOverrides['system_message'] ?? $responsesConfig['system_message'];
        if (!empty($systemMessage) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);
        }

        $fileIds = [];
        if ($fileData) {
            $fileIds = $this->uploadFiles($fileData, 'user_data', $tenantId, $conversationId, $agentId);
        }

        $userMessage = [
            'role' => 'user',
            'content' => $message,
        ];

        if (!empty($fileIds)) {
            $userMessage['file_ids'] = $fileIds;
        }

        $messages[] = $userMessage;

        $fileCount = count($fileIds);
        $charCount = mb_strlen($message ?? '', 'UTF-8');
        $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_MESSAGE, [
            'quantity' => 1,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'api_type' => 'responses',
            'streaming' => false,
            'characters' => $charCount,
            'estimated_tokens' => $this->estimateTokenCount($message),
            'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
            'file_count' => $fileCount,
            'has_files' => $fileCount > 0,
            'file_ids' => $fileIds,
            'prompt_id' => $effectivePromptId,
            'prompt_version' => $effectivePromptVersion
        ]);

        // Audit: Record user message
        $startTime = microtime(true);
        if ($this->auditService && $this->auditService->isEnabled()) {
            $this->auditService->appendMessage($conversationId, [
                'role' => 'user',
                'content' => $message,
                'attachments' => !empty($fileIds) ? ['file_ids' => $fileIds] : null,
                'request_meta' => [
                    'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                    'prompt_id' => $effectivePromptId,
                    'prompt_version' => $effectivePromptVersion,
                    'temperature' => $agentOverrides['temperature'] ?? $responsesConfig['temperature'],
                    'agent_id' => $agentId
                ]
            ]);
            
            $this->auditService->recordEvent($conversationId, 'request_start', [
                'api_type' => 'responses',
                'streaming' => false
            ]);
        }

        $payload = [
            'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
            'input' => $this->formatMessagesForResponses($messages),
            'temperature' => $agentOverrides['temperature'] ?? $responsesConfig['temperature'],
            'top_p' => $agentOverrides['top_p'] ?? $responsesConfig['top_p'],
            'max_output_tokens' => $agentOverrides['max_output_tokens'] ?? $responsesConfig['max_output_tokens'],
            'stream' => false,
        ];

        $tooling = $this->resolveResponsesTooling();
        $configTools = $tooling['tools'];
        
        // Merge agent tools if present
        $agentTools = isset($agentOverrides['tools']) ? $this->normalizeTools($agentOverrides['tools']) : [];
        if (!empty($agentTools)) {
            $configTools = $this->mergeTools($configTools, $agentTools, false);
        }
        
        $overrideProvided = $tools !== null;

        $requestTools = [];
        if ($overrideProvided && is_array($tools)) {
            $requestTools = $this->normalizeTools($tools);
        }

        // Merge precedence: request > agent+config
        $mergedTools = $this->mergeTools($configTools, $requestTools, $overrideProvided);
        
        // Apply agent vector_store_ids if present
        $vectorStoreIds = $agentOverrides['vector_store_ids'] ?? $tooling['default_vector_store_ids'];
        $maxNumResults = $agentOverrides['max_num_results'] ?? $tooling['default_max_num_results'];
        
        $mergedTools = $this->applyFileSearchDefaults($mergedTools, $vectorStoreIds, $maxNumResults);

        if (!empty($mergedTools)) {
            $payload['tools'] = $mergedTools;
        }

        if ($effectivePromptId !== null) {
            $prompt = ['id' => $effectivePromptId];
            if ($effectivePromptVersion !== null) {
                $prompt['version'] = $effectivePromptVersion;
            }
            $payload['prompt'] = $prompt;
        }

        // Apply response_format with precedence: request > agent > config
        $effectiveResponseFormat = null;
        if ($responseFormat !== null && is_array($responseFormat)) {
            $effectiveResponseFormat = $responseFormat;
        } elseif (isset($agentOverrides['response_format']) && is_array($agentOverrides['response_format'])) {
            $effectiveResponseFormat = $agentOverrides['response_format'];
        } elseif (isset($responsesConfig['response_format']) && is_array($responsesConfig['response_format'])) {
            $effectiveResponseFormat = $responsesConfig['response_format'];
        }

        if ($effectiveResponseFormat !== null) {
            $payload['response_format'] = $effectiveResponseFormat;
        }

        $execute = function(array $payload) use (&$messages, $conversationId, $startTime, $tenantId, $agentId, $message, $agentOverrides, $responsesConfig, $effectivePromptId, $effectivePromptVersion) {
            $response = $this->openAIClient->createResponse($payload);

            $usage = $response['usage'] ?? null;
            $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_COMPLETION, [
                'quantity' => 1,
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'api_type' => 'responses',
                'streaming' => false,
                'response_id' => $response['id'] ?? null,
                'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                'total_tokens' => $usage['total_tokens'] ?? null,
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'finish_reason' => $response['status'] ?? null,
                'prompt_id' => $effectivePromptId,
                'prompt_version' => $effectivePromptVersion
            ]);

            $assistantMessage = $this->extractResponseOutputText($response);

            if ($assistantMessage !== '') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $assistantMessage
                ];
                
                // Audit: Record assistant message
                if ($this->auditService && $this->auditService->isEnabled()) {
                    $latencyMs = round((microtime(true) - $startTime) * 1000);
                    
                    $assistantMessageId = $this->auditService->appendMessage($conversationId, [
                        'role' => 'assistant',
                        'content' => $assistantMessage
                    ]);
                    
                    $this->auditService->finalizeMessage($assistantMessageId, [
                        'response_id' => $response['id'] ?? null,
                        'finish_reason' => $response['status'] ?? 'completed',
                        'latency_ms' => $latencyMs,
                        'http_status' => 200
                    ]);
                    
                    $this->auditService->recordEvent($conversationId, 'request_end', [
                        'response_id' => $response['id'] ?? null,
                        'latency_ms' => $latencyMs
                    ], $assistantMessageId);
                }
            }

            $this->saveConversationHistory($conversationId, $messages);
            
            // Process with LeadSense
            $this->processLeadSenseTurn(
                $conversationId,
                $message,
                $assistantMessage,
                $agentId,
                [
                    'model' => $agentOverrides['model'] ?? $responsesConfig['model'],
                    'prompt_id' => $effectivePromptId,
                    'api_type' => 'responses'
                ]
            );

            return [
                'response' => $assistantMessage,
                'response_id' => $response['id'] ?? null,
            ];
        };

        try {
            return $execute($payload);
        } catch (Exception $e) {
            $err = $e->getMessage();
            $hasPrompt = isset($payload['prompt']);
            $clientErr = (strpos($err, 'HTTP 400') !== false) || (strpos($err, 'HTTP 404') !== false) || (strpos($err, 'HTTP 422') !== false);

            if ($hasPrompt && $clientErr) {
                // Audit: Fallback event
                if ($this->auditService && $this->auditService->isEnabled()) {
                    $this->auditService->recordEvent($conversationId, 'fallback_applied', [
                        'reason' => 'prompt_removed',
                        'original_error' => $err
                    ]);
                }
                
                unset($payload['prompt']);
                try {
                    return $execute($payload);
                } catch (Exception $inner) {
                    $err = $inner->getMessage();
                    $clientErr = (strpos($err, 'HTTP 400') !== false) || (strpos($err, 'HTTP 404') !== false) || (strpos($err, 'HTTP 422') !== false);
                }
            }

            if ($clientErr && isset($payload['model']) && $payload['model'] !== 'gpt-4o-mini') {
                // Audit: Fallback event
                if ($this->auditService && $this->auditService->isEnabled()) {
                    $this->auditService->recordEvent($conversationId, 'fallback_applied', [
                        'reason' => 'model_downgraded',
                        'original_model' => $payload['model'],
                        'fallback_model' => 'gpt-4o-mini',
                        'original_error' => $err
                    ]);
                }
                
                $payload['model'] = 'gpt-4o-mini';
                return $execute($payload);
            }

            throw $e;
        }
    }

    private function streamChatCompletion($messages, $conversationId, $agentOverrides = [], $tenantId = null, $agentId = null) {
        $payload = [
            'model' => $agentOverrides['model'] ?? $this->config['chat']['model'],
            'messages' => $messages,
            'temperature' => $agentOverrides['temperature'] ?? $this->config['chat']['temperature'],
            'max_tokens' => $this->config['chat']['max_tokens'],
            'top_p' => $agentOverrides['top_p'] ?? $this->config['chat']['top_p'],
            'frequency_penalty' => $this->config['chat']['frequency_penalty'],
            'presence_penalty' => $this->config['chat']['presence_penalty'],
            'stream' => true
        ];

        $messageStarted = false;
        $fullResponse = '';
        $startTime = microtime(true);
        $auditService = $this->auditService;
        $leadSenseService = $this->leadSenseService;
        $chatHandler = $this;
        $usageData = null;

        $this->openAIClient->streamChatCompletion($payload, function($chunk) use (&$messageStarted, &$fullResponse, $conversationId, $messages, $startTime, $auditService, $leadSenseService, $chatHandler, $agentOverrides, $tenantId, &$usageData, $agentId) {
            $messagesCopy = $messages;

            if (isset($chunk['choices'][0]['delta']['content'])) {
                if (!$messageStarted) {
                    sendSSEEvent('message', ['type' => 'start']);
                    $messageStarted = true;
                    
                    // Audit: Stream start event
                    if ($auditService && $auditService->isEnabled()) {
                        $auditService->recordEvent($conversationId, 'stream_start', []);
                    }
                }

                $content = $chunk['choices'][0]['delta']['content'];
                $fullResponse .= $content;

                sendSSEEvent('message', [
                    'type' => 'chunk',
                    'content' => $content
                ]);
            }
            
            // Capture usage data from chunk
            if (isset($chunk['usage'])) {
                $usageData = $chunk['usage'];
            }

            if (isset($chunk['choices'][0]['finish_reason'])) {
                $messagesCopy[] = [
                    'role' => 'assistant',
                    'content' => $fullResponse
                ];
                
                // Audit: Record assistant message
                if ($auditService && $auditService->isEnabled()) {
                    $latencyMs = round((microtime(true) - $startTime) * 1000);
                    
                    $assistantMessageId = $auditService->appendMessage($conversationId, [
                        'role' => 'assistant',
                        'content' => $fullResponse
                    ]);
                    
                    $auditService->finalizeMessage($assistantMessageId, [
                        'finish_reason' => $chunk['choices'][0]['finish_reason'],
                        'latency_ms' => $latencyMs,
                        'http_status' => 200
                    ]);
                    
                    $auditService->recordEvent($conversationId, 'stream_end', [
                        'finish_reason' => $chunk['choices'][0]['finish_reason'],
                        'latency_ms' => $latencyMs
                    ], $assistantMessageId);
                }
                
                $this->saveConversationHistory($conversationId, $messagesCopy);
                
                // Track usage for billing
                if ($tenantId) {
                    $chatHandler->trackUsage($tenantId, UsageTrackingService::RESOURCE_COMPLETION, [
                        'quantity' => 1,
                        'conversation_id' => $conversationId,
                        'agent_id' => $agentId,
                        'api_type' => 'chat',
                        'streaming' => true,
                        'model' => $agentOverrides['model'] ?? $chatHandler->config['chat']['model'],
                        'total_tokens' => $usageData['total_tokens'] ?? null,
                        'prompt_tokens' => $usageData['prompt_tokens'] ?? null,
                        'completion_tokens' => $usageData['completion_tokens'] ?? null,
                        'finish_reason' => $chunk['choices'][0]['finish_reason'] ?? null
                    ]);
                }
                
                // Process with LeadSense after stream ends
                if ($leadSenseService && $leadSenseService->isEnabled()) {
                    // Extract user message (last user message before assistant)
                    $userMessage = '';
                    for ($i = count($messagesCopy) - 2; $i >= 0; $i--) {
                        if (isset($messagesCopy[$i]['role']) && $messagesCopy[$i]['role'] === 'user') {
                            $userMessage = $messagesCopy[$i]['content'] ?? '';
                            break;
                        }
                    }
                    
                    $chatHandler->processLeadSenseTurn(
                        $conversationId,
                        $userMessage,
                        $fullResponse,
                        null, // agentId not available in this context
                        [
                            'model' => $agentOverrides['model'] ?? $chatHandler->config['chat']['model'],
                            'api_type' => 'chat'
                        ]
                    );
                }

                sendSSEEvent('message', [
                    'type' => 'done',
                    'finish_reason' => $chunk['choices'][0]['finish_reason']
                ]);
            }
        });
    }

    private function formatMessagesForResponses(array $messages) {
        $formatted = [];

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $role = $message['role'];

            $type = 'input_text';
            if ($role === 'assistant') {
                $type = 'output_text';
            } elseif ($role === 'tool') {
                $type = 'tool_result';
            }

            $formattedMessage = [
                'role' => $role,
                'content' => [
                    [
                        'type' => $type,
                        'text' => $content
                    ]
                ]
            ];

            if (!empty($message['file_ids'])) {
                $formattedMessage['attachments'] = array_map(function($fileId) {
                    return [
                        'file_id' => $fileId
                    ];
                }, $message['file_ids']);
            }

            $formatted[] = $formattedMessage;
        }

        return $formatted;
    }

    private function resolveResponsesTooling(): array {
        $config = $this->config['responses'] ?? [];

        $defaultVectorStoreIds = $this->sanitizeVectorStoreIds($config['default_vector_store_ids'] ?? []);
        $defaultMaxNumResults = $this->sanitizeMaxNumResults($config['default_max_num_results'] ?? null);

        $rawTools = [];

        if (!empty($config['default_tools']) && is_array($config['default_tools'])) {
            $rawTools = $config['default_tools'];
        } elseif (!empty($config['tools']) && is_array($config['tools'])) {
            // Backwards compatibility for older configs referencing 'tools'
            $rawTools = $config['tools'];
        }

        $normalized = [];
        if (!empty($rawTools)) {
            $normalized = $this->normalizeTools($rawTools);
        }

        if (empty($normalized)) {
            $autoTool = $this->buildDefaultFileSearchTool($defaultVectorStoreIds, $defaultMaxNumResults);
            if ($autoTool !== null) {
                $normalized[] = $autoTool;
            }
        } else {
            $normalized = $this->applyFileSearchDefaults($normalized, $defaultVectorStoreIds, $defaultMaxNumResults);
        }

        return [
            'tools' => $normalized,
            'default_vector_store_ids' => $defaultVectorStoreIds,
            'default_max_num_results' => $defaultMaxNumResults,
        ];
    }

    private function sanitizeVectorStoreIds($ids): array {
        if (!is_array($ids)) {
            return [];
        }

        $clean = [];

        foreach ($ids as $id) {
            if (!is_string($id) && !is_numeric($id)) {
                continue;
            }

            $value = trim((string)$id);
            if ($value === '') {
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/', $value)) {
                continue;
            }

            $clean[] = $value;
        }

        return array_values(array_unique($clean));
    }

    private function sanitizeMaxNumResults($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 200,
            ],
        ]);

        if ($filtered === false) {
            return null;
        }

        return (int)$filtered;
    }

    private function buildDefaultFileSearchTool(array $vectorStoreIds, ?int $maxNumResults): ?array {
        if (empty($vectorStoreIds) && $maxNumResults === null) {
            return null;
        }

        $tool = ['type' => 'file_search'];

        if (!empty($vectorStoreIds)) {
            $tool['vector_store_ids'] = $vectorStoreIds;
        }

        if ($maxNumResults !== null) {
            $tool['max_num_results'] = $maxNumResults;
        }

        return $this->normalizeFileSearchStructure($tool);
    }

    private function applyFileSearchDefaults(array $tools, array $vectorStoreIds, ?int $maxNumResults): array {
        if (empty($tools)) {
            return $tools;
        }

        if (empty($vectorStoreIds) && $maxNumResults === null) {
            return $tools;
        }

        foreach ($tools as &$tool) {
            if (($tool['type'] ?? '') !== 'file_search') {
                continue;
            }

            $tool = $this->normalizeFileSearchStructure($tool);

            if (!empty($vectorStoreIds) && !array_key_exists('vector_store_ids', $tool)) {
                $tool['vector_store_ids'] = $vectorStoreIds;
            }

            if ($maxNumResults !== null && !array_key_exists('max_num_results', $tool)) {
                $tool['max_num_results'] = $maxNumResults;
            }
        }
        unset($tool);

        return $tools;
    }

    private function normalizeFileSearchStructure(array $tool): array {
        if (($tool['type'] ?? '') !== 'file_search') {
            return $tool;
        }

        if (isset($tool['file_search']) && is_array($tool['file_search'])) {
            $options = $tool['file_search'];

            if (!array_key_exists('max_num_results', $tool) && array_key_exists('max_num_results', $options)) {
                $max = $this->sanitizeMaxNumResults($options['max_num_results']);
                if ($max !== null) {
                    $tool['max_num_results'] = $max;
                }
            }

            if (!array_key_exists('filters', $tool) && array_key_exists('filters', $options)) {
                $filters = $this->sanitizeToolValue($options['filters']);
                if ($filters !== null) {
                    $tool['filters'] = $filters;
                }
            }

            foreach ($options as $key => $value) {
                if (in_array($key, ['max_num_results', 'filters'], true)) {
                    continue;
                }

                if (!array_key_exists($key, $tool)) {
                    $tool[$key] = $value;
                }
            }

            unset($tool['file_search']);
        }

        return $tool;
    }

    private function normalizeTools(array $toolsConfig): array {
        $normalized = [];

        foreach ($toolsConfig as $tool) {
            if (!is_array($tool)) {
                continue;
            }

            $type = $tool['type'] ?? null;
            if (!is_string($type)) {
                continue;
            }

            $type = strtolower(trim($type));
            if ($type === '') {
                continue;
            }

            switch ($type) {
                case 'function':
                    $function = $tool['function'] ?? null;
                    if (!is_array($function)) {
                        continue 2;
                    }

                    $name = $function['name'] ?? '';
                    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
                        continue 2;
                    }

                    $normalizedFunction = ['name' => $name];

                    if (isset($function['description']) && is_string($function['description'])) {
                        $desc = trim($function['description']);
                        if ($desc !== '') {
                            $normalizedFunction['description'] = substr($desc, 0, 512);
                        }
                    }

                    if (array_key_exists('strict', $function)) {
                        $strict = filter_var($function['strict'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($strict !== null) {
                            $normalizedFunction['strict'] = $strict;
                        }
                    }

                    if (isset($function['parameters'])) {
                        $parameters = $this->sanitizeToolValue($function['parameters']);
                        if ($parameters !== null) {
                            $normalizedFunction['parameters'] = $parameters;
                        }
                    }

                    $normalized[] = [
                        'type' => 'function',
                        'function' => $normalizedFunction,
                    ];
                    break;

                case 'file_search':
                    $normalizedTool = ['type' => 'file_search'];

                    if (!empty($tool['vector_store_ids']) && is_array($tool['vector_store_ids'])) {
                        $vectorStoreIds = [];
                        foreach ($tool['vector_store_ids'] as $id) {
                            if (!is_string($id)) {
                                continue;
                            }

                            $id = trim($id);
                            if ($id === '' || !preg_match('/^[A-Za-z0-9._-]{1,128}$/', $id)) {
                                continue;
                            }

                            $vectorStoreIds[] = $id;
                        }

                        if (!empty($vectorStoreIds)) {
                            $normalizedTool['vector_store_ids'] = array_values(array_unique($vectorStoreIds));
                        }
                    }

                    $fileSearchOptions = [];
                    $optionsSources = [];
                    if (isset($tool['file_search']) && is_array($tool['file_search'])) {
                        $optionsSources[] = $tool['file_search'];
                    }
                    if (isset($tool['max_num_results'])) {
                        $optionsSources[] = ['max_num_results' => $tool['max_num_results']];
                    }
                    if (isset($tool['filters'])) {
                        $optionsSources[] = ['filters' => $tool['filters']];
                    }

                    foreach ($optionsSources as $options) {
                        if (!is_array($options)) {
                            continue;
                        }

                        if (isset($options['max_num_results'])) {
                            $maxResults = filter_var($options['max_num_results'], FILTER_VALIDATE_INT, [
                                'options' => ['min_range' => 1, 'max_range' => 200],
                            ]);
                            if ($maxResults !== false) {
                                $fileSearchOptions['max_num_results'] = $maxResults;
                            }
                        }

                        if (isset($options['filters'])) {
                            $filters = $this->sanitizeToolValue($options['filters']);
                            if ($filters !== null) {
                                $fileSearchOptions['filters'] = $filters;
                            }
                        }
                    }

                    if (!empty($fileSearchOptions)) {
                        foreach ($fileSearchOptions as $key => $value) {
                            $normalizedTool[$key] = $value;
                        }
                    }

                    $normalized[] = $this->normalizeFileSearchStructure($normalizedTool);
                    break;

                case 'code_interpreter':
                    $normalized[] = ['type' => 'code_interpreter'];
                    break;

                default:
                    continue 2;
            }
        }

        return $normalized;
    }

    private function mergeTools(array $defaultTools, array $overrideTools, bool $overrideProvided = false): array {
        if ($overrideProvided) {
            if (empty($overrideTools)) {
                return [];
            }
        } elseif (empty($overrideTools)) {
            return $defaultTools;
        }

        if (empty($defaultTools)) {
            return $overrideTools;
        }

        $merged = [];

        foreach ($defaultTools as $tool) {
            if (!isset($tool['type'])) {
                continue;
            }

            $merged[$this->buildToolKey($tool)] = $tool;
        }

        foreach ($overrideTools as $tool) {
            if (!isset($tool['type'])) {
                continue;
            }

            $key = $this->buildToolKey($tool);
            if (isset($merged[$key])) {
                $merged[$key] = $this->mergeSingleTool($merged[$key], $tool);
            } else {
                $merged[$key] = $tool;
            }
        }

        return array_values($merged);
    }

    private function mergeSingleTool(array $defaultTool, array $overrideTool): array {
        $type = $overrideTool['type'] ?? $defaultTool['type'] ?? null;

        if ($type === 'file_search') {
            $defaultTool = $this->normalizeFileSearchStructure($defaultTool);
            $overrideTool = $this->normalizeFileSearchStructure($overrideTool);

            $merged = $defaultTool;

            if (array_key_exists('vector_store_ids', $overrideTool)) {
                $ids = $overrideTool['vector_store_ids'];
                if (is_array($ids)) {
                    $merged['vector_store_ids'] = $ids;
                } else {
                    unset($merged['vector_store_ids']);
                }
            }

            foreach ($overrideTool as $key => $value) {
                if (in_array($key, ['type', 'vector_store_ids'], true)) {
                    continue;
                }

                if ($value === null) {
                    unset($merged[$key]);
                    continue;
                }

                $merged[$key] = $value;
            }

            $merged['type'] = 'file_search';

            return $merged;
        }

        if ($type === 'function') {
            $merged = $defaultTool;

            if (isset($overrideTool['function']) && is_array($overrideTool['function'])) {
                $functionConfig = $merged['function'] ?? [];
                foreach ($overrideTool['function'] as $key => $value) {
                    if ($value === null) {
                        unset($functionConfig[$key]);
                        continue;
                    }
                    $functionConfig[$key] = $value;
                }

                if (!empty($functionConfig)) {
                    $merged['function'] = $functionConfig;
                }
            }

            $merged['type'] = 'function';

            return $merged;
        }

        return array_merge($defaultTool, $overrideTool);
    }

    private function buildToolKey(array $tool): string {
        $type = $tool['type'] ?? '';
        $key = $type;

        if ($type === 'function' && isset($tool['function']['name'])) {
            $key .= ':' . $tool['function']['name'];
        }

        return $key;
    }

    private function sanitizeToolValue($value, int $depth = 0) {
        if ($depth > 10) {
            return null;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                if (!is_int($key) && !is_string($key)) {
                    continue;
                }

                $child = $this->sanitizeToolValue($item, $depth + 1);
                if ($child !== null) {
                    $sanitized[$key] = $child;
                }
            }
            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitizeToolValue((array)$value, $depth + 1);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }

            if (strlen($trimmed) > 2000) {
                $trimmed = substr($trimmed, 0, 2000);
            }

            return $trimmed;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function normalizePromptValue($value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    private function extractResponseOutputText(array $response) {
        $text = '';

        $outputs = $response['output'] ?? ($response['response']['output'] ?? []);

        if (is_array($outputs)) {
            foreach ($outputs as $output) {
                if (!is_array($output)) {
                    continue;
                }

                $contentSegments = $output['content'] ?? [];

                if (is_array($contentSegments)) {
                    foreach ($contentSegments as $segment) {
                        if (!is_array($segment)) {
                            continue;
                        }

                        if (isset($segment['text']) && is_string($segment['text'])) {
                            $text .= $segment['text'];
                        } elseif (isset($segment['output_text']) && is_string($segment['output_text'])) {
                            $text .= $segment['output_text'];
                        }
                    }
                }
            }
        }

        if ($text === '' && isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $segment) {
                if (isset($segment['text']) && is_string($segment['text'])) {
                    $text .= $segment['text'];
                }
            }
        }

        return $text;
    }

    private function extractTextDelta(array $event) {
        if (isset($event['delta'])) {
            $delta = $event['delta'];

            if (is_string($delta)) {
                return $delta;
            }

            if (is_array($delta)) {
                if (isset($delta['text'])) {
                    return $delta['text'];
                }

                if (isset($delta['output_text'])) {
                    return $delta['output_text'];
                }

                if (isset($delta['content']) && is_array($delta['content'])) {
                    $text = '';
                    foreach ($delta['content'] as $segment) {
                        if (is_array($segment) && isset($segment['text'])) {
                            $text .= $segment['text'];
                        }
                    }
                    return $text;
                }
            }
        }

        if (isset($event['text'])) {
            return $event['text'];
        }

        return '';
    }

    private function uploadFiles($fileData, $purpose = 'user_data', $tenantId = null, $conversationId = null, $agentId = null) {
        $fileIds = [];

        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        foreach ($fileData as $file) {
            $fileId = $this->openAIClient->uploadFile($file, $purpose);
            if ($fileId) {
                $fileIds[] = $fileId;

                $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_FILE_UPLOAD, [
                    'quantity' => 1,
                    'file_id' => $fileId,
                    'conversation_id' => $conversationId,
                    'agent_id' => $agentId,
                    'purpose' => $purpose,
                    'file_name' => $file['name'] ?? null,
                    'mime_type' => $file['type'] ?? null,
                    'bytes' => isset($file['size']) ? (int) $file['size'] : null
                ]);
            }
        }

        return $fileIds;
    }

    /**
     * Check and enforce tenant-based rate limits (DEPRECATED)
     * This method kept for backwards compatibility with older whitelabel integrations
     * 
     * @deprecated Since v2.0.0. Will be removed in v3.0.0. 
     *             Use tenant-based rate limiting via checkRateLimitTenant() instead.
     * 
     * Migration Example:
     * OLD: $this->checkRateLimitLegacy($agentConfig);
     * NEW: $this->checkRateLimitTenant($tenantId, 'api_call');
     * 
     * To migrate, ensure your requests include a tenant_id or API key that can be resolved to a tenant.
     * See docs/TENANT_RATE_LIMITING.md for details.
     * 
     * @param array|null $agentConfig Agent configuration
     * @throws Exception if rate limit exceeded
     */
    private function checkRateLimitLegacy($agentConfig = null) {
        // For whitelabel agents without tenant context, use IP + agent as fallback
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Construct a pseudo-tenant ID from IP and agent for backwards compatibility
        if ($agentConfig && isset($agentConfig['agent_public_id'])) {
            $pseudoTenantId = 'whitelabel_' . $agentConfig['agent_public_id'] . '_' . md5($clientIP);
            
            // Get custom rate limits from whitelabel config
            $limit = isset($agentConfig['wl_rate_limit_requests']) && $agentConfig['wl_rate_limit_requests'] > 0
                ? $agentConfig['wl_rate_limit_requests']
                : $this->config['chat_config']['rate_limit_requests'];
            
            $window = isset($agentConfig['wl_rate_limit_window_seconds']) && $agentConfig['wl_rate_limit_window_seconds'] > 0
                ? $agentConfig['wl_rate_limit_window_seconds']
                : $this->config['chat_config']['rate_limit_window'];
        } else {
            // No tenant context, use IP-based fallback
            $pseudoTenantId = 'ip_' . md5($clientIP);
            $limit = $this->config['chat_config']['rate_limit_requests'];
            $window = $this->config['chat_config']['rate_limit_window'];
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
        $requestsFile = sys_get_temp_dir() . '/chatbot_requests_' . md5($pseudoTenantId);
        $currentTime = time();
        
        // Read existing requests
        $requests = [];
        if (file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
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

    private function validateFileData($fileData) {
        // Load FileValidator if not already loaded
        if (!class_exists('FileValidator')) {
            require_once __DIR__ . '/FileValidator.php';
        }
        
        $validator = new FileValidator();
        
        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        foreach ($fileData as $file) {
            // Use comprehensive validation from FileValidator
            // This validates: filename, size (encoded & decoded), MIME type, malware
            $validator->validateFile($file, $this->config['chat_config']);
        }
    }

    private function getConversationHistory($conversationId) {
        switch ($this->config['storage']['type']) {
            case 'session':
                session_start();
                $sessionKey = 'chatbot_conversation_' . $conversationId;
                return $_SESSION[$sessionKey] ?? [];

            case 'file':
                $filePath = $this->config['storage']['path'] . '/' . $conversationId . '.json';
                if (file_exists($filePath)) {
                    return json_decode(file_get_contents($filePath), true) ?: [];
                }
                return [];

            default:
                return [];
        }
    }

    private function saveConversationHistory($conversationId, $messages) {
        // Limit conversation history
        $maxMessages = $this->config['chat_config']['max_messages'];
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        switch ($this->config['storage']['type']) {
            case 'session':
                session_start();
                $sessionKey = 'chatbot_conversation_' . $conversationId;
                $_SESSION[$sessionKey] = $messages;
                break;

            case 'file':
                $filePath = $this->config['storage']['path'] . '/' . $conversationId . '.json';
                file_put_contents($filePath, json_encode($messages));
                break;
        }
    }

    private function executeTool($toolCall, $tenantId = null, $conversationId = null, $agentId = null, $responseId = null) {
        $function = $toolCall['function'] ?? [
            'name' => $toolCall['name'] ?? '',
            'arguments' => $toolCall['arguments'] ?? '{}'
        ];

        $functionName = $function['name'];
        $arguments = json_decode($function['arguments'] ?? '{}', true) ?: [];

        $result = null;
        switch ($functionName) {
            case 'get_weather':
                $result = $this->getWeather($arguments['location'] ?? '');
                break;
            case 'search_knowledge':
                $result = $this->searchKnowledge($arguments['query'] ?? '');
                break;
            default:
                $result = ['error' => 'Unknown function: ' . $functionName];
                break;
        }

        $this->trackUsage($tenantId, UsageTrackingService::RESOURCE_TOOL_CALL, [
            'quantity' => 1,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'response_id' => $responseId,
            'tool_name' => $functionName,
            'arguments' => $arguments
        ]);

        return $result;
    }

    private function getWeather($location) {
        return ['weather' => 'sunny', 'temperature' => '25°C', 'location' => $location];
    }

    private function searchKnowledge($query) {
        return ['results' => 'Knowledge base search for: ' . $query];
    }

    private function estimateTokenCount($text) {
        if (!is_string($text) || $text === '') {
            return 0;
        }

        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount === 0) {
            return 0;
        }

        return (int) max(1, ceil($charCount / 4));
    }

    /**
     * Get agent configuration (public for channel integrations)
     *
     * @param string|null $agentId
     * @return array Agent configuration with overrides applied
     */
    public function getAgentConfig($agentId = null) {
        $overrides = $this->resolveAgentOverrides($agentId);
        
        // Merge with base config
        $apiType = $overrides['api_type'] ?? $this->config['api_type'] ?? 'responses';
        
        return array_merge([
            'api_type' => $apiType,
            'model' => $overrides['model'] ?? ($apiType === 'responses' ? $this->config['responses']['model'] : $this->config['chat']['model']),
            'temperature' => $overrides['temperature'] ?? ($apiType === 'responses' ? $this->config['responses']['temperature'] : $this->config['chat']['temperature']),
        ], $overrides);
    }
    
    /**
     * Process a conversation turn with LeadSense
     * 
     * @param string $conversationId
     * @param string $userMessage
     * @param string $assistantMessage
     * @param string|null $agentId
     * @param array $additionalContext
     */
    private function processLeadSenseTurn($conversationId, $userMessage, $assistantMessage, $agentId = null, $additionalContext = []) {
        if (!$this->leadSenseService || !$this->leadSenseService->isEnabled()) {
            return;
        }
        
        try {
            // Get conversation history
            $messages = $this->getConversationHistory($conversationId);
            
            // Build turn context
            $turnData = array_merge([
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'messages' => $messages,
                'timestamp' => time()
            ], $additionalContext);
            
            // Process the turn
            $result = $this->leadSenseService->processTurn($turnData);
            
            if ($result) {
                error_log("LeadSense: Lead detected - ID: {$result['lead_id']}, Score: {$result['score']}, Qualified: " . ($result['qualified'] ? 'yes' : 'no'));
            }
        } catch (Exception $e) {
            // Log error without exposing sensitive details
            error_log("LeadSense error in ChatHandler: " . $e->getMessage());
        }
    }
    
    /**
     * Track usage for billing and metering
     * 
     * @param string|null $tenantId Tenant ID
     * @param string $resourceType Type of resource (message, completion, etc)
     * @param array $metadata Additional metadata (tokens, model, etc)
     */
    private function trackUsage($tenantId, $resourceType, $metadata = []) {
        if (!$tenantId) {
            return; // Skip if no tenant ID
        }
        
        $enabled = $this->config['usage_tracking']['enabled'] ?? false;
        if (!$enabled || !$this->usageTrackingService) {
            return;
        }
        
        try {
            // Log to usage_logs
            $this->usageTrackingService->logUsage($tenantId, $resourceType, [
                'quantity' => $metadata['quantity'] ?? 1,
                'metadata' => $metadata
            ]);
            
            // Also update tenant_usage aggregation in real-time
            if ($this->tenantUsageService) {
                $this->tenantUsageService->incrementUsage(
                    $tenantId, 
                    $resourceType, 
                    $metadata['quantity'] ?? 1
                );
            }
        } catch (Exception $e) {
            error_log("Usage tracking error: " . $e->getMessage());
        }
    }
    
    /**
     * Check and enforce tenant-based rate limits (Tenant/API Key based - NOT IP based)
     * This is the primary rate limiting mechanism for multi-tenant support
     * 
     * @param string|null $tenantId Tenant ID or API key identifier
     * @param string $resourceType Type of resource being accessed (api_call, message, completion, etc)
     * @throws Exception if rate limit exceeded (429 error code)
     */
    private function checkRateLimitTenant($tenantId, $resourceType = 'api_call') {
        // Check configuration: should we require tenant ID?
        $requireTenantId = $this->config['usage_tracking']['require_tenant_id'] ?? false;
        
        if (!$tenantId) {
            if ($requireTenantId) {
                // Strict mode: require tenant ID for all requests
                error_log("ERROR: Tenant ID required but not provided for resource type: $resourceType");
                throw new Exception('Tenant identification required. Please include API key or X-Tenant-ID header.', 401);
            }
            // Permissive mode: allow anonymous requests (fallback to legacy rate limiting)
            error_log("Warning: Rate limit check called without tenant ID for resource type: $resourceType (permissive mode)");
            return;
        }
        
        $enabled = $this->config['usage_tracking']['enabled'] ?? false;
        if (!$enabled || !$this->rateLimitService) {
            // Rate limiting not enabled or service not available
            return;
        }
        
        try {
            // Get tenant-specific rate limit configuration or use defaults
            $rateLimit = $this->rateLimitService->getTenantRateLimit($tenantId, $resourceType);
            
            // Enforce the rate limit (throws exception if exceeded)
            $this->rateLimitService->enforceRateLimit(
                $tenantId,
                $resourceType,
                $rateLimit['limit'],
                $rateLimit['window_seconds']
            );
        } catch (Exception $e) {
            // Re-throw rate limit exceptions (429)
            if ($e->getCode() == 429) {
                throw $e;
            }
            // Log other errors but don't block the request
            error_log("Rate limit check error for tenant $tenantId: " . $e->getMessage());
        }
    }
    
    /**
     * Check and enforce tenant quotas
     * 
     * @param string|null $tenantId Tenant ID
     * @param string $resourceType Type of resource being accessed
     * @throws Exception if quota exceeded
     */
    private function checkQuota($tenantId, $resourceType, $period = 'daily') {
        if (!$tenantId) {
            return; // Skip if no tenant ID
        }
        
        $enabled = $this->config['quota_enforcement']['enabled'] ?? false;
        if (!$enabled || !$this->quotaService) {
            return;
        }
        
        try {
            // Enforce quota (throws exception if hard limit exceeded)
            $this->quotaService->enforceQuota($tenantId, $resourceType, $period);
        } catch (Exception $e) {
            // Re-throw quota exceptions
            if ($e->getCode() == 429) {
                throw $e;
            }
            error_log("Quota check error: " . $e->getMessage());
        }
    }
    
    /**
     * Get tenant ID from conversation or request context
     * Override this method or inject tenant_id via request
     * 
     * @param string $conversationId Conversation ID
     * @return string|null Tenant ID
     */
    public function getTenantId($conversationId = null) {
        // This can be extended to:
        // 1. Extract from conversation metadata
        // 2. Get from authenticated user session
        // 3. Look up from API key
        // For now, return null (multi-tenant support must be configured per deployment)
        return null;
    }
}
?>

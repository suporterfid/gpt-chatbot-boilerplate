<?php
/**
 * Unified Chat Handler for Chat Completions and Responses API
 */

class ChatHandler {
    private $config;
    private $openAIClient;

    public function __construct($config) {
        $this->config = $config;
        $this->openAIClient = new OpenAIClient($config['openai']);
    }

    public function validateRequest($message, $conversationId, $fileData = null) {
        // Rate limiting
        $this->checkRateLimit();

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

    public function handleChatCompletion($message, $conversationId) {
        // Get conversation history
        $messages = $this->getConversationHistory($conversationId);

        // Add system message if configured
        if (!empty($this->config['chat']['system_message']) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->config['chat']['system_message']
            ]);
        }

        // Add user message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        // Stream response from OpenAI
        $this->streamChatCompletion($messages, $conversationId);
    }

    public function handleChatCompletionSync($message, $conversationId) {
        $messages = $this->getConversationHistory($conversationId);

        if (!empty($this->config['chat']['system_message']) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->config['chat']['system_message']
            ]);
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        $payload = [
            'model' => $this->config['chat']['model'],
            'messages' => $messages,
            'temperature' => $this->config['chat']['temperature'],
            'max_tokens' => $this->config['chat']['max_tokens'],
            'top_p' => $this->config['chat']['top_p'],
            'frequency_penalty' => $this->config['chat']['frequency_penalty'],
            'presence_penalty' => $this->config['chat']['presence_penalty'],
            'stream' => false
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        $assistantMessage = $response['choices'][0]['message']['content'] ?? '';

        $messages[] = [
            'role' => 'assistant',
            'content' => $assistantMessage
        ];

        $this->saveConversationHistory($conversationId, $messages);

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
     */
    public function handleResponsesChat($message, $conversationId, $fileData = null, $promptId = null, $promptVersion = null, $tools = null) {
        $messages = $this->getConversationHistory($conversationId);
        $responsesConfig = $this->config['responses'];

        $promptIdOverride = $this->normalizePromptValue($promptId);
        $promptVersionOverride = $this->normalizePromptValue($promptVersion);
        $configuredPromptId = $this->normalizePromptValue($responsesConfig['prompt_id'] ?? null);
        $configuredPromptVersion = $this->normalizePromptValue($responsesConfig['prompt_version'] ?? null);

        $effectivePromptId = $promptIdOverride ?? $configuredPromptId;
        $effectivePromptVersion = $promptVersionOverride ?? $configuredPromptVersion;

        if (!empty($responsesConfig['system_message']) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $responsesConfig['system_message']
            ]);
        }

        $fileIds = [];
        if ($fileData) {
            $fileIds = $this->uploadFiles($fileData, 'user_data');
        }

        $userMessage = [
            'role' => 'user',
            'content' => $message,
        ];

        if (!empty($fileIds)) {
            $userMessage['file_ids'] = $fileIds;
        }

        $messages[] = $userMessage;

        $payload = [
            'model' => $responsesConfig['model'],
            'input' => $this->formatMessagesForResponses($messages),
            'temperature' => $responsesConfig['temperature'],
            'top_p' => $responsesConfig['top_p'],
            'max_output_tokens' => $responsesConfig['max_output_tokens'],
            'stream' => true,
        ];

        $tooling = $this->resolveResponsesTooling();
        $configTools = $tooling['tools'];
        $overrideProvided = $tools !== null;

        $requestTools = [];
        if ($overrideProvided && is_array($tools)) {
            $requestTools = $this->normalizeTools($tools);
        }

        $mergedTools = $this->mergeTools($configTools, $requestTools, $overrideProvided);
        $mergedTools = $this->applyFileSearchDefaults($mergedTools, $tooling['default_vector_store_ids'], $tooling['default_max_num_results']);

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

        $messageStarted = false;
        $fullResponse = '';
        $responseId = null;
        $toolCalls = [];

        $streamFn = function($p) use (&$messages, $conversationId, &$messageStarted, &$fullResponse, &$responseId, &$toolCalls) {
            $this->openAIClient->streamResponse($p, function($event) use (&$messages, $conversationId, &$messageStarted, &$fullResponse, &$responseId, &$toolCalls) {
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
                }
                return;
            }

            if ($eventType === 'response.required_action') {
                $responseId = $event['response']['id'] ?? $responseId;
                $toolCallsData = $event['response']['required_action']['submit_tool_outputs']['tool_calls'] ?? [];

                if (!empty($toolCallsData)) {
                    $toolOutputs = [];
                    foreach ($toolCallsData as $toolCall) {
                        $result = $this->executeTool($toolCall);
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
                }

                $this->saveConversationHistory($conversationId, $messages);

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
     *
     * @return array
     */
    public function handleResponsesChatSync($message, $conversationId, $fileData = null, $promptId = null, $promptVersion = null, $tools = null) {
        $messages = $this->getConversationHistory($conversationId);
        $responsesConfig = $this->config['responses'];

        $promptIdOverride = $this->normalizePromptValue($promptId);
        $promptVersionOverride = $this->normalizePromptValue($promptVersion);
        $configuredPromptId = $this->normalizePromptValue($responsesConfig['prompt_id'] ?? null);
        $configuredPromptVersion = $this->normalizePromptValue($responsesConfig['prompt_version'] ?? null);

        $effectivePromptId = $promptIdOverride ?? $configuredPromptId;
        $effectivePromptVersion = $promptVersionOverride ?? $configuredPromptVersion;

        if (!empty($responsesConfig['system_message']) && empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $responsesConfig['system_message']
            ]);
        }

        $fileIds = [];
        if ($fileData) {
            $fileIds = $this->uploadFiles($fileData, 'user_data');
        }

        $userMessage = [
            'role' => 'user',
            'content' => $message,
        ];

        if (!empty($fileIds)) {
            $userMessage['file_ids'] = $fileIds;
        }

        $messages[] = $userMessage;

        $payload = [
            'model' => $responsesConfig['model'],
            'input' => $this->formatMessagesForResponses($messages),
            'temperature' => $responsesConfig['temperature'],
            'top_p' => $responsesConfig['top_p'],
            'max_output_tokens' => $responsesConfig['max_output_tokens'],
            'stream' => false,
        ];

        $tooling = $this->resolveResponsesTooling();
        $configTools = $tooling['tools'];
        $overrideProvided = $tools !== null;

        $requestTools = [];
        if ($overrideProvided && is_array($tools)) {
            $requestTools = $this->normalizeTools($tools);
        }

        $mergedTools = $this->mergeTools($configTools, $requestTools, $overrideProvided);
        $mergedTools = $this->applyFileSearchDefaults($mergedTools, $tooling['default_vector_store_ids'], $tooling['default_max_num_results']);

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

        $execute = function(array $payload) use (&$messages, $conversationId) {
            $response = $this->openAIClient->createResponse($payload);

            $assistantMessage = $this->extractResponseOutputText($response);

            if ($assistantMessage !== '') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $assistantMessage
                ];
            }

            $this->saveConversationHistory($conversationId, $messages);

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
                unset($payload['prompt']);
                try {
                    return $execute($payload);
                } catch (Exception $inner) {
                    $err = $inner->getMessage();
                    $clientErr = (strpos($err, 'HTTP 400') !== false) || (strpos($err, 'HTTP 404') !== false) || (strpos($err, 'HTTP 422') !== false);
                }
            }

            if ($clientErr && isset($payload['model']) && $payload['model'] !== 'gpt-4o-mini') {
                $payload['model'] = 'gpt-4o-mini';
                return $execute($payload);
            }

            throw $e;
        }
    }

    private function streamChatCompletion($messages, $conversationId) {
        $payload = [
            'model' => $this->config['chat']['model'],
            'messages' => $messages,
            'temperature' => $this->config['chat']['temperature'],
            'max_tokens' => $this->config['chat']['max_tokens'],
            'top_p' => $this->config['chat']['top_p'],
            'frequency_penalty' => $this->config['chat']['frequency_penalty'],
            'presence_penalty' => $this->config['chat']['presence_penalty'],
            'stream' => true
        ];

        $messageStarted = false;
        $fullResponse = '';

        $this->openAIClient->streamChatCompletion($payload, function($chunk) use (&$messageStarted, &$fullResponse, $conversationId, $messages) {
            $messagesCopy = $messages;

            if (isset($chunk['choices'][0]['delta']['content'])) {
                if (!$messageStarted) {
                    sendSSEEvent('message', ['type' => 'start']);
                    $messageStarted = true;
                }

                $content = $chunk['choices'][0]['delta']['content'];
                $fullResponse .= $content;

                sendSSEEvent('message', [
                    'type' => 'chunk',
                    'content' => $content
                ]);
            }

            if (isset($chunk['choices'][0]['finish_reason'])) {
                $messagesCopy[] = [
                    'role' => 'assistant',
                    'content' => $fullResponse
                ];
                $this->saveConversationHistory($conversationId, $messagesCopy);

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

    private function uploadFiles($fileData, $purpose = 'user_data') {
        $fileIds = [];

        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        foreach ($fileData as $file) {
            $fileId = $this->openAIClient->uploadFile($file, $purpose);
            if ($fileId) {
                $fileIds[] = $fileId;
            }
        }

        return $fileIds;
    }

    private function checkRateLimit() {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitFile = sys_get_temp_dir() . '/chatbot_rate_limit_' . md5($clientIP);
        $requestsFile = sys_get_temp_dir() . '/chatbot_requests_' . md5($clientIP);

        $currentTime = time();
        $rateLimit = $this->config['chat_config']['rate_limit_requests'];
        $window = $this->config['chat_config']['rate_limit_window'];

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
        if (count($requests) >= $rateLimit) {
            throw new Exception('Rate limit exceeded. Please wait before sending another message.', 429);
        }

        // Add current request
        $requests[] = $currentTime;
        file_put_contents($requestsFile, json_encode($requests));
    }

    private function validateFileData($fileData) {
        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        foreach ($fileData as $file) {
            if (!isset($file['data']) || !isset($file['type'])) {
                throw new Exception('Invalid file data format', 400);
            }

            if ($file['size'] > $this->config['chat_config']['max_file_size']) {
                throw new Exception('File size exceeds limit', 400);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), $this->config['chat_config']['allowed_file_types'])) {
                throw new Exception('File type not allowed', 400);
            }
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

    private function executeTool($toolCall) {
        $function = $toolCall['function'] ?? [
            'name' => $toolCall['name'] ?? '',
            'arguments' => $toolCall['arguments'] ?? '{}'
        ];

        $functionName = $function['name'];
        $arguments = json_decode($function['arguments'] ?? '{}', true) ?: [];

        switch ($functionName) {
            case 'get_weather':
                return $this->getWeather($arguments['location'] ?? '');
            case 'search_knowledge':
                return $this->searchKnowledge($arguments['query'] ?? '');
            default:
                return ['error' => 'Unknown function: ' . $functionName];
        }
    }

    private function getWeather($location) {
        return ['weather' => 'sunny', 'temperature' => '25°C', 'location' => $location];
    }

    private function searchKnowledge($query) {
        return ['results' => 'Knowledge base search for: ' . $query];
    }
}
?>

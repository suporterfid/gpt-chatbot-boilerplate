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

    public function handleResponsesChat($message, $conversationId, $fileData = null) {
        $messages = $this->getConversationHistory($conversationId);
        $responsesConfig = $this->config['responses'];

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
            'frequency_penalty' => $responsesConfig['frequency_penalty'],
            'presence_penalty' => $responsesConfig['presence_penalty'],
            'max_output_tokens' => $responsesConfig['max_output_tokens'],
            'stream' => true,
        ];

        if (!empty($responsesConfig['prompt_id'])) {
            $prompt = ['id' => $responsesConfig['prompt_id']];
            if (!empty($responsesConfig['prompt_version'])) {
                $prompt['version'] = $responsesConfig['prompt_version'];
            }
            $payload['prompt'] = $prompt;
        }

        $messageStarted = false;
        $fullResponse = '';
        $responseId = null;
        $toolCalls = [];

        $this->openAIClient->streamResponse($payload, function($event) use (&$messages, $conversationId, &$messageStarted, &$fullResponse, &$responseId, &$toolCalls) {
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
            $formattedMessage = [
                'role' => $message['role'],
                'content' => [
                    [
                        'type' => 'input_text',
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

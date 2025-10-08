<?php
/**
 * Unified Chat Handler for both Chat Completions and Assistants API
 */

class ChatHandler {
    private $config;
    private $openAIClient;
    private $assistantManager;
    private $threadManager;

    public function __construct($config) {
        $this->config = $config;
        $this->openAIClient = new OpenAIClient($config['openai']);
        $this->assistantManager = new AssistantManager($this->openAIClient, $config);
        $this->threadManager = new ThreadManager($this->openAIClient, $config);
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

    public function handleAssistantChat($message, $conversationId, $fileData = null) {
        // Get or create assistant
        $assistantId = $this->assistantManager->getOrCreateAssistant();
        if (function_exists('log_debug')) {
            log_debug("Assistant chat start conv=$conversationId assistant=$assistantId msgLen=" . strlen($message));
        }

        // Get or create thread
        $threadId = $this->threadManager->getOrCreateThread($conversationId);
        if (function_exists('log_debug')) {
            log_debug("Using thread $threadId for conv=$conversationId");
        }

        // Upload file if provided
        $fileIds = [];
        if ($fileData) {
            $fileIds = $this->uploadFiles($fileData);
        }

        // Add message to thread
        $this->openAIClient->addMessageToThread($threadId, [
            'role' => 'user',
            'content' => $message,
            'file_ids' => $fileIds
        ]);

        // Create and stream run
        if (function_exists('log_debug')) {
            log_debug("Starting run for thread=$threadId conv=$conversationId");
        }
        $this->streamAssistantRun($threadId, $assistantId, $conversationId);
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

        $this->openAIClient->streamChatCompletion($payload, function($chunk) use ($conversationId, $messages) {
            static $messageStarted = false;
            static $fullResponse = '';

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
                // Save conversation including assistant response
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $fullResponse
                ];
                $this->saveConversationHistory($conversationId, $messages);

                sendSSEEvent('message', [
                    'type' => 'done',
                    'finish_reason' => $chunk['choices'][0]['finish_reason']
                ]);
            }
        });
    }

    private function streamAssistantRun($threadId, $assistantId, $conversationId) {
        $runPayload = [
            'assistant_id' => $assistantId,
            'temperature' => $this->config['assistants']['temperature'],
            'max_completion_tokens' => $this->config['assistants']['max_completion_tokens'],
            'stream' => true
        ];

        $this->openAIClient->streamAssistantRun($threadId, $runPayload, function($event) use ($conversationId, $threadId) {
            static $messageStarted = false;
            static $currentMessage = '';

            switch ($event['event']) {
                case 'thread.run.created':
                    sendSSEEvent('message', [
                        'type' => 'run_created',
                        'run_id' => $event['data']['id']
                    ]);
                    break;

                case 'thread.message.delta':
                    if (!$messageStarted) {
                        sendSSEEvent('message', ['type' => 'start']);
                        $messageStarted = true;
                    }

                    $delta = $event['data']['delta'];
                    if (isset($delta['content'][0]['text']['value'])) {
                        $content = $delta['content'][0]['text']['value'];
                        $currentMessage .= $content;

                        sendSSEEvent('message', [
                            'type' => 'chunk',
                            'content' => $content
                        ]);
                    }
                    break;

                case 'thread.run.completed':
                    // Update thread mapping
                    $this->threadManager->updateThreadMapping($conversationId, $threadId);

                    sendSSEEvent('message', [
                        'type' => 'done',
                        'run_status' => 'completed'
                    ]);
                    if (function_exists('log_debug')) {
                        log_debug("Run completed for thread=$threadId conv=$conversationId responseLen=" . strlen($currentMessage));
                    }
                    break;

                case 'thread.run.failed':
                    sendSSEEvent('error', [
                        'message' => 'Assistant run failed',
                        'details' => $event['data']['last_error'] ?? 'Unknown error'
                    ]);
                    if (function_exists('log_debug')) {
                        log_debug("Run failed for thread=$threadId conv=$conversationId error=" . json_encode($event['data']['last_error'] ?? 'Unknown'), 'error');
                    }
                    break;

                case 'thread.run.requires_action':
                    // Handle function calling
                    $this->handleRequiredAction($event['data'], $threadId);
                    break;
            }
        });
    }

    private function handleRequiredAction($runData, $threadId) {
        $toolCalls = $runData['required_action']['submit_tool_outputs']['tool_calls'];
        $toolOutputs = [];

        foreach ($toolCalls as $toolCall) {
            $result = $this->executeTool($toolCall);
            $toolOutputs[] = [
                'tool_call_id' => $toolCall['id'],
                'output' => json_encode($result)
            ];
        }

        // Submit tool outputs
        $this->openAIClient->submitToolOutputs($threadId, $runData['id'], $toolOutputs);
    }

    private function executeTool($toolCall) {
        // Implement custom function calling logic here
        $function = $toolCall['function'];
        $functionName = $function['name'];
        $arguments = json_decode($function['arguments'], true);

        // Example function execution
        switch ($functionName) {
            case 'get_weather':
                return $this->getWeather($arguments['location'] ?? '');
            case 'search_knowledge':
                return $this->searchKnowledge($arguments['query'] ?? '');
            default:
                return ['error' => 'Unknown function: ' . $functionName];
        }
    }

    private function uploadFiles($fileData) {
        $fileIds = [];

        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        foreach ($fileData as $file) {
            $fileId = $this->openAIClient->uploadFile($file);
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

    // Example custom functions for tool calling
    private function getWeather($location) {
        // Implement weather API integration
        return ['weather' => 'sunny', 'temperature' => '25°C', 'location' => $location];
    }

    private function searchKnowledge($query) {
        // Implement knowledge base search
        return ['results' => 'Knowledge base search for: ' . $query];
    }
}
?>

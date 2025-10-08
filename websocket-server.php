<?php
/**
 * GPT Chatbot - WebSocket Server
 * Standalone WebSocket server for real-time communication
 * 
 * Usage: php websocket-server.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatBotWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $conversations;
    private $config;

    public function __construct($config) {
        $this->clients = new \SplObjectStorage;
        $this->conversations = [];
        $this->config = $config;

        echo "ChatBot WebSocket Server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);

        echo "New connection: {$conn->resourceId}\n";

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Connected to ChatBot WebSocket server'
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: {$msg}\n";

        try {
            $data = json_decode($msg, true);

            if (!isset($data['message'])) {
                throw new Exception('Message field is required');
            }

            $message = $data['message'];
            $conversationId = $data['conversation_id'] ?? 'default';

            // Validate message
            if (empty(trim($message))) {
                throw new Exception('Message cannot be empty');
            }

            if (strlen($message) > ($this->config['security']['max_message_length'] ?? 4000)) {
                throw new Exception('Message too long');
            }

            // Get conversation history
            if (!isset($this->conversations[$conversationId])) {
                $this->conversations[$conversationId] = [];
            }

            // Add user message
            $this->conversations[$conversationId][] = [
                'role' => 'user',
                'content' => $message
            ];

            // Send start event
            $from->send(json_encode([
                'type' => 'start',
                'conversation_id' => $conversationId
            ]));

            // Stream response from OpenAI
            $this->streamOpenAIResponse($from, $this->conversations[$conversationId], $conversationId);

        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n";

            $from->send(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error on connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    private function streamOpenAIResponse(ConnectionInterface $conn, $messages, $conversationId) {
        $apiKey = $this->config['openai']['api_key'];
        $model = $this->config['openai']['model'] ?? 'gpt-3.5-turbo';

        if (empty($apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->config['openai']['temperature'] ?? 0.7,
            'max_tokens' => $this->config['openai']['max_tokens'] ?? 1000,
            'stream' => true
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($conn, $conversationId) {
                static $buffer = '';
                static $messageStarted = false;
                static $assistantMessage = '';

                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            // Save assistant message to conversation
                            if (!empty($assistantMessage)) {
                                $this->conversations[$conversationId][] = [
                                    'role' => 'assistant',
                                    'content' => $assistantMessage
                                ];

                                // Limit conversation history
                                $maxMessages = $this->config['chat']['max_messages'] ?? 50;
                                if (count($this->conversations[$conversationId]) > $maxMessages) {
                                    $this->conversations[$conversationId] = array_slice(
                                        $this->conversations[$conversationId], 
                                        -$maxMessages
                                    );
                                }
                            }

                            $conn->send(json_encode([
                                'type' => 'done'
                            ]));
                            return strlen($data);
                        }

                        $decoded = json_decode($json, true);
                        if ($decoded && isset($decoded['choices'][0]['delta'])) {
                            $delta = $decoded['choices'][0]['delta'];

                            if (isset($delta['content'])) {
                                if (!$messageStarted) {
                                    $conn->send(json_encode([
                                        'type' => 'start'
                                    ]));
                                    $messageStarted = true;
                                }

                                $content = $delta['content'];
                                $assistantMessage .= $content;

                                $conn->send(json_encode([
                                    'type' => 'chunk',
                                    'content' => $content
                                ]));
                            }

                            if (isset($delta['finish_reason']) && $delta['finish_reason'] === 'stop') {
                                $conn->send(json_encode([
                                    'type' => 'done'
                                ]));
                            }
                        }
                    }
                }

                return strlen($data);
            },
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('OpenAI API error: HTTP ' . $httpCode);
        }
    }
}

// Check if Ratchet is installed
if (!class_exists('Ratchet\Server\IoServer')) {
    echo "Error: Ratchet WebSocket library not installed.\n";
    echo "Please run: composer install\n";
    exit(1);
}

// Load configuration
$config = require_once 'config.php';

// Check WebSocket configuration
if (!($config['websocket']['enabled'] ?? false)) {
    echo "WebSocket server is disabled in configuration.\n";
    echo "Set WEBSOCKET_ENABLED=true in your .env file to enable.\n";
    exit(1);
}

// Start WebSocket server
$host = $config['websocket']['host'] ?? '0.0.0.0';
$port = $config['websocket']['port'] ?? 8080;

echo "Starting ChatBot WebSocket Server on {$host}:{$port}\n";
echo "Press Ctrl+C to stop the server\n\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatBotWebSocket($config)
        )
    ),
    $port,
    $host
);

// Handle graceful shutdown
pcntl_signal(SIGTERM, function() use ($server) {
    echo "\nShutting down WebSocket server...\n";
    $server->loop->stop();
});

pcntl_signal(SIGINT, function() use ($server) {
    echo "\nShutting down WebSocket server...\n";
    $server->loop->stop();
});

$server->run();
?>
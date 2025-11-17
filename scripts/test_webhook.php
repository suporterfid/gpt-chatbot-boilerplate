#!/usr/bin/env php
<?php
/**
 * Webhook Testing CLI Tool
 * 
 * A command-line utility for testing webhook deliveries, inspecting payloads,
 * and validating signatures.
 * 
 * Reference: docs/SPEC_WEBHOOK.md §10 - Extensibility
 * Task: wh-008b
 * 
 * Usage:
 *   php scripts/test_webhook.php send --url <url> --event <event> --data <json>
 *   php scripts/test_webhook.php validate-signature --body <body> --secret <secret> --signature <sig>
 *   php scripts/test_webhook.php inspect-logs --subscriber-id <id>
 *   php scripts/test_webhook.php mock-server --port <port>
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookDispatcher.php';
require_once __DIR__ . '/../includes/WebhookSecurityService.php';
require_once __DIR__ . '/../includes/WebhookLogRepository.php';

class WebhookTester {
    private $db;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $this->db = new DB($this->config['database'] ?? []);
    }
    
    /**
     * Send a test webhook to a URL
     */
    public function sendTestWebhook($url, $event, $data, $secret = null) {
        echo "=== Sending Test Webhook ===\n\n";
        echo "URL: {$url}\n";
        echo "Event: {$event}\n";
        echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        
        $timestamp = time();
        $payload = [
            'event' => $event,
            'timestamp' => $timestamp,
            'agent_id' => 'test_cli',
            'data' => $data
        ];
        
        $body = json_encode($payload);
        
        // Generate signature if secret provided
        $signature = null;
        if ($secret) {
            $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
            echo "Signature: {$signature}\n\n";
        }
        
        // Send request
        $startTime = microtime(true);
        
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'User-Agent: AI-Agent-Webhook-Tester/1.0'
        ];
        
        if ($signature) {
            $headers[] = 'X-Agent-Signature: ' . $signature;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false // For testing only
        ]);
        
        $response = curl_exec($ch);
        $duration = (microtime(true) - $startTime) * 1000;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Parse response
        $headerSize = strpos($response, "\r\n\r\n");
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize + 4);
        
        echo "=== Response ===\n\n";
        echo "HTTP Status: {$httpCode}\n";
        echo "Duration: " . round($duration, 2) . " ms\n\n";
        
        if ($error) {
            echo "Error: {$error}\n\n";
        }
        
        echo "Headers:\n{$responseHeaders}\n\n";
        echo "Body:\n{$responseBody}\n\n";
        
        // Try to format JSON response
        $jsonResponse = json_decode($responseBody, true);
        if ($jsonResponse) {
            echo "Formatted Response:\n";
            echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n";
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status_code' => $httpCode,
            'duration_ms' => $duration,
            'response' => $responseBody
        ];
    }
    
    /**
     * Validate webhook signature
     */
    public function validateSignature($body, $secret, $signature) {
        echo "=== Validating Webhook Signature ===\n\n";
        echo "Body: {$body}\n";
        echo "Secret: " . str_repeat('*', strlen($secret)) . "\n";
        echo "Provided Signature: {$signature}\n\n";
        
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        echo "Expected Signature: {$expected}\n\n";
        
        $isValid = hash_equals($expected, $signature);
        
        if ($isValid) {
            echo "✓ Signature is VALID\n";
        } else {
            echo "✗ Signature is INVALID\n";
        }
        
        return $isValid;
    }
    
    /**
     * Inspect webhook delivery logs
     */
    public function inspectLogs($subscriberId = null, $limit = 10) {
        echo "=== Webhook Delivery Logs ===\n\n";
        
        $logRepo = new WebhookLogRepository($this->db);
        
        if ($subscriberId) {
            echo "Filtering by Subscriber ID: {$subscriberId}\n\n";
            $logs = $logRepo->listBySubscriber($subscriberId, $limit);
        } else {
            $sql = "SELECT * FROM webhook_logs ORDER BY created_at DESC LIMIT ?";
            $logs = $this->db->query($sql, [$limit]);
        }
        
        if (empty($logs)) {
            echo "No logs found.\n";
            return;
        }
        
        foreach ($logs as $log) {
            echo "─────────────────────────────────────────\n";
            echo "Log ID: {$log['id']}\n";
            echo "Subscriber: {$log['subscriber_id']}\n";
            echo "Event: {$log['event']}\n";
            echo "Attempts: {$log['attempts']}\n";
            echo "Response Code: " . ($log['response_code'] ?? 'N/A') . "\n";
            echo "Created: {$log['created_at']}\n";
            
            if (!empty($log['response_body'])) {
                echo "\nResponse Body:\n";
                $responseJson = json_decode($log['response_body'], true);
                if ($responseJson) {
                    echo json_encode($responseJson, JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo $log['response_body'] . "\n";
                }
            }
            
            echo "\n";
        }
    }
    
    /**
     * Start a mock webhook receiver server
     */
    public function startMockServer($port = 8080) {
        echo "=== Starting Mock Webhook Server ===\n\n";
        echo "Listening on http://localhost:{$port}\n";
        echo "Press Ctrl+C to stop\n\n";
        
        // Create log file
        $logFile = '/tmp/webhook_mock_' . time() . '.log';
        echo "Logging to: {$logFile}\n\n";
        
        // Start PHP built-in server with request handler
        $handlerFile = $this->createMockHandler($logFile);
        
        $command = sprintf(
            'php -S localhost:%d %s',
            $port,
            escapeshellarg($handlerFile)
        );
        
        passthru($command);
    }
    
    /**
     * Create mock server handler file
     */
    private function createMockHandler($logFile) {
        $handlerPath = '/tmp/webhook_mock_handler.php';
        
        $handlerCode = <<<'PHP'
<?php
$logFile = '%LOG_FILE%';

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$headers = getallheaders();
$body = file_get_contents('php://input');

// Log request
$timestamp = date('Y-m-d H:i:s');
$logEntry = sprintf(
    "[%s] %s %s\nHeaders: %s\nBody: %s\n\n",
    $timestamp,
    $method,
    $uri,
    json_encode($headers, JSON_PRETTY_PRINT),
    $body
);

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Print to console
echo $logEntry;

// Send response
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'received',
    'timestamp' => time(),
    'received_at' => $timestamp
]);
PHP;
        
        $handlerCode = str_replace('%LOG_FILE%', $logFile, $handlerCode);
        file_put_contents($handlerPath, $handlerCode);
        
        return $handlerPath;
    }
    
    /**
     * Display help information
     */
    public function showHelp() {
        echo <<<HELP
Webhook Testing CLI Tool

Usage:
  php scripts/test_webhook.php <command> [options]

Commands:
  send                    Send a test webhook
    --url <url>           Target URL
    --event <event>       Event type
    --data <json>         Event data (JSON string)
    --secret <secret>     Optional HMAC secret

  validate-signature      Validate HMAC signature
    --body <body>         Request body
    --secret <secret>     HMAC secret
    --signature <sig>     Signature to validate

  inspect-logs           Inspect webhook delivery logs
    --subscriber-id <id>  Filter by subscriber ID (optional)
    --limit <n>           Number of logs to show (default: 10)

  mock-server            Start a mock webhook receiver
    --port <port>         Port to listen on (default: 8080)

  help                   Show this help message

Examples:
  # Send test webhook
  php scripts/test_webhook.php send \\
    --url "https://example.com/webhook" \\
    --event "ai.response" \\
    --data '{"message":"test"}' \\
    --secret "my-secret"

  # Validate signature
  php scripts/test_webhook.php validate-signature \\
    --body '{"event":"test"}' \\
    --secret "my-secret" \\
    --signature "sha256=abc123..."

  # Inspect recent logs
  php scripts/test_webhook.php inspect-logs --limit 20

  # Start mock server
  php scripts/test_webhook.php mock-server --port 9000

HELP;
    }
}

// Parse command line arguments
function parseArgs($argv) {
    $args = [];
    $currentKey = null;
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (strpos($arg, '--') === 0) {
            $currentKey = substr($arg, 2);
            $args[$currentKey] = true;
        } elseif ($currentKey !== null) {
            $args[$currentKey] = $arg;
            $currentKey = null;
        } else {
            $args[] = $arg;
        }
    }
    
    return $args;
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$args = parseArgs($argv);
$command = $args[0] ?? 'help';

$tester = new WebhookTester();

try {
    switch ($command) {
        case 'send':
            $url = $args['url'] ?? null;
            $event = $args['event'] ?? null;
            $dataJson = $args['data'] ?? '{}';
            $secret = $args['secret'] ?? null;
            
            if (!$url || !$event) {
                echo "Error: --url and --event are required\n";
                exit(1);
            }
            
            $data = json_decode($dataJson, true);
            if ($data === null && $dataJson !== 'null') {
                echo "Error: Invalid JSON in --data\n";
                exit(1);
            }
            
            $tester->sendTestWebhook($url, $event, $data, $secret);
            break;
            
        case 'validate-signature':
            $body = $args['body'] ?? null;
            $secret = $args['secret'] ?? null;
            $signature = $args['signature'] ?? null;
            
            if (!$body || !$secret || !$signature) {
                echo "Error: --body, --secret, and --signature are required\n";
                exit(1);
            }
            
            $isValid = $tester->validateSignature($body, $secret, $signature);
            exit($isValid ? 0 : 1);
            break;
            
        case 'inspect-logs':
            $subscriberId = $args['subscriber-id'] ?? null;
            $limit = isset($args['limit']) ? (int)$args['limit'] : 10;
            
            $tester->inspectLogs($subscriberId, $limit);
            break;
            
        case 'mock-server':
            $port = isset($args['port']) ? (int)$args['port'] : 8080;
            $tester->startMockServer($port);
            break;
            
        case 'help':
        default:
            $tester->showHelp();
            break;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

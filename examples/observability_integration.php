<?php
/**
 * Example: Integrating Observability 
 * 
 * Demonstrates structured logging and distributed tracing integration.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ObservabilityLogger.php';
require_once __DIR__ . '/../includes/TracingService.php';

// Initialize
$logger = new ObservabilityLogger($config);
$tracing = new TracingService($logger, $config);

// Example: Chat request with tracing
function handleChatRequest($message, $conversationId, $agentId, $logger, $tracing) {
    $requestSpanId = $tracing->startSpan('api.chat_request', [
        'conversation_id' => $conversationId,
        'agent_id' => $agentId
    ]);
    
    $logger->info('api', 'chat_request_received', [
        'conversation_id' => $conversationId,
        'agent_id' => $agentId,
        'message_length' => strlen($message)
    ]);
    
    try {
        // Simulate processing
        usleep(500000);
        $response = ['content' => 'Hello!', 'tokens' => 150];
        
        $logger->info('api', 'chat_request_completed', [
            'conversation_id' => $conversationId
        ]);
        
        $tracing->endSpan($requestSpanId, 'ok');
        return $response;
    } catch (Exception $e) {
        $logger->error('api', 'chat_request_failed', [
            'error' => $e->getMessage()
        ]);
        $tracing->recordError($requestSpanId, $e->getMessage());
        $tracing->endSpan($requestSpanId, 'error');
        throw $e;
    }
}

// Run demo
if (php_sapi_name() === 'cli') {
    echo "Running observability examples...\n";
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
    $result = handleChatRequest("Hello", "conv-123", "agent-456", $logger, $tracing);
    echo "Success: " . $result['content'] . "\n";
    $logger->flush();
    $tracing->flush();
}

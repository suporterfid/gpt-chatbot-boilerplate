<?php
/**
 * WhatsApp Webhook Endpoint (Z-API)
 * Receives incoming WhatsApp messages and routes them to the appropriate agent
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/DB.php';
require_once __DIR__ . '/../../includes/AgentService.php';
require_once __DIR__ . '/../../includes/ChatHandler.php';
require_once __DIR__ . '/../../includes/ChannelManager.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Webhook-Secret');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Logging helper
function logWebhook($message, $level = 'info') {
    global $config;
    $logFile = $config['logging']['file'] ?? __DIR__ . '/../../logs/chatbot.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = "[$ts][$level][WhatsApp Webhook][$ip] $message\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

try {
    // Get request body
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    
    if (!$payload) {
        logWebhook('Invalid JSON payload', 'error');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
    
    logWebhook('Received webhook: ' . substr($rawBody, 0, 200));
    
    // Initialize services
    $db = new DB($config['storage']);
    $channelManager = new ChannelManager($db);
    $agentService = new AgentService($db);
    $chatHandler = new ChatHandler($config, $agentService);
    
    // Determine agent ID
    // Method 1: From URL path (/channels/whatsapp/{agentId}/webhook)
    $agentId = null;
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    
    if (count($pathParts) >= 4 && $pathParts[0] === 'channels' && $pathParts[1] === 'whatsapp') {
        $agentId = $pathParts[2];
        logWebhook("Agent ID from path: $agentId");
    }
    
    // Method 2: From destination number (WhatsApp business number)
    if (!$agentId) {
        // Try to extract destination number from payload
        $destinationNumber = null;
        
        // Z-API might send the destination in different fields
        if (isset($payload['instanceId'])) {
            // Try to find by instance ID
            // This would require storing instance ID in channel config
        } elseif (isset($payload['to'])) {
            $destinationNumber = $payload['to'];
        } elseif (isset($payload['data']['to'])) {
            $destinationNumber = $payload['data']['to'];
        }
        
        if ($destinationNumber) {
            $agentId = $channelManager->findAgentByWhatsAppNumber($destinationNumber);
            if ($agentId) {
                logWebhook("Agent ID from business number: $agentId");
            }
        }
    }
    
    if (!$agentId) {
        logWebhook('Could not determine agent ID', 'error');
        http_response_code(404);
        echo json_encode(['error' => 'Agent not found']);
        exit();
    }
    
    // Get channel configuration
    $channelConfig = $channelManager->getChannelConfig($agentId, 'whatsapp');
    
    if (!$channelConfig) {
        logWebhook("WhatsApp not configured for agent: $agentId", 'error');
        http_response_code(404);
        echo json_encode(['error' => 'WhatsApp channel not configured']);
        exit();
    }
    
    // Verify webhook signature if secret is configured
    $webhookSecret = $channelConfig['zapi_webhook_secret'] ?? null;
    if ($webhookSecret) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $adapter = $channelManager->getChannelAdapter($agentId, 'whatsapp');
        
        if (!$adapter->verifySignature($headers, $rawBody, $webhookSecret)) {
            logWebhook('Webhook signature verification failed', 'error');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
    }
    
    // Process inbound message
    $result = $channelManager->processInbound($agentId, 'whatsapp', $payload, function($message, $conversationId, $session) use ($chatHandler, $channelManager, $agentId, $channelConfig) {
        
        // Check for opt-out commands
        $text = trim($message['text'] ?? '');
        if (strtoupper($text) === 'STOP' || strtoupper($text) === 'PARAR') {
            // Update session metadata to mark as opted out
            $metadata = $session['metadata'] ?? [];
            $metadata['opt_out'] = true;
            $channelManager->getSessionService()->updateMetadata($session['id'], $metadata);
            
            // Send confirmation
            $channelManager->sendText(
                $agentId,
                'whatsapp',
                $message['from'],
                'You have been unsubscribed. Send START to re-subscribe.',
                $conversationId
            );
            
            return ['opt_out' => true];
        }
        
        // Check if user is opted out
        $metadata = $session['metadata'] ?? [];
        if (!empty($metadata['opt_out'])) {
            if (strtoupper($text) === 'START' || strtoupper($text) === 'INICIAR') {
                // Re-subscribe
                $metadata['opt_out'] = false;
                $channelManager->getSessionService()->updateMetadata($session['id'], $metadata);
                
                $channelManager->sendText(
                    $agentId,
                    'whatsapp',
                    $message['from'],
                    'Welcome back! You have been re-subscribed.',
                    $conversationId
                );
                
                return ['opt_in' => true];
            }
            
            // User is opted out, don't process
            return ['skipped' => true, 'reason' => 'User opted out'];
        }
        
        // Prepare file data if media is present
        $fileData = null;
        if (!empty($message['media_url'])) {
            $allowMedia = $channelConfig['allow_media_upload'] ?? false;
            
            if (!$allowMedia) {
                $channelManager->sendText(
                    $agentId,
                    'whatsapp',
                    $message['from'],
                    'Sorry, media uploads are not supported at this time.',
                    $conversationId
                );
                return ['skipped' => true, 'reason' => 'Media not allowed'];
            }
            
            // Download and encode media
            try {
                $maxSize = $channelConfig['max_media_size_bytes'] ?? 10485760; // 10MB
                $allowedTypes = $channelConfig['allowed_media_types'] ?? ['image/jpeg', 'image/png', 'application/pdf'];
                
                // Validate MIME type
                if (!in_array($message['mime_type'], $allowedTypes)) {
                    $channelManager->sendText(
                        $agentId,
                        'whatsapp',
                        $message['from'],
                        'Sorry, this file type is not supported. Allowed types: ' . implode(', ', $allowedTypes),
                        $conversationId
                    );
                    return ['skipped' => true, 'reason' => 'Unsupported media type'];
                }
                
                // Download file
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'user_agent' => 'ChatbotWebhook/1.0'
                    ]
                ]);
                
                $fileContent = @file_get_contents($message['media_url'], false, $context);
                
                if ($fileContent === false) {
                    throw new Exception('Failed to download media');
                }
                
                // Check size
                if (strlen($fileContent) > $maxSize) {
                    $channelManager->sendText(
                        $agentId,
                        'whatsapp',
                        $message['from'],
                        'Sorry, the file is too large. Maximum size: ' . number_format($maxSize / 1048576, 1) . 'MB',
                        $conversationId
                    );
                    return ['skipped' => true, 'reason' => 'File too large'];
                }
                
                // Encode as base64
                $fileData = [[
                    'data' => base64_encode($fileContent),
                    'mime_type' => $message['mime_type'],
                    'filename' => 'media_' . time() . '.' . $this->getExtensionFromMime($message['mime_type'])
                ]];
                
            } catch (Exception $e) {
                error_log("Failed to process media: " . $e->getMessage());
                $channelManager->sendText(
                    $agentId,
                    'whatsapp',
                    $message['from'],
                    'Sorry, there was an error processing your file.',
                    $conversationId
                );
                return ['skipped' => true, 'reason' => 'Media processing error'];
            }
        }
        
        // Process message with ChatHandler (sync mode)
        try {
            // Use Responses API if configured for agent, otherwise fallback to chat
            $agentConfig = $chatHandler->getAgentConfig($agentId);
            $apiType = $agentConfig['api_type'] ?? 'responses';
            
            if ($apiType === 'responses') {
                $response = $chatHandler->handleResponsesChatSync(
                    $text,
                    $conversationId,
                    $fileData,
                    $agentId
                );
            } else {
                $response = $chatHandler->handleChatSync(
                    $text,
                    $conversationId,
                    $fileData,
                    $agentId
                );
            }
            
            // Send response back to user
            if (!empty($response['reply'])) {
                $channelManager->sendText(
                    $agentId,
                    'whatsapp',
                    $message['from'],
                    $response['reply'],
                    $conversationId
                );
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("ChatHandler error: " . $e->getMessage());
            
            // Send error message to user
            $channelManager->sendText(
                $agentId,
                'whatsapp',
                $message['from'],
                'Sorry, I encountered an error processing your message. Please try again later.',
                $conversationId
            );
            
            throw $e;
        }
    });
    
    logWebhook('Message processed successfully: ' . json_encode($result));
    
    // Return success
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'result' => $result
    ]);
    
} catch (Exception $e) {
    logWebhook('Webhook error: ' . $e->getMessage(), 'error');
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * Helper: Get file extension from MIME type
 */
function getExtensionFromMime($mimeType) {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    
    return $map[$mimeType] ?? 'bin';
}

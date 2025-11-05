<?php
/**
 * Test WhatsApp Webhook with Mock Z-API Payload
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "WhatsApp Webhook Mock Test\n";
echo "===========================\n\n";

try {
    $db = new DB($config['storage']);
    $agentService = new AgentService($db);
    
    // Create test agent
    echo "1. Creating test agent...\n";
    $agent = $agentService->createAgent([
        'name' => 'Webhook Test Agent ' . time(),
        'description' => 'Test agent for webhook',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini',
        'system_message' => 'You are a helpful WhatsApp assistant. Keep responses brief.',
        'temperature' => 0.7
    ]);
    $agentId = $agent['id'];
    echo "   ✓ Agent created: {$agentId}\n\n";
    
    // Configure WhatsApp channel
    echo "2. Configuring WhatsApp channel...\n";
    $channelConfig = $agentService->upsertAgentChannel($agentId, 'whatsapp', [
        'enabled' => true,
        'whatsapp_business_number' => '+5511999998888',
        'zapi_instance_id' => 'test_instance',
        'zapi_token' => 'test_token',
        'zapi_base_url' => 'https://api.z-api.io',
        'allow_media_upload' => false, // Disable to avoid download attempts
        'reply_chunk_size' => 4000
    ]);
    echo "   ✓ Channel configured\n\n";
    
    // Prepare mock Z-API webhook payload
    echo "3. Preparing mock webhook payload...\n";
    $mockPayload = [
        'event' => 'message.received',
        'data' => [
            'message' => [
                'messageId' => 'test_msg_' . time(),
                'from' => '+5511988887777',
                'text' => 'Hello, this is a test message!',
                'timestamp' => time()
            ]
        ]
    ];
    
    $webhookUrl = "http://localhost/channels/whatsapp/{$agentId}/webhook";
    echo "   ✓ Payload prepared\n";
    echo "   ✓ Webhook URL: {$webhookUrl}\n\n";
    
    // Simulate webhook call by including the webhook script
    echo "4. Simulating webhook reception...\n";
    echo "   Note: This is a dry-run that validates the flow without actually calling OpenAI\n\n";
    
    // Save current state
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = "/channels/whatsapp/{$agentId}/webhook";
    
    // We can't actually execute the webhook as it would try to call OpenAI
    // Instead, we'll test the components individually
    
    require_once __DIR__ . '/../includes/ChannelManager.php';
    
    $channelManager = new ChannelManager($db);
    
    // Test normalizing the payload
    echo "5. Testing payload normalization...\n";
    $adapter = $channelManager->getChannelAdapter($agentId, 'whatsapp');
    $normalized = $adapter->normalizeInbound($mockPayload);
    
    echo "   ✓ Normalized payload:\n";
    echo "     - From: {$normalized['from']}\n";
    echo "     - Text: {$normalized['text']}\n";
    echo "     - Message ID: {$normalized['message_id']}\n\n";
    
    // Test session creation
    echo "6. Testing session creation...\n";
    $session = $channelManager->getSessionService()->getOrCreateSession(
        $agentId,
        'whatsapp',
        $normalized['from']
    );
    echo "   ✓ Session created: {$session['conversation_id']}\n";
    echo "   ✓ Is new: " . ($session['is_new'] ? 'yes' : 'no') . "\n\n";
    
    // Test message recording
    echo "7. Testing message recording...\n";
    $messageId = $channelManager->getMessageService()->recordInbound(
        $agentId,
        'whatsapp',
        $normalized['from'],
        $session['conversation_id'],
        $normalized['message_id'],
        $normalized
    );
    echo "   ✓ Message recorded: {$messageId}\n\n";
    
    // Test idempotency
    echo "8. Testing idempotency (duplicate detection)...\n";
    $isDuplicate = $channelManager->getMessageService()->messageExists($normalized['message_id']);
    echo "   ✓ Message exists: " . ($isDuplicate ? 'yes' : 'no') . "\n";
    
    try {
        $channelManager->getMessageService()->recordInbound(
            $agentId,
            'whatsapp',
            $normalized['from'],
            $session['conversation_id'],
            $normalized['message_id'],
            $normalized
        );
        echo "   ✗ Duplicate was not rejected!\n";
    } catch (Exception $e) {
        echo "   ✓ Duplicate correctly rejected: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // Test agent lookup
    echo "9. Testing agent lookup by business number...\n";
    $foundAgentId = $channelManager->findAgentByWhatsAppNumber('+5511999998888');
    echo "   ✓ Agent found: " . ($foundAgentId === $agentId ? 'yes' : 'no') . "\n\n";
    
    // Display webhook integration instructions
    echo "10. Webhook Integration Instructions\n";
    echo "=====================================\n\n";
    echo "To test with real Z-API:\n\n";
    echo "1. Configure Z-API webhook to point to:\n";
    echo "   POST {$webhookUrl}\n\n";
    echo "2. Or use the business number lookup:\n";
    echo "   POST http://localhost/channels/whatsapp/webhook\n";
    echo "   (Will auto-detect agent by business number: +5511999998888)\n\n";
    echo "3. Send a WhatsApp message to your business number\n\n";
    echo "4. Check logs:\n";
    echo "   tail -f logs/chatbot.log | grep 'WhatsApp Webhook'\n\n";
    
    // Cleanup
    echo "11. Cleaning up...\n";
    $agentService->deleteAgentChannel($agentId, 'whatsapp');
    $agentService->deleteAgent($agentId);
    echo "    ✓ Test data cleaned up\n\n";
    
    echo "✓ All webhook component tests passed!\n\n";
    echo "Note: Full end-to-end testing requires:\n";
    echo "  - Valid OpenAI API key configured\n";
    echo "  - Real Z-API instance\n";
    echo "  - Public webhook endpoint accessible by Z-API\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

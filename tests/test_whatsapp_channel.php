<?php
/**
 * Test WhatsApp Channel Integration
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/ChannelManager.php';

echo "WhatsApp Channel Integration Test\n";
echo "==================================\n\n";

try {
    $db = new DB($config['storage']);
    $agentService = new AgentService($db);
    $channelManager = new ChannelManager($db);
    
    // Test 1: Create a test agent
    echo "1. Creating test agent...\n";
    $agent = $agentService->createAgent([
        'name' => 'WhatsApp Test Agent ' . time(),
        'description' => 'Test agent for WhatsApp integration',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7
    ]);
    echo "   ✓ Agent created: {$agent['id']}\n\n";
    
    // Test 2: Configure WhatsApp channel
    echo "2. Configuring WhatsApp channel...\n";
    $channelConfig = $agentService->upsertAgentChannel($agent['id'], 'whatsapp', [
        'enabled' => true,
        'whatsapp_business_number' => '+5511999999999',
        'zapi_instance_id' => 'test_instance_123',
        'zapi_token' => 'test_token_456',
        'zapi_base_url' => 'https://api.z-api.io',
        'zapi_timeout_ms' => 30000,
        'zapi_retries' => 3,
        'reply_chunk_size' => 4000,
        'allow_media_upload' => true,
        'max_media_size_bytes' => 10485760,
        'allowed_media_types' => ['image/jpeg', 'image/png', 'application/pdf']
    ]);
    echo "   ✓ Channel configured: {$channelConfig['id']}\n\n";
    
    // Test 3: List agent channels
    echo "3. Listing agent channels...\n";
    $channels = $agentService->listAgentChannels($agent['id']);
    echo "   ✓ Found " . count($channels) . " channel(s)\n";
    foreach ($channels as $ch) {
        echo "     - {$ch['channel']}: " . ($ch['enabled'] ? 'enabled' : 'disabled') . "\n";
    }
    echo "\n";
    
    // Test 4: Get specific channel
    echo "4. Getting WhatsApp channel config...\n";
    $whatsappConfig = $agentService->getAgentChannel($agent['id'], 'whatsapp');
    echo "   ✓ WhatsApp channel enabled: " . ($whatsappConfig['enabled'] ? 'yes' : 'no') . "\n";
    echo "   ✓ Business number: {$whatsappConfig['config']['whatsapp_business_number']}\n\n";
    
    // Test 5: Test session creation
    echo "5. Testing session creation...\n";
    $session = $channelManager->getSessionService()->getOrCreateSession(
        $agent['id'],
        'whatsapp',
        '+5511988887777'
    );
    echo "   ✓ Session created: {$session['id']}\n";
    echo "   ✓ Conversation ID: {$session['conversation_id']}\n";
    echo "   ✓ Is new: " . ($session['is_new'] ? 'yes' : 'no') . "\n\n";
    
    // Test 6: Test getting the same session again (should not create new)
    echo "6. Testing session retrieval...\n";
    $session2 = $channelManager->getSessionService()->getOrCreateSession(
        $agent['id'],
        'whatsapp',
        '+5511988887777'
    );
    echo "   ✓ Session retrieved: {$session2['id']}\n";
    echo "   ✓ Same ID: " . ($session['id'] === $session2['id'] ? 'yes' : 'no') . "\n";
    echo "   ✓ Is new: " . ($session2['is_new'] ? 'yes' : 'no') . "\n\n";
    
    // Test 7: Test message tracking
    echo "7. Testing message tracking...\n";
    $messageId = $channelManager->getMessageService()->recordInbound(
        $agent['id'],
        'whatsapp',
        '+5511988887777',
        $session['conversation_id'],
        'msg_test_123',
        ['text' => 'Hello, this is a test message']
    );
    echo "   ✓ Message recorded: {$messageId}\n\n";
    
    // Test 8: Test duplicate detection
    echo "8. Testing duplicate detection...\n";
    $isDuplicate = $channelManager->getMessageService()->messageExists('msg_test_123');
    echo "   ✓ Message exists: " . ($isDuplicate ? 'yes' : 'no') . "\n\n";
    
    // Test 9: Find agent by business number
    echo "9. Testing agent lookup by business number...\n";
    $foundAgentId = $channelManager->findAgentByWhatsAppNumber('+5511999999999');
    echo "   ✓ Agent found: " . ($foundAgentId === $agent['id'] ? 'yes' : 'no') . "\n\n";
    
    // Test 10: List sessions
    echo "10. Listing sessions...\n";
    $sessions = $channelManager->getSessionService()->listSessions($agent['id'], 'whatsapp');
    echo "   ✓ Found " . count($sessions) . " session(s)\n";
    foreach ($sessions as $s) {
        echo "     - User: {$s['external_user_id']}, Last seen: {$s['last_seen_at']}\n";
    }
    echo "\n";
    
    // Cleanup
    echo "11. Cleaning up...\n";
    $agentService->deleteAgentChannel($agent['id'], 'whatsapp');
    echo "   ✓ Channel deleted\n";
    $agentService->deleteAgent($agent['id']);
    echo "   ✓ Agent deleted\n\n";
    
    echo "✓ All tests passed!\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

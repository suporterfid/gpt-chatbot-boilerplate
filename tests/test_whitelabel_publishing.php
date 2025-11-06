#!/usr/bin/env php
<?php
/**
 * Test Whitelabel Agent Publishing
 * Tests the complete whitelabel flow
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/WhitelabelTokenService.php';

// ANSI color codes
const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[0;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m"; // No Color

function test_log($message, $color = NC) {
    echo $color . $message . NC . "\n";
}

function test_pass($message) {
    test_log("✓ PASS: " . $message, GREEN);
}

function test_fail($message) {
    test_log("✗ FAIL: " . $message, RED);
}

function test_info($message) {
    test_log("ℹ INFO: " . $message, BLUE);
}

function test_section($message) {
    test_log("\n=== " . $message . " ===", YELLOW);
}

// Initialize
test_section("Initializing Database and Services");

try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
    ];
    
    $db = new DB($dbConfig);
    test_pass("Database connected");
    
    $agentService = new AgentService($db);
    test_pass("AgentService initialized");
    
    $tokenService = new WhitelabelTokenService($db, $config);
    test_pass("WhitelabelTokenService initialized");
} catch (Exception $e) {
    test_fail("Initialization failed: " . $e->getMessage());
    exit(1);
}

// Test 1: Create a test agent
test_section("Test 1: Create Test Agent");

$testAgentData = [
    'name' => 'Test Whitelabel Agent ' . time(),
    'description' => 'Test agent for whitelabel functionality',
    'api_type' => 'responses',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'system_message' => 'You are a helpful assistant for testing whitelabel functionality.'
];

try {
    $agent = $agentService->createAgent($testAgentData);
    test_pass("Test agent created: " . $agent['id']);
    test_info("Agent name: " . $agent['name']);
} catch (Exception $e) {
    test_fail("Failed to create agent: " . $e->getMessage());
    exit(1);
}

// Test 2: Enable whitelabel
test_section("Test 2: Enable Whitelabel");

$whitelabelConfig = [
    'wl_title' => 'Test Chatbot',
    'wl_welcome_message' => 'Welcome to our test chatbot!',
    'wl_placeholder' => 'Ask me anything...',
    'wl_enable_file_upload' => false,
    'wl_theme' => [
        'primaryColor' => '#FF5733',
        'backgroundColor' => '#F0F0F0',
        'surfaceColor' => '#FFFFFF',
        'textColor' => '#333333'
    ]
];

try {
    $agent = $agentService->enableWhitelabel($agent['id'], $whitelabelConfig);
    test_pass("Whitelabel enabled");
    test_info("Public ID: " . $agent['agent_public_id']);
    test_info("HMAC Secret: " . substr($agent['wl_hmac_secret'], 0, 16) . "...");
    test_info("Whitelabel enabled: " . ($agent['whitelabel_enabled'] ? 'Yes' : 'No'));
} catch (Exception $e) {
    test_fail("Failed to enable whitelabel: " . $e->getMessage());
    $agentService->deleteAgent($agent['id']);
    exit(1);
}

// Test 3: Generate and validate token
test_section("Test 3: Token Generation and Validation");

try {
    $token = $tokenService->generateToken(
        $agent['agent_public_id'],
        $agent['wl_hmac_secret'],
        600
    );
    test_pass("Token generated: " . substr($token, 0, 40) . "...");
    
    // Validate the token
    $validatedPayload = $tokenService->validateToken(
        $token,
        $agent['agent_public_id'],
        $agent['wl_hmac_secret']
    );
    
    if ($validatedPayload) {
        test_pass("Token validated successfully");
        test_info("Token age: " . (time() - $validatedPayload['ts']) . " seconds");
    } else {
        test_fail("Token validation failed");
    }
    
    // Try to reuse the same nonce (should fail due to replay protection)
    test_info("Testing nonce replay protection...");
    $revalidated = $tokenService->validateToken(
        $token,
        $agent['agent_public_id'],
        $agent['wl_hmac_secret']
    );
    
    if (!$revalidated) {
        test_pass("Nonce replay protection working");
    } else {
        test_fail("Nonce replay protection NOT working (token reused)");
    }
    
} catch (Exception $e) {
    test_fail("Token test failed: " . $e->getMessage());
}

// Test 4: Retrieve by public ID
test_section("Test 4: Retrieve Agent by Public ID");

try {
    $retrievedAgent = $agentService->getAgentByPublicId($agent['agent_public_id']);
    
    if ($retrievedAgent && $retrievedAgent['id'] === $agent['id']) {
        test_pass("Agent retrieved by public ID");
    } else {
        test_fail("Failed to retrieve agent by public ID");
    }
    
    // Try with invalid public ID
    $invalidAgent = $agentService->getAgentByPublicId('INVALID_PUB_ID_123');
    if ($invalidAgent === null) {
        test_pass("Invalid public ID correctly returns null");
    } else {
        test_fail("Invalid public ID should return null");
    }
} catch (Exception $e) {
    test_fail("Retrieval test failed: " . $e->getMessage());
}

// Test 5: Get public configuration
test_section("Test 5: Get Public Configuration");

try {
    $publicConfig = $agentService->getPublicWhitelabelConfig($agent['agent_public_id']);
    
    if ($publicConfig) {
        test_pass("Public configuration retrieved");
        test_info("Title: " . $publicConfig['title']);
        test_info("Welcome message: " . $publicConfig['welcome_message']);
        test_info("File upload enabled: " . ($publicConfig['enable_file_upload'] ? 'Yes' : 'No'));
        
        // Verify secrets are not exposed
        if (!isset($publicConfig['wl_hmac_secret']) && !isset($publicConfig['id'])) {
            test_pass("Secrets not exposed in public config");
        } else {
            test_fail("Public config exposes sensitive data");
        }
    } else {
        test_fail("Failed to retrieve public configuration");
    }
} catch (Exception $e) {
    test_fail("Public config test failed: " . $e->getMessage());
}

// Test 6: Update whitelabel configuration
test_section("Test 6: Update Whitelabel Configuration");

try {
    $updatedConfig = [
        'wl_title' => 'Updated Test Chatbot',
        'wl_footer_brand_md' => 'Powered by **TestCorp**',
        'wl_rate_limit_requests' => 10,
        'wl_rate_limit_window_seconds' => 60
    ];
    
    $agent = $agentService->updateWhitelabelConfig($agent['id'], $updatedConfig);
    test_pass("Whitelabel configuration updated");
    test_info("New title: " . $agent['wl_title']);
    test_info("Rate limit: " . $agent['wl_rate_limit_requests'] . " requests per " . 
              $agent['wl_rate_limit_window_seconds'] . " seconds");
} catch (Exception $e) {
    test_fail("Config update failed: " . $e->getMessage());
}

// Test 7: Rotate HMAC secret
test_section("Test 7: Rotate HMAC Secret");

try {
    $oldSecret = $agent['wl_hmac_secret'];
    $agent = $agentService->rotateHmacSecret($agent['id']);
    
    if ($agent['wl_hmac_secret'] !== $oldSecret) {
        test_pass("HMAC secret rotated");
        test_info("New secret: " . substr($agent['wl_hmac_secret'], 0, 16) . "...");
        
        // Old tokens should no longer validate
        $newToken = $tokenService->generateToken(
            $agent['agent_public_id'],
            $agent['wl_hmac_secret'],
            600
        );
        
        $validated = $tokenService->validateToken(
            $newToken,
            $agent['agent_public_id'],
            $agent['wl_hmac_secret']
        );
        
        if ($validated) {
            test_pass("New tokens validate with new secret");
        } else {
            test_fail("New tokens do not validate");
        }
    } else {
        test_fail("HMAC secret not rotated");
    }
} catch (Exception $e) {
    test_fail("Secret rotation failed: " . $e->getMessage());
}

// Test 8: Disable whitelabel
test_section("Test 8: Disable Whitelabel");

try {
    $agent = $agentService->disableWhitelabel($agent['id']);
    
    if (!$agent['whitelabel_enabled']) {
        test_pass("Whitelabel disabled");
        
        // Should not be retrievable by public ID anymore
        $retrievedAgent = $agentService->getAgentByPublicId($agent['agent_public_id']);
        if ($retrievedAgent === null) {
            test_pass("Disabled agent not retrievable by public ID");
        } else {
            test_fail("Disabled agent still retrievable by public ID");
        }
    } else {
        test_fail("Whitelabel not disabled");
    }
} catch (Exception $e) {
    test_fail("Disable failed: " . $e->getMessage());
}

// Cleanup
test_section("Cleanup");

try {
    $agentService->deleteAgent($agent['id']);
    test_pass("Test agent deleted");
} catch (Exception $e) {
    test_fail("Failed to delete test agent: " . $e->getMessage());
}

// Summary
test_section("Test Summary");
test_info("All whitelabel publishing tests completed!");
test_info("Review the results above to ensure all tests passed.");

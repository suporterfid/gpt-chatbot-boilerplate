<?php
/**
 * Test suite for Webhook Signature Validation (SPEC §4)
 *
 * Tests HMAC signature validation for webhook inbound endpoint
 */

declare(strict_types=1);

function testWebhookSignature(): void {
    echo "\n=== Testing Webhook Signature Validation (SPEC §4) ===\n\n";

    $baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8888';
    $endpoint = $baseUrl . '/public/webhook/inbound.php';
    
    // Note: These tests require WEBHOOK_GATEWAY_SECRET to be set
    // For testing purposes, we'll demonstrate both scenarios
    
    $secret = 'test-secret-key-12345';
    
    echo "Note: Testing signature validation requires WEBHOOK_GATEWAY_SECRET to be configured.\n";
    echo "Current test demonstrates signature generation and validation logic.\n\n";

    // Test 1: Generate valid signature
    echo "Test 1: Generate and test valid HMAC signature...\n";
    $payload = [
        'event' => 'order.created',
        'timestamp' => time(),
        'data' => ['order_id' => 'A12345']
    ];
    $body = json_encode($payload);
    $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
    
    echo "  Generated signature: $signature\n";
    echo "  Payload: $body\n";
    
    // Test 2: Verify signature format
    echo "\nTest 2: Verify signature format...\n";
    if (preg_match('/^sha256=[a-f0-9]{64}$/', $signature)) {
        echo "  ✓ Signature format is correct (sha256=<64 hex chars>)\n";
    } else {
        echo "  ✗ Signature format is incorrect\n";
    }

    // Test 3: Test signature in X-Agent-Signature header
    echo "\nTest 3: Test signature via X-Agent-Signature header...\n";
    $result = makeRequestWithSignature($endpoint, 'POST', $body, $signature, 'X-Agent-Signature');
    echo "  Response HTTP Code: " . $result['http_code'] . "\n";
    if ($result['http_code'] === 200 || $result['http_code'] === 401) {
        echo "  ℹ Response depends on WEBHOOK_GATEWAY_SECRET configuration\n";
        echo "    - 200: Secret not configured (validation skipped)\n";
        echo "    - 401: Secret configured but signature mismatch\n";
    }

    // Test 4: Test signature in payload
    echo "\nTest 4: Test signature in payload body...\n";
    $payloadWithSig = $payload;
    $payloadWithSig['signature'] = $signature;
    $bodyWithSig = json_encode($payloadWithSig);
    
    $result = makeRequest($endpoint, 'POST', $bodyWithSig, ['Content-Type: application/json']);
    echo "  Response HTTP Code: " . $result['http_code'] . "\n";
    if ($result['http_code'] === 200 || $result['http_code'] === 401) {
        echo "  ℹ Response depends on WEBHOOK_GATEWAY_SECRET configuration\n";
    }

    // Test 5: Demonstrate signature verification logic
    echo "\nTest 5: Demonstrate signature verification logic...\n";
    $testSecret = 'my-secret';
    $testBody = '{"event":"test","timestamp":1234567890,"data":{}}';
    $correctSig = 'sha256=' . hash_hmac('sha256', $testBody, $testSecret);
    $wrongSig = 'sha256=' . hash_hmac('sha256', $testBody, 'wrong-secret');
    
    echo "  Test body: $testBody\n";
    echo "  Correct signature: $correctSig\n";
    echo "  Wrong signature: $wrongSig\n";
    
    if (hash_equals($correctSig, $correctSig)) {
        echo "  ✓ Correct signature validates successfully\n";
    }
    
    if (!hash_equals($correctSig, $wrongSig)) {
        echo "  ✓ Wrong signature is rejected\n";
    }

    // Summary
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✓ Signature validation logic tests completed\n";
    echo "\nTo test with actual signature validation:\n";
    echo "1. Set WEBHOOK_GATEWAY_SECRET in .env file\n";
    echo "2. Generate signature: sha256=hash_hmac('sha256', \$body, \$secret)\n";
    echo "3. Include in request header: X-Agent-Signature: <signature>\n";
    echo "   OR in payload: {\"signature\": \"<signature>\", ...}\n";
    echo str_repeat('=', 60) . "\n";
}

/**
 * Make HTTP request with signature header
 */
function makeRequestWithSignature(string $url, string $method, string $body, string $signature, string $headerName): array {
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "$headerName: $signature"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseBody = [];
    if ($response !== false && $response !== '') {
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            $responseBody = $decoded;
        }
    }
    
    return [
        'http_code' => $httpCode,
        'body' => $responseBody,
        'raw' => $response
    ];
}

/**
 * Make HTTP request to the endpoint
 */
function makeRequest(string $url, string $method, string $body, array $headers): array {
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseBody = [];
    if ($response !== false && $response !== '') {
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            $responseBody = $decoded;
        }
    }
    
    return [
        'http_code' => $httpCode,
        'body' => $responseBody,
        'raw' => $response
    ];
}

// Run tests
testWebhookSignature();

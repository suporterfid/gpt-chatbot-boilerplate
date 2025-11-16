<?php
/**
 * Test suite for Webhook Inbound Endpoint (SPEC §4)
 *
 * Validates the public/webhook/inbound.php entrypoint against requirements:
 * - POST method validation
 * - Content-Type validation
 * - Request body validation
 * - Integration with WebhookGatewayService
 * - Standardized JSON responses
 */

declare(strict_types=1);

function testWebhookInbound(): void {
    echo "\n=== Testing Webhook Inbound Endpoint (SPEC §4) ===\n\n";

    $baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8888';
    $endpoint = $baseUrl . '/public/webhook/inbound.php';
    
    $allPassed = true;

    // Test 1: Reject non-POST requests
    echo "Test 1: Reject GET request...\n";
    $result = makeRequest($endpoint, 'GET', null, ['Content-Type: application/json']);
    if ($result['http_code'] === 405 && isset($result['body']['error']) && $result['body']['error'] === 'method_not_allowed') {
        echo "  ✓ GET request rejected with 405\n";
    } else {
        echo "  ✗ Expected 405 method_not_allowed, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 2: Reject requests without JSON Content-Type
    echo "\nTest 2: Reject request without application/json Content-Type...\n";
    $result = makeRequest($endpoint, 'POST', '{"event":"test"}', ['Content-Type: text/plain']);
    if ($result['http_code'] === 415 && isset($result['body']['error']) && $result['body']['error'] === 'unsupported_media_type') {
        echo "  ✓ Request rejected with 415 unsupported_media_type\n";
    } else {
        echo "  ✗ Expected 415 unsupported_media_type, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 3: Reject empty body
    echo "\nTest 3: Reject empty request body...\n";
    $result = makeRequest($endpoint, 'POST', '', ['Content-Type: application/json']);
    if ($result['http_code'] === 400 && isset($result['body']['error']) && $result['body']['error'] === 'empty_body') {
        echo "  ✓ Empty body rejected with 400\n";
    } else {
        echo "  ✗ Expected 400 empty_body, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 4: Reject invalid JSON
    echo "\nTest 4: Reject invalid JSON...\n";
    $result = makeRequest($endpoint, 'POST', '{invalid json}', ['Content-Type: application/json']);
    if ($result['http_code'] === 400 && isset($result['body']['error']) && $result['body']['error'] === 'invalid_json') {
        echo "  ✓ Invalid JSON rejected with 400\n";
    } else {
        echo "  ✗ Expected 400 invalid_json, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 5: Reject missing event field
    echo "\nTest 5: Reject payload without 'event' field...\n";
    $payload = json_encode([
        'timestamp' => time(),
        'data' => ['test' => 'value']
    ]);
    $result = makeRequest($endpoint, 'POST', $payload, ['Content-Type: application/json']);
    if ($result['http_code'] === 400 && isset($result['body']['error']) && $result['body']['error'] === 'invalid_event') {
        echo "  ✓ Missing event field rejected with 400\n";
    } else {
        echo "  ✗ Expected 400 invalid_event, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 6: Reject missing timestamp
    echo "\nTest 6: Reject payload without 'timestamp' field...\n";
    $payload = json_encode([
        'event' => 'test.event',
        'data' => ['test' => 'value']
    ]);
    $result = makeRequest($endpoint, 'POST', $payload, ['Content-Type: application/json']);
    if ($result['http_code'] === 400 && isset($result['body']['error']) && $result['body']['error'] === 'invalid_timestamp') {
        echo "  ✓ Missing timestamp rejected with 400\n";
    } else {
        echo "  ✗ Expected 400 invalid_timestamp, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Test 7: Accept valid payload without signature (when gateway_secret is empty)
    echo "\nTest 7: Accept valid payload (no signature required when secret is empty)...\n";
    $payload = json_encode([
        'event' => 'order.created',
        'timestamp' => time(),
        'data' => ['order_id' => 'A12345']
    ]);
    $result = makeRequest($endpoint, 'POST', $payload, ['Content-Type: application/json']);
    if ($result['http_code'] === 200 && isset($result['body']['status']) && $result['body']['status'] === 'received') {
        echo "  ✓ Valid payload accepted with 200\n";
        echo "    Response: " . json_encode($result['body']) . "\n";
    } else {
        echo "  ✗ Expected 200 with status:received, got: " . $result['http_code'] . "\n";
        echo "    Response: " . json_encode($result['body']) . "\n";
        $allPassed = false;
    }

    // Test 8: Validate response structure
    echo "\nTest 8: Validate response structure...\n";
    if (isset($result['body']['status']) && $result['body']['status'] === 'received' &&
        isset($result['body']['event']) && $result['body']['event'] === 'order.created' &&
        isset($result['body']['received_at']) && is_int($result['body']['received_at'])) {
        echo "  ✓ Response has correct structure (status, event, received_at)\n";
    } else {
        echo "  ✗ Response structure is incorrect\n";
        $allPassed = false;
    }

    // Test 9: Test with timestamp outside tolerance window (if configured)
    echo "\nTest 9: Test timestamp outside tolerance window...\n";
    $oldTimestamp = time() - 400; // Assuming default tolerance is 300 seconds
    $payload = json_encode([
        'event' => 'test.old',
        'timestamp' => $oldTimestamp,
        'data' => ['test' => 'value']
    ]);
    $result = makeRequest($endpoint, 'POST', $payload, ['Content-Type: application/json']);
    // Note: This might pass if tolerance is set to 0 or high value
    if ($result['http_code'] === 422 || $result['http_code'] === 200) {
        echo "  ℹ Timestamp tolerance test: HTTP " . $result['http_code'] . " (depends on config)\n";
    } else {
        echo "  ? Unexpected response: " . $result['http_code'] . "\n";
    }

    // Test 10: Test with data field as array
    echo "\nTest 10: Accept payload with data as array...\n";
    $payload = json_encode([
        'event' => 'user.updated',
        'timestamp' => time(),
        'data' => ['user_id' => 'U123', 'name' => 'Test User']
    ]);
    $result = makeRequest($endpoint, 'POST', $payload, ['Content-Type: application/json']);
    if ($result['http_code'] === 200) {
        echo "  ✓ Payload with data array accepted\n";
    } else {
        echo "  ✗ Expected 200, got: " . $result['http_code'] . "\n";
        $allPassed = false;
    }

    // Summary
    echo "\n" . str_repeat('=', 60) . "\n";
    if ($allPassed) {
        echo "✓ All webhook inbound tests PASSED\n";
    } else {
        echo "✗ Some tests FAILED\n";
        exit(1);
    }
    echo str_repeat('=', 60) . "\n";
}

/**
 * Make HTTP request to the endpoint
 */
function makeRequest(string $url, string $method, ?string $body, array $headers): array {
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    
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
testWebhookInbound();

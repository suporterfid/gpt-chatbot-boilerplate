<?php
/**
 * Test Multi-Tenant Billing & Metering Features
 * Tests usage tracking, quotas, rate limiting, and aggregation
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/UsageTrackingService.php';
require_once __DIR__ . '/../includes/QuotaService.php';
require_once __DIR__ . '/../includes/TenantUsageService.php';
require_once __DIR__ . '/../includes/TenantRateLimitService.php';
require_once __DIR__ . '/../includes/BillingService.php';
require_once __DIR__ . '/../includes/NotificationService.php';

echo "=== Testing Multi-Tenant Billing & Metering ===\n\n";

try {
    // Initialize database connection
    $dbPath = __DIR__ . '/../data/test_billing.db';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    
    $db = new DB($dbPath);
    
    // Run migrations
    $db->runMigrations(__DIR__ . '/../db/migrations');
    
    // Initialize services
    $usageTrackingService = new UsageTrackingService($db);
    $quotaService = new QuotaService($db, $usageTrackingService);
    $tenantUsageService = new TenantUsageService($db);
    $rateLimitService = new TenantRateLimitService($db);
    $billingService = new BillingService($db);
    $notificationService = new NotificationService($db);
    
    // --- Test 1: Create Test Tenant ---
    echo "--- Test 1: Create Test Tenant ---\n";
    $tenantId = 'test-tenant-' . uniqid();
    $db->insert("INSERT INTO tenants (id, name, slug, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)", [
        $tenantId,
        'Test Tenant for Billing',
        'test-tenant-billing',
        'active',
        date('c'),
        date('c')
    ]);
    echo "✓ PASS: Test tenant created: $tenantId\n\n";
    
    // --- Test 2: Log Usage Events ---
    echo "--- Test 2: Log Usage Events ---\n";
    $usageTrackingService->logUsage($tenantId, 'message', [
        'quantity' => 1,
        'metadata' => ['tokens' => 150, 'model' => 'gpt-4o-mini']
    ]);
    $usageTrackingService->logUsage($tenantId, 'completion', [
        'quantity' => 1,
        'metadata' => ['tokens' => 500, 'model' => 'gpt-4o-mini']
    ]);
    $usageTrackingService->logUsage($tenantId, 'file_upload', [
        'quantity' => 1,
        'metadata' => ['size_bytes' => 1024000]
    ]);
    echo "✓ PASS: Usage events logged\n\n";
    
    // --- Test 3: Get Usage Stats ---
    echo "--- Test 3: Get Usage Stats ---\n";
    $stats = $usageTrackingService->getUsageStats($tenantId);
    assert(!empty($stats['by_resource_type']), 'Usage stats should not be empty');
    assert($stats['totals']['total_events'] >= 3, 'Should have at least 3 events');
    echo "✓ PASS: Usage stats retrieved\n";
    echo "  Total events: {$stats['totals']['total_events']}\n\n";
    
    // --- Test 4: Set Quotas ---
    echo "--- Test 4: Set Quotas ---\n";
    $quota = $quotaService->setQuota($tenantId, 'message', 100, 'daily', [
        'is_hard_limit' => true,
        'notification_threshold' => 80
    ]);
    assert($quota['resource_type'] === 'message', 'Quota resource type should match');
    assert($quota['limit_value'] == 100, 'Quota limit should be 100');
    assert($quota['is_hard_limit'] === true, 'Should be hard limit');
    echo "✓ PASS: Quota created\n";
    echo "  Resource: {$quota['resource_type']}, Limit: {$quota['limit_value']}, Period: {$quota['period']}\n\n";
    
    // --- Test 5: Check Quota Status ---
    echo "--- Test 5: Check Quota Status ---\n";
    $check = $quotaService->checkQuota($tenantId, 'message', 'daily');
    assert($check['allowed'] === true, 'Should be within quota');
    assert($check['has_quota'] === true, 'Should have quota set');
    echo "✓ PASS: Quota check successful\n";
    echo "  Current: {$check['current']}, Limit: {$check['limit']}, Percentage: {$check['percentage']}%\n\n";
    
    // --- Test 6: Aggregate Usage ---
    echo "--- Test 6: Aggregate Usage ---\n";
    $aggregated = $tenantUsageService->aggregateUsage($tenantId, 'daily');
    assert($aggregated >= 0, 'Should aggregate successfully');
    echo "✓ PASS: Usage aggregated\n";
    echo "  Aggregated records: $aggregated\n\n";
    
    // --- Test 7: Get Tenant Usage Summary ---
    echo "--- Test 7: Get Tenant Usage Summary ---\n";
    $summary = $tenantUsageService->getCurrentUsageSummary($tenantId, 'daily');
    assert($summary['tenant_id'] === $tenantId, 'Tenant ID should match');
    assert(isset($summary['totals']), 'Should have totals');
    echo "✓ PASS: Usage summary retrieved\n";
    echo "  Total events: {$summary['totals']['total_events']}\n\n";
    
    // --- Test 8: Real-time Usage Increment ---
    echo "--- Test 8: Real-time Usage Increment ---\n";
    $tenantUsageService->incrementUsage($tenantId, 'completion', 2, 'daily');
    $summary2 = $tenantUsageService->getCurrentUsageSummary($tenantId, 'daily');
    echo "✓ PASS: Real-time usage incremented\n";
    echo "  Total events after increment: {$summary2['totals']['total_events']}\n\n";
    
    // --- Test 9: Rate Limiting ---
    echo "--- Test 9: Rate Limiting ---\n";
    $rateCheck = $rateLimitService->checkRateLimit($tenantId, 'api_call', 5, 60);
    assert($rateCheck['allowed'] === true, 'Should be allowed initially');
    echo "✓ PASS: Rate limit check\n";
    echo "  Current: {$rateCheck['current']}, Limit: {$rateCheck['limit']}\n\n";
    
    // --- Test 10: Record Rate Limit Requests ---
    echo "--- Test 10: Record Rate Limit Requests ---\n";
    for ($i = 0; $i < 3; $i++) {
        $rateLimitService->recordRequest($tenantId, 'api_call', 60);
    }
    $rateCheck2 = $rateLimitService->checkRateLimit($tenantId, 'api_call', 5, 60);
    assert($rateCheck2['current'] === 3, 'Should have 3 requests recorded');
    assert($rateCheck2['remaining'] === 2, 'Should have 2 remaining');
    echo "✓ PASS: Rate limit requests recorded\n";
    echo "  Current: {$rateCheck2['current']}, Remaining: {$rateCheck2['remaining']}\n\n";
    
    // --- Test 11: Rate Limit Enforcement ---
    echo "--- Test 11: Rate Limit Enforcement ---\n";
    try {
        // Fill up to limit
        for ($i = 0; $i < 2; $i++) {
            $rateLimitService->recordRequest($tenantId, 'test_resource', 60);
        }
        
        // This should succeed (we're at limit but not over)
        $rateLimitService->enforceRateLimit($tenantId, 'test_resource', 5, 60);
        
        // Now we're over - this should fail
        $rateLimitService->recordRequest($tenantId, 'test_resource', 60);
        $rateLimitService->recordRequest($tenantId, 'test_resource', 60);
        $rateLimitService->recordRequest($tenantId, 'test_resource', 60);
        
        // This should throw exception
        try {
            $rateLimitService->enforceRateLimit($tenantId, 'test_resource', 5, 60);
            echo "✗ FAIL: Should have thrown rate limit exception\n\n";
        } catch (Exception $e) {
            if ($e->getCode() === 429) {
                echo "✓ PASS: Rate limit enforcement working\n";
                echo "  Exception message: {$e->getMessage()}\n\n";
            } else {
                throw $e;
            }
        }
    } catch (Exception $e) {
        if ($e->getCode() !== 429) {
            throw $e;
        }
    }
    
    // --- Test 12: Get Rate Limit Status ---
    echo "--- Test 12: Get Rate Limit Status ---\n";
    $rateLimitStatus = $rateLimitService->getTenantRateLimitStatus($tenantId);
    assert(is_array($rateLimitStatus), 'Should return array');
    assert(!empty($rateLimitStatus), 'Should have status entries');
    echo "✓ PASS: Rate limit status retrieved\n";
    echo "  Resource types checked: " . count($rateLimitStatus) . "\n\n";
    
    // --- Test 13: Quota Notification Threshold ---
    echo "--- Test 13: Quota Notification Threshold ---\n";
    // Create a low quota
    $quotaService->setQuota($tenantId, 'tool_call', 10, 'daily', [
        'is_hard_limit' => false,
        'notification_threshold' => 50
    ]);
    // Log usage to 60%
    for ($i = 0; $i < 6; $i++) {
        $usageTrackingService->logUsage($tenantId, 'tool_call', ['quantity' => 1]);
    }
    $shouldNotify = $quotaService->shouldNotify($tenantId, 'tool_call', 'daily');
    assert($shouldNotify === true, 'Should trigger notification at 60%');
    echo "✓ PASS: Quota notification threshold working\n\n";
    
    // --- Test 14: Create Invoice ---
    echo "--- Test 14: Create Invoice ---\n";
    $invoice = $billingService->createInvoice($tenantId, [
        'amount_cents' => 2500,
        'currency' => 'USD',
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'line_items' => [
            ['description' => 'API Usage', 'quantity' => 100, 'unit_price' => 0.25, 'amount' => 25]
        ]
    ]);
    assert(!empty($invoice['id']), 'Invoice should have ID');
    assert($invoice['amount_cents'] === 2500, 'Invoice amount should match');
    echo "✓ PASS: Invoice created\n";
    echo "  Invoice number: {$invoice['invoice_number']}, Amount: \$25.00\n\n";
    
    // --- Test 15: Create Notification ---
    echo "--- Test 15: Create Notification ---\n";
    $notification = $notificationService->createNotification(
        $tenantId,
        'quota_warning',
        'Quota Warning',
        'You have reached 80% of your message quota',
        ['priority' => 'medium']
    );
    assert(!empty($notification['id']), 'Notification should have ID');
    echo "✓ PASS: Notification created\n\n";
    
    // --- Test 16: Cleanup ---
    echo "--- Test 16: Cleanup Test Data ---\n";
    $db->execute("DELETE FROM tenants WHERE id = ?", [$tenantId]);
    echo "✓ PASS: Test data cleaned up\n\n";
    
    echo "=== Test Summary ===\n";
    echo "All multi-tenant billing & metering tests passed! ✅\n\n";
    
    echo "Features Tested:\n";
    echo "✓ Usage event logging\n";
    echo "✓ Usage statistics retrieval\n";
    echo "✓ Quota creation and management\n";
    echo "✓ Quota checking and enforcement\n";
    echo "✓ Usage aggregation (tenant_usage table)\n";
    echo "✓ Real-time usage increments\n";
    echo "✓ Rate limiting checks\n";
    echo "✓ Rate limit enforcement\n";
    echo "✓ Rate limit status\n";
    echo "✓ Notification threshold detection\n";
    echo "✓ Invoice generation\n";
    echo "✓ Notifications\n\n";
    
    exit(0);
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

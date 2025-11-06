<?php
/**
 * Test Billing Services
 * Tests the core billing and metering functionality
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantService.php';
require_once __DIR__ . '/../includes/UsageTrackingService.php';
require_once __DIR__ . '/../includes/QuotaService.php';
require_once __DIR__ . '/../includes/BillingService.php';
require_once __DIR__ . '/../includes/NotificationService.php';

echo "=== Testing Billing Services ===\n\n";

// Initialize database
$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? null,
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
];
$db = new DB($dbConfig);

// Run migrations
echo "--- Running Migrations ---\n";
$db->runMigrations(__DIR__ . '/../db/migrations');
echo "✓ Migrations complete\n\n";

// Initialize services
$tenantService = new TenantService($db);
$usageTrackingService = new UsageTrackingService($db);
$quotaService = new QuotaService($db, $usageTrackingService);
$billingService = new BillingService($db);
$notificationService = new NotificationService($db);

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Create a test tenant
echo "--- Test 1: Create Test Tenant ---\n";
try {
    $tenant = $tenantService->createTenant([
        'name' => 'Billing Test Corp',
        'slug' => 'billing-test-' . time(),
        'status' => 'active',
        'billing_email' => 'billing@test.com'
    ]);
    echo "✓ PASS: Tenant created with ID: {$tenant['id']}\n";
    $testsPassed++;
    $testTenantId = $tenant['id'];
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
    exit(1);
}
echo "\n";

// Test 2: Log usage events
echo "--- Test 2: Log Usage Events ---\n";
try {
    // Log some messages
    $usageTrackingService->logUsage($testTenantId, UsageTrackingService::RESOURCE_MESSAGE, [
        'quantity' => 1,
        'metadata' => ['content_length' => 100]
    ]);
    $usageTrackingService->logUsage($testTenantId, UsageTrackingService::RESOURCE_MESSAGE, [
        'quantity' => 1,
        'metadata' => ['content_length' => 150]
    ]);
    $usageTrackingService->logUsage($testTenantId, UsageTrackingService::RESOURCE_COMPLETION, [
        'quantity' => 1,
        'metadata' => ['tokens' => 500]
    ]);
    echo "✓ PASS: Usage events logged\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 3: Get usage statistics
echo "--- Test 3: Get Usage Statistics ---\n";
try {
    $stats = $usageTrackingService->getUsageStats($testTenantId);
    $totalEvents = (int)$stats['totals']['total_events'];
    
    if ($totalEvents === 3) {
        echo "✓ PASS: Usage stats correct (3 events logged)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Expected 3 events, got {$totalEvents}\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 4: Create quotas
echo "--- Test 4: Create Quotas ---\n";
try {
    $quota = $quotaService->setQuota(
        $testTenantId,
        UsageTrackingService::RESOURCE_MESSAGE,
        10,
        QuotaService::PERIOD_DAILY,
        ['is_hard_limit' => false, 'notification_threshold' => 80]
    );
    
    if ($quota['limit_value'] == 10) {
        echo "✓ PASS: Quota created with limit 10\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Quota limit mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 5: Check quota status
echo "--- Test 5: Check Quota Status ---\n";
try {
    $check = $quotaService->checkQuota(
        $testTenantId,
        UsageTrackingService::RESOURCE_MESSAGE,
        QuotaService::PERIOD_DAILY
    );
    
    if ($check['allowed'] && $check['current'] == 2 && $check['limit'] == 10) {
        echo "✓ PASS: Quota check correct (2/10 used)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Quota check failed\n";
        echo "  Allowed: " . ($check['allowed'] ? 'true' : 'false') . "\n";
        echo "  Current: {$check['current']}, Limit: {$check['limit']}\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 6: Create subscription
echo "--- Test 6: Create Subscription ---\n";
try {
    $subscription = $billingService->createSubscription($testTenantId, [
        'plan_type' => BillingService::PLAN_PROFESSIONAL,
        'billing_cycle' => BillingService::CYCLE_MONTHLY,
        'price_cents' => 9900,
        'status' => BillingService::STATUS_ACTIVE
    ]);
    
    if ($subscription['plan_type'] === BillingService::PLAN_PROFESSIONAL) {
        echo "✓ PASS: Subscription created\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Subscription plan mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 7: Create invoice
echo "--- Test 7: Create Invoice ---\n";
try {
    $invoice = $billingService->createInvoice($testTenantId, [
        'amount_cents' => 9900,
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'line_items' => [
            [
                'description' => 'Professional Plan - Monthly',
                'amount_cents' => 9900,
                'quantity' => 1
            ]
        ]
    ]);
    
    if ($invoice['amount_cents'] == 9900) {
        echo "✓ PASS: Invoice created\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Invoice amount mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 8: Create notification
echo "--- Test 8: Create Notification ---\n";
try {
    $notification = $notificationService->sendQuotaWarning(
        $testTenantId,
        UsageTrackingService::RESOURCE_MESSAGE,
        8,
        10,
        80
    );
    
    if ($notification['type'] === NotificationService::TYPE_QUOTA_WARNING) {
        echo "✓ PASS: Notification created\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Notification type mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 9: List notifications
echo "--- Test 9: List Notifications ---\n";
try {
    $notifications = $notificationService->listNotifications($testTenantId);
    
    if (count($notifications) > 0) {
        echo "✓ PASS: Notifications retrieved\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: No notifications found\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 10: Update subscription
echo "--- Test 10: Update Subscription ---\n";
try {
    $updated = $billingService->updateSubscription($testTenantId, [
        'plan_type' => BillingService::PLAN_ENTERPRISE
    ]);
    
    if ($updated['plan_type'] === BillingService::PLAN_ENTERPRISE) {
        echo "✓ PASS: Subscription updated\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Subscription not updated\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Total tests passed: $testsPassed\n";
echo "Total tests failed: $testsFailed\n\n";

if ($testsFailed === 0) {
    echo "✅ All tests passed!\n";
    exit(0);
} else {
    echo "❌ Some tests failed\n";
    exit(1);
}

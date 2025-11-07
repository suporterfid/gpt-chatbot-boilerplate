#!/usr/bin/env php
<?php
/**
 * Check Quotas and Send Alerts
 * Run this script periodically via cron to check tenant quotas and send notifications
 * 
 * Usage:
 *   php scripts/check_quotas.php [--tenant-id=xxx]
 * 
 * Cron example:
 *   # Check quotas every 5 minutes
 *   */5 * * * * cd /path/to/app && php scripts/check_quotas.php
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/QuotaService.php';
require_once __DIR__ . '/../includes/UsageTrackingService.php';
require_once __DIR__ . '/../includes/NotificationService.php';

// Parse command line arguments
$options = getopt('', ['tenant-id::', 'verbose::']);
$tenantId = $options['tenant-id'] ?? null;
$verbose = isset($options['verbose']);

try {
    // Initialize services
    $db = DB::getInstance();
    $usageTrackingService = new UsageTrackingService($db);
    $quotaService = new QuotaService($db, $usageTrackingService);
    $notificationService = new NotificationService($db);
    
    if ($verbose) {
        echo "Starting quota check...\n";
    }
    
    // Get all tenants or specific tenant
    if ($tenantId) {
        $tenants = [$db->queryOne("SELECT * FROM tenants WHERE id = ?", [$tenantId])];
    } else {
        $tenants = $db->query("SELECT * FROM tenants WHERE status = 'active'");
    }
    
    if (empty($tenants)) {
        if ($verbose) {
            echo "No active tenants found.\n";
        }
        exit(0);
    }
    
    $alertsSent = 0;
    $quotasChecked = 0;
    
    foreach ($tenants as $tenant) {
        if (!$tenant) continue;
        
        $tid = $tenant['id'];
        
        if ($verbose) {
            echo "\nChecking tenant: {$tenant['name']} ($tid)\n";
        }
        
        // Get all quotas for this tenant
        $quotas = $quotaService->listQuotas($tid);
        
        foreach ($quotas as $quota) {
            $quotasChecked++;
            
            // Check if notification should be sent
            $shouldNotify = $quotaService->shouldNotify(
                $tid,
                $quota['resource_type'],
                $quota['period']
            );
            
            if ($shouldNotify) {
                $status = $quotaService->checkQuota(
                    $tid,
                    $quota['resource_type'],
                    $quota['period']
                );
                
                // Check if notification was already sent recently (avoid spam)
                $recentNotifications = $notificationService->listNotifications($tid, [
                    'type' => 'quota_warning',
                    'limit' => 1
                ]);
                
                $shouldSend = true;
                if (!empty($recentNotifications)) {
                    $lastNotification = $recentNotifications[0];
                    $lastNotificationTime = strtotime($lastNotification['created_at']);
                    $timeSinceLastNotification = time() - $lastNotificationTime;
                    
                    // Only send if last notification was more than 1 hour ago
                    if ($timeSinceLastNotification < 3600) {
                        $shouldSend = false;
                    }
                }
                
                if ($shouldSend) {
                    // Send quota warning notification
                    $notificationService->sendQuotaWarning(
                        $tid,
                        $quota['resource_type'],
                        $status['current'],
                        $status['limit'],
                        $status['percentage']
                    );
                    
                    $alertsSent++;
                    
                    if ($verbose) {
                        echo "  ⚠️  Quota warning: {$quota['resource_type']} ({$quota['period']}) - {$status['percentage']}%\n";
                    }
                }
            }
            
            // Check for hard limit violations
            if (!$quota['is_hard_limit']) {
                continue;
            }
            
            try {
                $quotaService->enforceQuota(
                    $tid,
                    $quota['resource_type'],
                    $quota['period']
                );
            } catch (Exception $e) {
                // Hard limit exceeded - create notification
                if ($e->getCode() == 429) {
                    $status = $quotaService->checkQuota(
                        $tid,
                        $quota['resource_type'],
                        $quota['period']
                    );
                    
                    $notificationService->createNotification($tid, [
                        'type' => 'quota_exceeded',
                        'title' => 'Quota Limit Exceeded',
                        'message' => "Hard limit exceeded for {$quota['resource_type']} ({$quota['period']}). Current: {$status['current']}, Limit: {$status['limit']}",
                        'priority' => 'high',
                        'metadata' => [
                            'resource_type' => $quota['resource_type'],
                            'period' => $quota['period'],
                            'current' => $status['current'],
                            'limit' => $status['limit']
                        ]
                    ]);
                    
                    $alertsSent++;
                    
                    if ($verbose) {
                        echo "  🛑 Hard limit exceeded: {$quota['resource_type']} ({$quota['period']})\n";
                    }
                }
            }
        }
    }
    
    if ($verbose) {
        echo "\nQuota check complete!\n";
        echo "Quotas checked: $quotasChecked\n";
        echo "Alerts sent: $alertsSent\n";
    }
    
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Quota check error: " . $e->getMessage());
    exit(1);
}

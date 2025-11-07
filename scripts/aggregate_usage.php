#!/usr/bin/env php
<?php
/**
 * Aggregate Usage Data
 * Run this script periodically via cron to aggregate usage_logs into tenant_usage
 * 
 * Usage:
 *   php scripts/aggregate_usage.php [--period=daily] [--tenant-id=xxx]
 * 
 * Cron examples:
 *   # Aggregate hourly data every hour
 *   0 * * * * cd /path/to/app && php scripts/aggregate_usage.php --period=hourly
 *   
 *   # Aggregate daily data once per day
 *   0 1 * * * cd /path/to/app && php scripts/aggregate_usage.php --period=daily
 *   
 *   # Aggregate monthly data once per month
 *   0 2 1 * * cd /path/to/app && php scripts/aggregate_usage.php --period=monthly
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantUsageService.php';

// Parse command line arguments
$options = getopt('', ['period::', 'tenant-id::']);
$period = $options['period'] ?? 'daily';
$tenantId = $options['tenant-id'] ?? null;

// Validate period
$validPeriods = ['hourly', 'daily', 'monthly', 'total'];
if (!in_array($period, $validPeriods)) {
    echo "Error: Invalid period. Must be one of: " . implode(', ', $validPeriods) . "\n";
    exit(1);
}

try {
    // Initialize database
    $db = DB::getInstance();
    $tenantUsageService = new TenantUsageService($db);
    
    echo "Starting usage aggregation...\n";
    echo "Period: $period\n";
    if ($tenantId) {
        echo "Tenant ID: $tenantId\n";
    } else {
        echo "Aggregating for all tenants\n";
    }
    
    $startTime = microtime(true);
    
    // Aggregate usage
    $count = $tenantUsageService->aggregateUsage($tenantId, $period);
    
    $elapsed = round(microtime(true) - $startTime, 3);
    
    echo "Aggregation complete!\n";
    echo "Aggregated records: $count\n";
    echo "Time elapsed: {$elapsed}s\n";
    
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Usage aggregation error: " . $e->getMessage());
    exit(1);
}

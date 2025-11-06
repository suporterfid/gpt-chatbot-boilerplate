#!/usr/bin/env php
<?php
/**
 * Audit Retention Cleanup Job
 * Deletes audit conversations older than configured retention period
 * 
 * Usage: php scripts/audit_retention_cleanup.php
 * 
 * This script can be run as a cron job:
 * 0 2 * * * cd /path/to/project && php scripts/audit_retention_cleanup.php >> logs/retention.log 2>&1
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AuditService.php';

echo "[" . date('Y-m-d H:i:s') . "] Audit Retention Cleanup Job Started\n";

// Check if audit is enabled
if (!$config['auditing']['enabled']) {
    echo "[" . date('Y-m-d H:i:s') . "] Audit service is not enabled. Exiting.\n";
    exit(0);
}

try {
    $auditService = new AuditService($config['auditing']);
    
    $retentionDays = $config['auditing']['retention_days'];
    echo "[" . date('Y-m-d H:i:s') . "] Retention period: $retentionDays days\n";
    
    $deleted = $auditService->deleteExpired($retentionDays);
    
    echo "[" . date('Y-m-d H:i:s') . "] Deleted $deleted expired audit conversations\n";
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup job completed successfully\n";
    
    exit(0);
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

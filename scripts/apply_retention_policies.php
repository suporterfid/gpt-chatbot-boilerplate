#!/usr/bin/env php
<?php
/**
 * Apply Data Retention Policies
 * 
 * This script applies automated data retention policies across all tenants.
 * Should be run via cron (e.g., weekly or monthly).
 * 
 * Usage:
 *   php scripts/apply_retention_policies.php [--tenant-id=ID] [--dry-run]
 * 
 * Options:
 *   --tenant-id=ID    Only apply to specific tenant
 *   --conversation-days=N  Delete conversations older than N days (default: 180)
 *   --audit-days=N    Delete audit logs older than N days (default: 365)
 *   --usage-days=N    Archive usage logs older than N days (default: 730)
 *   --dry-run         Show what would be deleted without actually deleting
 *   --verbose         Show detailed output
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/ComplianceService.php';

// Parse command line arguments
$options = getopt('', [
    'tenant-id:',
    'conversation-days:',
    'audit-days:',
    'usage-days:',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Usage: php scripts/apply_retention_policies.php [options]\n\n";
    echo "Options:\n";
    echo "  --tenant-id=ID           Only apply to specific tenant\n";
    echo "  --conversation-days=N    Delete conversations older than N days (default: 180)\n";
    echo "  --audit-days=N           Delete audit logs older than N days (default: 365)\n";
    echo "  --usage-days=N           Archive usage logs older than N days (default: 730)\n";
    echo "  --dry-run                Show what would be deleted without actually deleting\n";
    echo "  --verbose                Show detailed output\n";
    echo "  --help                   Show this help message\n\n";
    exit(0);
}

$specificTenantId = $options['tenant-id'] ?? null;
$conversationDays = $options['conversation-days'] ?? 180;
$auditDays = $options['audit-days'] ?? 365;
$usageDays = $options['usage-days'] ?? 730;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Initialize database
$db = DB::getInstance();

// Function to log output
function logMessage($message, $forceOutput = false) {
    global $verbose;
    if ($verbose || $forceOutput) {
        echo "[" . date('Y-m-d H:i:s') . "] $message\n";
    }
}

// Function to log to file
function logToFile($message) {
    $logFile = __DIR__ . '/../logs/retention_policy.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Starting retention policy application", true);
logMessage("Parameters: Conversations={$conversationDays}d, Audit={$auditDays}d, Usage={$usageDays}d", true);

if ($dryRun) {
    logMessage("DRY RUN MODE - No data will be deleted", true);
}

// Get tenants to process
if ($specificTenantId) {
    $tenants = $db->query(
        "SELECT id, name, slug, status FROM tenants WHERE id = ?",
        [$specificTenantId]
    );
    
    if (empty($tenants)) {
        logMessage("ERROR: Tenant ID {$specificTenantId} not found", true);
        exit(1);
    }
} else {
    $tenants = $db->query(
        "SELECT id, name, slug, status FROM tenants WHERE status = 'active' ORDER BY id"
    );
}

logMessage("Processing " . count($tenants) . " tenant(s)", true);

$totalSummary = [
    'tenants_processed' => 0,
    'tenants_skipped' => 0,
    'tenants_failed' => 0,
    'total_records_deleted' => [
        'conversations' => 0,
        'messages' => 0,
        'audit_events' => 0,
        'usage_logs' => 0,
        'expired_consents' => 0
    ],
    'total_aggregated' => 0
];

// Process each tenant
foreach ($tenants as $tenant) {
    $tenantId = $tenant['id'];
    $tenantName = $tenant['name'];
    $tenantSlug = $tenant['slug'];
    
    logMessage("Processing tenant: {$tenantName} (ID: {$tenantId}, Slug: {$tenantSlug})", true);
    
    // Skip suspended or inactive tenants
    if ($tenant['status'] !== 'active') {
        logMessage("  Skipping: Tenant status is {$tenant['status']}", true);
        $totalSummary['tenants_skipped']++;
        logToFile("Skipped tenant {$tenantId} ({$tenantName}): status={$tenant['status']}");
        continue;
    }
    
    try {
        // Create compliance service for this tenant
        $complianceService = new ComplianceService($db, $tenantId);
        
        if ($dryRun) {
            // In dry-run mode, just count what would be deleted
            logMessage("  DRY RUN: Counting records that would be deleted...");
            
            $conversationCutoff = date('Y-m-d H:i:s', strtotime("-{$conversationDays} days"));
            $auditCutoff = date('Y-m-d H:i:s', strtotime("-{$auditDays} days"));
            $usageCutoff = date('Y-m-d H:i:s', strtotime("-{$usageDays} days"));
            
            $convCount = $db->query(
                "SELECT COUNT(*) as c FROM audit_conversations 
                 WHERE tenant_id = ? AND created_at < ?",
                [$tenantId, $conversationCutoff]
            )[0]['c'];
            
            $msgCount = $db->query(
                "SELECT COUNT(*) as c FROM channel_messages 
                 WHERE tenant_id = ? AND created_at < ?",
                [$tenantId, $conversationCutoff]
            )[0]['c'];
            
            $auditCount = $db->query(
                "SELECT COUNT(*) as c FROM audit_events 
                 WHERE tenant_id = ? AND created_at < ? AND event_type != 'data_deletion'",
                [$tenantId, $auditCutoff]
            )[0]['c'];
            
            $usageCount = $db->query(
                "SELECT COUNT(*) as c FROM usage_logs 
                 WHERE tenant_id = ? AND created_at < ?",
                [$tenantId, $usageCutoff]
            )[0]['c'];
            
            $expiredCount = $db->query(
                "SELECT COUNT(*) as c FROM user_consents 
                 WHERE tenant_id = ? AND expires_at IS NOT NULL AND expires_at < NOW()",
                [$tenantId]
            )[0]['c'];
            
            logMessage("  Would delete:", true);
            logMessage("    - Conversations: {$convCount}", true);
            logMessage("    - Messages: {$msgCount}", true);
            logMessage("    - Audit events: {$auditCount}", true);
            logMessage("    - Usage logs: {$usageCount} (would be aggregated)", true);
            logMessage("    - Expired consents: {$expiredCount}", true);
            
            $totalSummary['tenants_processed']++;
            
        } else {
            // Actually apply the retention policy
            logMessage("  Applying retention policy...");
            
            $summary = $complianceService->applyRetentionPolicy(
                $conversationDays,
                $auditDays,
                $usageDays
            );
            
            if ($summary['status'] === 'completed') {
                logMessage("  ✓ Successfully applied retention policy", true);
                logMessage("    - Conversations deleted: {$summary['records_deleted']['conversations']}", true);
                logMessage("    - Messages deleted: {$summary['records_deleted']['messages']}", true);
                logMessage("    - Audit events deleted: {$summary['records_deleted']['audit_events']}", true);
                logMessage("    - Usage logs deleted: {$summary['records_deleted']['usage_logs']}", true);
                logMessage("    - Usage logs aggregated: {$summary['aggregated']}", true);
                logMessage("    - Expired consents deleted: {$summary['records_deleted']['expired_consents']}", true);
                
                // Update totals
                $totalSummary['tenants_processed']++;
                foreach ($summary['records_deleted'] as $key => $count) {
                    if (isset($totalSummary['total_records_deleted'][$key])) {
                        $totalSummary['total_records_deleted'][$key] += $count;
                    }
                }
                $totalSummary['total_aggregated'] += $summary['aggregated'];
                
                // Log to file
                logToFile("Applied retention policy for tenant {$tenantId} ({$tenantName}): " . json_encode($summary['records_deleted']));
                
            } else {
                logMessage("  ✗ Failed to apply retention policy: {$summary['error']}", true);
                $totalSummary['tenants_failed']++;
                logToFile("FAILED for tenant {$tenantId} ({$tenantName}): {$summary['error']}");
            }
        }
        
    } catch (Exception $e) {
        logMessage("  ✗ Exception: " . $e->getMessage(), true);
        $totalSummary['tenants_failed']++;
        logToFile("EXCEPTION for tenant {$tenantId} ({$tenantName}): " . $e->getMessage());
    }
    
    logMessage("");
}

// Final summary
logMessage("=== SUMMARY ===", true);
logMessage("Tenants processed: {$totalSummary['tenants_processed']}", true);
logMessage("Tenants skipped: {$totalSummary['tenants_skipped']}", true);
logMessage("Tenants failed: {$totalSummary['tenants_failed']}", true);

if (!$dryRun) {
    logMessage("\nTotal records deleted:", true);
    foreach ($totalSummary['total_records_deleted'] as $type => $count) {
        logMessage("  - " . ucfirst($type) . ": {$count}", true);
    }
    logMessage("  - Usage logs aggregated: {$totalSummary['total_aggregated']}", true);
}

logMessage("\nRetention policy application completed", true);

// Exit with appropriate code
exit($totalSummary['tenants_failed'] > 0 ? 1 : 0);

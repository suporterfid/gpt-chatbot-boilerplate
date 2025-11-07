#!/usr/bin/env php
<?php
/**
 * Determine Appropriate Backup Frequency Based on Active Tenant Tiers
 * 
 * This script queries the database to find the highest tier tenant and
 * returns the appropriate backup frequency for that tier.
 * 
 * Usage: php scripts/determine_backup_frequency.php
 * 
 * Output: hourly|6hourly|daily|weekly
 * Exit codes: 0 = success, 1 = error
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

try {
    $db = DB::getInstance();
    
    // Query for highest tier among active tenants
    // Check both plan column and settings_json for tier information
    $sql = "
        SELECT 
            CASE
                WHEN LOWER(COALESCE(plan, '')) LIKE '%enterprise%' OR LOWER(COALESCE(settings_json, '{}')) LIKE '%enterprise%' THEN 'hourly'
                WHEN LOWER(COALESCE(plan, '')) LIKE '%pro%' OR LOWER(COALESCE(settings_json, '{}')) LIKE '%pro%' THEN '6hourly'
                WHEN LOWER(COALESCE(plan, '')) LIKE '%starter%' OR LOWER(COALESCE(settings_json, '{}')) LIKE '%starter%' THEN 'daily'
                ELSE 'weekly'
            END as frequency,
            COUNT(*) as tenant_count
        FROM tenants
        WHERE status = 'active'
        GROUP BY 1
        ORDER BY 
            CASE frequency
                WHEN 'hourly' THEN 1
                WHEN '6hourly' THEN 2
                WHEN 'daily' THEN 3
                WHEN 'weekly' THEN 4
            END
        LIMIT 1
    ";
    
    $result = $db->query($sql);
    
    if (!empty($result)) {
        $frequency = $result[0]['frequency'];
        $count = $result[0]['tenant_count'];
        
        // Output to stdout for capture by scripts
        echo $frequency;
        
        // Log to stderr for debugging (if needed)
        if (getenv('DEBUG') === 'true') {
            error_log("Determined backup frequency: $frequency (based on $count active tenant(s))");
        }
        
        exit(0);
    } else {
        // No active tenants found, use default
        echo "daily";
        
        if (getenv('DEBUG') === 'true') {
            error_log("No active tenants found, using default frequency: daily");
        }
        
        exit(0);
    }
    
} catch (Exception $e) {
    // On error, use safe default
    error_log("Error determining backup frequency: " . $e->getMessage());
    echo "daily";
    exit(1);
}

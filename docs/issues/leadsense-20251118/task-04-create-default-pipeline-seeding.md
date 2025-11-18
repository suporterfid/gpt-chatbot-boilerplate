# Task 4: Create Default Pipeline Seeding Script

## Objective
Create a migration or seeding script that initializes a default CRM pipeline with standard stages for new installations and existing LeadSense users.

## Prerequisites
- Task 1 completed (CRM tables exist)
- Task 2 completed (leads table extended)
- Understanding of UUID generation in PHP

## Overview

Every LeadSense instance needs at least one pipeline with stages. This task creates:
1. A migration that seeds the default pipeline
2. A reusable seeding function for multi-tenant scenarios
3. Backfill script for existing leads

## Files to Create

### 1. Migration: `db/migrations/045_seed_default_pipeline.sql`

**Note:** SQL migrations can't easily generate UUIDs. We'll create a companion PHP script.

```sql
-- LeadSense CRM: Seed default pipeline
-- This is a placeholder - actual seeding done via PHP script
-- See: scripts/seed_default_pipeline.php

-- This migration just ensures the PHP script has been run
-- by checking for the existence of default pipeline

-- If you need to manually verify:
-- SELECT * FROM crm_pipelines WHERE is_default = 1;
-- SELECT * FROM crm_pipeline_stages WHERE pipeline_id IN (SELECT id FROM crm_pipelines WHERE is_default = 1);
```

### 2. Seeding Script: `scripts/seed_default_pipeline.php`

```php
#!/usr/bin/env php
<?php
/**
 * Seed Default CRM Pipeline
 * 
 * Creates a default pipeline with standard stages for LeadSense CRM
 * Idempotent - safe to run multiple times
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

try {
    $db = Database::getInstance();
    
    echo "🔍 Checking for existing default pipeline...\n";
    
    // Check if default pipeline already exists
    $stmt = $db->prepare("SELECT id, name FROM crm_pipelines WHERE is_default = 1 LIMIT 1");
    $stmt->execute();
    $existingPipeline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingPipeline) {
        echo "✓ Default pipeline already exists: {$existingPipeline['name']} ({$existingPipeline['id']})\n";
        
        // Check stages
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM crm_pipeline_stages WHERE pipeline_id = ?");
        $stmt->execute([$existingPipeline['id']]);
        $stageCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "✓ Pipeline has {$stageCount} stages\n";
        
        if ($stageCount > 0) {
            echo "✅ Default pipeline already seeded. Nothing to do.\n";
            exit(0);
        }
    }
    
    echo "📝 Creating default pipeline...\n";
    
    $db->beginTransaction();
    
    // Create default pipeline
    $pipelineId = generateUUID();
    $stmt = $db->prepare("
        INSERT INTO crm_pipelines (id, client_id, name, description, is_default, color, created_at, updated_at)
        VALUES (?, NULL, ?, ?, 1, ?, datetime('now'), datetime('now'))
    ");
    $stmt->execute([
        $pipelineId,
        'Default',
        'Default pipeline for all leads',
        '#8b5cf6'  // Purple
    ]);
    
    echo "✓ Created pipeline: {$pipelineId}\n";
    
    // Define default stages
    $stages = [
        [
            'name' => 'Lead Capture',
            'slug' => 'lead_capture',
            'color' => '#a855f7',  // Purple
            'position' => 0,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Support',
            'slug' => 'support',
            'color' => '#3b82f6',  // Blue
            'position' => 1,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Commercial Lead',
            'slug' => 'commercial_lead',
            'color' => '#22c55e',  // Green
            'position' => 2,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Negotiation',
            'slug' => 'negotiation',
            'color' => '#f59e0b',  // Amber
            'position' => 3,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Closed Won',
            'slug' => 'closed_won',
            'color' => '#10b981',  // Emerald
            'position' => 4,
            'is_won' => 1,
            'is_lost' => 0,
            'is_closed' => 1
        ],
        [
            'name' => 'Closed Lost',
            'slug' => 'closed_lost',
            'color' => '#ef4444',  // Red
            'position' => 5,
            'is_won' => 0,
            'is_lost' => 1,
            'is_closed' => 1
        ]
    ];
    
    // Insert stages
    $stmt = $db->prepare("
        INSERT INTO crm_pipeline_stages 
        (id, pipeline_id, name, slug, position, color, is_won, is_lost, is_closed, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    $stageIds = [];
    foreach ($stages as $stage) {
        $stageId = generateUUID();
        $stageIds[$stage['slug']] = $stageId;
        
        $stmt->execute([
            $stageId,
            $pipelineId,
            $stage['name'],
            $stage['slug'],
            $stage['position'],
            $stage['color'],
            $stage['is_won'],
            $stage['is_lost'],
            $stage['is_closed']
        ]);
        
        echo "  ✓ Created stage: {$stage['name']} ({$stageId})\n";
    }
    
    $db->commit();
    
    echo "\n✅ Default pipeline seeded successfully!\n";
    echo "Pipeline ID: {$pipelineId}\n";
    echo "Stages: " . count($stages) . "\n";
    
    // Return IDs for further processing
    return [
        'pipeline_id' => $pipelineId,
        'stage_ids' => $stageIds
    ];
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

### 3. Backfill Script: `scripts/backfill_existing_leads.php`

```php
#!/usr/bin/env php
<?php
/**
 * Backfill Existing Leads with Default Pipeline
 * 
 * Assigns all existing leads without pipeline to the default pipeline
 * Run after seeding default pipeline
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

try {
    $db = Database::getInstance();
    
    echo "🔍 Finding default pipeline...\n";
    
    // Get default pipeline and first stage
    $stmt = $db->prepare("SELECT id FROM crm_pipelines WHERE is_default = 1 LIMIT 1");
    $stmt->execute();
    $pipeline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pipeline) {
        echo "❌ No default pipeline found. Run seed_default_pipeline.php first.\n";
        exit(1);
    }
    
    $pipelineId = $pipeline['id'];
    echo "✓ Found pipeline: {$pipelineId}\n";
    
    // Get first stage (Lead Capture)
    $stmt = $db->prepare("
        SELECT id, name FROM crm_pipeline_stages 
        WHERE pipeline_id = ? 
        ORDER BY position ASC 
        LIMIT 1
    ");
    $stmt->execute([$pipelineId]);
    $stage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stage) {
        echo "❌ No stages found in pipeline. Check seeding.\n";
        exit(1);
    }
    
    $stageId = $stage['id'];
    echo "✓ Found first stage: {$stage['name']} ({$stageId})\n";
    
    // Count leads without pipeline
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM leads WHERE pipeline_id IS NULL");
    $stmt->execute();
    $leadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($leadCount === 0) {
        echo "✅ No leads to backfill. All leads already assigned to pipelines.\n";
        exit(0);
    }
    
    echo "📊 Found {$leadCount} leads without pipeline assignment\n";
    echo "🔄 Assigning to default pipeline...\n";
    
    // Update leads
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        UPDATE leads 
        SET pipeline_id = ?, 
            stage_id = ?,
            updated_at = datetime('now')
        WHERE pipeline_id IS NULL
    ");
    $stmt->execute([$pipelineId, $stageId]);
    
    $updatedCount = $stmt->rowCount();
    
    $db->commit();
    
    echo "✅ Backfilled {$updatedCount} leads\n";
    echo "   Pipeline: {$pipelineId}\n";
    echo "   Stage: {$stage['name']}\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Integration with Migration Runner

Update `scripts/run_migrations.php` to run PHP seeding after SQL migrations:

```php
// At the end of run_migrations.php, add:

echo "\n--- Running Post-Migration Scripts ---\n";

// Seed default pipeline if CRM tables exist
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='crm_pipelines'")->fetchAll();
if (!empty($tables)) {
    echo "\n🌱 Seeding default CRM pipeline...\n";
    require_once __DIR__ . '/seed_default_pipeline.php';
    
    echo "\n🔄 Backfilling existing leads...\n";
    require_once __DIR__ . '/backfill_existing_leads.php';
}
```

## Multi-Tenant Support

For multi-tenant installations, modify seeding to create pipeline per tenant:

```php
// scripts/seed_pipeline_for_tenant.php
function seedPipelineForTenant($tenantId) {
    // Similar to seed_default_pipeline.php but with client_id = $tenantId
    // Called during tenant creation
}
```

## Stage Templates

For future extensibility, consider stage templates:

```php
// includes/CRM/StageTemplates.php
class StageTemplates {
    public static function getDefault() {
        return [
            ['name' => 'Lead Capture', 'slug' => 'lead_capture', 'color' => '#a855f7'],
            ['name' => 'Support', 'slug' => 'support', 'color' => '#3b82f6'],
            ['name' => 'Commercial Lead', 'slug' => 'commercial_lead', 'color' => '#22c55e'],
            ['name' => 'Negotiation', 'slug' => 'negotiation', 'color' => '#f59e0b'],
            ['name' => 'Closed Won', 'slug' => 'closed_won', 'color' => '#10b981', 'is_won' => true, 'is_closed' => true],
            ['name' => 'Closed Lost', 'slug' => 'closed_lost', 'color' => '#ef4444', 'is_lost' => true, 'is_closed' => true]
        ];
    }
    
    public static function getSaaS() {
        return [
            ['name' => 'Trial', 'slug' => 'trial', 'color' => '#8b5cf6'],
            ['name' => 'Active Discussion', 'slug' => 'discussion', 'color' => '#3b82f6'],
            ['name' => 'Proposal Sent', 'slug' => 'proposal', 'color' => '#22c55e'],
            ['name' => 'Contract Review', 'slug' => 'contract', 'color' => '#f59e0b'],
            ['name' => 'Customer', 'slug' => 'customer', 'color' => '#10b981', 'is_won' => true, 'is_closed' => true],
            ['name' => 'Lost', 'slug' => 'lost', 'color' => '#ef4444', 'is_lost' => true, 'is_closed' => true]
        ];
    }
}
```

## Testing

### Manual Testing

```bash
# 1. Run seeding
php scripts/seed_default_pipeline.php

# 2. Verify pipeline created
sqlite3 data/chatbot.db "SELECT * FROM crm_pipelines WHERE is_default = 1;"

# 3. Verify stages created
sqlite3 data/chatbot.db "SELECT id, name, slug, position FROM crm_pipeline_stages ORDER BY position;"

# 4. Run backfill
php scripts/backfill_existing_leads.php

# 5. Verify leads assigned
sqlite3 data/chatbot.db "SELECT COUNT(*) FROM leads WHERE pipeline_id IS NOT NULL;"
```

### Automated Test

```php
// tests/test_pipeline_seeding.php
<?php
require_once __DIR__ . '/../scripts/seed_default_pipeline.php';

$db = Database::getInstance();

// Test 1: Default pipeline exists
$stmt = $db->query("SELECT COUNT(*) as count FROM crm_pipelines WHERE is_default = 1");
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
assert($count === 1, "Expected 1 default pipeline");
echo "✓ PASS: Default pipeline exists\n";

// Test 2: Stages exist
$stmt = $db->query("SELECT COUNT(*) as count FROM crm_pipeline_stages");
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
assert($count >= 4, "Expected at least 4 stages");
echo "✓ PASS: Stages created\n";

// Test 3: Stage order correct
$stmt = $db->query("SELECT position FROM crm_pipeline_stages ORDER BY position");
$positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
assert($positions === range(0, count($positions) - 1), "Positions should be sequential");
echo "✓ PASS: Stage positions sequential\n";
```

## Idempotency

The seeding scripts are designed to be idempotent:
- Check for existing default pipeline before creating
- Skip if already seeded
- Safe to run multiple times
- Safe to run in CI/CD pipelines

## Configuration

Add to `config.php` for customization:

```php
'leadsense' => [
    'crm' => [
        'default_pipeline_name' => 'Default',
        'default_pipeline_stages' => 'default',  // 'default', 'saas', 'custom'
        'auto_assign_new_leads' => true,
        'default_stage_slug' => 'lead_capture'
    ]
]
```

## Testing Checklist

- [ ] Migration file created
- [ ] Seeding script created
- [ ] Backfill script created
- [ ] Scripts are idempotent
- [ ] UUID generation works
- [ ] Default pipeline created
- [ ] All stages created with correct order
- [ ] Colors assigned correctly
- [ ] is_won/is_lost flags set correctly
- [ ] Existing leads backfilled
- [ ] Multi-tenant scenarios considered
- [ ] Scripts integrated with migration runner

## Related Tasks
- Task 1: Create CRM tables (prerequisite)
- Task 2: Extend leads table (prerequisite)
- Task 5: CRM Pipeline Service (uses seeded data)

## References
- Spec Section 6: Migration Strategy
- Spec Section 2.2.1: crm_pipelines schema
- Spec Section 2.2.2: crm_pipeline_stages schema

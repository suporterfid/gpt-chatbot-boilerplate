# Multi-Tenant Backup & Disaster Recovery

## Overview

This document extends the general [Backup & Restore Guide](backup_restore.md) and [Disaster Recovery Runbook](disaster_recovery.md) with multi-tenant specific considerations and procedures.

## Multi-Tenant Architecture Considerations

The GPT Chatbot platform supports multi-tenancy at the database level with:
- Tenant isolation via `tenant_id` foreign keys
- Per-tenant resource ownership
- Shared database with logical separation
- Optional tenant status management (active/suspended)

This architecture impacts backup and disaster recovery in several ways:
1. **All tenants share the same database** - A database backup includes all tenant data
2. **Tenant isolation must be maintained** during recovery operations
3. **Individual tenant data can be selectively restored** if needed
4. **RPO/RTO requirements may vary by tenant tier** (free, starter, pro, enterprise)

## RPO/RTO by Tenant Tier

### Recovery Point Objective (RPO) - Maximum Acceptable Data Loss

| Tenant Tier | RPO | Backup Frequency | Justification |
|-------------|-----|------------------|---------------|
| **Enterprise** | 1 hour | Hourly | Mission-critical operations, SLA requirements |
| **Pro** | 6 hours | Every 6 hours | Business-critical, moderate SLA |
| **Starter** | 24 hours | Daily | Standard business operations |
| **Free** | 7 days | Weekly | Best-effort, no SLA |

### Recovery Time Objective (RTO) - Maximum Acceptable Downtime

| Tenant Tier | RTO | Priority | SLA Commitment |
|-------------|-----|----------|----------------|
| **Enterprise** | 30 minutes | P0 | 99.95% uptime |
| **Pro** | 2 hours | P1 | 99.9% uptime |
| **Starter** | 4 hours | P2 | 99.5% uptime |
| **Free** | Best effort | P3 | No SLA |

### Data Class Priorities

| Data Type | Enterprise | Pro | Starter | Free |
|-----------|-----------|-----|---------|------|
| Database | 1h RPO | 6h RPO | 24h RPO | 7d RPO |
| Configuration | 1h RPO | 6h RPO | 24h RPO | 7d RPO |
| Uploaded Files | 1h RPO | 6h RPO | 24h RPO | 7d RPO |
| Audit Logs | 24h RPO | 24h RPO | 7d RPO | 30d RPO |

## Backup Strategy for Multi-Tenant Systems

### 1. Shared Database Backups

All tenants are backed up together in a single database backup. This ensures:
- Consistency across all tenants at a point in time
- Referential integrity is maintained
- Simplified backup operations
- Cost-effective storage

**Implementation:**
```bash
# Standard database backup includes all tenants
./scripts/db_backup.sh

# Result: Single backup file containing all tenant data
# /data/backups/admin_sqlite_TIMESTAMP.db.gz
```

### 2. Per-Tenant Backup Schedules

While the database is backed up as a whole, the backup **frequency** should match the most stringent RPO requirement among active tenants.

**Example Configuration:**
```bash
# In .env or cron configuration
# If any Enterprise tenant exists, use hourly backups
BACKUP_FREQUENCY=hourly  # for Enterprise tenants
BACKUP_RETENTION_DAYS=30 # longer for compliance

# If only Pro/Starter tenants exist, use 6-hour backups
BACKUP_FREQUENCY=6hourly
BACKUP_RETENTION_DAYS=7

# For Free tier only, use daily backups
BACKUP_FREQUENCY=daily
BACKUP_RETENTION_DAYS=7
```

**Cron Schedule (Adaptive):**
```bash
# Hourly backups (Enterprise tier present)
0 * * * * cd /var/www/chatbot && ./scripts/db_backup.sh --retention-days 30

# Every 6 hours (Pro tier minimum)
0 */6 * * * cd /var/www/chatbot && ./scripts/db_backup.sh --retention-days 7

# Daily backups (Starter/Free tier only)
0 2 * * * cd /var/www/chatbot && ./scripts/backup_all.sh --retention-days 7
```

### 3. Tenant-Specific Restore Operations

Even though backups are shared, individual tenant data can be selectively restored.

## Selective Tenant Restore Procedures

### Scenario: Restore Single Tenant Data

When a single tenant's data is corrupted or accidentally deleted, you can restore only that tenant's data without affecting others.

**Prerequisites:**
- Tenant ID of affected tenant
- Backup file from before the incident
- List of tables with tenant_id column

**Procedure:**

#### 1. Identify Tenant and Backup
```bash
# Get tenant ID
TENANT_ID=123

# Identify affected tables
TENANT_TABLES=(
  "agents"
  "prompts"
  "vector_stores"
  "admin_users"
  "audit_conversations"
  "channel_sessions"
  "channel_messages"
  "leads"
  "jobs"
)

# Choose backup file
BACKUP_FILE="/data/backups/admin_sqlite_20251107_020000.db.gz"
```

#### 2. Extract Tenant Data from Backup
```bash
# Create temporary restore location
TEMP_DIR=$(mktemp -d)
gunzip -c "$BACKUP_FILE" > "$TEMP_DIR/backup.db"

# Export tenant-specific data
for table in "${TENANT_TABLES[@]}"; do
  sqlite3 "$TEMP_DIR/backup.db" <<EOF > "$TEMP_DIR/${table}.sql"
.mode insert $table
SELECT * FROM $table WHERE tenant_id = $TENANT_ID;
EOF
done
```

#### 3. Create Safety Backup of Current State
```bash
# Backup current state before making changes
./scripts/db_backup.sh
cp data/admin.db data/admin.db.before_tenant_restore_$(date +%Y%m%d_%H%M%S)
```

#### 4. Delete Current Tenant Data (if needed)
```bash
# CAUTION: This deletes current tenant data!
# Only do this if data is corrupted or you're doing a full tenant restore

sqlite3 data/admin.db <<EOF
BEGIN TRANSACTION;
$(for table in "${TENANT_TABLES[@]}"; do
  echo "DELETE FROM $table WHERE tenant_id = $TENANT_ID;"
done)
COMMIT;
EOF
```

#### 5. Restore Tenant Data
```bash
# Import tenant data from backup
for table in "${TENANT_TABLES[@]}"; do
  sqlite3 data/admin.db < "$TEMP_DIR/${table}.sql"
done
```

#### 6. Verify Restoration
```bash
# Count restored records per table
for table in "${TENANT_TABLES[@]}"; do
  count=$(sqlite3 data/admin.db "SELECT COUNT(*) FROM $table WHERE tenant_id = $TENANT_ID;")
  echo "Table $table: $count records for tenant $TENANT_ID"
done

# Test tenant functionality
curl -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://localhost/admin-api.php?action=list_agents" | jq .
```

#### 7. Cleanup
```bash
# Remove temporary files
rm -rf "$TEMP_DIR"
```

### Scenario: Clone Tenant Data

Create a copy of a tenant's data for testing or migration purposes.

```bash
#!/bin/bash
# Clone tenant data to new tenant

SOURCE_TENANT_ID=123
TARGET_TENANT_ID=456

# Ensure target tenant exists
sqlite3 data/admin.db <<EOF
INSERT INTO tenants (id, name, slug, status, settings)
SELECT $TARGET_TENANT_ID, 'Cloned Tenant', 'cloned-tenant', 'active', settings
FROM tenants WHERE id = $SOURCE_TENANT_ID;
EOF

# Copy all tenant-specific data
for table in "${TENANT_TABLES[@]}"; do
  sqlite3 data/admin.db <<EOF
INSERT INTO $table
SELECT NULL as id, $TARGET_TENANT_ID as tenant_id, *
FROM $table
WHERE tenant_id = $SOURCE_TENANT_ID;
EOF
done

echo "✓ Tenant $SOURCE_TENANT_ID cloned to $TARGET_TENANT_ID"
```

## Multi-Tenant Disaster Recovery Scenarios

### Scenario 1: Single Tenant Data Corruption

**Symptoms:**
- Reports from a single tenant about missing or corrupted data
- Audit logs show suspicious delete operations for one tenant
- Queries for specific tenant_id return inconsistent results

**Impact Assessment:**
- Affected: Single tenant
- Other tenants: Not affected
- System availability: Operational

**Recovery Procedure:**

1. **Isolate Tenant** (5 minutes)
   ```bash
   # Suspend tenant to prevent further data changes
   sqlite3 data/admin.db <<EOF
   UPDATE tenants SET status = 'suspended' WHERE id = $TENANT_ID;
   EOF
   ```

2. **Verify Scope** (10 minutes)
   ```bash
   # Check which tables are affected
   for table in agents prompts vector_stores admin_users audit_conversations channel_sessions channel_messages leads jobs; do
     current=$(sqlite3 data/admin.db "SELECT COUNT(*) FROM $table WHERE tenant_id = $TENANT_ID;")
     echo "$table: $current records"
   done
   ```

3. **Restore Tenant Data** (30 minutes)
   - Follow "Selective Tenant Restore" procedure above
   - Verify data integrity
   - Test tenant functionality

4. **Reactivate Tenant** (5 minutes)
   ```bash
   sqlite3 data/admin.db <<EOF
   UPDATE tenants SET status = 'active' WHERE id = $TENANT_ID;
   EOF
   ```

5. **Communicate** (15 minutes)
   - Notify affected tenant
   - Document data loss period
   - Provide incident report

**Total Estimated Recovery Time: 1-2 hours**

### Scenario 2: Multi-Tenant System Failure

**Symptoms:**
- Complete system failure affecting all tenants
- Database corruption or loss
- Server failure

**Impact Assessment:**
- Affected: All tenants
- System availability: Down
- Priority: Restore based on tier (Enterprise first)

**Recovery Procedure:**

1. **Follow Standard DR Procedure**
   - See [Disaster Recovery Runbook](disaster_recovery.md) - Scenario 1: Complete System Failure
   - Restore entire database from backup
   - Restore configuration and files

2. **Prioritize Tenant Recovery** (if partial restoration needed)
   ```bash
   # Get tenants by priority tier
   sqlite3 data/admin.db <<EOF
   SELECT id, name, 
          CASE 
            WHEN settings LIKE '%enterprise%' THEN 1
            WHEN settings LIKE '%pro%' THEN 2
            WHEN settings LIKE '%starter%' THEN 3
            ELSE 4
          END as priority
   FROM tenants
   WHERE status = 'active'
   ORDER BY priority;
   EOF
   ```

3. **Verify Each Tenant** (based on tier priority)
   - Enterprise tenants: Full verification (30 min each)
   - Pro tenants: Standard verification (15 min each)
   - Starter/Free: Basic verification (5 min each)

4. **Communicate Status**
   - Send status updates to all tenants
   - Provide ETA for each tier
   - Document actual recovery times

**Total Estimated Recovery Time: 2-6 hours**
(Depends on number of tenants and tier distribution)

### Scenario 3: Tenant Migration to Isolated Database

**Use Case:**
- Enterprise tenant requires dedicated database
- Compliance requirements mandate data isolation
- Performance optimization for high-volume tenant

**Migration Procedure:**

1. **Prepare Target Database** (30 minutes)
   ```bash
   # Create new database for tenant
   createdb chatbot_tenant_${TENANT_ID}
   
   # Run migrations
   export DATABASE_URL="postgresql://user:pass@localhost/chatbot_tenant_${TENANT_ID}"
   php scripts/run_migrations.php
   ```

2. **Backup Current Data** (10 minutes)
   ```bash
   # Full backup before migration
   ./scripts/backup_all.sh
   ```

3. **Export Tenant Data** (30 minutes)
   ```bash
   # Export all tenant data
   for table in "${TENANT_TABLES[@]}"; do
     sqlite3 data/admin.db <<EOF > /tmp/tenant_${TENANT_ID}_${table}.sql
.mode insert $table
SELECT * FROM $table WHERE tenant_id = $TENANT_ID;
EOF
   done
   ```

4. **Import to Target Database** (30 minutes)
   ```bash
   # Import tenant data to new database
   for table in "${TENANT_TABLES[@]}"; do
     psql "postgresql://user:pass@localhost/chatbot_tenant_${TENANT_ID}" \
       < "/tmp/tenant_${TENANT_ID}_${table}.sql"
   done
   ```

5. **Update Configuration** (15 minutes)
   ```bash
   # Update tenant record with new database URL
   sqlite3 data/admin.db <<EOF
   UPDATE tenants 
   SET settings = json_set(settings, '$.database_url', 
       'postgresql://user:pass@localhost/chatbot_tenant_${TENANT_ID}')
   WHERE id = $TENANT_ID;
   EOF
   ```

6. **Verify and Test** (30 minutes)
   - Test all tenant functionality
   - Verify data integrity
   - Performance testing

7. **Remove from Shared Database** (15 minutes)
   ```bash
   # Only after verification is complete!
   # Create final backup
   ./scripts/db_backup.sh
   
   # Remove tenant data from shared database
   for table in "${TENANT_TABLES[@]}"; do
     sqlite3 data/admin.db "DELETE FROM $table WHERE tenant_id = $TENANT_ID;"
   done
   ```

**Total Estimated Migration Time: 3-4 hours**

## Backup Scripts - Multi-Tenant Enhancements

### Tenant-Aware Backup Script

Create a new script for tenant-specific backup operations:

**Location:** `scripts/tenant_backup.sh`

```bash
#!/bin/bash
# Tenant-specific backup operations
# Usage: ./scripts/tenant_backup.sh <tenant_id> [--export-only]

TENANT_ID=$1
EXPORT_ONLY=false

if [[ "$2" == "--export-only" ]]; then
  EXPORT_ONLY=true
fi

# Validate tenant ID
if [ -z "$TENANT_ID" ]; then
  echo "Error: Tenant ID required"
  echo "Usage: $0 <tenant_id> [--export-only]"
  exit 1
fi

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/data/backups/tenants}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
TENANT_BACKUP_DIR="$BACKUP_DIR/tenant_${TENANT_ID}_${TIMESTAMP}"

mkdir -p "$TENANT_BACKUP_DIR"

echo "Backing up tenant $TENANT_ID..."

# Tenant tables
TABLES=(
  "agents"
  "prompts"
  "vector_stores"
  "admin_users"
  "audit_conversations"
  "channel_sessions"
  "channel_messages"
  "leads"
  "jobs"
)

# Export tenant data
for table in "${TABLES[@]}"; do
  echo "Exporting table: $table"
  sqlite3 data/admin.db <<EOF > "$TENANT_BACKUP_DIR/${table}.sql"
.mode insert $table
SELECT * FROM $table WHERE tenant_id = $TENANT_ID;
EOF
done

# Export tenant record
sqlite3 data/admin.db <<EOF > "$TENANT_BACKUP_DIR/tenant.sql"
.mode insert tenants
SELECT * FROM tenants WHERE id = $TENANT_ID;
EOF

# Create manifest
cat > "$TENANT_BACKUP_DIR/MANIFEST.txt" <<EOF
Tenant Backup Manifest
======================
Tenant ID: $TENANT_ID
Timestamp: $(date -u +%Y-%m-%dT%H:%M:%SZ)
Backup Type: $([ "$EXPORT_ONLY" = true ] && echo "Export" || echo "Full Backup")

Tables Backed Up:
$(for table in "${TABLES[@]}"; do
  count=$(wc -l < "$TENANT_BACKUP_DIR/${table}.sql" | tr -d ' ')
  echo "  - $table: $count lines"
done)
EOF

# Create archive
cd "$BACKUP_DIR"
tar -czf "tenant_${TENANT_ID}_${TIMESTAMP}.tar.gz" "tenant_${TENANT_ID}_${TIMESTAMP}"
rm -rf "tenant_${TENANT_ID}_${TIMESTAMP}"

echo "✓ Tenant backup complete: $BACKUP_DIR/tenant_${TENANT_ID}_${TIMESTAMP}.tar.gz"
```

> **PostgreSQL deployments:** Set `ADMIN_DB_TYPE=postgres` and either provide a full `DATABASE_URL` or the discrete `PGHOST`/`PGPORT`/`PGUSER`/`PGPASSWORD`/`PGDATABASE` variables. The export script will scope `PGPASSWORD` to each `psql` invocation and emits tenant-filtered `COPY ... FROM STDIN WITH CSV` statements so that restores can run through `psql` without manual editing. Custom schemas can be supplied via `DATABASE_URL?...&schema=analytics` or `PGSCHEMA`.

**Manual smoke tests**

```bash
# SQLite demo tenant
./scripts/tenant_backup.sh demo-tenant --export-only

# PostgreSQL example (password read from DATABASE_URL)
ADMIN_DB_TYPE=postgres \
DATABASE_URL="postgres://chatbot:secret@db.example.com:5432/chatbot?schema=public" \
./scripts/tenant_backup.sh demo-tenant

# Inspect manifest to confirm record counts
tar -tzf /data/backups/tenants/tenant_demo-tenant_*.tar.gz MANIFEST.txt
```

### Integration with Existing Backup Monitoring

Update monitoring to track tenant-level backup metrics:

```bash
# Add to scripts/monitor_backups.sh

# Check if tenant-specific backups exist (if any)
TENANT_BACKUPS=$(find "$BACKUP_DIR/tenants" -name "tenant_*.tar.gz" 2>/dev/null | wc -l)
if [ $TENANT_BACKUPS -gt 0 ]; then
  echo "Tenant-specific backups: $TENANT_BACKUPS"
fi
```

## Configuration for Multi-Tenant Backups

### Environment Variables

Add to `.env`:

```bash
# Multi-Tenant Backup Configuration
BACKUP_FREQUENCY=hourly  # hourly, 6hourly, daily (based on tenant tier)
BACKUP_TENANT_ISOLATION=false  # Set to true to enable per-tenant backup files
BACKUP_TIER_AWARE=true  # Adjust retention based on tenant tier

# Retention by tier (days)
BACKUP_RETENTION_ENTERPRISE=30
BACKUP_RETENTION_PRO=14
BACKUP_RETENTION_STARTER=7
BACKUP_RETENTION_FREE=3
```

### Adaptive Backup Scheduling

Create a script to determine appropriate backup frequency:

**Location:** `scripts/determine_backup_frequency.php`

```php
#!/usr/bin/env php
<?php
/**
 * Determine appropriate backup frequency based on active tenant tiers
 */

require_once __DIR__ . '/../includes/Database.php';

$db = new Database();

// Query for highest tier tenant
$result = $db->query("
  SELECT 
    CASE
      WHEN settings LIKE '%enterprise%' THEN 'hourly'
      WHEN settings LIKE '%pro%' THEN '6hourly'
      WHEN settings LIKE '%starter%' THEN 'daily'
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
");

if (!empty($result)) {
  echo $result[0]['frequency'];
  exit(0);
} else {
  echo "daily";  // default
  exit(0);
}
```

Use in cron:
```bash
# Dynamic backup frequency based on tenant tiers
0 * * * * cd /var/www/chatbot && [ "$(php scripts/determine_backup_frequency.php)" = "hourly" ] && ./scripts/db_backup.sh
0 */6 * * * cd /var/www/chatbot && [ "$(php scripts/determine_backup_frequency.php)" = "6hourly" ] && ./scripts/db_backup.sh
0 2 * * * cd /var/www/chatbot && [ "$(php scripts/determine_backup_frequency.php)" = "daily" ] && ./scripts/backup_all.sh
```

## Testing Multi-Tenant Backup & Restore

### Test Checklist

- [ ] Full database backup includes all tenant data
- [ ] Backup frequency matches highest tenant tier requirement
- [ ] Selective tenant restore works without affecting other tenants
- [ ] Tenant isolation is maintained during restore operations
- [ ] Backup monitoring tracks tenant-level metrics
- [ ] Off-site backups include all tenant data
- [ ] DR procedures prioritize tenants by tier
- [ ] Documentation covers multi-tenant scenarios

### Test Script

Create `tests/test_multi_tenant_backup.php`:

```php
#!/usr/bin/env php
<?php
/**
 * Multi-Tenant Backup & Restore Test Suite
 */

// Test 1: Verify all tenants are in backup
// Test 2: Selective tenant restore
// Test 3: Tenant isolation during restore
// Test 4: Backup frequency determination
// Test 5: Per-tenant data export
// (Implementation follows same pattern as test_backup_restore.php)
```

## Compliance and Audit

### Data Retention by Tenant Tier

Different tenant tiers may have different compliance requirements:

| Tenant Tier | Audit Log Retention | Backup Retention | Compliance |
|-------------|---------------------|------------------|------------|
| Enterprise | 7 years | 1 year | SOC 2, HIPAA |
| Pro | 3 years | 90 days | SOC 2 |
| Starter | 1 year | 30 days | Standard |
| Free | 90 days | 7 days | Best effort |

### GDPR Compliance

For EU tenants:
- Data must be restorable for 30 days after deletion
- Right to be forgotten applies to backups
- Data export must be available upon request

**Procedures:**

1. **Data Export Request**
   ```bash
   ./scripts/tenant_backup.sh <tenant_id> --export-only
   # Provides tenant with their data in portable format
   ```

2. **Right to Deletion**
   ```bash
   # After 30-day grace period, purge tenant from old backups
   # Document which backups still contain tenant data
   ```

## References

- [Backup & Restore Guide](backup_restore.md) - General procedures
- [Disaster Recovery Runbook](disaster_recovery.md) - DR scenarios
- [Multi-Tenancy](../MULTI_TENANCY.md) - Architecture overview and implementation
- [Multi-Tenancy Implementation Summary](../../MULTI_TENANCY_IMPLEMENTATION_SUMMARY.md) - Implementation details

## Revision History

| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2025-11-07 | 1.0 | Initial multi-tenant backup & DR guide | System |

## Next Steps

1. **Implement tenant-aware backup scripts**
2. **Configure adaptive backup scheduling**
3. **Test selective tenant restore procedures**
4. **Document tenant-specific SLAs**
5. **Train operations team on multi-tenant DR procedures**

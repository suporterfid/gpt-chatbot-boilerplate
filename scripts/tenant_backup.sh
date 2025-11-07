#!/bin/bash
#
# Tenant-Specific Backup Script
# Exports all data for a single tenant for backup, migration, or GDPR compliance
#
# Usage: ./scripts/tenant_backup.sh <tenant_id> [--export-only]
#

set -e

TENANT_ID=$1
EXPORT_ONLY=false

# Parse arguments
shift || true
while [[ $# -gt 0 ]]; do
    case $1 in
        --export-only)
            EXPORT_ONLY=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Validate tenant ID
if [ -z "$TENANT_ID" ]; then
    echo "Error: Tenant ID required"
    echo "Usage: $0 <tenant_id> [--export-only]"
    exit 1
fi

# Load .env if exists
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Configuration
BACKUP_BASE_DIR="${BACKUP_DIR:-/data/backups}"
TENANT_BACKUP_DIR="$BACKUP_BASE_DIR/tenants"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="tenant_${TENANT_ID}_${TIMESTAMP}"
BACKUP_DIR="$TENANT_BACKUP_DIR/$BACKUP_NAME"
DB_PATH="${DATABASE_PATH:-./data/chatbot.db}"
DB_TYPE="${ADMIN_DB_TYPE:-sqlite}"

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo "========================================="
echo "Tenant Backup Script"
echo "========================================="
echo "Tenant ID: $TENANT_ID"
echo "Database Type: $DB_TYPE"
echo "Database Path: $DB_PATH"
echo "Backup Directory: $BACKUP_DIR"
echo "Export Only: $EXPORT_ONLY"
echo "Timestamp: $TIMESTAMP"
echo "========================================="
echo ""

# Verify database exists
if [ "$DB_TYPE" = "sqlite" ] && [ ! -f "$DB_PATH" ]; then
    echo "ERROR: SQLite database not found at $DB_PATH"
    exit 1
fi

# Tables that have tenant_id
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
    "notifications"
    "audit_events"
    "usage_records"
    "quotas"
    "subscriptions"
)

BACKUP_SUCCESS=true
BACKUP_ERRORS=""

# Function to log error
log_error() {
    BACKUP_SUCCESS=false
    BACKUP_ERRORS="${BACKUP_ERRORS}\n  - $1"
    echo "❌ ERROR: $1" >&2
}

# Verify tenant exists
echo "Verifying tenant exists..."
if [ "$DB_TYPE" = "sqlite" ]; then
    TENANT_EXISTS=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM tenants WHERE id = $TENANT_ID;" 2>/dev/null || echo "0")
    if [ "$TENANT_EXISTS" -eq 0 ]; then
        log_error "Tenant $TENANT_ID not found in database"
        exit 1
    fi
    
    # Get tenant info
    TENANT_NAME=$(sqlite3 "$DB_PATH" "SELECT name FROM tenants WHERE id = $TENANT_ID;" 2>/dev/null || echo "Unknown")
    TENANT_STATUS=$(sqlite3 "$DB_PATH" "SELECT status FROM tenants WHERE id = $TENANT_ID;" 2>/dev/null || echo "unknown")
    
    echo "✓ Tenant found: $TENANT_NAME (Status: $TENANT_STATUS)"
else
    log_error "PostgreSQL backup not yet implemented for tenant-specific backups"
    exit 1
fi

# Create manifest header
MANIFEST_FILE="$BACKUP_DIR/MANIFEST.txt"
cat > "$MANIFEST_FILE" <<EOF
Tenant Backup Manifest
======================
Tenant ID: $TENANT_ID
Tenant Name: $TENANT_NAME
Tenant Status: $TENANT_STATUS
Backup Timestamp: $(date -u +%Y-%m-%dT%H:%M:%SZ)
Backup Type: $([ "$EXPORT_ONLY" = true ] && echo "Export Only" || echo "Full Tenant Backup")
Database Type: $DB_TYPE
Database Path: $DB_PATH

Tables Backed Up:
EOF

# Export tenant record first
echo ""
echo "Exporting tenant metadata..."
if [ "$DB_TYPE" = "sqlite" ]; then
    sqlite3 "$DB_PATH" > "$BACKUP_DIR/tenant_record.sql" 2>&1 <<EOF
.mode insert tenants
SELECT * FROM tenants WHERE id = $TENANT_ID;
EOF
    echo "✓ Tenant record exported"
    echo "  - tenant_record.sql" >> "$MANIFEST_FILE"
else
    log_error "PostgreSQL export not implemented"
fi

# Export tenant data from each table
echo ""
echo "Exporting tenant data..."
TOTAL_RECORDS=0

for table in "${TENANT_TABLES[@]}"; do
    echo "Processing table: $table"
    
    if [ "$DB_TYPE" = "sqlite" ]; then
        # Check if table exists and has tenant_id column
        TABLE_EXISTS=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table';" 2>/dev/null || echo "0")
        
        if [ "$TABLE_EXISTS" -eq 0 ]; then
            echo "  ⚠ Table $table does not exist, skipping"
            continue
        fi
        
        HAS_TENANT_COL=$(sqlite3 "$DB_PATH" "PRAGMA table_info($table);" 2>/dev/null | grep -c "tenant_id" || echo "0")
        
        if [ "$HAS_TENANT_COL" -eq 0 ]; then
            echo "  ⚠ Table $table has no tenant_id column, skipping"
            continue
        fi
        
        # Count records
        RECORD_COUNT=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM $table WHERE tenant_id = $TENANT_ID;" 2>/dev/null || echo "0")
        
        if [ "$RECORD_COUNT" -eq 0 ]; then
            echo "  ⓘ No records found in $table for tenant $TENANT_ID"
            continue
        fi
        
        # Export data
        sqlite3 "$DB_PATH" > "$BACKUP_DIR/${table}.sql" 2>&1 <<EOF
.mode insert $table
SELECT * FROM $table WHERE tenant_id = $TENANT_ID;
EOF
        
        if [ $? -eq 0 ]; then
            echo "  ✓ Exported $RECORD_COUNT records from $table"
            echo "  - $table.sql ($RECORD_COUNT records)" >> "$MANIFEST_FILE"
            TOTAL_RECORDS=$((TOTAL_RECORDS + RECORD_COUNT))
        else
            log_error "Failed to export table $table"
        fi
    else
        log_error "PostgreSQL export not implemented for table $table"
    fi
done

echo ""
echo "Total records exported: $TOTAL_RECORDS"
echo "" >> "$MANIFEST_FILE"
echo "Total Records: $TOTAL_RECORDS" >> "$MANIFEST_FILE"

# Calculate backup size
BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "Backup Size: $BACKUP_SIZE" >> "$MANIFEST_FILE"

# Add status
echo "Backup Status: $([ "$BACKUP_SUCCESS" = true ] && echo "SUCCESS" || echo "PARTIAL")" >> "$MANIFEST_FILE"

if [ "$BACKUP_SUCCESS" = false ]; then
    echo "" >> "$MANIFEST_FILE"
    echo "Errors:" >> "$MANIFEST_FILE"
    echo -e "$BACKUP_ERRORS" >> "$MANIFEST_FILE"
fi

# Create archive
echo ""
echo "Creating backup archive..."
cd "$TENANT_BACKUP_DIR"
tar -czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME" 2>/dev/null && \
echo "✓ Created backup archive: ${BACKUP_NAME}.tar.gz" || \
log_error "Failed to create backup archive"

# Calculate archive size
ARCHIVE_SIZE=$(du -h "${BACKUP_NAME}.tar.gz" 2>/dev/null | cut -f1)
echo "Archive size: $ARCHIVE_SIZE"

# Remove uncompressed backup directory
rm -rf "$BACKUP_NAME"

# Final status
echo ""
echo "========================================="
if [ "$BACKUP_SUCCESS" = true ]; then
    echo "✅ Tenant backup completed successfully"
    echo "========================================="
    echo "Backup file: $TENANT_BACKUP_DIR/${BACKUP_NAME}.tar.gz"
    echo "Archive size: $ARCHIVE_SIZE"
    echo "Records backed up: $TOTAL_RECORDS"
    exit 0
else
    echo "⚠️  Tenant backup completed with errors"
    echo "========================================="
    echo "Backup file: $TENANT_BACKUP_DIR/${BACKUP_NAME}.tar.gz"
    echo "Archive size: $ARCHIVE_SIZE"
    echo "Records backed up: $TOTAL_RECORDS"
    echo ""
    echo "Errors encountered:"
    echo -e "$BACKUP_ERRORS"
    exit 1
fi

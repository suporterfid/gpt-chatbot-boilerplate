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

# Validate tenant ID format (alphanumeric, hyphens, underscores only)
if ! [[ "$TENANT_ID" =~ ^[a-zA-Z0-9_-]+$ ]]; then
    echo "Error: Invalid tenant ID format. Only alphanumeric characters, hyphens, and underscores allowed."
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

PGDATABASE_FROM_URL=""
PGUSER_FROM_URL=""
PGPASSWORD_FROM_URL=""
PGHOST_FROM_URL=""
PGPORT_FROM_URL=""
PGSCHEMA_FROM_URL=""
PGSSLMODE_FROM_URL=""

if [ "$DB_TYPE" = "postgres" ] && [ -n "$DATABASE_URL" ]; then
    mapfile -t _DATABASE_URL_PARTS < <(python3 <<'PY'
import os
from urllib.parse import urlparse, parse_qs

url = os.environ.get("DATABASE_URL", "")
if not url:
    raise SystemExit

parsed = urlparse(url)
query = parse_qs(parsed.query)

def val(item):
    return item or ""

parts = [
    val(parsed.path.lstrip("/")),
    val(parsed.username),
    val(parsed.password),
    val(parsed.hostname),
    str(parsed.port or ""),
    val(query.get("schema", [""])[0]),
    val(query.get("sslmode", [""])[0]),
]

for piece in parts:
    print(piece)
PY
    )

    if [ ${#_DATABASE_URL_PARTS[@]} -ge 7 ]; then
        PGDATABASE_FROM_URL="${_DATABASE_URL_PARTS[0]}"
        PGUSER_FROM_URL="${_DATABASE_URL_PARTS[1]}"
        PGPASSWORD_FROM_URL="${_DATABASE_URL_PARTS[2]}"
        PGHOST_FROM_URL="${_DATABASE_URL_PARTS[3]}"
        PGPORT_FROM_URL="${_DATABASE_URL_PARTS[4]}"
        PGSCHEMA_FROM_URL="${_DATABASE_URL_PARTS[5]}"
        PGSSLMODE_FROM_URL="${_DATABASE_URL_PARTS[6]}"
    fi
fi

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo "========================================="
echo "Tenant Backup Script"
echo "========================================="
echo "Tenant ID: $TENANT_ID"
echo "Database Type: $DB_TYPE"
if [ "$DB_TYPE" = "postgres" ]; then
    PGHOST="${PGHOST:-${PGHOST_FROM_URL:-localhost}}"
    PGPORT="${PGPORT:-${PGPORT_FROM_URL:-5432}}"
    PGUSER="${PGUSER:-${PGUSER_FROM_URL:-postgres}}"
    PGDATABASE="${PGDATABASE:-${PGDATABASE_FROM_URL}}"
    PGSCHEMA="${PGSCHEMA:-${PGSCHEMA_FROM_URL:-public}}"
    PGSSLMODE_VALUE="${PGSSLMODE:-${PGSSLMODE_FROM_URL:-}}"
    PGPASSWORD_VALUE="${PGPASSWORD:-${PGPASSWORD_FROM_URL}}"

    if ! command -v psql >/dev/null 2>&1; then
        echo "ERROR: psql command not found. Please install PostgreSQL client tools."
        exit 1
    fi

    if [ -z "$PGDATABASE" ]; then
        echo "ERROR: PostgreSQL database name not provided. Set PGDATABASE or DATABASE_URL."
        exit 1
    fi

    PSQL_CONN=("--no-psqlrc" "--host=$PGHOST" "--port=$PGPORT" "--username=$PGUSER" "--dbname=$PGDATABASE" "--set=ON_ERROR_STOP=1")

    echo "Database Host: $PGHOST"
    echo "Database Port: $PGPORT"
    echo "Database Name: $PGDATABASE"
    echo "Database User: $PGUSER"
    echo "Database Schema: $PGSCHEMA"
    if [ -n "$PGSSLMODE_VALUE" ]; then
        echo "SSL Mode: $PGSSLMODE_VALUE"
    fi
else
    echo "Database Path: $DB_PATH"
fi
echo "Backup Directory: $BACKUP_DIR"
echo "Export Only: $EXPORT_ONLY"
echo "Timestamp: $TIMESTAMP"
echo "========================================="
echo ""

# Verify database exists / connection
if [ "$DB_TYPE" = "sqlite" ]; then
    if [ ! -f "$DB_PATH" ]; then
        echo "ERROR: SQLite database not found at $DB_PATH"
        exit 1
    fi
else
    set +e
    if [ -n "$PGSSLMODE_VALUE" ]; then
        PGPASSWORD="$PGPASSWORD_VALUE" PGSSLMODE="$PGSSLMODE_VALUE" psql "${PSQL_CONN[@]}" -tA -c "SELECT 1;" >/dev/null 2>&1
        status=$?
    else
        PGPASSWORD="$PGPASSWORD_VALUE" psql "${PSQL_CONN[@]}" -tA -c "SELECT 1;" >/dev/null 2>&1
        status=$?
    fi
    set -e
    if [ $status -ne 0 ]; then
        echo "ERROR: Unable to connect to PostgreSQL database using provided credentials"
        exit 1
    fi
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

SAFE_TENANT_ID="${TENANT_ID//\'/\'\'}"

if [ "$DB_TYPE" = "postgres" ]; then
    psql_cmd() {
        if [ -n "$PGSSLMODE_VALUE" ]; then
            PGPASSWORD="$PGPASSWORD_VALUE" PGSSLMODE="$PGSSLMODE_VALUE" psql "${PSQL_CONN[@]}" "$@"
        else
            PGPASSWORD="$PGPASSWORD_VALUE" psql "${PSQL_CONN[@]}" "$@"
        fi
    }

    run_psql_query() {
        local sql="$1"
        local result
        local status
        set +e
        if [ -n "$PGSSLMODE_VALUE" ]; then
            result=$(PGPASSWORD="$PGPASSWORD_VALUE" PGSSLMODE="$PGSSLMODE_VALUE" psql "${PSQL_CONN[@]}" -tA -c "$sql")
            status=$?
        else
            result=$(PGPASSWORD="$PGPASSWORD_VALUE" psql "${PSQL_CONN[@]}" -tA -c "$sql")
            status=$?
        fi
        set -e
        if [ $status -ne 0 ]; then
            return $status
        fi
        echo "$result"
    }

    postgres_table_ref() {
        printf '"%s"."%s"' "$PGSCHEMA" "$1"
    }

    postgres_table_exists() {
        local table_name="$1"
        run_psql_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$PGSCHEMA' AND table_name = '$table_name';"
    }

    postgres_table_has_tenant_column() {
        local table_name="$1"
        run_psql_query "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$PGSCHEMA' AND table_name = '$table_name' AND column_name = 'tenant_id';"
    }

    postgres_export_table_where() {
        local table_name="$1"
        local where_clause="$2"
        local output_file="$3"
        local description="$4"
        local table_ref
        local column_list
        local record_count
        local select_query
        local tmp_csv
        local status

        table_ref=$(postgres_table_ref "$table_name")
        column_list=$(run_psql_query "SELECT string_agg(quote_ident(column_name), ', ' ORDER BY ordinal_position) FROM information_schema.columns WHERE table_schema = '$PGSCHEMA' AND table_name = '$table_name';")

        if [ -z "$column_list" ]; then
            return 2
        fi

        record_count=$(run_psql_query "SELECT COUNT(*) FROM $table_ref WHERE $where_clause;" || echo "0")

        if [ -z "$record_count" ] || [ "$record_count" -eq 0 ]; then
            echo "0"
            return 0
        fi

        select_query="SELECT $column_list FROM $table_ref WHERE $where_clause"
        tmp_csv=$(mktemp)

        set +e
        psql_cmd <<EOF > "$tmp_csv"
\copy ($select_query) TO STDOUT WITH CSV HEADER
EOF
        status=$?
        set -e

        if [ $status -ne 0 ]; then
            rm -f "$tmp_csv"
            return 1
        fi

        {
            if [ -n "$description" ]; then
                echo "-- $description"
            else
                echo "-- Data for table $table_name"
            fi
            echo "COPY $table_ref ($column_list) FROM STDIN WITH CSV HEADER;"
            cat "$tmp_csv"
            echo "\\."
        } > "$output_file"

        rm -f "$tmp_csv"

        echo "$record_count"
        return 0
    }
fi

# Verify tenant exists
echo "Verifying tenant exists..."
if [ "$DB_TYPE" = "sqlite" ]; then
    # Use parameterized query approach - escape single quotes in tenant ID
    TENANT_EXISTS=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM tenants WHERE id = '$SAFE_TENANT_ID';" 2>/dev/null || echo "0")
    if [ "$TENANT_EXISTS" -eq 0 ]; then
        log_error "Tenant $TENANT_ID not found in database"
        exit 1
    fi

    # Get tenant info
    TENANT_NAME=$(sqlite3 "$DB_PATH" "SELECT name FROM tenants WHERE id = '$SAFE_TENANT_ID';" 2>/dev/null || echo "Unknown")
    TENANT_STATUS=$(sqlite3 "$DB_PATH" "SELECT status FROM tenants WHERE id = '$SAFE_TENANT_ID';" 2>/dev/null || echo "unknown")

    echo "✓ Tenant found: $TENANT_NAME (Status: $TENANT_STATUS)"
else
    TENANT_TABLE=$(postgres_table_ref "tenants")
    TENANT_EXISTS=$(run_psql_query "SELECT COUNT(*) FROM $TENANT_TABLE WHERE id = '$SAFE_TENANT_ID';" || echo "0")

    if [ "$TENANT_EXISTS" -eq 0 ]; then
        log_error "Tenant $TENANT_ID not found in database"
        exit 1
    fi

    TENANT_NAME=$(run_psql_query "SELECT COALESCE(name, '') FROM $TENANT_TABLE WHERE id = '$SAFE_TENANT_ID' LIMIT 1;" || echo "")
    TENANT_STATUS=$(run_psql_query "SELECT COALESCE(status, '') FROM $TENANT_TABLE WHERE id = '$SAFE_TENANT_ID' LIMIT 1;" || echo "")

    [ -z "$TENANT_NAME" ] && TENANT_NAME="Unknown"
    [ -z "$TENANT_STATUS" ] && TENANT_STATUS="unknown"

    echo "✓ Tenant found: $TENANT_NAME (Status: $TENANT_STATUS)"
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
EOF

if [ "$DB_TYPE" = "postgres" ]; then
    {
        echo "Database Host: $PGHOST"
        echo "Database Port: $PGPORT"
        echo "Database Name: $PGDATABASE"
        echo "Database User: $PGUSER"
        echo "Database Schema: $PGSCHEMA"
        [ -n "$PGSSLMODE_VALUE" ] && echo "SSL Mode: $PGSSLMODE_VALUE"
    } >> "$MANIFEST_FILE"
else
    echo "Database Path: $DB_PATH" >> "$MANIFEST_FILE"
fi

{
    echo ""
    echo "Tables Backed Up:"
} >> "$MANIFEST_FILE"

# Export tenant record first
echo ""
echo "Exporting tenant metadata..."
if [ "$DB_TYPE" = "sqlite" ]; then
    sqlite3 "$DB_PATH" > "$BACKUP_DIR/tenant_record.sql" 2>&1 <<EOF
.mode insert tenants
SELECT * FROM tenants WHERE id = '$SAFE_TENANT_ID';
EOF
    echo "✓ Tenant record exported"
    echo "  - tenant_record.sql" >> "$MANIFEST_FILE"
else
    set +e
    TENANT_RECORD_COUNT=$(postgres_export_table_where "tenants" "id = '$SAFE_TENANT_ID'" "$BACKUP_DIR/tenant_record.sql" "Tenant record for $TENANT_ID")
    status=$?
    set -e

    if [ $status -eq 2 ]; then
        log_error "Table tenants not found in schema $PGSCHEMA"
    elif [ $status -ne 0 ]; then
        log_error "Failed to export tenant metadata"
    elif [ "${TENANT_RECORD_COUNT:-0}" -eq 0 ]; then
        log_error "Tenant $TENANT_ID metadata query returned no rows"
    else
        echo "✓ Tenant record exported"
        echo "  - tenant_record.sql ($TENANT_RECORD_COUNT record)" >> "$MANIFEST_FILE"
    fi
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
        RECORD_COUNT=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM $table WHERE tenant_id = '$SAFE_TENANT_ID';" 2>/dev/null || echo "0")
        
        if [ "$RECORD_COUNT" -eq 0 ]; then
            echo "  ⓘ No records found in $table for tenant $TENANT_ID"
            continue
        fi
        
        # Export data
        sqlite3 "$DB_PATH" > "$BACKUP_DIR/${table}.sql" 2>&1 <<EOF
.mode insert $table
SELECT * FROM $table WHERE tenant_id = '$SAFE_TENANT_ID';
EOF
        
        if [ $? -eq 0 ]; then
            echo "  ✓ Exported $RECORD_COUNT records from $table"
            echo "  - $table.sql ($RECORD_COUNT records)" >> "$MANIFEST_FILE"
            TOTAL_RECORDS=$((TOTAL_RECORDS + RECORD_COUNT))
        else
            log_error "Failed to export table $table"
        fi
    else
        TABLE_EXISTS=$(postgres_table_exists "$table" || echo "0")

        if [ -z "$TABLE_EXISTS" ] || [ "$TABLE_EXISTS" -eq 0 ]; then
            echo "  ⚠ Table $table does not exist in schema $PGSCHEMA, skipping"
            continue
        fi

        HAS_TENANT_COL=$(postgres_table_has_tenant_column "$table" || echo "0")

        if [ -z "$HAS_TENANT_COL" ] || [ "$HAS_TENANT_COL" -eq 0 ]; then
            echo "  ⚠ Table $table has no tenant_id column, skipping"
            continue
        fi

        set +e
        EXPORT_COUNT=$(postgres_export_table_where "$table" "tenant_id = '$SAFE_TENANT_ID'" "$BACKUP_DIR/${table}.sql" "Tenant data for $TENANT_ID from $table")
        status=$?
        set -e

        if [ $status -eq 2 ]; then
            echo "  ⚠ Table $table does not exist in schema $PGSCHEMA, skipping"
            continue
        elif [ $status -ne 0 ]; then
            log_error "Failed to export table $table"
            continue
        fi

        if [ "${EXPORT_COUNT:-0}" -eq 0 ]; then
            echo "  ⓘ No records found in $table for tenant $TENANT_ID"
            continue
        fi

        echo "  ✓ Exported $EXPORT_COUNT records from $table"
        echo "  - $table.sql ($EXPORT_COUNT records)" >> "$MANIFEST_FILE"
        TOTAL_RECORDS=$((TOTAL_RECORDS + EXPORT_COUNT))
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

#!/bin/bash
#
# Database Restore Script
# Supports both SQLite and PostgreSQL
# 
# Usage: ./scripts/db_restore.sh <backup_file>
#

set -e

# Check if backup file is provided
if [ $# -lt 1 ]; then
    echo "Usage: $0 <backup_file>"
    echo ""
    echo "Examples:"
    echo "  $0 /data/backups/admin_sqlite_20251104_120000.db.gz"
    echo "  $0 /data/backups/admin_postgres_20251104_120000.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Load .env if exists
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Determine database type
DB_TYPE="${ADMIN_DB_TYPE:-sqlite}"

echo "========================================="
echo "Database Restore Script"
echo "========================================="
echo "Database Type: $DB_TYPE"
echo "Backup File: $BACKUP_FILE"
echo "========================================="

# Warning prompt
echo ""
echo "⚠️  WARNING: This will OVERWRITE the current database!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
echo ""

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Restore cancelled."
    exit 0
fi

# Decompress if needed
TEMP_FILE=""
if [[ "$BACKUP_FILE" == *.gz ]]; then
    echo "Decompressing backup file..."
    TEMP_FILE=$(mktemp)
    gunzip -c "$BACKUP_FILE" > "$TEMP_FILE"
    SOURCE_FILE="$TEMP_FILE"
    echo "✓ Decompressed to temporary file"
else
    SOURCE_FILE="$BACKUP_FILE"
fi

# Perform restore based on database type
if [ "$DB_TYPE" = "sqlite" ]; then
    # SQLite restore
    DB_PATH="${ADMIN_DB_PATH:-./data/admin.db}"
    
    echo "Restoring SQLite database..."
    echo "Destination: $DB_PATH"
    
    # Create backup of current database
    if [ -f "$DB_PATH" ]; then
        CURRENT_BACKUP="${DB_PATH}.before_restore.$(date +%Y%m%d_%H%M%S)"
        echo "Creating backup of current database: $CURRENT_BACKUP"
        cp "$DB_PATH" "$CURRENT_BACKUP"
    fi
    
    # Ensure data directory exists
    mkdir -p "$(dirname "$DB_PATH")"
    
    # Restore the database
    cp "$SOURCE_FILE" "$DB_PATH"
    
    # Verify the restored database
    if command -v sqlite3 &> /dev/null; then
        echo "Verifying restored database..."
        sqlite3 "$DB_PATH" "PRAGMA integrity_check;" > /dev/null
        echo "✓ Database integrity check passed"
    fi
    
    echo "✓ SQLite restore completed: $DB_PATH"
    
elif [ "$DB_TYPE" = "postgres" ] || [ "$DB_TYPE" = "postgresql" ]; then
    # PostgreSQL restore
    DATABASE_URL="${ADMIN_DATABASE_URL}"
    
    if [ -z "$DATABASE_URL" ]; then
        echo "ERROR: ADMIN_DATABASE_URL not set for PostgreSQL restore"
        exit 1
    fi
    
    # Parse PostgreSQL connection string
    if [[ $DATABASE_URL =~ postgresql://([^:]+):([^@]+)@([^:]+):([^/]+)/(.+) ]]; then
        PG_USER="${BASH_REMATCH[1]}"
        PG_PASSWORD="${BASH_REMATCH[2]}"
        PG_HOST="${BASH_REMATCH[3]}"
        PG_PORT="${BASH_REMATCH[4]}"
        PG_DATABASE="${BASH_REMATCH[5]}"
    elif [[ $DATABASE_URL =~ postgresql://([^:]+):([^@]+)@([^/]+)/(.+) ]]; then
        PG_USER="${BASH_REMATCH[1]}"
        PG_PASSWORD="${BASH_REMATCH[2]}"
        PG_HOST="${BASH_REMATCH[3]}"
        PG_PORT="5432"
        PG_DATABASE="${BASH_REMATCH[4]}"
    else
        echo "ERROR: Invalid DATABASE_URL format"
        exit 1
    fi
    
    echo "Restoring PostgreSQL database..."
    echo "Host: $PG_HOST:$PG_PORT"
    echo "Database: $PG_DATABASE"
    echo "User: $PG_USER"
    
    # Export password
    export PGPASSWORD="$PG_PASSWORD"
    
    # Create backup of current database
    CURRENT_BACKUP="/tmp/admin_postgres_before_restore_$(date +%Y%m%d_%H%M%S).sql"
    echo "Creating backup of current database: $CURRENT_BACKUP"
    pg_dump -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
        --format=plain --no-owner --no-acl > "$CURRENT_BACKUP" 2>/dev/null || true
    
    # Drop all tables (clean restore)
    echo "Dropping existing tables..."
    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
        -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" 2>&1
    
    # Restore from backup
    echo "Restoring from backup file..."
    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
        --quiet < "$SOURCE_FILE" 2>&1
    
    unset PGPASSWORD
    
    echo "✓ PostgreSQL restore completed"
    echo "✓ Pre-restore backup saved to: $CURRENT_BACKUP"
    
else
    echo "ERROR: Unsupported database type: $DB_TYPE"
    echo "Supported types: sqlite, postgres"
    exit 1
fi

# Clean up temporary file
if [ -n "$TEMP_FILE" ] && [ -f "$TEMP_FILE" ]; then
    rm "$TEMP_FILE"
    echo "✓ Cleaned up temporary files"
fi

echo ""
echo "========================================="
echo "✅ Restore completed successfully"
echo "========================================="

exit 0

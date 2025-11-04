#!/bin/bash
#
# Database Backup Script
# Supports both SQLite and PostgreSQL
# 
# Usage: ./scripts/db_backup.sh [--retention-days N]
#

set -e

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/data/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Load .env if exists
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --retention-days)
            RETENTION_DAYS="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Determine database type
DB_TYPE="${ADMIN_DB_TYPE:-sqlite}"

echo "========================================="
echo "Database Backup Script"
echo "========================================="
echo "Database Type: $DB_TYPE"
echo "Backup Directory: $BACKUP_DIR"
echo "Retention: $RETENTION_DAYS days"
echo "Timestamp: $TIMESTAMP"
echo "========================================="

# Perform backup based on database type
if [ "$DB_TYPE" = "sqlite" ]; then
    # SQLite backup
    DB_PATH="${ADMIN_DB_PATH:-./data/admin.db}"
    
    if [ ! -f "$DB_PATH" ]; then
        echo "ERROR: SQLite database not found at $DB_PATH"
        exit 1
    fi
    
    BACKUP_FILE="$BACKUP_DIR/admin_sqlite_${TIMESTAMP}.db"
    
    echo "Backing up SQLite database..."
    echo "Source: $DB_PATH"
    echo "Destination: $BACKUP_FILE"
    
    # Use sqlite3 .backup command for consistent backup
    if command -v sqlite3 &> /dev/null; then
        sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE'"
    else
        # Fallback to file copy if sqlite3 not available
        cp "$DB_PATH" "$BACKUP_FILE"
    fi
    
    # Compress the backup
    gzip "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
    
    echo "✓ SQLite backup completed: $BACKUP_FILE"
    
elif [ "$DB_TYPE" = "postgres" ] || [ "$DB_TYPE" = "postgresql" ]; then
    # PostgreSQL backup
    DATABASE_URL="${ADMIN_DATABASE_URL}"
    
    if [ -z "$DATABASE_URL" ]; then
        echo "ERROR: ADMIN_DATABASE_URL not set for PostgreSQL backup"
        exit 1
    fi
    
    # Parse PostgreSQL connection string
    # Format: postgresql://user:password@host:port/database
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
    
    BACKUP_FILE="$BACKUP_DIR/admin_postgres_${TIMESTAMP}.sql"
    
    echo "Backing up PostgreSQL database..."
    echo "Host: $PG_HOST:$PG_PORT"
    echo "Database: $PG_DATABASE"
    echo "User: $PG_USER"
    echo "Destination: $BACKUP_FILE"
    
    # Use pg_dump with password from environment
    export PGPASSWORD="$PG_PASSWORD"
    pg_dump -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
        --format=plain \
        --no-owner \
        --no-acl \
        --verbose \
        > "$BACKUP_FILE" 2>&1
    
    unset PGPASSWORD
    
    # Compress the backup
    gzip "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
    
    echo "✓ PostgreSQL backup completed: $BACKUP_FILE"
    
else
    echo "ERROR: Unsupported database type: $DB_TYPE"
    echo "Supported types: sqlite, postgres"
    exit 1
fi

# Calculate file size
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "Backup size: $BACKUP_SIZE"

# Rotate old backups (keep only last N days)
echo ""
echo "Rotating old backups (keeping last $RETENTION_DAYS days)..."
find "$BACKUP_DIR" -name "admin_*" -type f -mtime +"$RETENTION_DAYS" -print -delete

# List remaining backups
echo ""
echo "Current backups:"
ls -lh "$BACKUP_DIR"/admin_* 2>/dev/null || echo "No backups found"

echo ""
echo "========================================="
echo "✅ Backup completed successfully"
echo "========================================="

exit 0

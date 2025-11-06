#!/bin/bash
#
# Comprehensive Restore Script
# Restores all data from a full backup archive
#
# Usage: ./scripts/restore_all.sh <backup_archive>
#

set -e

# Check if backup file is provided
if [ $# -lt 1 ]; then
    echo "Usage: $0 <backup_archive>"
    echo ""
    echo "Example:"
    echo "  $0 /data/backups/full_backup_20251104_120000.tar.gz"
    echo ""
    echo "Available backups:"
    ls -lh /data/backups/full_backup_*.tar.gz 2>/dev/null || echo "  No backups found"
    exit 1
fi

BACKUP_ARCHIVE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_ARCHIVE" ]; then
    echo "ERROR: Backup archive not found: $BACKUP_ARCHIVE"
    exit 1
fi

# Load .env if exists
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

echo "========================================="
echo "Comprehensive Restore Script"
echo "========================================="
echo "Backup Archive: $BACKUP_ARCHIVE"
echo "========================================="

# Warning prompt
echo ""
echo "⚠️  WARNING: This will OVERWRITE current data!"
echo ""
echo "This restore will:"
echo "  - Replace the current database"
echo "  - Replace uploaded files"
echo "  - Replace configuration files (you may need to review)"
echo "  - Replace application data"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
echo ""

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Restore cancelled."
    exit 0
fi

# Create temporary directory for extraction
TEMP_DIR=$(mktemp -d)
echo "Extracting backup to temporary directory..."
tar -xzf "$BACKUP_ARCHIVE" -C "$TEMP_DIR" 2>/dev/null || {
    echo "ERROR: Failed to extract backup archive"
    rm -rf "$TEMP_DIR"
    exit 1
}

# Find the backup directory (should be the only directory in temp)
BACKUP_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)

if [ -z "$BACKUP_DIR" ] || [ ! -d "$BACKUP_DIR" ]; then
    echo "ERROR: Invalid backup archive structure"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "✓ Backup extracted successfully"

# Read and display manifest
if [ -f "$BACKUP_DIR/MANIFEST.txt" ]; then
    echo ""
    echo "Backup Manifest:"
    echo "========================================="
    cat "$BACKUP_DIR/MANIFEST.txt"
    echo "========================================="
    echo ""
fi

RESTORE_SUCCESS=true
RESTORE_ERRORS=""

# Function to log error
log_error() {
    RESTORE_SUCCESS=false
    RESTORE_ERRORS="${RESTORE_ERRORS}\n  - $1"
    echo "❌ ERROR: $1" >&2
}

# 1. Restore Database
echo "Step 1/4: Restoring database..."
DB_TYPE="${ADMIN_DB_TYPE:-sqlite}"

if [ -d "$BACKUP_DIR/database" ]; then
    if [ "$DB_TYPE" = "sqlite" ]; then
        DB_PATH="${ADMIN_DB_PATH:-./data/admin.db}"
        
        # Find the database backup file
        DB_BACKUP_FILE=$(find "$BACKUP_DIR/database" -name "admin.db.gz" -o -name "admin.db" | head -n 1)
        
        if [ -n "$DB_BACKUP_FILE" ]; then
            # Create backup of current database
            if [ -f "$DB_PATH" ]; then
                CURRENT_BACKUP="${DB_PATH}.before_restore.$(date +%Y%m%d_%H%M%S)"
                echo "  Creating safety backup: $CURRENT_BACKUP"
                cp "$DB_PATH" "$CURRENT_BACKUP"
            fi
            
            # Ensure data directory exists
            mkdir -p "$(dirname "$DB_PATH")"
            
            # Decompress if needed
            if [[ "$DB_BACKUP_FILE" == *.gz ]]; then
                gunzip -c "$DB_BACKUP_FILE" > "$DB_PATH"
            else
                cp "$DB_BACKUP_FILE" "$DB_PATH"
            fi
            
            # Verify the restored database
            if command -v sqlite3 &> /dev/null; then
                sqlite3 "$DB_PATH" "PRAGMA integrity_check;" > /dev/null && \
                echo "✓ SQLite database restored and verified" || \
                log_error "Database integrity check failed"
            else
                echo "✓ SQLite database restored (verification skipped - sqlite3 not available)"
            fi
        else
            echo "  No SQLite database backup found"
        fi
        
    elif [ "$DB_TYPE" = "postgres" ] || [ "$DB_TYPE" = "postgresql" ]; then
        DATABASE_URL="${ADMIN_DATABASE_URL}"
        
        if [ -z "$DATABASE_URL" ]; then
            log_error "ADMIN_DATABASE_URL not set for PostgreSQL restore"
        else
            # Find the database backup file
            DB_BACKUP_FILE=$(find "$BACKUP_DIR/database" -name "*.sql.gz" -o -name "*.sql" | head -n 1)
            
            if [ -n "$DB_BACKUP_FILE" ]; then
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
                    log_error "Invalid DATABASE_URL format"
                fi
                
                if [ -n "$PG_USER" ]; then
                    export PGPASSWORD="$PG_PASSWORD"
                    
                    # Create backup of current database
                    CURRENT_BACKUP="/tmp/admin_postgres_before_restore_$(date +%Y%m%d_%H%M%S).sql"
                    echo "  Creating safety backup: $CURRENT_BACKUP"
                    pg_dump -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
                        --format=plain --no-owner --no-acl > "$CURRENT_BACKUP" 2>/dev/null || true
                    
                    # Drop and recreate schema
                    echo "  Dropping existing tables..."
                    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
                        -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" 2>&1 || \
                        log_error "Failed to drop existing schema"
                    
                    # Restore from backup
                    echo "  Restoring from backup..."
                    if [[ "$DB_BACKUP_FILE" == *.gz ]]; then
                        gunzip -c "$DB_BACKUP_FILE" | psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
                            --quiet 2>&1 && \
                        echo "✓ PostgreSQL database restored" || \
                        log_error "Failed to restore PostgreSQL database"
                    else
                        psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
                            --quiet < "$DB_BACKUP_FILE" 2>&1 && \
                        echo "✓ PostgreSQL database restored" || \
                        log_error "Failed to restore PostgreSQL database"
                    fi
                    
                    unset PGPASSWORD
                fi
            else
                echo "  No PostgreSQL database backup found"
            fi
        fi
    fi
else
    echo "  No database backup found in archive"
fi

# 2. Restore uploaded files
echo ""
echo "Step 2/4: Restoring uploaded files..."

if [ -d "$BACKUP_DIR/files" ]; then
    FILES_COUNT=0
    
    for file_archive in "$BACKUP_DIR/files"/*.tar.gz; do
        if [ -f "$file_archive" ]; then
            ARCHIVE_NAME=$(basename "$file_archive" .tar.gz)
            
            # Determine restore location
            if [ -d "./uploads" ]; then
                RESTORE_BASE="."
            elif [ -d "./data/uploads" ]; then
                RESTORE_BASE="./data"
            elif [ -d "/data/uploads" ]; then
                RESTORE_BASE="/data"
            else
                RESTORE_BASE="."
            fi
            
            # Create directory if needed
            mkdir -p "$RESTORE_BASE"
            
            # Extract files
            tar -xzf "$file_archive" -C "$RESTORE_BASE" 2>/dev/null && \
            FILES_COUNT=$((FILES_COUNT + 1)) && \
            echo "  ✓ Restored $ARCHIVE_NAME" || \
            log_error "Failed to restore $ARCHIVE_NAME"
        fi
    done
    
    if [ $FILES_COUNT -gt 0 ]; then
        echo "✓ Restored $FILES_COUNT file archive(s)"
    else
        echo "  No file archives found"
    fi
else
    echo "  No uploaded files backup found"
fi

# 3. Restore configuration files
echo ""
echo "Step 3/4: Restoring configuration files..."
echo "  ⚠️  Note: This will overwrite your current configuration!"
echo "  You may need to review and adjust after restore."
echo ""

if [ -d "$BACKUP_DIR/config" ]; then
    CONFIG_COUNT=0
    
    for config_file in "$BACKUP_DIR/config"/*; do
        if [ -f "$config_file" ]; then
            FILENAME=$(basename "$config_file")
            
            # Create backup of current config file
            if [ -f "$FILENAME" ]; then
                cp "$FILENAME" "${FILENAME}.before_restore.$(date +%Y%m%d_%H%M%S)"
            fi
            
            # Restore config file
            cp "$config_file" "$FILENAME" && \
            CONFIG_COUNT=$((CONFIG_COUNT + 1)) && \
            echo "  ✓ Restored $FILENAME" || \
            log_error "Failed to restore $FILENAME"
        fi
    done
    
    if [ $CONFIG_COUNT -gt 0 ]; then
        echo "✓ Restored $CONFIG_COUNT configuration file(s)"
    else
        echo "  No configuration files found"
    fi
else
    echo "  No configuration backup found"
fi

# 4. Restore application data
echo ""
echo "Step 4/4: Restoring application data..."

if [ -d "$BACKUP_DIR/data" ]; then
    DATA_ARCHIVE="$BACKUP_DIR/data/app_data.tar.gz"
    
    if [ -f "$DATA_ARCHIVE" ]; then
        # Create backup of current data directory
        if [ -d "./data" ]; then
            tar -czf "./data.before_restore.$(date +%Y%m%d_%H%M%S).tar.gz" ./data 2>/dev/null || true
        fi
        
        # Extract data
        tar -xzf "$DATA_ARCHIVE" -C . 2>/dev/null && \
        echo "✓ Restored application data directory" || \
        log_error "Failed to restore application data"
    else
        echo "  No application data archive found"
    fi
else
    echo "  No application data backup found"
fi

# Clean up temporary directory
rm -rf "$TEMP_DIR"
echo ""
echo "✓ Cleaned up temporary files"

# Final status
echo ""
echo "========================================="
if [ "$RESTORE_SUCCESS" = true ]; then
    echo "✅ Restore completed successfully"
    echo "========================================="
    echo ""
    echo "Next steps:"
    echo "  1. Review restored configuration files"
    echo "  2. Verify database connectivity"
    echo "  3. Test application functionality"
    echo "  4. Check file permissions"
    echo "  5. Restart application services"
    exit 0
else
    echo "⚠️  Restore completed with errors"
    echo "========================================="
    echo ""
    echo "Errors encountered:"
    echo -e "$RESTORE_ERRORS"
    echo ""
    echo "Please review the errors and take corrective action."
    exit 1
fi

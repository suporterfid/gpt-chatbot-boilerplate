#!/bin/bash
#
# Comprehensive Backup Script
# Backs up all persistent data including database, files, and configuration
#
# Usage: ./scripts/backup_all.sh [--retention-days N] [--offsite]
#

set -e

# Configuration
BACKUP_BASE_DIR="${BACKUP_DIR:-/data/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="full_backup_${TIMESTAMP}"
BACKUP_DIR="$BACKUP_BASE_DIR/$BACKUP_NAME"
OFFSITE_BACKUP=false
OFFSITE_DESTINATION="${OFFSITE_DESTINATION:-}"

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
        --offsite)
            OFFSITE_BACKUP=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--retention-days N] [--offsite]"
            exit 1
            ;;
    esac
done

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo "========================================="
echo "Comprehensive Backup Script"
echo "========================================="
echo "Timestamp: $TIMESTAMP"
echo "Backup Directory: $BACKUP_DIR"
echo "Retention: $RETENTION_DAYS days"
echo "Off-site: $OFFSITE_BACKUP"
echo "========================================="
echo ""

# Create backup manifest
MANIFEST_FILE="$BACKUP_DIR/MANIFEST.txt"
echo "Backup created at: $(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$MANIFEST_FILE"
echo "Backup name: $BACKUP_NAME" >> "$MANIFEST_FILE"
echo "Hostname: $(hostname)" >> "$MANIFEST_FILE"
echo "" >> "$MANIFEST_FILE"
echo "Components:" >> "$MANIFEST_FILE"

BACKUP_SUCCESS=true
BACKUP_ERRORS=""

# Function to add to manifest
add_to_manifest() {
    echo "- $1" >> "$MANIFEST_FILE"
}

# Function to log error
log_error() {
    BACKUP_SUCCESS=false
    BACKUP_ERRORS="${BACKUP_ERRORS}\n  - $1"
    echo "❌ ERROR: $1" >&2
}

# 1. Backup Database
echo "Step 1/5: Backing up database..."
DB_TYPE="${ADMIN_DB_TYPE:-sqlite}"
DB_BACKUP_DIR="$BACKUP_DIR/database"
mkdir -p "$DB_BACKUP_DIR"

if [ "$DB_TYPE" = "sqlite" ]; then
    DB_PATH="${ADMIN_DB_PATH:-./data/admin.db}"
    
    if [ -f "$DB_PATH" ]; then
        DB_BACKUP_FILE="$DB_BACKUP_DIR/admin.db"
        
        if command -v sqlite3 &> /dev/null; then
            sqlite3 "$DB_PATH" ".backup '$DB_BACKUP_FILE'" && \
            gzip "$DB_BACKUP_FILE" && \
            add_to_manifest "Database: SQLite (admin.db.gz)" && \
            echo "✓ SQLite database backed up" || \
            log_error "Failed to backup SQLite database"
        else
            cp "$DB_PATH" "$DB_BACKUP_FILE" && \
            gzip "$DB_BACKUP_FILE" && \
            add_to_manifest "Database: SQLite (admin.db.gz, copied)" && \
            echo "✓ SQLite database backed up (copy method)" || \
            log_error "Failed to backup SQLite database"
        fi
    else
        log_error "SQLite database not found at $DB_PATH"
    fi
    
elif [ "$DB_TYPE" = "postgres" ] || [ "$DB_TYPE" = "postgresql" ]; then
    DATABASE_URL="${ADMIN_DATABASE_URL}"
    
    if [ -z "$DATABASE_URL" ]; then
        log_error "ADMIN_DATABASE_URL not set for PostgreSQL backup"
    else
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
            DB_BACKUP_FILE="$DB_BACKUP_DIR/admin_postgres.sql"
            
            export PGPASSWORD="$PG_PASSWORD"
            pg_dump -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DATABASE" \
                --format=plain --no-owner --no-acl \
                > "$DB_BACKUP_FILE" 2>&1 && \
            gzip "$DB_BACKUP_FILE" && \
            add_to_manifest "Database: PostgreSQL (admin_postgres.sql.gz)" && \
            echo "✓ PostgreSQL database backed up" || \
            log_error "Failed to backup PostgreSQL database"
            
            unset PGPASSWORD
        fi
    fi
else
    log_error "Unsupported database type: $DB_TYPE"
fi

# 2. Backup uploaded files (if they exist)
echo ""
echo "Step 2/5: Backing up uploaded files..."
FILES_BACKUP_DIR="$BACKUP_DIR/files"
FILES_FOUND=false

# Check for common upload directories
for upload_dir in "./uploads" "./data/uploads" "/data/uploads" "./public/uploads"; do
    if [ -d "$upload_dir" ] && [ "$(ls -A $upload_dir 2>/dev/null)" ]; then
        FILES_FOUND=true
        mkdir -p "$FILES_BACKUP_DIR"
        
        UPLOAD_BASENAME=$(basename "$upload_dir")
        tar -czf "$FILES_BACKUP_DIR/${UPLOAD_BASENAME}.tar.gz" -C "$(dirname $upload_dir)" "$UPLOAD_BASENAME" 2>/dev/null && \
        add_to_manifest "Files: $upload_dir (${UPLOAD_BASENAME}.tar.gz)" && \
        echo "✓ Backed up $upload_dir" || \
        log_error "Failed to backup $upload_dir"
    fi
done

if [ "$FILES_FOUND" = false ]; then
    echo "  No upload directories found (this is normal if file uploads are disabled)"
    add_to_manifest "Files: None found"
fi

# 3. Backup configuration files
echo ""
echo "Step 3/5: Backing up configuration..."
CONFIG_BACKUP_DIR="$BACKUP_DIR/config"
mkdir -p "$CONFIG_BACKUP_DIR"

CONFIG_FILES=(
    ".env"
    "config.php"
    "composer.json"
    "package.json"
    ".htaccess"
    "nginx.conf"
    "docker-compose.yml"
)

CONFIG_COUNT=0
for config_file in "${CONFIG_FILES[@]}"; do
    if [ -f "$config_file" ]; then
        cp "$config_file" "$CONFIG_BACKUP_DIR/" 2>/dev/null && \
        CONFIG_COUNT=$((CONFIG_COUNT + 1)) || \
        log_error "Failed to backup $config_file"
    fi
done

if [ $CONFIG_COUNT -gt 0 ]; then
    add_to_manifest "Configuration: $CONFIG_COUNT files"
    echo "✓ Backed up $CONFIG_COUNT configuration files"
else
    log_error "No configuration files found"
fi

# 4. Backup application data directory
echo ""
echo "Step 4/5: Backing up application data..."
DATA_BACKUP_DIR="$BACKUP_DIR/data"

if [ -d "./data" ]; then
    mkdir -p "$DATA_BACKUP_DIR"
    
    # Exclude database files (already backed up separately)
    tar -czf "$DATA_BACKUP_DIR/app_data.tar.gz" \
        --exclude='*.db' \
        --exclude='*.db-journal' \
        --exclude='*.db-wal' \
        --exclude='*.db-shm' \
        -C . data 2>/dev/null && \
    add_to_manifest "Application Data: ./data (app_data.tar.gz)" && \
    echo "✓ Backed up application data directory" || \
    log_error "Failed to backup application data directory"
else
    echo "  No ./data directory found (this may be normal for your setup)"
    add_to_manifest "Application Data: None found"
fi

# 5. Create backup summary
echo ""
echo "Step 5/5: Creating backup summary..."

BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "" >> "$MANIFEST_FILE"
echo "Backup Size: $BACKUP_SIZE" >> "$MANIFEST_FILE"
echo "Backup Status: $([ "$BACKUP_SUCCESS" = true ] && echo "SUCCESS" || echo "PARTIAL")" >> "$MANIFEST_FILE"

if [ "$BACKUP_SUCCESS" = false ]; then
    echo "" >> "$MANIFEST_FILE"
    echo "Errors:" >> "$MANIFEST_FILE"
    echo -e "$BACKUP_ERRORS" >> "$MANIFEST_FILE"
fi

# Create a tarball of the entire backup
echo ""
echo "Creating backup archive..."
cd "$BACKUP_BASE_DIR"
tar -czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME" 2>/dev/null && \
echo "✓ Created backup archive: ${BACKUP_NAME}.tar.gz" || \
log_error "Failed to create backup archive"

# Calculate final size
ARCHIVE_SIZE=$(du -h "${BACKUP_NAME}.tar.gz" 2>/dev/null | cut -f1)
echo "Archive size: $ARCHIVE_SIZE"

# Remove uncompressed backup directory to save space
rm -rf "$BACKUP_NAME"

# 6. Off-site backup (if enabled)
if [ "$OFFSITE_BACKUP" = true ]; then
    echo ""
    echo "Step 6/6: Copying to off-site location..."
    
    if [ -z "$OFFSITE_DESTINATION" ]; then
        log_error "OFFSITE_DESTINATION not set. Set it in .env or export it before running."
    else
        # Try to copy to off-site location
        # This could be rsync to a remote server, S3, etc.
        if command -v rsync &> /dev/null && [[ "$OFFSITE_DESTINATION" =~ ^[a-zA-Z0-9_-]+@.+:.+ ]]; then
            # Remote rsync
            rsync -avz "${BACKUP_NAME}.tar.gz" "$OFFSITE_DESTINATION/" && \
            echo "✓ Copied to off-site location: $OFFSITE_DESTINATION" || \
            log_error "Failed to copy to off-site location"
        elif command -v aws &> /dev/null && [[ "$OFFSITE_DESTINATION" =~ ^s3:// ]]; then
            # AWS S3
            aws s3 cp "${BACKUP_NAME}.tar.gz" "$OFFSITE_DESTINATION/" && \
            echo "✓ Uploaded to S3: $OFFSITE_DESTINATION" || \
            log_error "Failed to upload to S3"
        else
            # Local/mounted filesystem copy
            cp "${BACKUP_NAME}.tar.gz" "$OFFSITE_DESTINATION/" && \
            echo "✓ Copied to off-site location: $OFFSITE_DESTINATION" || \
            log_error "Failed to copy to off-site location"
        fi
    fi
fi

# Rotate old backups
echo ""
echo "Rotating old backups (keeping last $RETENTION_DAYS days)..."
find "$BACKUP_BASE_DIR" -name "full_backup_*.tar.gz" -type f -mtime +"$RETENTION_DAYS" -print -delete

# List remaining backups
echo ""
echo "Current backups:"
ls -lh "$BACKUP_BASE_DIR"/full_backup_*.tar.gz 2>/dev/null || echo "No backups found"

# Final status
echo ""
echo "========================================="
if [ "$BACKUP_SUCCESS" = true ]; then
    echo "✅ Backup completed successfully"
    echo "========================================="
    echo "Backup file: $BACKUP_BASE_DIR/${BACKUP_NAME}.tar.gz"
    echo "Backup size: $ARCHIVE_SIZE"
    exit 0
else
    echo "⚠️  Backup completed with errors"
    echo "========================================="
    echo "Backup file: $BACKUP_BASE_DIR/${BACKUP_NAME}.tar.gz"
    echo "Backup size: $ARCHIVE_SIZE"
    echo ""
    echo "Errors encountered:"
    echo -e "$BACKUP_ERRORS"
    exit 1
fi

#!/bin/bash
#
# Backup Restore Testing Script
# Validates that backups can be successfully restored
#
# Usage: ./scripts/test_restore.sh [--staging-server HOST] [--backup-file FILE]
#

set -e

# Configuration
STAGING_SERVER="${STAGING_SERVER:-}"
BACKUP_FILE="${BACKUP_FILE:-}"
TEMP_RESTORE_DIR="/tmp/restore_test_$(date +%Y%m%d_%H%M%S)"
TEST_REPORT="/tmp/restore_test_report_$(date +%Y%m%d_%H%M%S).txt"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --staging-server)
            STAGING_SERVER="$2"
            shift 2
            ;;
        --backup-file)
            BACKUP_FILE="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--staging-server HOST] [--backup-file FILE]"
            exit 1
            ;;
    esac
done

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=========================================" | tee "$TEST_REPORT"
echo "Backup Restore Testing" | tee -a "$TEST_REPORT"
echo "=========================================" | tee -a "$TEST_REPORT"
echo "Date: $(date)" | tee -a "$TEST_REPORT"
echo "" | tee -a "$TEST_REPORT"

TEST_PASSED=0
TEST_FAILED=0
TEST_WARNINGS=0

# Function to log test result
log_test() {
    local status=$1
    local test_name=$2
    local message=$3
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name" | tee -a "$TEST_REPORT"
        TEST_PASSED=$((TEST_PASSED + 1))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}✗ FAIL${NC}: $test_name - $message" | tee -a "$TEST_REPORT"
        TEST_FAILED=$((TEST_FAILED + 1))
    else
        echo -e "${YELLOW}⚠ WARN${NC}: $test_name - $message" | tee -a "$TEST_REPORT"
        TEST_WARNINGS=$((TEST_WARNINGS + 1))
    fi
}

# Test 1: Verify backup file selection
echo "Test 1: Verify backup file" | tee -a "$TEST_REPORT"
if [ -z "$BACKUP_FILE" ]; then
    # Find latest backup
    BACKUP_FILE=$(find /data/backups -name "full_backup_*.tar.gz" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    
    if [ -z "$BACKUP_FILE" ]; then
        log_test "FAIL" "Backup file discovery" "No backup files found in /data/backups"
        exit 1
    fi
fi

if [ ! -f "$BACKUP_FILE" ]; then
    log_test "FAIL" "Backup file existence" "File not found: $BACKUP_FILE"
    exit 1
fi

log_test "PASS" "Backup file found" ""
echo "  Using backup: $BACKUP_FILE" | tee -a "$TEST_REPORT"

# Test 2: Verify backup archive integrity
echo "" | tee -a "$TEST_REPORT"
echo "Test 2: Verify archive integrity" | tee -a "$TEST_REPORT"
if tar -tzf "$BACKUP_FILE" > /dev/null 2>&1; then
    log_test "PASS" "Archive integrity" ""
else
    log_test "FAIL" "Archive integrity" "Archive is corrupted"
    exit 1
fi

# Test 3: Extract backup to temporary location
echo "" | tee -a "$TEST_REPORT"
echo "Test 3: Extract backup archive" | tee -a "$TEST_REPORT"
mkdir -p "$TEMP_RESTORE_DIR"
if tar -xzf "$BACKUP_FILE" -C "$TEMP_RESTORE_DIR" 2>/dev/null; then
    log_test "PASS" "Backup extraction" ""
else
    log_test "FAIL" "Backup extraction" "Failed to extract archive"
    rm -rf "$TEMP_RESTORE_DIR"
    exit 1
fi

BACKUP_DIR=$(find "$TEMP_RESTORE_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)

# Test 4: Verify backup manifest
echo "" | tee -a "$TEST_REPORT"
echo "Test 4: Verify backup manifest" | tee -a "$TEST_REPORT"
if [ -f "$BACKUP_DIR/MANIFEST.txt" ]; then
    log_test "PASS" "Manifest exists" ""
    echo "Manifest contents:" | tee -a "$TEST_REPORT"
    cat "$BACKUP_DIR/MANIFEST.txt" | tee -a "$TEST_REPORT"
else
    log_test "WARN" "Manifest missing" "Backup manifest not found"
fi

# Test 5: Verify database backup
echo "" | tee -a "$TEST_REPORT"
echo "Test 5: Verify database backup" | tee -a "$TEST_REPORT"
if [ -d "$BACKUP_DIR/database" ]; then
    DB_FILES=$(find "$BACKUP_DIR/database" -type f | wc -l)
    if [ $DB_FILES -gt 0 ]; then
        log_test "PASS" "Database backup present" ""
        echo "  Database files: $DB_FILES" | tee -a "$TEST_REPORT"
        
        # Test database integrity if SQLite
        SQLITE_DB=$(find "$BACKUP_DIR/database" -name "*.db" -o -name "*.db.gz" | head -1)
        if [ -n "$SQLITE_DB" ] && command -v sqlite3 &> /dev/null; then
            TEMP_DB="/tmp/test_db_$(date +%s).db"
            
            if [[ "$SQLITE_DB" == *.gz ]]; then
                gunzip -c "$SQLITE_DB" > "$TEMP_DB"
            else
                cp "$SQLITE_DB" "$TEMP_DB"
            fi
            
            if sqlite3 "$TEMP_DB" "PRAGMA integrity_check;" > /dev/null 2>&1; then
                log_test "PASS" "Database integrity check" ""
            else
                log_test "FAIL" "Database integrity check" "SQLite integrity check failed"
            fi
            
            rm -f "$TEMP_DB"
        fi
    else
        log_test "FAIL" "Database backup" "No database files found"
    fi
else
    log_test "FAIL" "Database backup" "Database directory not found"
fi

# Test 6: Verify configuration files
echo "" | tee -a "$TEST_REPORT"
echo "Test 6: Verify configuration files" | tee -a "$TEST_REPORT"
if [ -d "$BACKUP_DIR/config" ]; then
    CONFIG_FILES=$(find "$BACKUP_DIR/config" -type f | wc -l)
    if [ $CONFIG_FILES -gt 0 ]; then
        log_test "PASS" "Configuration backup present" ""
        echo "  Configuration files: $CONFIG_FILES" | tee -a "$TEST_REPORT"
        
        # List key files
        for key_file in .env config.php composer.json; do
            if [ -f "$BACKUP_DIR/config/$key_file" ]; then
                echo "    ✓ $key_file" | tee -a "$TEST_REPORT"
            fi
        done
    else
        log_test "WARN" "Configuration backup" "No configuration files found"
    fi
else
    log_test "WARN" "Configuration backup" "Configuration directory not found"
fi

# Test 7: Verify file backups
echo "" | tee -a "$TEST_REPORT"
echo "Test 7: Verify uploaded files backup" | tee -a "$TEST_REPORT"
if [ -d "$BACKUP_DIR/files" ]; then
    FILE_ARCHIVES=$(find "$BACKUP_DIR/files" -name "*.tar.gz" | wc -l)
    if [ $FILE_ARCHIVES -gt 0 ]; then
        log_test "PASS" "File backups present" ""
        echo "  File archives: $FILE_ARCHIVES" | tee -a "$TEST_REPORT"
        
        # Test one archive
        TEST_ARCHIVE=$(find "$BACKUP_DIR/files" -name "*.tar.gz" | head -1)
        if tar -tzf "$TEST_ARCHIVE" > /dev/null 2>&1; then
            log_test "PASS" "File archive integrity" ""
        else
            log_test "FAIL" "File archive integrity" "Archive is corrupted"
        fi
    else
        log_test "WARN" "File backups" "No file archives found (may be normal)"
    fi
else
    log_test "WARN" "File backups" "Files directory not found (may be normal)"
fi

# Test 8: Verify application data
echo "" | tee -a "$TEST_REPORT"
echo "Test 8: Verify application data backup" | tee -a "$TEST_REPORT"
if [ -d "$BACKUP_DIR/data" ]; then
    if [ -f "$BACKUP_DIR/data/app_data.tar.gz" ]; then
        log_test "PASS" "Application data backup present" ""
        
        if tar -tzf "$BACKUP_DIR/data/app_data.tar.gz" > /dev/null 2>&1; then
            log_test "PASS" "Application data archive integrity" ""
        else
            log_test "FAIL" "Application data archive integrity" "Archive is corrupted"
        fi
    else
        log_test "WARN" "Application data backup" "Archive not found"
    fi
else
    log_test "WARN" "Application data backup" "Data directory not found"
fi

# Test 9: Staging server restore test (if specified)
if [ -n "$STAGING_SERVER" ]; then
    echo "" | tee -a "$TEST_REPORT"
    echo "Test 9: Staging server restore test" | tee -a "$TEST_REPORT"
    
    # Copy backup to staging server
    echo "  Copying backup to staging server..." | tee -a "$TEST_REPORT"
    if scp "$BACKUP_FILE" "$STAGING_SERVER:/tmp/test_backup.tar.gz" 2>/dev/null; then
        log_test "PASS" "Backup transfer to staging" ""
    else
        log_test "FAIL" "Backup transfer to staging" "Failed to copy backup"
    fi
    
    # Run restore on staging
    echo "  Running restore on staging server..." | tee -a "$TEST_REPORT"
    if ssh "$STAGING_SERVER" "cd /var/www/chatbot && ./scripts/restore_all.sh /tmp/test_backup.tar.gz <<< yes" 2>/dev/null; then
        log_test "PASS" "Staging server restore" ""
    else
        log_test "FAIL" "Staging server restore" "Restore failed on staging"
    fi
    
    # Test staging health
    echo "  Testing staging server health..." | tee -a "$TEST_REPORT"
    if ssh "$STAGING_SERVER" "cd /var/www/chatbot && php -r \"require 'config.php'; echo 'OK';\"" 2>/dev/null | grep -q "OK"; then
        log_test "PASS" "Staging server configuration" ""
    else
        log_test "FAIL" "Staging server configuration" "Configuration test failed"
    fi
    
    # Cleanup
    ssh "$STAGING_SERVER" "rm -f /tmp/test_backup.tar.gz" 2>/dev/null || true
else
    echo "" | tee -a "$TEST_REPORT"
    echo "Test 9: Staging server restore test - SKIPPED" | tee -a "$TEST_REPORT"
    echo "  Use --staging-server to test actual restore" | tee -a "$TEST_REPORT"
fi

# Cleanup
rm -rf "$TEMP_RESTORE_DIR"

# Summary
echo "" | tee -a "$TEST_REPORT"
echo "=========================================" | tee -a "$TEST_REPORT"
echo "Test Summary" | tee -a "$TEST_REPORT"
echo "=========================================" | tee -a "$TEST_REPORT"
echo "Passed:   $TEST_PASSED" | tee -a "$TEST_REPORT"
echo "Failed:   $TEST_FAILED" | tee -a "$TEST_REPORT"
echo "Warnings: $TEST_WARNINGS" | tee -a "$TEST_REPORT"
echo "" | tee -a "$TEST_REPORT"

if [ $TEST_FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All critical tests passed${NC}" | tee -a "$TEST_REPORT"
    if [ $TEST_WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠  Some warnings detected - review report${NC}" | tee -a "$TEST_REPORT"
    fi
else
    echo -e "${RED}❌ Some tests failed - backup may not be restorable${NC}" | tee -a "$TEST_REPORT"
fi

echo "" | tee -a "$TEST_REPORT"
echo "Full report saved to: $TEST_REPORT" | tee -a "$TEST_REPORT"
echo "=========================================" | tee -a "$TEST_REPORT"

# Exit with appropriate code
if [ $TEST_FAILED -eq 0 ]; then
    exit 0
else
    exit 1
fi

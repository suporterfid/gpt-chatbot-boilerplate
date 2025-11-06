#!/bin/bash
#
# Backup Monitoring Script
# Checks backup health and sends alerts if issues are detected
#
# Usage: ./scripts/monitor_backups.sh
#

set -e

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/data/backups}"
MAX_BACKUP_AGE_HOURS="${MAX_BACKUP_AGE_HOURS:-25}"  # Alert if latest backup is older than this
MIN_BACKUP_COUNT="${MIN_BACKUP_COUNT:-3}"  # Alert if fewer backups than this
ALERT_EMAIL="${ALERT_EMAIL:-}"
ALERT_WEBHOOK="${ALERT_WEBHOOK:-}"
ALERT_SLACK_WEBHOOK="${ALERT_SLACK_WEBHOOK:-}"

# Load .env if exists
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

ALERTS=()
WARNINGS=()
STATUS="OK"

# Function to add alert
add_alert() {
    ALERTS+=("$1")
    STATUS="CRITICAL"
}

# Function to add warning
add_warning() {
    WARNINGS+=("$1")
    if [ "$STATUS" != "CRITICAL" ]; then
        STATUS="WARNING"
    fi
}

echo "========================================="
echo "Backup Monitoring Report"
echo "========================================="
echo "Timestamp: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "Backup Directory: $BACKUP_DIR"
echo ""

# Check 1: Backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    add_alert "Backup directory does not exist: $BACKUP_DIR"
    echo -e "${RED}✗ Backup directory not found${NC}"
else
    echo -e "${GREEN}✓ Backup directory exists${NC}"
    
    # Check 2: Find latest backup
    LATEST_BACKUP=$(find "$BACKUP_DIR" -name "full_backup_*.tar.gz" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    
    if [ -z "$LATEST_BACKUP" ]; then
        add_alert "No backup files found in $BACKUP_DIR"
        echo -e "${RED}✗ No backups found${NC}"
    else
        # Get backup age
        BACKUP_TIMESTAMP=$(stat -c %Y "$LATEST_BACKUP" 2>/dev/null || stat -f %m "$LATEST_BACKUP" 2>/dev/null)
        CURRENT_TIMESTAMP=$(date +%s)
        BACKUP_AGE_SECONDS=$((CURRENT_TIMESTAMP - BACKUP_TIMESTAMP))
        BACKUP_AGE_HOURS=$((BACKUP_AGE_SECONDS / 3600))
        BACKUP_AGE_MINUTES=$(((BACKUP_AGE_SECONDS % 3600) / 60))
        
        echo -e "${GREEN}✓ Latest backup found${NC}"
        echo "  File: $(basename $LATEST_BACKUP)"
        echo "  Age: ${BACKUP_AGE_HOURS}h ${BACKUP_AGE_MINUTES}m"
        
        # Check backup age
        if [ $BACKUP_AGE_HOURS -gt $MAX_BACKUP_AGE_HOURS ]; then
            add_alert "Latest backup is ${BACKUP_AGE_HOURS} hours old (threshold: ${MAX_BACKUP_AGE_HOURS}h)"
            echo -e "${RED}✗ Backup is too old${NC}"
        else
            echo -e "${GREEN}✓ Backup age is acceptable${NC}"
        fi
        
        # Check backup size
        BACKUP_SIZE=$(stat -c %s "$LATEST_BACKUP" 2>/dev/null || stat -f %z "$LATEST_BACKUP" 2>/dev/null)
        BACKUP_SIZE_MB=$((BACKUP_SIZE / 1024 / 1024))
        
        echo "  Size: ${BACKUP_SIZE_MB} MB"
        
        if [ $BACKUP_SIZE_MB -lt 1 ]; then
            add_warning "Latest backup size is suspiciously small (${BACKUP_SIZE_MB} MB)"
            echo -e "${YELLOW}⚠ Backup size seems small${NC}"
        else
            echo -e "${GREEN}✓ Backup size looks reasonable${NC}"
        fi
        
        # Check backup integrity (if tar can test it)
        echo -n "  Testing archive integrity... "
        if tar -tzf "$LATEST_BACKUP" > /dev/null 2>&1; then
            echo -e "${GREEN}✓ Archive is valid${NC}"
        else
            add_alert "Latest backup archive is corrupted or invalid"
            echo -e "${RED}✗ Archive test failed${NC}"
        fi
    fi
    
    # Check 3: Backup count
    BACKUP_COUNT=$(find "$BACKUP_DIR" -name "full_backup_*.tar.gz" -type f 2>/dev/null | wc -l)
    echo ""
    echo "Total backups: $BACKUP_COUNT"
    
    if [ $BACKUP_COUNT -lt $MIN_BACKUP_COUNT ]; then
        add_warning "Only $BACKUP_COUNT backups found (minimum: $MIN_BACKUP_COUNT)"
        echo -e "${YELLOW}⚠ Backup count is low${NC}"
    else
        echo -e "${GREEN}✓ Sufficient backup count${NC}"
    fi
    
    # Check 4: Backup rotation is working
    if [ $BACKUP_COUNT -gt 0 ]; then
        # Check if we have backups from different days
        UNIQUE_DAYS=$(find "$BACKUP_DIR" -name "full_backup_*.tar.gz" -type f -printf '%TY-%Tm-%Td\n' 2>/dev/null | sort -u | wc -l)
        echo "Backups from $UNIQUE_DAYS different day(s)"
        
        if [ $UNIQUE_DAYS -eq 1 ] && [ $BACKUP_COUNT -gt 1 ]; then
            add_warning "All backups are from the same day - rotation may not be working"
            echo -e "${YELLOW}⚠ Rotation may be stuck${NC}"
        else
            echo -e "${GREEN}✓ Backup rotation appears to be working${NC}"
        fi
    fi
    
    # Check 5: Disk space
    DISK_USAGE=$(df -h "$BACKUP_DIR" | awk 'NR==2 {print $5}' | sed 's/%//')
    echo ""
    echo "Disk usage: ${DISK_USAGE}%"
    
    if [ $DISK_USAGE -gt 90 ]; then
        add_alert "Disk usage is critical: ${DISK_USAGE}%"
        echo -e "${RED}✗ Disk space critical${NC}"
    elif [ $DISK_USAGE -gt 80 ]; then
        add_warning "Disk usage is high: ${DISK_USAGE}%"
        echo -e "${YELLOW}⚠ Disk space running low${NC}"
    else
        echo -e "${GREEN}✓ Disk space is adequate${NC}"
    fi
fi

# Check 6: Backup scripts are executable
echo ""
BACKUP_SCRIPTS=(
    "./scripts/db_backup.sh"
    "./scripts/backup_all.sh"
    "./scripts/db_restore.sh"
    "./scripts/restore_all.sh"
)

SCRIPT_ISSUES=0
for script in "${BACKUP_SCRIPTS[@]}"; do
    if [ ! -f "$script" ]; then
        add_warning "Backup script not found: $script"
        SCRIPT_ISSUES=$((SCRIPT_ISSUES + 1))
    elif [ ! -x "$script" ]; then
        add_warning "Backup script not executable: $script"
        SCRIPT_ISSUES=$((SCRIPT_ISSUES + 1))
    fi
done

if [ $SCRIPT_ISSUES -eq 0 ]; then
    echo -e "${GREEN}✓ All backup scripts are present and executable${NC}"
else
    echo -e "${YELLOW}⚠ Some backup scripts have issues ($SCRIPT_ISSUES)${NC}"
fi

# Summary
echo ""
echo "========================================="
echo "Summary"
echo "========================================="

if [ "$STATUS" = "OK" ]; then
    echo -e "${GREEN}Status: OK${NC}"
    echo "All backup checks passed successfully."
elif [ "$STATUS" = "WARNING" ]; then
    echo -e "${YELLOW}Status: WARNING${NC}"
    echo "Some non-critical issues detected:"
    for warning in "${WARNINGS[@]}"; do
        echo "  - $warning"
    done
else
    echo -e "${RED}Status: CRITICAL${NC}"
    echo "Critical issues detected:"
    for alert in "${ALERTS[@]}"; do
        echo "  - $alert"
    done
    if [ ${#WARNINGS[@]} -gt 0 ]; then
        echo ""
        echo "Additional warnings:"
        for warning in "${WARNINGS[@]}"; do
            echo "  - $warning"
        done
    fi
fi

# Send alerts if configured
if [ ${#ALERTS[@]} -gt 0 ] || [ ${#WARNINGS[@]} -gt 0 ]; then
    # Email alert
    if [ -n "$ALERT_EMAIL" ] && command -v mail &> /dev/null; then
        {
            echo "Backup monitoring detected issues on $(hostname)"
            echo ""
            echo "Status: $STATUS"
            echo ""
            if [ ${#ALERTS[@]} -gt 0 ]; then
                echo "Critical Issues:"
                for alert in "${ALERTS[@]}"; do
                    echo "  - $alert"
                done
                echo ""
            fi
            if [ ${#WARNINGS[@]} -gt 0 ]; then
                echo "Warnings:"
                for warning in "${WARNINGS[@]}"; do
                    echo "  - $warning"
                done
            fi
        } | mail -s "[BACKUP] $STATUS: Backup monitoring alert" "$ALERT_EMAIL"
        echo ""
        echo "Alert email sent to: $ALERT_EMAIL"
    fi
    
    # Slack webhook
    if [ -n "$ALERT_SLACK_WEBHOOK" ]; then
        COLOR="good"
        if [ "$STATUS" = "WARNING" ]; then
            COLOR="warning"
        elif [ "$STATUS" = "CRITICAL" ]; then
            COLOR="danger"
        fi
        
        SLACK_MESSAGE=""
        if [ ${#ALERTS[@]} -gt 0 ]; then
            SLACK_MESSAGE="*Critical Issues:*\n"
            for alert in "${ALERTS[@]}"; do
                SLACK_MESSAGE="${SLACK_MESSAGE}• $alert\n"
            done
        fi
        if [ ${#WARNINGS[@]} -gt 0 ]; then
            SLACK_MESSAGE="${SLACK_MESSAGE}*Warnings:*\n"
            for warning in "${WARNINGS[@]}"; do
                SLACK_MESSAGE="${SLACK_MESSAGE}• $warning\n"
            done
        fi
        
        curl -X POST "$ALERT_SLACK_WEBHOOK" \
            -H 'Content-Type: application/json' \
            -d "{\"attachments\":[{\"color\":\"$COLOR\",\"title\":\"Backup Monitoring: $STATUS\",\"text\":\"$SLACK_MESSAGE\",\"footer\":\"$(hostname)\"}]}" \
            &> /dev/null
        echo "Alert sent to Slack"
    fi
    
    # Generic webhook
    if [ -n "$ALERT_WEBHOOK" ]; then
        WEBHOOK_PAYLOAD=$(cat <<EOF
{
  "status": "$STATUS",
  "hostname": "$(hostname)",
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "alerts": $(printf '%s\n' "${ALERTS[@]}" | jq -R . | jq -s .),
  "warnings": $(printf '%s\n' "${WARNINGS[@]}" | jq -R . | jq -s .)
}
EOF
)
        curl -X POST "$ALERT_WEBHOOK" \
            -H 'Content-Type: application/json' \
            -d "$WEBHOOK_PAYLOAD" \
            &> /dev/null
        echo "Alert sent to webhook"
    fi
fi

echo ""
echo "========================================="

# Exit with appropriate code
if [ "$STATUS" = "OK" ]; then
    exit 0
elif [ "$STATUS" = "WARNING" ]; then
    exit 1
else
    exit 2
fi

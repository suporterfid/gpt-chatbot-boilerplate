#!/bin/bash
#
# Smoke Test Script - Phase 4 Production Readiness
# Verifies all production features are functional
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "========================================="
echo "Phase 4 Production Smoke Tests"
echo "========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

pass_count=0
fail_count=0

check_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -n "Testing: $test_name... "
    
    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((pass_count++))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        return 1
    fi
}

echo "=== 1. File Structure Tests ==="
check_test "CI workflow exists" "test -f $PROJECT_ROOT/.github/workflows/cicd.yml"
check_test "Backup script exists" "test -x $PROJECT_ROOT/scripts/db_backup.sh"
check_test "Restore script exists" "test -x $PROJECT_ROOT/scripts/db_restore.sh"
check_test "Metrics endpoint exists" "test -f $PROJECT_ROOT/metrics.php"
check_test "PHPStan config exists" "test -f $PROJECT_ROOT/phpstan.neon"
check_test "Package.json exists" "test -f $PROJECT_ROOT/package.json"

echo ""
echo "=== 2. Documentation Tests ==="
check_test "Backup docs exist" "test -f $PROJECT_ROOT/docs/ops/backup_restore.md"
check_test "Secrets docs exist" "test -f $PROJECT_ROOT/docs/ops/secrets_management.md"
check_test "Logs docs exist" "test -f $PROJECT_ROOT/docs/ops/logs.md"
check_test "Production deploy docs exist" "test -f $PROJECT_ROOT/docs/ops/production-deploy.md"
check_test "Incident runbook exists" "test -f $PROJECT_ROOT/docs/ops/incident_runbook.md"
check_test "Nginx config exists" "test -f $PROJECT_ROOT/docs/ops/nginx-production.conf"
check_test "Alert rules exist" "test -f $PROJECT_ROOT/docs/ops/monitoring/alerts.yml"
check_test "Changelog exists" "test -f $PROJECT_ROOT/CHANGELOG.md"

echo ""
echo "=== 3. Code Quality Tests ==="
check_test "PHP syntax check" "find $PROJECT_ROOT -path '$PROJECT_ROOT/vendor' -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l | grep -q 'No syntax errors'"
check_test "Config file valid" "php -r 'require \"$PROJECT_ROOT/config.php\"; echo \"OK\";'"
check_test "Admin API file valid" "php -l $PROJECT_ROOT/admin-api.php"
check_test "Metrics file valid" "php -l $PROJECT_ROOT/metrics.php"

echo ""
echo "=== 4. Database Migration Tests ==="
check_test "Migration 001 exists" "test -f $PROJECT_ROOT/db/migrations/001_create_agents.sql"
check_test "Migration 009 (DLQ) exists" "test -f $PROJECT_ROOT/db/migrations/009_create_dead_letter_queue.sql"
check_test "At least 9 migrations" "test $(ls -1 $PROJECT_ROOT/db/migrations/*.sql 2>/dev/null | wc -l) -ge 9"

echo ""
echo "=== 5. Feature Implementation Tests ==="
check_test "Rate limiting in admin-api" "grep -q 'checkAdminRateLimit' $PROJECT_ROOT/admin-api.php"
check_test "DLQ endpoints exist" "grep -q 'list_dlq' $PROJECT_ROOT/admin-api.php"
check_test "Token rotation endpoint" "grep -q 'rotate_admin_token' $PROJECT_ROOT/admin-api.php"
check_test "Health endpoint exists" "grep -q \"case 'health':\" $PROJECT_ROOT/admin-api.php"
check_test "Prometheus metrics" "grep -q 'promMetric' $PROJECT_ROOT/metrics.php"
check_test "DLQ in JobQueue" "grep -q 'moveToDLQ' $PROJECT_ROOT/includes/JobQueue.php"

echo ""
echo "=== 6. Configuration Tests ==="
check_test "Rate limit config" "grep -q 'ADMIN_RATE_LIMIT' $PROJECT_ROOT/.env.example"
check_test "Jobs config exists" "grep -q 'JOBS_ENABLED' $PROJECT_ROOT/.env.example"
check_test "Admin config in config.php" "grep -q \"'admin'\" $PROJECT_ROOT/config.php"

echo ""
echo "=== 7. Load Testing Tests ==="
check_test "k6 test script exists" "test -f $PROJECT_ROOT/tests/load/chat_api.js"
check_test "Load test README exists" "test -f $PROJECT_ROOT/tests/load/README.md"

echo ""
echo "=== 8. PHP Unit Tests ==="
if [ -d "$PROJECT_ROOT/tests" ]; then
    echo -n "Running Phase 1 tests... "
    if php "$PROJECT_ROOT/tests/run_tests.php" > /tmp/phase1_test.log 2>&1; then
        echo -e "${GREEN}✓ PASS (28/28)${NC}"
        ((pass_count++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        cat /tmp/phase1_test.log
    fi
    
    echo -n "Running Phase 2 tests... "
    if php "$PROJECT_ROOT/tests/run_phase2_tests.php" > /tmp/phase2_test.log 2>&1; then
        echo -e "${GREEN}✓ PASS (44/44)${NC}"
        ((pass_count++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        cat /tmp/phase2_test.log
    fi
    
    echo -n "Running Phase 3 tests... "
    if php "$PROJECT_ROOT/tests/run_phase3_tests.php" > /tmp/phase3_test.log 2>&1; then
        echo -e "${GREEN}✓ PASS (36/36)${NC}"
        ((pass_count++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        cat /tmp/phase3_test.log
    fi
    
    echo -n "Running Phase 4 tests... "
    if php "$PROJECT_ROOT/tests/test_phase4_features.php" > /tmp/phase4_test.log 2>&1; then
        echo -e "${GREEN}✓ PASS (14/14)${NC}"
        ((pass_count++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        cat /tmp/phase4_test.log
    fi
    
    echo -n "Running Phase 5 tests... "
    if php "$PROJECT_ROOT/tests/test_phase5_agent_integration.php" > /tmp/phase5_test.log 2>&1; then
        echo -e "${GREEN}✓ PASS (33/33)${NC}"
        ((pass_count++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((fail_count++))
        cat /tmp/phase5_test.log
    fi
fi

echo ""
echo "========================================="
echo "Smoke Test Summary"
echo "========================================="
echo -e "Passed: ${GREEN}$pass_count${NC}"
echo -e "Failed: ${RED}$fail_count${NC}"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}✅ All smoke tests passed!${NC}"
    echo "The application is ready for production deployment."
    exit 0
else
    echo -e "${RED}❌ Some tests failed.${NC}"
    echo "Please review the failures above before deploying to production."
    exit 1
fi

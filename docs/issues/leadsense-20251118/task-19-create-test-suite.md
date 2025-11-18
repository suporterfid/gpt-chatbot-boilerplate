# Task 19: Create Comprehensive Test Suite

## Objective
Create automated tests for all CRM functionality.

## Test Files to Create

### 1. `tests/test_pipeline_service.php`
- Pipeline CRUD operations
- Stage management
- Default pipeline logic
- Tenant isolation
- Validation

### 2. `tests/test_lead_management_service.php`
- Stage movement
- Owner assignment
- Deal updates
- Board queries
- Event recording

### 3. `tests/test_automation_service.php`
- Rule CRUD
- Trigger evaluation
- Action execution
- Logging
- Filter matching

### 4. `tests/test_crm_api.php`
- All API endpoints
- Authentication
- Authorization
- Error handling
- Tenant isolation

### 5. `tests/test_crm_integration.php`
- LeadSense → CRM flow
- New lead assignment
- Stage progression
- Automation triggers
- End-to-end scenarios

### 6. `tests/test_crm_migrations.php`
- Migration execution
- Backfill script
- Data integrity
- Rollback capability

## Test Patterns

### Service Tests
```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/PipelineService.php';

$db = Database::getInstance();
$service = new PipelineService($db);

echo "\n=== Testing PipelineService ===\n";

// Test create
$result = $service->createPipeline([...]);
assert(!isset($result['error']));
echo "✓ PASS: Pipeline created\n";

// Test update
// Test delete
// Test validation
```

### API Tests
```php
// tests/test_crm_api.php
function testListPipelines() {
    $response = apiRequest('GET', 'leadsense.crm.list_pipelines');
    assert($response['status'] === 200);
    assert(isset($response['body']['pipelines']));
    echo "✓ PASS: List pipelines API\n";
}

function apiRequest($method, $action, $data = []) {
    // Helper to make API requests
    $ch = curl_init("http://localhost:8088/admin-api.php?action={$action}");
    // ... configure curl ...
    return curl_exec($ch);
}
```

## Test Coverage Goals
- 80%+ code coverage
- All happy paths
- All error paths
- Edge cases
- Concurrent operations
- Multi-tenant scenarios

## Running Tests
```bash
# Run all CRM tests
php tests/run_tests.php --filter crm

# Run specific test
php tests/test_pipeline_service.php

# Run with coverage (if available)
phpunit --coverage-html coverage/
```

## Prerequisites
- All implementation complete
- Test database available

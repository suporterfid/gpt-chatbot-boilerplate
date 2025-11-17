# Issue 002: SQL Injection Vulnerabilities in chat-unified.php

**Category:** Security  
**Severity:** Critical  
**Priority:** Critical  
**File:** `chat-unified.php`  
**Status:** ✅ **RESOLVED**

## Resolution Summary

**Implemented:** 2025-11-17  
**Implementation Time:** ~2 hours  

### Changes Made

1. **Created `SecurityValidator` class** (`includes/SecurityValidator.php`)
   - Centralized input validation and sanitization
   - Validates tenant_id, API keys, conversation IDs, agent slugs, filenames
   - Uses strict regex patterns to prevent injection attacks
   - Returns null instead of throwing for API keys (prevents enumeration)

2. **Updated `extractTenantId()` function** in `chat-unified.php`
   - Added validation for all tenant_id sources (headers, GET, POST, database)
   - Improved error handling to prevent information disclosure
   - Validates API key format before database lookup
   - All database queries already use prepared statements (verified safe)

3. **Created comprehensive test suite** (`tests/test_sql_injection_prevention.php`)
   - 56 test cases covering all validation scenarios
   - Tests SQL injection attempts, path traversal, special characters
   - Tests filename validation, API key validation, message sanitization
   - All tests passing ✅

### Security Improvements

- ✅ Tenant ID validation with regex: `^[a-zA-Z0-9_-]{1,64}$`
- ✅ API key validation with length checks (20-128 chars)
- ✅ Path traversal prevention
- ✅ Special character filtering
- ✅ Null byte detection
- ✅ Error message sanitization (no information disclosure)
- ✅ SQL injection prevention verified with test suite

### Testing

```bash
# Run SQL injection prevention tests
php tests/test_sql_injection_prevention.php
# Result: 56/56 tests passed ✅

# Run existing test suite
php tests/run_tests.php
# Result: All tests passed ✅
```

---

## Original Problem Description

## Problem Description

The `extractTenantId()` function in `chat-unified.php` (lines 126-180) constructs SQL queries that may be vulnerable to SQL injection attacks. While the code uses prepared statements, there are potential issues in the query construction.

## Vulnerable Code

```php
// Line 158-159 in chat-unified.php
$sql = "SELECT tenant_id FROM admin_api_keys WHERE api_key = ? AND (expires_at IS NULL OR expires_at > datetime('now'))";
$result = $db->query($sql, [$apiKey]);
```

Additionally:
```php
// Lines 170-176 - Direct user input from $_GET and $_POST
if (!empty($_GET['tenant_id'])) {
    return trim($_GET['tenant_id']);
}

if (!empty($_POST['tenant_id'])) {
    return trim($_POST['tenant_id']);
}
```

## Issues

1. **Unvalidated tenant_id from GET/POST**: The tenant_id from request parameters is returned without validation or sanitization
2. **Potential Type Confusion**: The code doesn't validate that tenant_id is actually a valid identifier
3. **Database Error Exposure**: The catch block logs but doesn't prevent potential information leakage

## Attack Scenarios

### Scenario 1: Tenant ID Injection
An attacker could send malicious tenant_id values in GET/POST parameters:
```
POST /chat-unified.php
Content-Type: application/json

{
  "message": "test",
  "tenant_id": "'; DROP TABLE agents; --"
}
```

While the DB class should use prepared statements, if this tenant_id is used in string concatenation elsewhere, it could be exploited.

### Scenario 2: API Key Enumeration
The error handling might reveal whether API keys exist:
```php
// Line 164
error_log("Failed to lookup tenant from API key: " . $e->getMessage());
```

This could help attackers enumerate valid API keys through timing attacks or error message differences.

## Impact

- **Critical**: Potential for data breach if SQL injection is successful
- **High**: Information disclosure through error messages
- **Medium**: Tenant ID spoofing if used without validation downstream

## Recommendations

### 1. Validate Tenant ID Format

```php
function extractTenantId($db = null) {
    // Method 1: Check for X-Tenant-ID header
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    }
    
    if (!empty($headers['x-tenant-id'])) {
        $tenantId = trim($headers['x-tenant-id']);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
            throw new Exception('Invalid tenant ID format', 400);
        }
        return $tenantId;
    }
    
    // ... rest of function
    
    // Method 3: Validate tenant_id from GET/POST
    if (!empty($_GET['tenant_id'])) {
        $tenantId = trim($_GET['tenant_id']);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
            throw new Exception('Invalid tenant ID format', 400);
        }
        return $tenantId;
    }
    
    if (!empty($_POST['tenant_id'])) {
        $tenantId = trim($_POST['tenant_id']);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
            throw new Exception('Invalid tenant ID format', 400);
        }
        return $tenantId;
    }
    
    return null;
}
```

### 2. Improve Error Handling

```php
// Method 2: API Key lookup
if ($apiKey && $db) {
    try {
        $sql = "SELECT tenant_id FROM admin_api_keys 
                WHERE api_key = ? 
                AND (expires_at IS NULL OR expires_at > datetime('now'))";
        $result = $db->query($sql, [$apiKey]);
        
        if (!empty($result) && isset($result[0]['tenant_id'])) {
            // Validate tenant_id from database
            $tenantId = $result[0]['tenant_id'];
            if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
                error_log("Invalid tenant_id format in database");
                return null;
            }
            return $tenantId;
        }
    } catch (Exception $e) {
        // Don't expose detailed error information
        error_log("Failed to lookup tenant: " . $e->getCode());
        // Return null instead of exposing details
        return null;
    }
}
```

### 3. Use DB::query Safely

Verify that the `DB::query()` method actually uses prepared statements:

```php
// In includes/DB.php - ensure this pattern is used
public function query($sql, $params = []) {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### 4. Add Input Validation Centrally

Create a `SecurityValidator` class:

```php
class SecurityValidator {
    public static function validateTenantId(?string $tenantId): ?string {
        if ($tenantId === null) {
            return null;
        }
        
        $tenantId = trim($tenantId);
        
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
            throw new Exception('Invalid tenant ID format', 400);
        }
        
        return $tenantId;
    }
    
    public static function validateApiKey(?string $apiKey): ?string {
        if ($apiKey === null) {
            return null;
        }
        
        $apiKey = trim($apiKey);
        
        // API keys should match expected format
        if (!preg_match('/^[a-zA-Z0-9_-]{20,128}$/', $apiKey)) {
            return null; // Invalid format, don't throw to prevent enumeration
        }
        
        return $apiKey;
    }
}
```

## Verification Steps

1. **Code Audit**: Review all SQL query construction points
2. **Penetration Testing**: Test with malicious tenant_id payloads
3. **Fuzzing**: Use automated tools to test input validation
4. **Static Analysis**: Run PHPStan at level 8+ to catch type issues

## Additional Security Measures

1. **Rate Limiting on Failed Auth**: Implement exponential backoff for failed API key lookups
2. **Audit Logging**: Log all tenant_id extraction attempts with source IP
3. **Sanitize Error Messages**: Never expose database structure in error messages
4. **Parameterize Everything**: Ensure ALL database queries use prepared statements

## Testing Requirements

```php
// Test invalid tenant_id formats
$invalidIds = [
    "'; DROP TABLE agents; --",
    "../../../etc/passwd",
    "<script>alert('xss')</script>",
    "' OR '1'='1",
    "a".str_repeat('x', 100), // Too long
];

foreach ($invalidIds as $id) {
    try {
        $_GET['tenant_id'] = $id;
        $result = extractTenantId($db);
        // Should throw exception or return null
        if ($result === $id) {
            throw new Exception("SECURITY: Unvalidated tenant_id accepted: $id");
        }
    } catch (Exception $e) {
        // Expected behavior
        echo "✓ Rejected invalid tenant_id: " . substr($id, 0, 20) . "\n";
    }
}
```

## Estimated Effort

- **Effort:** 1 day
- **Risk:** Low (straightforward input validation)

## Related Issues

- Issue 003: Admin API authentication timing attacks
- Issue 004: File upload security
- Issue 015: Error message information disclosure

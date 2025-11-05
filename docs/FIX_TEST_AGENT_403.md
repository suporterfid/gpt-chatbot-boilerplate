# Fix for 403 Forbidden Error When Testing Agents

## Problem
When users tried to test an agent from the admin panel by clicking the "Test" button and entering a message, they received a `403 Forbidden` error in the browser console:

```
GET http://localhost:8088/admin-api.php?action=test_agent&id=<agent-id>&token=<token> 
net::ERR_ABORTED 403 (Forbidden)
```

## Root Cause
The issue was caused by two incompatibilities between the frontend and backend:

1. **HTTP Method Mismatch**: The frontend uses `EventSource` to receive Server-Sent Events (SSE), which only supports GET requests. However, the `test_agent` endpoint was configured to only accept POST requests.

2. **Authentication Parameter Name**: The frontend passes the authentication token as a `token` query parameter, but the backend authentication logic only checked for `admin_token` (not `token`).

## Solution
Made minimal changes to `admin-api.php`:

### 1. Support `token` Parameter (Lines 96-99)
Added fallback authentication support for both `token` and `admin_token` parameters:

```php
} elseif (isset($_GET['token'])) {
    $fallbackToken = $_GET['token'];
} elseif (isset($_POST['token'])) {
    $fallbackToken = $_POST['token'];
```

### 2. Accept GET Requests (Line 815)
Changed the HTTP method check to accept both GET and POST:

```php
if ($method !== 'POST' && $method !== 'GET') {
    sendError('Method not allowed', 405);
}
```

### 3. Conditional Body Parsing (Lines 825-834)
Made request body parsing conditional based on HTTP method:

```php
// Support both POST body and GET/POST parameters for message
$message = 'Hello, this is a test message.';
if ($method === 'POST') {
    $data = getRequestBody();
    $message = $data['message'] ?? $message;
} elseif (isset($_GET['message'])) {
    $message = $_GET['message'];
} elseif (isset($_POST['message'])) {
    $message = $_POST['message'];
}
```

## Testing
Created comprehensive tests to verify the fix:

### Integration Test (`tests/test_agent_test_endpoint.php`)
Tests 5 scenarios:
1. ✅ GET request with `token` parameter
2. ✅ GET request with Authorization header
3. ✅ GET request with `admin_token` parameter (legacy support)
4. ✅ GET request without authentication (properly rejected with 403)
5. ✅ POST request with JSON body (backward compatibility)

### Manual SSE Test (`tests/manual_test_agent_sse.sh`)
Simulates the browser's EventSource behavior:
- Sends GET request with `token` parameter
- Verifies SSE events are received
- Confirms HTTP 200 response (not 403)

### Results
- All existing tests pass (28/28)
- All new tests pass (5/5)
- Manual test confirms SSE streaming works

## Backward Compatibility
The changes maintain full backward compatibility:
- POST requests still work
- `admin_token` parameter still supported
- Authorization header authentication unchanged
- Request body parsing for POST unchanged

## Security
- No security vulnerabilities introduced
- Authentication still required for all requests
- Test tokens clearly marked as test-only
- Environment variable override support for test credentials

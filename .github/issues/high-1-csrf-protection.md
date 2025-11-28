# [HIGH] Add CSRF Protection to WordPress Blog Endpoints

## Priority
🟠 **High** - Security vulnerability

## Type
- [x] Security Issue
- [x] Enhancement

## Description
WordPress Blog endpoints don't implement CSRF (Cross-Site Request Forgery) protection. An attacker could craft malicious requests that execute actions on behalf of authenticated users.

## Security Impact
- **Severity**: Medium (CWE-352: Cross-Site Request Forgery)
- Attacker can trick authenticated users into:
  - Creating unwanted configurations
  - Queuing malicious articles
  - Deleting configurations
  - Modifying settings
- No token validation on state-changing operations

## Affected Endpoints
All state-changing WordPress blog endpoints:
- POST `wordpress_blog_create_config`
- PUT `wordpress_blog_update_config`
- DELETE `wordpress_blog_delete_config`
- POST `wordpress_blog_queue_article`
- PUT `wordpress_blog_update_article_status`
- POST `wordpress_blog_process_queue`
- And 10+ other endpoints

## Attack Scenario
```html
<!-- Attacker's malicious website -->
<form action="https://victim-site.com/admin-api.php" method="POST" id="evil">
  <input type="hidden" name="action" value="wordpress_blog_delete_config">
  <input type="hidden" name="configuration_id" value="victim-config-123">
</form>
<script>
  // Auto-submit when victim visits attacker's page while logged into victim-site.com
  document.getElementById('evil').submit();
</script>
```

If victim is authenticated, their configuration gets deleted!

## Implementation Tasks

### Task 1: Extend SecurityHelper with CSRF Methods

Check if `includes/SecurityHelper.php` has CSRF support. If not, add:

```php
// includes/SecurityHelper.php

class SecurityHelper {
    /**
     * Generate CSRF token for current session
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     *
     * @param string|null $token Token to validate
     * @param int $maxAge Maximum token age in seconds (default: 1 hour)
     * @return bool
     */
    public static function validateCSRFToken($token, $maxAge = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists in session
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // Check token match
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }

        // Check token age (prevent token fixation)
        if (isset($_SESSION['csrf_token_time'])) {
            $tokenAge = time() - $_SESSION['csrf_token_time'];
            if ($tokenAge > $maxAge) {
                // Token expired, regenerate
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }

        return true;
    }

    /**
     * Get CSRF token from request headers or body
     */
    public static function getCSRFTokenFromRequest() {
        // Check X-CSRF-Token header (for AJAX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check POST data
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Check JSON body
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (isset($data['csrf_token'])) {
                return $data['csrf_token'];
            }
        }

        return null;
    }
}
```

### Task 2: Add CSRF Validation to admin-api.php

```php
// admin-api.php (add after authentication check)

// List of actions that require CSRF protection (state-changing operations)
$csrfProtectedActions = [
    'wordpress_blog_create_config',
    'wordpress_blog_update_config',
    'wordpress_blog_delete_config',
    'wordpress_blog_queue_article',
    'wordpress_blog_update_article_status',
    'wordpress_blog_retry_article',
    'wordpress_blog_cancel_article',
    'wordpress_blog_process_queue',
    'wordpress_blog_create_internal_link',
    'wordpress_blog_update_internal_link',
    'wordpress_blog_delete_internal_link',
    // Add all POST/PUT/DELETE WordPress blog actions
];

// Validate CSRF token for protected actions
if (in_array($action, $csrfProtectedActions)) {
    $csrfToken = SecurityHelper::getCSRFTokenFromRequest();

    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Invalid or missing CSRF token',
            'code' => 'CSRF_VALIDATION_FAILED'
        ]);
        log_admin("CSRF validation failed for action: {$action}, IP: " . $_SERVER['REMOTE_ADDR'], 'security');
        exit;
    }
}
```

### Task 3: Add CSRF Token Generation Endpoint

```php
// admin-api.php

case 'get_csrf_token':
    // Generate or return existing token
    $token = SecurityHelper::generateCSRFToken();

    echo json_encode([
        'success' => true,
        'csrf_token' => $token,
        'expires_in' => 3600 // 1 hour
    ]);
    break;
```

### Task 4: Update Frontend JavaScript to Include CSRF Token

**Fetch CSRF token on page load:**
```javascript
// public/admin/wordpress-blog-config.js (add at top)

let csrfToken = null;

// Fetch CSRF token when page loads
async function initCSRF() {
    try {
        const response = await fetch('/admin-api.php?action=get_csrf_token', {
            credentials: 'include'
        });
        const data = await response.json();
        csrfToken = data.csrf_token;
        console.log('CSRF token initialized');
    } catch (error) {
        console.error('Failed to fetch CSRF token:', error);
    }
}

// Call on page load
initCSRF();
```

**Include token in all POST/PUT/DELETE requests:**
```javascript
// Example: Create configuration
async function createConfiguration(configData) {
    if (!csrfToken) {
        alert('Security token not initialized. Please refresh the page.');
        return;
    }

    const response = await fetch('/admin-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken  // ✅ Include CSRF token
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'wordpress_blog_create_config',
            csrf_token: csrfToken,  // ✅ Also in body as fallback
            ...configData
        })
    });

    // Handle CSRF error
    if (response.status === 403) {
        const error = await response.json();
        if (error.code === 'CSRF_VALIDATION_FAILED') {
            alert('Security token expired. Refreshing...');
            await initCSRF(); // Refresh token
            return createConfiguration(configData); // Retry
        }
    }

    return response.json();
}
```

**Apply to all JavaScript modules:**
- `public/admin/wordpress-blog-config.js`
- `public/admin/wordpress-blog-queue.js`
- `public/admin/wordpress-blog-metrics.js`

### Task 5: Add CSRF Token Refresh on Expiry

```javascript
// Create a wrapper for all API calls
async function apiCall(endpoint, options = {}) {
    // Ensure CSRF token is fresh
    if (!csrfToken) {
        await initCSRF();
    }

    // Add CSRF token to headers
    options.headers = options.headers || {};
    options.headers['X-CSRF-Token'] = csrfToken;

    // Make request
    let response = await fetch(endpoint, options);

    // If CSRF failed, refresh token and retry once
    if (response.status === 403) {
        const error = await response.json();
        if (error.code === 'CSRF_VALIDATION_FAILED') {
            console.log('CSRF token expired, refreshing...');
            await initCSRF();

            // Retry with new token
            options.headers['X-CSRF-Token'] = csrfToken;
            response = await fetch(endpoint, options);
        }
    }

    return response;
}
```

### Task 6: Create Tests for CSRF Protection

```php
// tests/Security/CSRFProtectionTest.php

final class CSRFProtectionTest extends TestCase
{
    public function testRequestWithoutCSRFTokenFails(): void
    {
        $response = $this->post('/admin-api.php', [
            'action' => 'wordpress_blog_create_config',
            'config_name' => 'Test Config'
            // No CSRF token
        ]);

        $this->assertEquals(403, $response->status);
        $this->assertStringContainsString('CSRF', $response->body['error']);
    }

    public function testRequestWithValidCSRFTokenSucceeds(): void
    {
        // Get valid token
        $token = SecurityHelper::generateCSRFToken();

        $response = $this->post('/admin-api.php', [
            'action' => 'wordpress_blog_create_config',
            'csrf_token' => $token,
            'config_name' => 'Test Config'
        ]);

        $this->assertEquals(200, $response->status);
    }

    public function testRequestWithInvalidCSRFTokenFails(): void
    {
        $response = $this->post('/admin-api.php', [
            'action' => 'wordpress_blog_create_config',
            'csrf_token' => 'invalid-token-12345',
            'config_name' => 'Test Config'
        ]);

        $this->assertEquals(403, $response->status);
    }

    public function testExpiredCSRFTokenFails(): void
    {
        // Generate token and manipulate session to make it expired
        $_SESSION['csrf_token'] = 'old-token';
        $_SESSION['csrf_token_time'] = time() - 7200; // 2 hours ago

        $response = $this->post('/admin-api.php', [
            'action' => 'wordpress_blog_create_config',
            'csrf_token' => 'old-token'
        ]);

        $this->assertEquals(403, $response->status);
    }
}
```

## Acceptance Criteria
- [ ] `SecurityHelper::generateCSRFToken()` implemented
- [ ] `SecurityHelper::validateCSRFToken()` implemented
- [ ] CSRF validation added to all state-changing WordPress blog endpoints
- [ ] Frontend JavaScript fetches CSRF token on load
- [ ] All AJAX requests include CSRF token in header
- [ ] Expired tokens automatically refreshed
- [ ] 403 response for missing/invalid CSRF tokens
- [ ] CSRF validation failures logged
- [ ] Tests pass for CSRF protection
- [ ] Manual testing: Request without token fails
- [ ] Documentation updated

## Testing Steps
1. Load WordPress Blog admin UI
2. Open browser DevTools → Network tab
3. Create a configuration and verify:
   - Request includes `X-CSRF-Token` header
   - Request includes `csrf_token` in body
4. Manually craft request without CSRF token:
   ```bash
   curl -X POST https://your-site.com/admin-api.php \
     -H "Content-Type: application/json" \
     -b "admin_session=valid-session-cookie" \
     -d '{
       "action": "wordpress_blog_create_config",
       "config_name": "Should Fail"
     }'
   ```
   Should return 403 with CSRF error
5. Wait for token to expire (1 hour) and verify auto-refresh works

## Related Issues
- Related to: Security hardening
- Part of: WordPress Blog security audit

## Estimated Effort
**4-6 hours**
- SecurityHelper extension: 1 hour
- Backend validation: 1 hour
- Frontend integration: 2-3 hours
- Testing: 1-2 hours

## Additional Context
Identified in code review as High Priority Issue #3. CSRF protection is a standard security requirement for all web applications with state-changing operations.

**Standard**: OWASP ASVS V4.0 - Section 4.2 (Operation Level Access Control)
**Reference**: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html

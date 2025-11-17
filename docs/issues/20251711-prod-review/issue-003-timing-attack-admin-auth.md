# Issue 003: Timing Attack Vulnerability in Admin Authentication

**Category:** Security  
**Severity:** High  
**Priority:** High  
**File:** `admin-api.php`, `includes/AdminAuth.php`

## Problem Description

The admin authentication mechanism in `admin-api.php` (lines 104-186) may be vulnerable to timing attacks. The code uses standard string comparison which can reveal information about token validity through response time differences.

## Vulnerable Code

```php
// admin-api.php lines 169-175
try {
    $user = $adminAuth->authenticate($token);

    if (!$user) {
        log_admin('Invalid authentication token', 'warn');
        sendError('Invalid authentication token', 403);
    }
    // ...
} catch (Exception $e) {
    log_admin('Authentication error: ' . $e->getMessage(), 'error');
    sendError('Authentication failed', 403);
}
```

The authentication process likely uses standard string comparison somewhere in the chain, which can be exploited through timing analysis.

## Attack Scenario

An attacker can measure response times to determine:

1. **Whether a token prefix is correct**: If the first characters match, comparison takes slightly longer
2. **Token format validation**: Different timing for valid vs invalid formats
3. **Database lookup success**: Different timing if token exists in database

Example attack:
```python
import requests
import time

def timing_attack(url, token_charset="abcdefghijklmnopqrstuvwxyz0123456789"):
    token = ""
    
    while len(token) < 32:  # Assume 32-char tokens
        best_char = None
        max_time = 0
        
        for char in token_charset:
            test_token = token + char + ("0" * (31 - len(token)))
            
            start = time.perf_counter()
            requests.post(url, 
                headers={"Authorization": f"Bearer {test_token}"})
            elapsed = time.perf_counter() - start
            
            if elapsed > max_time:
                max_time = elapsed
                best_char = char
        
        token += best_char
        print(f"Found: {token}")
    
    return token
```

## Impact

- **High**: Token enumeration through timing analysis
- **Medium**: Reduced security margin for admin access
- **Medium**: Information disclosure about token format/validity

## Issues

1. **No Constant-Time Comparison**: Token comparison likely uses `===` or `strcmp()`
2. **Different Code Paths**: Valid vs invalid tokens take different execution paths
3. **Error Message Timing**: Different error paths have different response times
4. **Lack of Rate Limiting**: No exponential backoff for failed attempts

## Recommendations

### 1. Implement Constant-Time String Comparison

```php
// In AdminAuth.php or SecurityHelper.php
class SecurityHelper {
    /**
     * Constant-time string comparison to prevent timing attacks
     * 
     * @param string $knownString The known token/password
     * @param string $userInput The user-provided value
     * @return bool True if strings match
     */
    public static function timingSafeEquals(string $knownString, string $userInput): bool {
        // Use PHP's built-in hash_equals for constant-time comparison
        return hash_equals($knownString, $userInput);
    }
    
    /**
     * Verify token with constant-time comparison
     * 
     * @param string $providedToken Token from request
     * @param string $validToken Token from database/config
     * @return bool True if valid
     */
    public static function verifyToken(string $providedToken, string $validToken): bool {
        // Normalize lengths before comparison to prevent length-based timing
        $providedHash = hash('sha256', $providedToken);
        $validHash = hash('sha256', $validToken);
        
        return hash_equals($validHash, $providedHash);
    }
}
```

### 2. Update AdminAuth to Use Constant-Time Comparison

```php
// In includes/AdminAuth.php
class AdminAuth {
    public function authenticate(string $token): ?array {
        // Add artificial delay to mask timing differences
        $startTime = microtime(true);
        
        try {
            // Validate token format first (constant-time check)
            if (!$this->isValidTokenFormat($token)) {
                $this->ensureMinimumAuthTime($startTime);
                return null;
            }
            
            // Check against legacy token (if enabled)
            if ($this->config['admin']['token'] ?? null) {
                $legacyToken = $this->config['admin']['token'];
                if (SecurityHelper::timingSafeEquals($legacyToken, $token)) {
                    $this->ensureMinimumAuthTime($startTime);
                    return [
                        'id' => 1,
                        'email' => 'admin@local',
                        'role' => 'admin',
                        'auth_method' => 'legacy_token_deprecated'
                    ];
                }
            }
            
            // Check against API keys (use constant-time comparison in query result)
            $apiKey = $this->db->findApiKey($token);
            if ($apiKey) {
                // Always perform the comparison even if null
                $isValid = SecurityHelper::timingSafeEquals(
                    $apiKey['key'] ?? '', 
                    $token
                );
                
                if ($isValid) {
                    $this->ensureMinimumAuthTime($startTime);
                    return $apiKey['user'];
                }
            }
            
            // Check against session tokens
            $session = $this->db->findSession($token);
            if ($session) {
                $isValid = SecurityHelper::timingSafeEquals(
                    $session['token'] ?? '',
                    $token
                );
                
                if ($isValid && !$this->isSessionExpired($session)) {
                    $this->ensureMinimumAuthTime($startTime);
                    return $session['user'];
                }
            }
            
            // Always take minimum time even on failure
            $this->ensureMinimumAuthTime($startTime);
            return null;
            
        } catch (Exception $e) {
            // Ensure consistent timing even on exception
            $this->ensureMinimumAuthTime($startTime);
            throw $e;
        }
    }
    
    /**
     * Ensure authentication takes minimum time to prevent timing analysis
     * 
     * @param float $startTime Time when authentication started
     */
    private function ensureMinimumAuthTime(float $startTime): void {
        $minimumTime = 0.1; // 100ms minimum authentication time
        $elapsed = microtime(true) - $startTime;
        
        if ($elapsed < $minimumTime) {
            usleep(($minimumTime - $elapsed) * 1000000);
        }
    }
    
    /**
     * Validate token format without revealing existence
     * 
     * @param string $token Token to validate
     * @return bool True if format is valid
     */
    private function isValidTokenFormat(string $token): bool {
        // Don't reveal exact format requirements
        // Just ensure it's reasonable
        $length = strlen($token);
        return $length >= 20 && $length <= 256;
    }
}
```

### 3. Add Rate Limiting with Exponential Backoff

```php
// In admin-api.php, before authentication
function checkAuthRateLimit(string $identifier): void {
    $cacheKey = "auth_attempts_$identifier";
    $attempts = apcu_fetch($cacheKey) ?: 0;
    
    if ($attempts > 5) {
        // Exponential backoff: 2^attempts seconds
        $backoffTime = min(pow(2, $attempts - 5), 300); // Max 5 minutes
        
        log_admin("Rate limit exceeded for $identifier: $attempts attempts", 'warn');
        
        http_response_code(429);
        header('Retry-After: ' . $backoffTime);
        echo json_encode([
            'error' => 'Too many authentication attempts. Please try again later.',
            'retry_after' => $backoffTime
        ]);
        exit();
    }
    
    // Increment attempts
    apcu_store($cacheKey, $attempts + 1, 3600); // 1 hour TTL
}

// Usage in admin-api.php
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkAuthRateLimit($clientIp);

$user = checkAuthentication($config, $adminAuth);

// Clear attempts on successful auth
apcu_delete("auth_attempts_$clientIp");
```

### 4. Use HMAC for Token Generation

For session tokens and API keys, use HMAC to prevent forgery:

```php
class TokenGenerator {
    private string $secret;
    
    public function __construct(string $secret) {
        $this->secret = $secret;
    }
    
    /**
     * Generate secure token with HMAC
     * 
     * @param array $payload Token payload (user_id, expiry, etc.)
     * @return string Secure token
     */
    public function generate(array $payload): string {
        $data = json_encode($payload);
        $random = random_bytes(16);
        $timestamp = time();
        
        // Create token: random|timestamp|data
        $tokenData = base64_encode($random) . '|' . $timestamp . '|' . base64_encode($data);
        
        // Sign with HMAC
        $signature = hash_hmac('sha256', $tokenData, $this->secret);
        
        // Return: signature.tokenData
        return $signature . '.' . $tokenData;
    }
    
    /**
     * Verify and decode token
     * 
     * @param string $token Token to verify
     * @return array|null Payload if valid, null otherwise
     */
    public function verify(string $token): ?array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        
        [$providedSignature, $tokenData] = $parts;
        
        // Verify signature using constant-time comparison
        $expectedSignature = hash_hmac('sha256', $tokenData, $this->secret);
        
        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }
        
        // Decode payload
        $components = explode('|', $tokenData, 3);
        if (count($components) !== 3) {
            return null;
        }
        
        [, $timestamp, $encodedData] = $components;
        
        // Check expiry (e.g., 24 hours)
        if (time() - $timestamp > 86400) {
            return null;
        }
        
        $data = base64_decode($encodedData);
        return json_decode($data, true);
    }
}
```

## Testing Requirements

### Timing Attack Test

```php
// Test constant-time comparison
function testTimingAttack() {
    $validToken = 'valid_token_12345678901234567890';
    $iterations = 1000;
    
    // Test with completely wrong token
    $wrongTimes = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        SecurityHelper::timingSafeEquals($validToken, 'wrong_token_00000000000000000000');
        $wrongTimes[] = microtime(true) - $start;
    }
    
    // Test with partially correct token
    $partialTimes = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        SecurityHelper::timingSafeEquals($validToken, 'valid_token_00000000000000000000');
        $partialTimes[] = microtime(true) - $start;
    }
    
    $wrongAvg = array_sum($wrongTimes) / count($wrongTimes);
    $partialAvg = array_sum($partialTimes) / count($partialTimes);
    
    // Timing difference should be negligible
    $difference = abs($wrongAvg - $partialAvg);
    $maxAcceptable = 0.000001; // 1 microsecond
    
    if ($difference > $maxAcceptable) {
        echo "⚠ TIMING LEAK DETECTED: {$difference}s difference\n";
        return false;
    }
    
    echo "✓ Constant-time comparison working correctly\n";
    return true;
}
```

## Estimated Effort

- **Effort:** 1-2 days
- **Risk:** Low (well-established security patterns)

## Related Issues

- Issue 002: SQL injection risks
- Issue 005: Session management security
- Issue 016: Lack of security headers

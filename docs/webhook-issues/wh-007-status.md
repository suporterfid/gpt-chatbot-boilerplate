# WH-007 Status: Configuration (Phase 7)

**Status:** ✅ COMPLETED  
**Completion Date:** 2025-11-17  
**Tasks:** wh-007a, wh-007b  
**Specification:** `docs/SPEC_WEBHOOK.md` §9

---

## Overview

Phase 7 implements comprehensive configuration management for the webhook system, centralizing all webhook-related settings in `config.php` and exposing them via environment variables. This phase ensures consistent, environment-specific configuration for both inbound security (secrets, whitelists) and outbound behavior (retry policies, timeouts).

---

## Implemented Components

### ✅ WH-007a: Webhook Configuration Section in config.php

**Objective:** Expand `config.php` with centralized webhook settings for easy management.

**Implementation:**
- **File:** `config.php`
- **Status:** Fully implemented
- **Configuration Structure:** Comprehensive webhooks section with inbound/outbound subsections

**Features Delivered:**
1. ✅ Webhooks configuration section
2. ✅ Environment variable parsing
3. ✅ Inbound webhook settings (security-focused)
4. ✅ Outbound webhook settings (delivery-focused)
5. ✅ Default values for all settings
6. ✅ Type casting and validation
7. ✅ Array parsing for complex values (IP whitelist)
8. ✅ Boolean flag handling

---

## Configuration Structure

### Main Webhooks Section
```php
$config['webhooks'] = [
    // Legacy settings (backward compatibility)
    'gateway_secret' => $_ENV['WEBHOOK_GATEWAY_SECRET'] 
        ?? getenv('WEBHOOK_GATEWAY_SECRET') ?: '',
    'timestamp_tolerance' => (int)($_ENV['WEBHOOK_GATEWAY_TOLERANCE'] 
        ?? getenv('WEBHOOK_GATEWAY_TOLERANCE') ?: 300),
    'log_payloads' => filter_var(
        $_ENV['WEBHOOK_GATEWAY_LOG_PAYLOADS'] 
        ?? getenv('WEBHOOK_GATEWAY_LOG_PAYLOADS') ?: 'false',
        FILTER_VALIDATE_BOOLEAN
    ),
    'openai_signing_secret' => $_ENV['OPENAI_WEBHOOK_SIGNING_SECRET'] 
        ?? getenv('OPENAI_WEBHOOK_SIGNING_SECRET') ?: '',
    'ip_whitelist' => parseFlexibleEnvArray(
        $_ENV['WEBHOOK_IP_WHITELIST'] ?? getenv('WEBHOOK_IP_WHITELIST'),
        ['delimiter' => ',', 'trim_strings' => true, 'filter_empty' => true]
    ),
    
    // Inbound webhook configuration (SPEC_WEBHOOK.md §9)
    'inbound' => [
        'enabled' => filter_var(
            getEnvValue('WEBHOOK_INBOUND_ENABLED') ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        ),
        'path' => getEnvValue('WEBHOOK_INBOUND_PATH') ?: '/webhook/inbound',
        'validate_signature' => filter_var(
            getEnvValue('WEBHOOK_VALIDATE_SIGNATURE') ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        ),
        'max_clock_skew' => (int)(getEnvValue('WEBHOOK_MAX_CLOCK_SKEW') ?: 120),
        'ip_whitelist' => parseFlexibleEnvArray(getEnvValue('WEBHOOK_IP_WHITELIST')),
    ],
    
    // Outbound webhook configuration (SPEC_WEBHOOK.md §9)
    'outbound' => [
        'enabled' => filter_var(
            getEnvValue('WEBHOOK_OUTBOUND_ENABLED') ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        ),
        'max_attempts' => (int)(getEnvValue('WEBHOOK_MAX_ATTEMPTS') ?: 6),
        'timeout' => (int)(getEnvValue('WEBHOOK_TIMEOUT') ?: 5),
        'concurrency' => (int)(getEnvValue('WEBHOOK_CONCURRENCY') ?: 10),
    ],
];
```

---

## Configuration Sections

### 1. Inbound Webhook Configuration

**Purpose:** Control how the system receives and validates external webhooks.

**Settings:**

#### enabled (boolean)
- **Environment Variable:** `WEBHOOK_INBOUND_ENABLED`
- **Default:** `true`
- **Description:** Master switch for inbound webhook processing
- **Usage:** Disable to reject all inbound webhooks (returns 503)

#### path (string)
- **Environment Variable:** `WEBHOOK_INBOUND_PATH`
- **Default:** `/webhook/inbound`
- **Description:** URL path for the canonical inbound webhook endpoint
- **Usage:** Configure custom path for webhook routing

#### validate_signature (boolean)
- **Environment Variable:** `WEBHOOK_VALIDATE_SIGNATURE`
- **Default:** `true`
- **Description:** Enable/disable HMAC signature validation
- **Usage:** Disable for testing, always enable in production

#### max_clock_skew (integer)
- **Environment Variable:** `WEBHOOK_MAX_CLOCK_SKEW`
- **Default:** `120` (seconds)
- **Description:** Maximum allowed time difference for timestamp validation
- **Usage:** Anti-replay protection, adjust based on network latency

#### ip_whitelist (array)
- **Environment Variable:** `WEBHOOK_IP_WHITELIST`
- **Default:** `[]` (empty = all IPs allowed)
- **Format:** Comma-separated IPs or CIDR ranges
- **Description:** Restrict webhook sources by IP address
- **Example:** `192.168.1.100,10.0.0.0/8`

**Access in Code:**
```php
if ($config['webhooks']['inbound']['enabled']) {
    // Process inbound webhook
}

$clockSkew = $config['webhooks']['inbound']['max_clock_skew'];
$whitelist = $config['webhooks']['inbound']['ip_whitelist'];
```

---

### 2. Outbound Webhook Configuration

**Purpose:** Control how the system sends webhooks to external subscribers.

**Settings:**

#### enabled (boolean)
- **Environment Variable:** `WEBHOOK_OUTBOUND_ENABLED`
- **Default:** `true`
- **Description:** Master switch for outbound webhook delivery
- **Usage:** Disable to prevent all webhook dispatching (testing/maintenance)

#### max_attempts (integer)
- **Environment Variable:** `WEBHOOK_MAX_ATTEMPTS`
- **Default:** `6`
- **Description:** Maximum delivery attempts before permanent failure
- **Usage:** Balance between reliability and resource usage

#### timeout (integer)
- **Environment Variable:** `WEBHOOK_TIMEOUT`
- **Default:** `5` (seconds)
- **Description:** HTTP timeout for webhook delivery
- **Usage:** Adjust based on subscriber responsiveness

#### concurrency (integer)
- **Environment Variable:** `WEBHOOK_CONCURRENCY`
- **Default:** `10`
- **Description:** Maximum parallel webhook deliveries
- **Usage:** Control resource usage, prevent overwhelming subscribers

**Access in Code:**
```php
$maxAttempts = $config['webhooks']['outbound']['max_attempts'];
$timeout = $config['webhooks']['outbound']['timeout'];

// Use in WebhookDispatcher
$dispatcher = new WebhookDispatcher($config, $db);
```

---

### 3. Legacy/Global Webhook Settings

**Purpose:** Backward compatibility and shared settings.

**Settings:**

#### gateway_secret (string)
- **Environment Variable:** `WEBHOOK_GATEWAY_SECRET`
- **Default:** `''` (empty = signature validation disabled)
- **Description:** HMAC secret for inbound webhook signature validation
- **Security:** Keep secret, rotate periodically

#### timestamp_tolerance (integer)
- **Environment Variable:** `WEBHOOK_GATEWAY_TOLERANCE`
- **Default:** `300` (seconds)
- **Description:** Timestamp tolerance for anti-replay (5 minutes default)
- **Note:** Used by legacy code, prefer `max_clock_skew` for new code

#### log_payloads (boolean)
- **Environment Variable:** `WEBHOOK_GATEWAY_LOG_PAYLOADS`
- **Default:** `false`
- **Description:** Log full webhook payloads (debugging only)
- **Security:** Disable in production (may log sensitive data)

#### openai_signing_secret (string)
- **Environment Variable:** `OPENAI_WEBHOOK_SIGNING_SECRET`
- **Default:** `''`
- **Description:** OpenAI-specific webhook signing secret
- **Usage:** Used by OpenAI webhook handler

#### ip_whitelist (array)
- **Environment Variable:** `WEBHOOK_IP_WHITELIST`
- **Default:** `[]`
- **Description:** Global IP whitelist (shared with inbound.ip_whitelist)
- **Note:** Both settings reference same environment variable

---

## ✅ WH-007b: Environment Variables and Documentation

**Objective:** Update `.env.example` with all webhook variables and provide documentation.

**Implementation:**
- **File:** `.env.example`
- **Status:** Fully implemented with comprehensive webhook section
- **Documentation:** Inline comments and grouped sections

---

## Environment Variables (.env.example)

### Complete Webhook Configuration Block
```bash
# ==============================================================================
# Webhook Gateway Security (SPEC_WEBHOOK.md §6)
# ==============================================================================

# HMAC signature secret for webhook authentication
# Generate strong random key: openssl rand -hex 32
WEBHOOK_GATEWAY_SECRET=

# Timestamp tolerance in seconds (anti-replay protection)
# Default: 300 (5 minutes)
WEBHOOK_GATEWAY_TOLERANCE=300

# Enable payload logging for debugging (disable in production)
# Default: false
WEBHOOK_GATEWAY_LOG_PAYLOADS=false

# OpenAI webhook signing secret
# Obtained from OpenAI dashboard when configuring webhooks
OPENAI_WEBHOOK_SIGNING_SECRET=

# IP whitelist for webhook access control (comma-separated IPs or CIDR ranges)
# Examples: 192.168.1.100,10.0.0.0/8
# Empty = all IPs allowed (not recommended for production)
WEBHOOK_IP_WHITELIST=

# ==============================================================================
# Webhook I/O Module Configuration (SPEC_WEBHOOK.md §9-10)
# ==============================================================================

# ---- Inbound Webhooks ----
# Receive events from external systems (e.g., payment processors, CRM)

# Enable/disable inbound webhook processing
# Default: true
WEBHOOK_INBOUND_ENABLED=true

# URL path for inbound webhook endpoint
# Default: /webhook/inbound
WEBHOOK_INBOUND_PATH=/webhook/inbound

# Enable HMAC signature validation for inbound webhooks
# Default: true (always enable in production)
WEBHOOK_VALIDATE_SIGNATURE=true

# Maximum clock skew for timestamp validation (seconds)
# Default: 120 (2 minutes)
WEBHOOK_MAX_CLOCK_SKEW=120

# ---- Outbound Webhooks ----
# Send callbacks to configured subscriber URLs

# Enable/disable outbound webhook delivery
# Default: true
WEBHOOK_OUTBOUND_ENABLED=true

# Maximum delivery attempts before permanent failure
# Default: 6 (with exponential backoff)
WEBHOOK_MAX_ATTEMPTS=6

# HTTP timeout for webhook delivery (seconds)
# Default: 5
WEBHOOK_TIMEOUT=5

# Maximum parallel webhook deliveries
# Default: 10
WEBHOOK_CONCURRENCY=10
```

---

## Configuration Patterns

### 1. Environment Variable Parsing

**Pattern:** Dual lookup with fallback
```php
$_ENV['VAR_NAME'] ?? getenv('VAR_NAME') ?: 'default'
```

**Benefits:**
- ✅ Works with both `$_ENV` and `getenv()`
- ✅ Handles missing variables gracefully
- ✅ Type-safe defaults

**Example:**
```php
'enabled' => filter_var(
    $_ENV['WEBHOOK_INBOUND_ENABLED'] ?? getenv('WEBHOOK_INBOUND_ENABLED') ?: 'true',
    FILTER_VALIDATE_BOOLEAN
)
```

---

### 2. Type Casting

**Integers:**
```php
'timeout' => (int)(getEnvValue('WEBHOOK_TIMEOUT') ?: 5)
```

**Booleans:**
```php
'enabled' => filter_var(
    getEnvValue('WEBHOOK_INBOUND_ENABLED') ?? 'true',
    FILTER_VALIDATE_BOOLEAN
)
```

**Arrays (comma-separated):**
```php
'ip_whitelist' => parseFlexibleEnvArray(
    getEnvValue('WEBHOOK_IP_WHITELIST'),
    ['delimiter' => ',', 'trim_strings' => true, 'filter_empty' => true]
)
```

---

### 3. Helper Functions

#### getEnvValue()
```php
function getEnvValue(string $key): ?string {
    return $_ENV[$key] ?? getenv($key) ?: null;
}
```

#### parseFlexibleEnvArray()
```php
function parseFlexibleEnvArray(?string $value, array $options = []): array {
    if ($value === null || trim($value) === '') {
        return [];
    }
    
    $delimiter = $options['delimiter'] ?? ',';
    $parts = explode($delimiter, $value);
    
    if ($options['trim_strings'] ?? false) {
        $parts = array_map('trim', $parts);
    }
    
    if ($options['filter_empty'] ?? false) {
        $parts = array_filter($parts, fn($v) => $v !== '');
    }
    
    return array_values($parts);
}
```

---

## Usage Examples

### Example 1: Check if Inbound Webhooks Enabled
```php
if (!$config['webhooks']['inbound']['enabled']) {
    http_response_code(503);
    echo json_encode(['error' => 'Webhooks are currently disabled']);
    exit;
}
```

### Example 2: Configure WebhookDispatcher
```php
$dispatcher = new WebhookDispatcher($config, $db);

// Config automatically used for:
// - Max retry attempts
// - Timeout settings
// - Concurrency limits
```

### Example 3: Security Service Configuration
```php
$security = new WebhookSecurityService($config);

// Uses config values:
// - gateway_secret
// - timestamp_tolerance (or max_clock_skew)
// - ip_whitelist

$isValid = $security->validateSignature($sig, $body, $config['webhooks']['gateway_secret']);
```

### Example 4: Dynamic Configuration
```php
// Override default for specific environment
if (getenv('APP_ENV') === 'development') {
    $config['webhooks']['log_payloads'] = true;
    $config['webhooks']['inbound']['validate_signature'] = false;
}

// Override for maintenance mode
if (file_exists('/tmp/maintenance.lock')) {
    $config['webhooks']['inbound']['enabled'] = false;
    $config['webhooks']['outbound']['enabled'] = false;
}
```

---

## Environment-Specific Configuration

### Development (.env.development)
```bash
# Relaxed settings for local development
WEBHOOK_GATEWAY_SECRET=dev_secret_key_not_for_prod
WEBHOOK_GATEWAY_TOLERANCE=3600  # 1 hour (loose)
WEBHOOK_GATEWAY_LOG_PAYLOADS=true  # Enable debugging
WEBHOOK_VALIDATE_SIGNATURE=false  # Optional for testing
WEBHOOK_MAX_CLOCK_SKEW=3600
WEBHOOK_IP_WHITELIST=  # Allow all IPs
```

### Staging (.env.staging)
```bash
# Production-like settings
WEBHOOK_GATEWAY_SECRET=${VAULT_WEBHOOK_SECRET}
WEBHOOK_GATEWAY_TOLERANCE=300  # 5 minutes
WEBHOOK_GATEWAY_LOG_PAYLOADS=false
WEBHOOK_VALIDATE_SIGNATURE=true
WEBHOOK_MAX_CLOCK_SKEW=120  # 2 minutes
WEBHOOK_IP_WHITELIST=10.0.0.0/8  # Internal network only
```

### Production (.env.production)
```bash
# Strict security settings
WEBHOOK_GATEWAY_SECRET=${VAULT_WEBHOOK_SECRET}
WEBHOOK_GATEWAY_TOLERANCE=120  # 2 minutes (strict)
WEBHOOK_GATEWAY_LOG_PAYLOADS=false  # Never log in production
WEBHOOK_VALIDATE_SIGNATURE=true  # Always validate
WEBHOOK_MAX_CLOCK_SKEW=60  # 1 minute (very strict)
WEBHOOK_IP_WHITELIST=203.0.113.10,198.51.100.0/24  # Specific IPs only
WEBHOOK_MAX_ATTEMPTS=3  # Fail faster in production
WEBHOOK_TIMEOUT=3  # Shorter timeout
```

---

## Configuration Validation

### Startup Validation
```php
// Optional: Validate critical config at startup
function validateWebhookConfig(array $config): void {
    $webhooks = $config['webhooks'] ?? [];
    
    // Check inbound config
    if ($webhooks['inbound']['enabled'] ?? false) {
        if (empty($webhooks['gateway_secret']) && ($webhooks['inbound']['validate_signature'] ?? true)) {
            trigger_error('Webhook signature validation enabled but no secret configured', E_USER_WARNING);
        }
    }
    
    // Check outbound config
    $maxAttempts = $webhooks['outbound']['max_attempts'] ?? 0;
    if ($maxAttempts < 1 || $maxAttempts > 10) {
        trigger_error("Invalid max_attempts: $maxAttempts (must be 1-10)", E_USER_WARNING);
    }
    
    // Check IP whitelist format
    foreach ($webhooks['ip_whitelist'] ?? [] as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('#^[\d.]+/\d+$#', $ip)) {
            trigger_error("Invalid IP whitelist entry: $ip", E_USER_WARNING);
        }
    }
}
```

---

## Migration Guide

### From Hardcoded Values
```php
// Before
define('WEBHOOK_SECRET', 'hardcoded_secret');
define('MAX_RETRIES', 5);

// After
$secret = $config['webhooks']['gateway_secret'];
$maxRetries = $config['webhooks']['outbound']['max_attempts'];
```

### From Scattered Config
```php
// Before
$timeout = getenv('WEBHOOK_TIMEOUT') ?: 10;
$secret = getenv('WEBHOOK_SECRET') ?: '';

// After
$timeout = $config['webhooks']['outbound']['timeout'];
$secret = $config['webhooks']['gateway_secret'];
```

---

## Documentation Updates

### README.md Section
```markdown
## Webhook Configuration

The webhook system is configured via environment variables. Copy `.env.example` to `.env` and configure:

### Inbound Webhooks
- `WEBHOOK_GATEWAY_SECRET` - HMAC signature secret
- `WEBHOOK_IP_WHITELIST` - Allowed source IPs
- `WEBHOOK_MAX_CLOCK_SKEW` - Timestamp tolerance (seconds)

### Outbound Webhooks
- `WEBHOOK_MAX_ATTEMPTS` - Maximum retry attempts
- `WEBHOOK_TIMEOUT` - HTTP timeout (seconds)

See `.env.example` for complete list and descriptions.
```

### Deployment Guide Section
```markdown
## Webhook Deployment Checklist

1. ✅ Set `WEBHOOK_GATEWAY_SECRET` to a strong random value
2. ✅ Configure `WEBHOOK_IP_WHITELIST` for production
3. ✅ Set `WEBHOOK_VALIDATE_SIGNATURE=true` in production
4. ✅ Adjust `WEBHOOK_MAX_CLOCK_SKEW` based on network latency
5. ✅ Test webhook delivery with `scripts/test_webhook.php`
6. ✅ Monitor webhook metrics at `/webhook/metrics`
```

---

## Benefits of Centralized Configuration

### ✅ Single Source of Truth
- All webhook settings in one place (`config.php`)
- No scattered configuration across files
- Easy to audit and review

### ✅ Environment-Specific Overrides
- Different settings for dev/staging/prod
- No code changes needed
- Managed via `.env` files

### ✅ Type Safety
- Consistent type casting
- Default values prevent errors
- Validation at startup

### ✅ Documentation
- Inline comments in `.env.example`
- Clear descriptions and examples
- Links to specification

### ✅ Maintainability
- Easy to add new settings
- Consistent patterns
- Helper functions reduce duplication

---

## Test Coverage

### Configuration Loading Tests
```php
// Verify config structure
assert(isset($config['webhooks']['inbound']));
assert(isset($config['webhooks']['outbound']));

// Verify defaults
assert($config['webhooks']['inbound']['enabled'] === true);
assert($config['webhooks']['outbound']['max_attempts'] === 6);

// Verify type casting
assert(is_bool($config['webhooks']['inbound']['enabled']));
assert(is_int($config['webhooks']['outbound']['timeout']));
assert(is_array($config['webhooks']['ip_whitelist']));
```

### Environment Variable Parsing Tests
**File:** `tests/test_webhook_config.php`

Tests verify:
- ✅ Environment variable precedence ($_ENV > getenv() > default)
- ✅ Type casting (string → bool, int, array)
- ✅ Array parsing (comma-separated values)
- ✅ Default value fallback
- ✅ Empty value handling

---

## Conclusion

Phase 7 (WH-007) is **fully implemented** with:
- ✅ Comprehensive webhook configuration in `config.php`
- ✅ All environment variables documented in `.env.example`
- ✅ Inbound and outbound settings separated
- ✅ Type-safe configuration parsing
- ✅ Backward compatibility maintained
- ✅ Environment-specific override support
- ✅ Helper functions for common patterns
- ✅ Inline documentation and comments

The configuration system provides a solid foundation for managing webhook behavior across environments while maintaining flexibility and ease of use.

---

**Related Phases:**
- Phase 1 (WH-001): Uses inbound configuration
- Phase 2 (WH-002): Uses security settings
- Phase 5 (WH-005): Uses outbound configuration
- Phase 6 (WH-006): Uses retry settings

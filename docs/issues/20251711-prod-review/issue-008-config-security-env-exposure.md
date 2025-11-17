# Issue 008: Configuration Security and Secrets Management

**Category:** Security & Configuration  
**Severity:** High  
**Priority:** High  
**File:** `config.php`

## Problem Description

The current configuration system loads secrets directly from environment variables without validation, centralized management, or proper security controls. There's no verification that critical secrets are set, and error messages might expose configuration details.

## Issues

### 1. No Required Configuration Validation

```php
// config.php - No validation that OPENAI_API_KEY is set
$config['openai']['api_key'] = getEnvValue('OPENAI_API_KEY');
```

If `OPENAI_API_KEY` is not set, the application starts but fails at runtime with unclear errors.

### 2. Secrets in Error Messages

When configuration is missing or invalid, error messages might expose:
- Which environment variables are expected
- Configuration structure
- Default values

### 3. No Secret Rotation Support

- No mechanism to reload configuration without restart
- API keys hardcoded for entire application lifetime
- No support for key rotation strategies

### 4. .env File in Repository Risk

While `.env` is in `.gitignore`, developers might accidentally commit it:
- No pre-commit hook to prevent this
- `.env.example` might contain real secrets by mistake

### 5. Insufficient Secrets Isolation

All parts of the application have access to all secrets:
- Frontend might accidentally log secrets
- Audit logs might include sensitive config
- Error reports might expose secrets

## Impact

- **High**: Accidental secret exposure in logs/errors
- **Medium**: Application fails to start without clear error
- **Medium**: Difficult to rotate secrets
- **Low**: Configuration sprawl

## Recommendations

### 1. Implement Configuration Validator

```php
// includes/ConfigValidator.php
class ConfigValidator {
    private array $requiredKeys = [
        'openai.api_key' => 'OPENAI_API_KEY environment variable',
        'openai.base_url' => 'OPENAI_BASE_URL environment variable',
        'admin.database_path' => 'Database path configuration',
    ];
    
    private array $errors = [];
    
    public function validate(array $config): bool {
        $this->errors = [];
        
        foreach ($this->requiredKeys as $key => $description) {
            if (!$this->hasKey($config, $key)) {
                $this->errors[] = "Missing required configuration: $description";
                continue;
            }
            
            $value = $this->getKey($config, $key);
            if (empty($value)) {
                $this->errors[] = "Empty value for required configuration: $description";
            }
        }
        
        // Validate specific formats
        $this->validateApiKey($config);
        $this->validateUrl($config['openai']['base_url'] ?? null, 'OpenAI Base URL');
        $this->validatePath($config['admin']['database_path'] ?? null, 'Database path');
        
        return empty($this->errors);
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    private function validateApiKey(array $config): void {
        $apiKey = $config['openai']['api_key'] ?? null;
        
        if (!$apiKey) {
            return; // Already reported as missing
        }
        
        // Validate format without exposing the key
        if (!preg_match('/^sk-[a-zA-Z0-9]{32,}$/', $apiKey)) {
            $this->errors[] = "OPENAI_API_KEY has invalid format";
        }
    }
    
    private function validateUrl(?string $url, string $name): void {
        if (!$url) {
            return;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors[] = "$name must be a valid URL";
        }
        
        if (!str_starts_with($url, 'https://')) {
            $this->errors[] = "$name must use HTTPS";
        }
    }
    
    private function validatePath(?string $path, string $name): void {
        if (!$path) {
            return;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->errors[] = "$name directory does not exist: $dir";
        }
        
        if (!is_writable($dir)) {
            $this->errors[] = "$name directory is not writable: $dir";
        }
    }
    
    private function hasKey(array $config, string $key): bool {
        $keys = explode('.', $key);
        $current = $config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return false;
            }
            $current = $current[$k];
        }
        
        return true;
    }
    
    private function getKey(array $config, string $key) {
        $keys = explode('.', $key);
        $current = $config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }
        
        return $current;
    }
}

// In config.php - after loading configuration
$validator = new ConfigValidator();
if (!$validator->validate($config)) {
    foreach ($validator->getErrors() as $error) {
        error_log("Configuration Error: $error");
    }
    
    // In web context, show user-friendly error
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo "Application configuration error. Please contact support.";
        exit(1);
    }
    
    // In CLI context, show detailed errors
    echo "Configuration Errors:\n";
    foreach ($validator->getErrors() as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
```

### 2. Implement Secrets Manager

```php
// includes/SecretsManager.php
class SecretsManager {
    private array $secrets = [];
    private bool $loaded = false;
    
    public function __construct(private string $secretsSource = 'env') {
        // Support multiple secret sources
        $this->loadSecrets();
    }
    
    /**
     * Get secret by key
     * 
     * @param string $key Secret key (e.g., 'openai.api_key')
     * @param mixed $default Default value if not found
     * @return mixed Secret value
     */
    public function get(string $key, $default = null) {
        if (!$this->loaded) {
            throw new RuntimeException('Secrets not loaded');
        }
        
        return $this->secrets[$key] ?? $default;
    }
    
    /**
     * Set secret (for testing or runtime updates)
     */
    public function set(string $key, $value): void {
        $this->secrets[$key] = $value;
    }
    
    /**
     * Check if secret exists
     */
    public function has(string $key): bool {
        return isset($this->secrets[$key]);
    }
    
    /**
     * Reload secrets (for rotation)
     */
    public function reload(): void {
        $this->loadSecrets();
    }
    
    /**
     * Get redacted secret for logging
     */
    public function getRedacted(string $key): string {
        $secret = $this->get($key);
        
        if (!$secret || !is_string($secret)) {
            return '[not set]';
        }
        
        $len = strlen($secret);
        if ($len <= 8) {
            return '****';
        }
        
        // Show first 4 and last 4 characters
        return substr($secret, 0, 4) . '****' . substr($secret, -4);
    }
    
    /**
     * Load secrets from configured source
     */
    private function loadSecrets(): void {
        switch ($this->secretsSource) {
            case 'env':
                $this->loadFromEnv();
                break;
                
            case 'aws-secrets-manager':
                $this->loadFromAWS();
                break;
                
            case 'vault':
                $this->loadFromVault();
                break;
                
            default:
                throw new RuntimeException("Unknown secrets source: {$this->secretsSource}");
        }
        
        $this->loaded = true;
    }
    
    /**
     * Load from environment variables
     */
    private function loadFromEnv(): void {
        $this->secrets = [
            'openai.api_key' => getenv('OPENAI_API_KEY') ?: null,
            'openai.organization' => getenv('OPENAI_ORGANIZATION') ?: null,
            'admin.token' => getenv('ADMIN_TOKEN') ?: null,
            'database.password' => getenv('DB_PASSWORD') ?: null,
            'jwt.secret' => getenv('JWT_SECRET') ?: null,
        ];
    }
    
    /**
     * Load from AWS Secrets Manager
     */
    private function loadFromAWS(): void {
        // Implement AWS Secrets Manager integration
        // Requires: composer require aws/aws-sdk-php
        
        /*
        $client = new SecretsManagerClient([
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'version' => 'latest'
        ]);
        
        $secretName = getenv('AWS_SECRET_NAME');
        $result = $client->getSecretValue(['SecretId' => $secretName]);
        $secrets = json_decode($result['SecretString'], true);
        
        $this->secrets = $secrets;
        */
        
        throw new RuntimeException('AWS Secrets Manager not implemented');
    }
    
    /**
     * Load from HashiCorp Vault
     */
    private function loadFromVault(): void {
        // Implement Vault integration
        throw new RuntimeException('Vault integration not implemented');
    }
}

// Usage in config.php
$secretsManager = new SecretsManager('env');

$config['openai']['api_key'] = $secretsManager->get('openai.api_key');
$config['openai']['organization'] = $secretsManager->get('openai.organization');
```

### 3. Sanitize Error Messages

```php
// includes/ErrorHandler.php
class ErrorHandler {
    /**
     * Sanitize error message to remove sensitive information
     */
    public static function sanitize(string $message): string {
        // Remove API keys
        $message = preg_replace('/sk-[a-zA-Z0-9]{32,}/', '[API_KEY_REDACTED]', $message);
        
        // Remove Bearer tokens
        $message = preg_replace('/Bearer\s+[a-zA-Z0-9_-]+/', 'Bearer [REDACTED]', $message);
        
        // Remove passwords
        $message = preg_replace('/password["\s:=]+[^"\s]+/', 'password=[REDACTED]', $message);
        
        // Remove database connection strings
        $message = preg_replace('/mysql:\/\/[^@]+@/', 'mysql://[REDACTED]@', $message);
        
        // Remove absolute paths
        $message = preg_replace('/\/var\/www\/[^\s"\']+/', '[PATH_REDACTED]', $message);
        $message = preg_replace('/\/home\/[^\s"\']+/', '[PATH_REDACTED]', $message);
        
        return $message;
    }
    
    /**
     * Log error safely
     */
    public static function logError(string $message, array $context = []): void {
        // Sanitize message
        $safeMessage = self::sanitize($message);
        
        // Sanitize context
        $safeContext = self::sanitizeContext($context);
        
        error_log($safeMessage . ' ' . json_encode($safeContext));
    }
    
    /**
     * Sanitize context array
     */
    private static function sanitizeContext(array $context): array {
        $sensitiveKeys = [
            'password', 'api_key', 'token', 'secret', 
            'authorization', 'cookie', 'session'
        ];
        
        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key is sensitive
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
            
            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
            
            // Sanitize string values
            if (is_string($value)) {
                $context[$key] = self::sanitize($value);
            }
        }
        
        return $context;
    }
}
```

### 4. Add Git Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

# Check for .env file in staged files
if git diff --cached --name-only | grep -q "^\.env$"; then
    echo "ERROR: Attempting to commit .env file!"
    echo "This file contains secrets and should not be committed."
    echo "Please unstage it with: git reset HEAD .env"
    exit 1
fi

# Check for potential secrets in staged files
if git diff --cached | grep -E "(api[_-]?key|password|secret|token|bearer)" | grep -E "(sk-[a-zA-Z0-9]{32,}|['\"][a-zA-Z0-9_-]{32,}['\"])"; then
    echo "WARNING: Potential secrets detected in staged changes!"
    echo "Please review your changes and remove any sensitive data."
    echo ""
    echo "Detected patterns:"
    git diff --cached | grep -E "(api[_-]?key|password|secret|token)" | grep -E "(sk-[a-zA-Z0-9]{32,}|['\"][a-zA-Z0-9_-]{32,}['\"])"
    echo ""
    read -p "Do you want to continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

exit 0
```

Install hook:
```bash
chmod +x .git/hooks/pre-commit
```

### 5. Use phpdotenv for Better .env Handling

```bash
composer require vlucas/phpdotenv
```

```php
// config.php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);

try {
    $dotenv->load();
    
    // Require specific variables
    $dotenv->required([
        'OPENAI_API_KEY',
        'APP_ENV'
    ]);
    
    // Validate formats
    $dotenv->required('OPENAI_API_KEY')->notEmpty();
    $dotenv->required('APP_ENV')->allowedValues(['local', 'staging', 'production']);
    
} catch (Exception $e) {
    error_log("Environment configuration error: " . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo "Application configuration error. Please contact support.";
        exit(1);
    }
    
    die("Configuration Error: " . $e->getMessage() . "\n");
}
```

## Testing Requirements

```php
// tests/ConfigValidationTest.php
class ConfigValidationTest {
    public function testMissingApiKey() {
        unset($_ENV['OPENAI_API_KEY']);
        
        $validator = new ConfigValidator();
        $result = $validator->validate($config);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('OPENAI_API_KEY', 
            implode(' ', $validator->getErrors()));
    }
    
    public function testInvalidApiKeyFormat() {
        $_ENV['OPENAI_API_KEY'] = 'invalid_key';
        
        $validator = new ConfigValidator();
        $result = $validator->validate($config);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('invalid format',
            implode(' ', $validator->getErrors()));
    }
    
    public function testErrorMessageSanitization() {
        $error = "Failed with API key: sk-abc123xyz789";
        $sanitized = ErrorHandler::sanitize($error);
        
        $this->assertStringNotContainsString('sk-abc123xyz789', $sanitized);
        $this->assertStringContainsString('[API_KEY_REDACTED]', $sanitized);
    }
}
```

## Documentation Updates

Create `docs/CONFIGURATION.md`:

```markdown
# Configuration Guide

## Environment Variables

### Required Variables

- `OPENAI_API_KEY`: Your OpenAI API key (format: sk-...)
- `APP_ENV`: Environment (local, staging, production)
- `DATABASE_PATH`: Path to SQLite database file

### Optional Variables

- `OPENAI_ORGANIZATION`: OpenAI organization ID
- `LOG_LEVEL`: Logging level (debug, info, warning, error)

## Secrets Management

### Development

Use `.env` file (never commit this file):

```bash
cp .env.example .env
# Edit .env with your local values
```

### Production

**Option 1: Environment Variables**

Set directly in server configuration:

```bash
export OPENAI_API_KEY=sk-...
export APP_ENV=production
```

**Option 2: AWS Secrets Manager**

```bash
export AWS_SECRET_NAME=chatbot-secrets
export SECRETS_SOURCE=aws-secrets-manager
```

**Option 3: HashiCorp Vault**

```bash
export VAULT_ADDR=https://vault.example.com
export VAULT_TOKEN=...
export SECRETS_SOURCE=vault
```

## Security Best Practices

1. Never commit `.env` file
2. Rotate secrets regularly (every 90 days)
3. Use different secrets per environment
4. Monitor secret access logs
5. Revoke secrets after incidents
```

## Estimated Effort

- **Effort:** 2 days
- **Risk:** Low (configuration infrastructure)

## Related Issues

- Issue 002: SQL injection (input validation)
- Issue 003: Timing attacks (secret comparison)
- Issue 015: Error message disclosure

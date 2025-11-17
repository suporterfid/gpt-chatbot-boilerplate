# Issue 007: Lack of Composer Autoloading and Dependency Management

**Category:** Maintainability & Architecture  
**Severity:** Medium  
**Priority:** High  
**Files:** Multiple `require_once` statements across the codebase

## Problem Description

The project uses manual `require_once` statements instead of Composer's autoloading, creating maintenance burden and technical debt. This is evident in files like:

- `chat-unified.php` (lines 6-13): 8 manual requires
- `admin-api.php` (lines 7-20): 14 manual requires
- Other entry points with similar patterns

## Current Code Pattern

```php
// chat-unified.php
require_once 'config.php';
require_once 'includes/OpenAIClient.php';
require_once 'includes/ChatHandler.php';
require_once 'includes/AuditService.php';
require_once 'includes/UsageTrackingService.php';
require_once 'includes/QuotaService.php';
require_once 'includes/TenantRateLimitService.php';
require_once 'includes/TenantUsageService.php';
```

## Issues

### 1. Maintenance Burden

- Adding new dependencies requires updating multiple files
- Dependency order matters - easy to get wrong
- No IDE support for autocompletion/navigation
- Renaming/moving files breaks includes

### 2. Performance

- Every request loads all files regardless of code path
- No opcode cache optimization
- Slower than optimized autoloader

### 3. No Dependency Resolution

- No way to manage external dependencies
- Third-party libraries must be manually included
- Version management is impossible
- Security updates are manual

### 4. Testing Difficulty

- Hard to mock dependencies
- Can't isolate classes for unit testing
- Test setup is complex

### 5. Missing PSR-4 Structure

- No namespace organization
- Global namespace pollution
- Class naming conflicts possible

## Impact

- **High**: Increased development time
- **Medium**: Harder onboarding for new developers
- **Medium**: Difficult to upgrade dependencies
- **Low**: Minor performance impact

## Recommendations

### 1. Implement Composer

Create `composer.json`:

```json
{
    "name": "suporterfid/gpt-chatbot-boilerplate",
    "description": "Production-ready GPT chatbot boilerplate with PHP backend",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": ">=8.0",
        "ext-curl": "*",
        "ext-json": "*",
        "ext-mbstring": "*",
        "ext-pdo": "*"
    },
    "require-dev": {
        "phpstan/phpstan": "^1.10",
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "GPTChatbot\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "GPTChatbot\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true,
        "classmap-authoritative": false
    },
    "scripts": {
        "analyze": "phpstan analyse --level=8 src/",
        "test": "phpunit tests/",
        "autoload": "composer dump-autoload --optimize"
    }
}
```

### 2. Restructure to PSR-4

Move classes to namespaced structure:

```
Before:
includes/
├── ChatHandler.php
├── OpenAIClient.php
├── AgentService.php
└── ...

After:
src/
├── Chat/
│   ├── ChatHandler.php
│   └── MessageValidator.php
├── OpenAI/
│   ├── OpenAIClient.php
│   └── ResponseParser.php
├── Agent/
│   ├── AgentService.php
│   └── AgentRepository.php
├── Auth/
│   ├── AdminAuth.php
│   └── SessionManager.php
└── ...
```

### 3. Add Namespaces to Classes

```php
<?php
// Before: includes/ChatHandler.php
class ChatHandler {
    // ...
}

// After: src/Chat/ChatHandler.php
declare(strict_types=1);

namespace GPTChatbot\Chat;

use GPTChatbot\OpenAI\OpenAIClient;
use GPTChatbot\Agent\AgentService;
use GPTChatbot\Audit\AuditService;

class ChatHandler {
    private OpenAIClient $openAIClient;
    private ?AgentService $agentService;
    private ?AuditService $auditService;
    
    public function __construct(
        array $config,
        ?AgentService $agentService = null,
        ?AuditService $auditService = null
    ) {
        $this->openAIClient = new OpenAIClient($config['openai']);
        $this->agentService = $agentService;
        $this->auditService = $auditService;
    }
    
    // ... methods ...
}
```

### 4. Update Entry Points

```php
<?php
// chat-unified.php

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration (still a simple include)
require_once __DIR__ . '/config.php';

use GPTChatbot\Chat\ChatHandler;
use GPTChatbot\OpenAI\OpenAIClient;
use GPTChatbot\Agent\AgentService;
use GPTChatbot\Audit\AuditService;
use GPTChatbot\Database\DB;
use GPTChatbot\Observability\ObservabilityMiddleware;

// All classes automatically loaded by Composer
$db = new DB($dbConfig);
$agentService = new AgentService($db);
$auditService = new AuditService($config['auditing']);
$observability = new ObservabilityMiddleware($config);
$chatHandler = new ChatHandler($config, $agentService, $auditService, $observability);

// ... rest of the code
```

### 5. Add External Dependencies

```json
{
    "require": {
        "php": ">=8.0",
        "ext-curl": "*",
        "ext-json": "*",
        "ext-mbstring": "*",
        "ext-pdo": "*",
        "guzzlehttp/guzzle": "^7.8",
        "monolog/monolog": "^3.5",
        "vlucas/phpdotenv": "^5.6",
        "ramsey/uuid": "^4.7"
    },
    "require-dev": {
        "phpstan/phpstan": "^1.10",
        "phpunit/phpunit": "^10.5",
        "squizlabs/php_codesniffer": "^3.8",
        "mockery/mockery": "^1.6"
    }
}
```

### 6. Migration Strategy

To minimize risk, migrate incrementally:

#### Phase 1: Add Composer (Week 1)
- Create `composer.json`
- Run `composer install`
- Add autoloader to entry points: `require __DIR__ . '/vendor/autoload.php';`
- Keep existing `require_once` statements temporarily

#### Phase 2: Add Namespaces to New Code (Week 2)
- New classes go in `src/` with namespaces
- Existing classes stay in `includes/` temporarily
- Update `composer.json` to include both:
  ```json
  "autoload": {
      "psr-4": {
          "GPTChatbot\\": "src/"
      },
      "classmap": [
          "includes/"
      ]
  }
  ```

#### Phase 3: Migrate Core Classes (Week 3-4)
- Move and namespace classes one module at a time
- Test thoroughly after each module
- Priority: ChatHandler, OpenAIClient, AgentService, DB

#### Phase 4: Cleanup (Week 5)
- Remove all `require_once` statements
- Delete `includes/` directory
- Update all references
- Run full test suite

### 7. Testing After Migration

```php
// Before migration test
class LegacyLoadTest {
    public function testAllClassesLoad() {
        $files = [
            'includes/ChatHandler.php',
            'includes/OpenAIClient.php',
            'includes/AgentService.php',
            // ... all files
        ];
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new Exception("Missing file: $file");
            }
            require_once $file;
        }
        
        // Verify classes exist
        $this->assertTrue(class_exists('ChatHandler'));
        $this->assertTrue(class_exists('OpenAIClient'));
    }
}

// After migration test
class AutoloadTest {
    public function testComposerAutoload() {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Verify autoloading works
        $this->assertTrue(class_exists('GPTChatbot\\Chat\\ChatHandler'));
        $this->assertTrue(class_exists('GPTChatbot\\OpenAI\\OpenAIClient'));
        $this->assertTrue(class_exists('GPTChatbot\\Agent\\AgentService'));
    }
    
    public function testNoManualRequires() {
        $entryPoints = [
            'chat-unified.php',
            'admin-api.php',
            'metrics.php'
        ];
        
        foreach ($entryPoints as $file) {
            $content = file_get_contents($file);
            
            // Should only have one require for vendor/autoload.php
            $requireCount = substr_count($content, 'require_once');
            $this->assertLessThanOrEqual(2, $requireCount, 
                "$file has too many require_once statements");
        }
    }
}
```

## Benefits After Migration

### 1. Easier Dependency Management

```bash
# Add new dependency
composer require monolog/monolog

# Update dependencies
composer update

# Install from scratch
composer install
```

### 2. Better IDE Support

```php
// IDE can now:
// - Autocomplete class names
// - Navigate to class definitions
// - Show method signatures
// - Detect errors before runtime

use GPTChatbot\Chat\ChatHandler;

$handler = new ChatHandler($config); // IDE suggests constructor params
$handler->handle... // IDE autocompletes methods
```

### 3. Improved Testing

```php
// tests/Chat/ChatHandlerTest.php
namespace GPTChatbot\Tests\Chat;

use GPTChatbot\Chat\ChatHandler;
use GPTChatbot\OpenAI\OpenAIClient;
use PHPUnit\Framework\TestCase;
use Mockery;

class ChatHandlerTest extends TestCase {
    public function testHandleMessage() {
        // Easy to mock dependencies
        $mockClient = Mockery::mock(OpenAIClient::class);
        $mockClient->shouldReceive('createChatCompletion')
            ->once()
            ->andReturn(['response' => 'test']);
        
        $handler = new ChatHandler($config, null, null, $mockClient);
        $result = $handler->handleChatCompletionSync('test', 'conv_123');
        
        $this->assertEquals('test', $result['response']);
    }
}
```

### 4. Production Optimization

```bash
# Generate optimized classmap
composer dump-autoload --optimize --classmap-authoritative

# Result: ~10-20% faster autoloading in production
```

## Documentation Updates

Update `docs/deployment.md`:

```markdown
## Installation

1. Clone repository
2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Copy `.env.example` to `.env`
4. Configure environment variables
5. Run migrations:
   ```bash
   php scripts/run_migrations.php
   ```

## Development Setup

```bash
# Install all dependencies (including dev)
composer install

# Run tests
composer test

# Run static analysis
composer analyze

# Check coding standards
composer cs-check
```
```

## Estimated Effort

- **Effort:** 1-2 weeks (including testing)
- **Risk:** Low (can be done incrementally)

## Related Issues

- Issue 001: ChatHandler SRP violation (easier to split with namespaces)
- Issue 014: PSR-12 compliance
- Issue 008: Testing infrastructure

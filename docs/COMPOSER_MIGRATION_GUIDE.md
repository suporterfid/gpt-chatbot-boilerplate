# Composer Autoloading Migration Guide

This guide documents the migration strategy for moving from manual `require_once` statements to Composer autoloading with PSR-4 namespaces.

## Current Status

**Phase 1: ✅ COMPLETE** (Completed 2025-11-18)
- Composer configuration updated
- Autoloader generated and integrated
- Entry points updated
- All tests passing (28/28)
- Backward compatible

## Migration Phases

### Phase 1: Enable Composer Autoloading ✅

**Goal:** Add Composer autoloading infrastructure alongside existing manual requires.

**Actions Completed:**
- ✅ Updated `composer.json` with classmap for `includes/`
- ✅ Added PSR-4 configuration for future `src/` directory
- ✅ Generated autoloader: `composer install --no-dev`
- ✅ Added `require 'vendor/autoload.php'` to entry points
- ✅ Kept all existing `require_once` statements
- ✅ Verified all tests pass

**Result:** Classes can be loaded via autoloader OR manual requires (dual loading).

### Phase 2: Add Namespaces to New Code ⏳

**Timeline:** 1-2 weeks  
**Goal:** Start using PSR-4 namespaces for all new features.

**Actions Required:**
1. Create `src/` directory structure:
   ```
   src/
   ├── Chat/
   ├── OpenAI/
   ├── Agent/
   ├── Auth/
   ├── Database/
   ├── Security/
   └── ...
   ```

2. For new features, create classes in `src/` with namespaces:
   ```php
   <?php
   declare(strict_types=1);
   
   namespace GPTChatbot\Chat;
   
   use GPTChatbot\OpenAI\OpenAIClient;
   
   class NewFeature {
       // Implementation
   }
   ```

3. Import namespaced classes in entry points:
   ```php
   <?php
   require_once __DIR__ . '/vendor/autoload.php';
   
   use GPTChatbot\Chat\NewFeature;
   
   $feature = new NewFeature();
   ```

4. Update documentation with namespace conventions

**Guidelines:**
- All new code MUST use namespaces
- Existing code can remain in `includes/`
- Both approaches coexist during transition

### Phase 3: Migrate Existing Classes ⏳

**Timeline:** 2-3 weeks  
**Goal:** Move existing classes to `src/` with proper namespaces.

**Migration Priority:**

1. **High Priority** (Week 1):
   - ChatHandler → `src/Chat/ChatHandler.php`
   - OpenAIClient → `src/OpenAI/OpenAIClient.php`
   - DB → `src/Database/DB.php`
   - AgentService → `src/Agent/AgentService.php`

2. **Medium Priority** (Week 2):
   - Security classes → `src/Security/`
   - Services → `src/Services/`
   - Repositories → `src/Repository/`

3. **Low Priority** (Week 3):
   - Utilities and helpers
   - Legacy code with minimal usage

**Migration Steps per Class:**

1. Create namespaced version in `src/`:
   ```php
   // src/Chat/ChatHandler.php
   <?php
   declare(strict_types=1);
   
   namespace GPTChatbot\Chat;
   
   use GPTChatbot\OpenAI\OpenAIClient;
   use GPTChatbot\Agent\AgentService;
   
   class ChatHandler {
       // Same implementation, updated imports
   }
   ```

2. Update imports in files using the class:
   ```php
   // Before
   require_once 'includes/ChatHandler.php';
   $handler = new ChatHandler($config);
   
   // After
   use GPTChatbot\Chat\ChatHandler;
   $handler = new ChatHandler($config);
   ```

3. Run tests after each migration
4. Delete old file from `includes/` once fully migrated

**Testing Strategy:**
- Test after each module migration
- Run full test suite: `composer test`
- Verify entry points still work
- Check for any missed imports

### Phase 4: Complete Cleanup ⏳

**Timeline:** 1 week  
**Goal:** Remove all manual requires and legacy structure.

**Actions Required:**

1. Remove manual `require_once` from entry points:
   ```php
   // Before
   require_once __DIR__ . '/vendor/autoload.php';
   require_once 'includes/OpenAIClient.php';
   require_once 'includes/ChatHandler.php';
   
   // After
   require_once __DIR__ . '/vendor/autoload.php';
   
   use GPTChatbot\Chat\ChatHandler;
   use GPTChatbot\OpenAI\OpenAIClient;
   ```

2. Remove classmap from `composer.json`:
   ```json
   {
       "autoload": {
           "psr-4": {
               "GPTChatbot\\": "src/"
           }
       }
   }
   ```

3. Delete `includes/` directory
4. Regenerate autoloader: `composer dump-autoload --optimize`
5. Update all documentation
6. Run full test suite
7. Performance benchmarking

**Final Verification:**
- [ ] All tests pass
- [ ] No manual requires remain
- [ ] `includes/` directory deleted
- [ ] Documentation updated
- [ ] Performance acceptable
- [ ] Production deployment tested

## Namespace Conventions

### Structure

```
GPTChatbot\
├── Chat\               # Chat handling, messages
├── OpenAI\            # OpenAI API client
├── Agent\             # Agent management
├── Auth\              # Authentication, authorization
├── Database\          # Database connections, migrations
├── Security\          # Security utilities
├── Observability\     # Logging, metrics, tracing
├── Webhook\           # Webhook handling
├── Queue\             # Job queue
└── Services\          # Business services
```

### Naming Conventions

**Classes:**
- Use `PascalCase`
- Clear, descriptive names
- Avoid generic names like "Manager", "Helper"

**Namespaces:**
- Use `PascalCase`
- Singular for single concept (e.g., `Chat`, not `Chats`)
- Plural for collections (e.g., `Services`)

**Files:**
- One class per file
- Filename matches class name
- Located in directory matching namespace

### Example

```php
// src/Chat/MessageValidator.php
<?php
declare(strict_types=1);

namespace GPTChatbot\Chat;

use GPTChatbot\Security\SecurityValidator;

class MessageValidator {
    private SecurityValidator $securityValidator;
    
    public function __construct(SecurityValidator $securityValidator) {
        $this->securityValidator = $securityValidator;
    }
    
    public function validate(string $message): bool {
        return $this->securityValidator->validateMessage($message);
    }
}
```

## Composer Commands

### Installation

```bash
# Production
composer install --no-dev --optimize-autoloader

# Development
composer install
```

### Testing

```bash
# Run tests
composer test

# Static analysis
composer analyze

# Code style check
composer cs-check

# Code style fix
composer cs-fix
```

### Dependencies

```bash
# Add production dependency
composer require vendor/package

# Add development dependency
composer require --dev vendor/package

# Update all dependencies
composer update

# Update specific package
composer update vendor/package

# Remove package
composer remove vendor/package

# Show outdated packages
composer outdated

# Check for security vulnerabilities
composer audit
```

### Autoloader

```bash
# Regenerate autoloader
composer dump-autoload

# Optimized autoloader (production)
composer dump-autoload --optimize --classmap-authoritative

# Clear cache
composer clear-cache
```

## IDE Configuration

### PhpStorm / IntelliJ

1. Go to Settings → PHP → Composer
2. Set "Path to composer.json": `{project_root}/composer.json`
3. Enable "Synchronize IDE settings with composer.json"
4. Go to Settings → PHP → Include Path
5. Add `vendor/` directory

### VS Code

Install extensions:
- PHP Intelephense
- PHP Namespace Resolver

Add to `settings.json`:
```json
{
    "intelephense.environment.phpVersion": "8.0",
    "intelephense.files.associations": ["*.php"],
    "namespaceResolver.autoSort": true
}
```

## Troubleshooting

### Class Not Found

**Problem:** `Fatal error: Class 'ClassName' not found`

**Solutions:**
1. Regenerate autoloader: `composer dump-autoload`
2. Check class is in `includes/` or `src/` 
3. Verify namespace matches directory structure
4. Check composer.json autoload configuration

### Autoloader Not Loading

**Problem:** Autoloader file doesn't exist

**Solution:**
```bash
composer install
```

### Outdated Classmap

**Problem:** New class not found even though file exists

**Solution:**
```bash
composer dump-autoload
```

### Namespace Conflicts

**Problem:** Multiple classes with same name

**Solution:**
- Use full namespace: `GPTChatbot\Chat\Handler`
- Or alias: `use GPTChatbot\Chat\Handler as ChatHandler`

### Performance Issues

**Problem:** Slow autoloading

**Solutions:**
1. Use optimized autoloader: `composer dump-autoload --optimize`
2. Enable APCu in production
3. Use classmap-authoritative: `composer dump-autoload --classmap-authoritative`

## Best Practices

### General

- ✅ Always use namespaces for new code
- ✅ One class per file
- ✅ Import all dependencies at the top
- ✅ Use type hints with namespaced classes
- ✅ Document with PHPDoc including namespaces

### Example: Good Practice

```php
<?php
declare(strict_types=1);

namespace GPTChatbot\Chat;

use GPTChatbot\OpenAI\OpenAIClient;
use GPTChatbot\Security\SecurityValidator;

/**
 * Handles chat message processing
 */
class MessageHandler {
    private OpenAIClient $client;
    private SecurityValidator $validator;
    
    /**
     * @param OpenAIClient $client
     * @param SecurityValidator $validator
     */
    public function __construct(
        OpenAIClient $client,
        SecurityValidator $validator
    ) {
        $this->client = $client;
        $this->validator = $validator;
    }
    
    /**
     * Process a chat message
     * 
     * @param string $message
     * @return array
     */
    public function process(string $message): array {
        if (!$this->validator->validate($message)) {
            return ['error' => 'Invalid message'];
        }
        
        return $this->client->sendMessage($message);
    }
}
```

### Testing with Namespaces

```php
// tests/Chat/MessageHandlerTest.php
<?php
declare(strict_types=1);

namespace GPTChatbot\Tests\Chat;

use GPTChatbot\Chat\MessageHandler;
use GPTChatbot\OpenAI\OpenAIClient;
use PHPUnit\Framework\TestCase;
use Mockery;

class MessageHandlerTest extends TestCase {
    public function testProcessMessage(): void {
        $mockClient = Mockery::mock(OpenAIClient::class);
        $mockClient->shouldReceive('sendMessage')
            ->once()
            ->andReturn(['response' => 'test']);
        
        $handler = new MessageHandler($mockClient);
        $result = $handler->process('test message');
        
        $this->assertEquals('test', $result['response']);
    }
}
```

## Resources

- [PSR-4 Specification](https://www.php-fig.org/psr/psr-4/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHP Namespaces](https://www.php.net/manual/en/language.namespaces.php)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

## Support

For questions or issues with the migration:
- Check this guide first
- Review existing namespaced code in `src/` for examples
- Ask in team chat or create an issue
- Refer to PHP-FIG standards for PSR-4 questions

---

**Document Version:** 1.0  
**Last Updated:** 2025-11-18  
**Status:** Phase 1 Complete, Phases 2-4 Pending

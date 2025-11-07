# Multi-Tenant Architecture - Design Decisions

## Service Tenant Context API Design

### Constructor + Method Parameter Pattern

Several services (ChannelSessionService, ChannelMessageService, AuditService) support tenant context through both:
1. Constructor parameter: `new Service($db, $tenantId)`
2. Method parameter: `service->method(..., $tenantId = null)`

**Rationale:**
- **Flexibility**: Allows different usage patterns depending on context
- **Backward Compatibility**: Existing code can add tenant context without changing all call sites
- **Override Capability**: Constructor sets default, method parameter can override for specific operations
- **Testing**: Makes it easy to test cross-tenant scenarios

**Precedence Rule:**
Method parameter `$tenantId` takes precedence over constructor `$this->tenantId` when provided (not null).

```php
// Pattern used in services
$tenantId = $tenantId ?? $this->tenantId;
```

**Example Usage:**

```php
// Usage 1: Set tenant context at construction
$service = new ChannelSessionService($db, $tenant1Id);
$session = $service->getOrCreateSession($agentId, 'whatsapp', '+123'); // Uses $tenant1Id

// Usage 2: Override per method call
$service = new ChannelSessionService($db, $tenant1Id);
$session = $service->getOrCreateSession($agentId, 'whatsapp', '+123', $tenant2Id); // Uses $tenant2Id

// Usage 3: No tenant context (super-admin operations)
$service = new ChannelSessionService($db, null);
$session = $service->getOrCreateSession($agentId, 'whatsapp', '+123'); // No tenant filtering
```

### TenantContext Helper SQL Manipulation

The `TenantContext::applyFilter()` method uses string manipulation with `stripos()` to inject tenant filters into SQL queries.

**Known Limitations:**
- Can match keywords in string literals or comments (rare in practice)
- Not a full SQL parser

**Recommended Usage:**
- Primarily for reference/documentation purposes
- Production code should use service-level tenant filtering (already implemented)
- Each service (AgentService, PromptService, etc.) has proper tenant filtering built-in

**Why Keep It:**
- Useful utility for quick prototypes or admin tools
- Documents the tenant filtering pattern clearly
- Provides a centralized place for tenant access validation

### Alternative Approaches Considered

1. **Constructor-only pattern**: Would break backward compatibility
2. **Setter method only**: Less explicit, harder to track tenant context
3. **Middleware pattern**: Would require significant refactoring of existing services
4. **ORM/Query Builder**: Would require adding a dependency

### Best Practices

For new code:
1. Use constructor injection for tenant context: `new Service($db, $tenantId)`
2. Don't pass tenant in method parameters unless you need override capability
3. Use `TenantContext` singleton for tracking current user's tenant
4. Let services handle their own tenant filtering (don't use TenantContext::applyFilter in production)

For existing code:
1. Adding tenant support is non-breaking - just pass tenant in constructor
2. Method parameter remains optional for specific use cases

# Issue 001: ChatHandler Violates Single Responsibility Principle

**Category:** Architecture & Design  
**Severity:** High  
**Priority:** High  
**File:** `includes/ChatHandler.php`

## Problem Description

The `ChatHandler` class has grown to 2352 lines and has accumulated too many responsibilities, violating the Single Responsibility Principle (SRP). The class currently handles:

1. **Agent Configuration Resolution** (lines 42-163)
2. **Request Validation** (lines 165-197)
3. **Rate Limiting** (legacy + tenant-based, lines 1968-2044)
4. **Quota Management** (lines 2308-2335)
5. **Conversation History Management** (lines 2067-2105)
6. **Chat Orchestration** for both APIs (lines 199-923)
7. **File Upload Handling** (lines 1939-1965, 2046-2065)
8. **Tool Execution** (lines 2107-2147)
9. **Usage Tracking/Billing** (lines 2222-2256)
10. **LeadSense Integration** (lines 2183-2219)
11. **Tool Configuration Normalization** (lines 1387-1861)
12. **Streaming Logic** (lines 1225-1347, 648-923)

## Issues

1. **High Cyclomatic Complexity**: The class is difficult to understand, test, and maintain
2. **Mixed Concerns**: Business logic, validation, storage, and API communication are intertwined
3. **Testing Difficulty**: Unit testing requires mocking too many dependencies
4. **Hard to Extend**: Adding new features requires modifying this massive class
5. **Code Duplication**: Similar logic repeated between `handleResponsesChat` and `handleResponsesChatSync`

## Impact

- Reduced code maintainability
- Increased bug surface area
- Slower development velocity
- Harder onboarding for new developers
- Risk of unintended side effects when making changes

## Recommendations

### 1. Extract Validation Layer
Create `ChatRequestValidator` class:
```php
class ChatRequestValidator {
    public function validateMessage(string $message, array $config): void
    public function validateConversationId(string $conversationId): void
    public function validateFileData($fileData, array $config): void
}
```

### 2. Extract Rate Limiting
Create `ChatRateLimiter` class (consolidate both legacy and tenant-based):
```php
class ChatRateLimiter {
    public function checkRateLimit($identifier, array $config): void
    public function checkQuota($tenantId, string $resourceType): void
}
```

### 3. Extract Agent Configuration
Create `AgentConfigResolver` class:
```php
class AgentConfigResolver {
    public function resolve(?string $agentId): array
    public function mergeWithDefaults(array $agentConfig, array $defaults): array
}
```

### 4. Extract Tool Configuration
Create `ResponsesToolingService` class:
```php
class ResponsesToolingService {
    public function resolveTools(array $config, ?array $requestTools, ?array $agentTools): array
    public function normalizeTools(array $tools): array
    public function mergeTools(array $default, array $override): array
}
```

### 5. Extract Conversation Storage
Create `ConversationRepository` class:
```php
class ConversationRepository {
    public function getHistory(string $conversationId): array
    public function saveHistory(string $conversationId, array $messages): void
}
```

### 6. Extract Streaming Handlers
Create separate handlers for each API type:
```php
class ChatCompletionStreamHandler {
    public function stream(array $payload, callable $callback): void
}

class ResponsesStreamHandler {
    public function stream(array $payload, callable $callback): void
}
```

### 7. Simplified ChatHandler Structure
After refactoring, `ChatHandler` should orchestrate these components:

```php
class ChatHandler {
    private ChatRequestValidator $validator;
    private ChatRateLimiter $rateLimiter;
    private AgentConfigResolver $agentResolver;
    private ConversationRepository $conversationRepo;
    private ChatCompletionHandler $chatHandler;
    private ResponsesHandler $responsesHandler;
    
    public function handleRequest(ChatRequest $request): ChatResponse {
        // High-level orchestration only
        $this->validator->validate($request);
        $this->rateLimiter->check($request->getTenantId());
        $agent = $this->agentResolver->resolve($request->getAgentId());
        
        return $request->isStreamingMode()
            ? $this->streamResponse($request, $agent)
            : $this->syncResponse($request, $agent);
    }
}
```

## Estimated Effort

- **Effort:** 3-5 days
- **Risk:** Medium (requires careful refactoring and testing)

## Testing Requirements

1. Create unit tests for each extracted class
2. Maintain integration tests for end-to-end flows
3. Add regression tests to ensure no behavioral changes
4. Test both Chat Completions and Responses API modes

## Related Issues

- Issue 002: Lack of dependency injection
- Issue 003: Testing infrastructure
- Issue 014: PSR-12 compliance

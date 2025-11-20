# Template Agent

## Overview

This is a template for creating specialized agents in the GPT Chatbot Boilerplate platform. Copy this directory and customize it for your specific use case.

## Quick Start

### 1. Copy the Template

```bash
cp -r agents/_template agents/your-agent-type
cd agents/your-agent-type
mv TemplateAgent.php YourAgentTypeAgent.php
```

### 2. Update the Agent Class

Edit `YourAgentTypeAgent.php`:

- **Class name**: Change `class TemplateAgent` to `class YourAgentTypeAgent`
- **Agent type**: Update `getAgentType()` to return your unique identifier (e.g., `'wordpress'`, `'linkedin'`)
- **Display name**: Update `getDisplayName()` to return a user-friendly name (e.g., `'WordPress Content Manager'`)
- **Description**: Update `getDescription()` with what your agent does
- **Process method**: Implement the `process()` method with your custom logic

### 3. Define Configuration Schema

Edit `config.schema.json`:

- Define required configuration fields
- Add property descriptions and validation rules
- Mark sensitive fields with `"sensitive": true` for encryption support

### 4. Implement Custom Logic

In `YourAgentTypeAgent.php`:

```php
public function process(array $input, array $context): array
{
    // 1. Extract user intent or data
    $userMessage = $this->extractUserMessage($input, -1);

    // 2. Get configuration
    $apiKey = $this->getConfig('api_key', null, $context);
    $endpoint = $this->getConfig('api_endpoint', null, $context);

    // 3. Call external API or perform processing
    $result = $this->callExternalApi($userMessage, $apiKey, $endpoint);

    // 4. Return processed data
    return [
        'messages' => $input,
        'custom_data' => $result
    ];
}
```

### 5. Add Custom Tools (Optional)

If your agent needs custom functions that the LLM can call:

```php
public function getCustomTools(): array
{
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_something',
                'description' => 'Creates something useful',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'string']
                    ],
                    'required' => ['title', 'content']
                ]
            ]
        ]
    ];
}

public function executeCustomTool(string $toolName, array $arguments, array $context): array
{
    switch ($toolName) {
        case 'create_something':
            return $this->handleCreateSomething($arguments, $context);
        default:
            return parent::executeCustomTool($toolName, $arguments, $context);
    }
}
```

### 6. Write Tests

Create test files in the `tests/` directory:

```php
// tests/YourAgentTypeAgentTest.php
require_once __DIR__ . '/../YourAgentTypeAgent.php';

class YourAgentTypeAgentTest extends PHPUnit\Framework\TestCase
{
    public function testProcessReturnsExpectedStructure()
    {
        $agent = new YourAgentTypeAgent();
        $agent->initialize([
            'db' => $this->createMock(DB::class),
            'logger' => $this->createMock(Psr\Log\LoggerInterface::class),
            'config' => []
        ]);

        $result = $agent->process(
            [['role' => 'user', 'content' => 'test message']],
            ['agent_id' => '123', 'specialized_config' => []]
        );

        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('custom_data', $result);
    }
}
```

## Features

### Available Helper Methods

From `AbstractSpecializedAgent`:

- `logInfo($message, $data)` - Log info message
- `logError($message, $data)` - Log error message
- `logWarning($message, $data)` - Log warning message
- `logDebug($message, $data)` - Log debug message
- `getConfig($key, $default, $context)` - Get configuration value
- `extractUserMessage($messages, $index)` - Extract user message content
- `detectIntent($message, $patterns)` - Detect intent from user message

### Configuration Access

```php
// Get configuration value from specialized_agent_configs
$apiKey = $this->getConfig('api_key', null, $context);

// Access from agent config
$systemMessage = $context['agent_config']['system_message'];
$model = $context['agent_config']['model'];
```

### Context Structure

The `$context` array contains:

```php
[
    'conversation_id' => 'conv-123',
    'tenant_id' => 'tenant-456',
    'agent_id' => 'agent-789',
    'agent_type' => 'your-agent-type',
    'message_count' => 5,
    'timestamp' => 1234567890,
    'agent_config' => [...],
    'specialized_config' => [...]
]
```

## Configuration Example

### Database Configuration

When creating/updating an agent via admin API:

```json
{
  "name": "My Custom Agent",
  "agent_type": "your-agent-type",
  "system_message": "You are a helpful assistant.",
  "model": "gpt-4o"
}
```

### Specialized Configuration

Configure agent-specific settings:

```bash
POST /admin-api.php?action=configure_specialized_agent
```

```json
{
  "agent_id": "agent-123",
  "agent_type": "your-agent-type",
  "config": {
    "api_endpoint": "https://api.example.com",
    "api_key": "${MY_API_KEY}",
    "enable_feature_x": true,
    "max_retries": 3
  }
}
```

## Processing Pipeline

Your agent goes through this pipeline:

1. **Build Context** - `buildContext()` - Prepare processing context
2. **Validate Input** - `validateInput()` - Validate incoming messages
3. **Process** - `process()` - Your custom logic
4. **LLM Check** - `requiresLLM()` - Decide if LLM is needed
5. **Prepare LLM** - `prepareLLMMessages()` - Format messages for LLM
6. **Format Output** - `formatOutput()` - Format the response
7. **Validate Output** - `validateOutput()` - Validate response
8. **Cleanup** - `cleanup()` - Release resources

## Best Practices

### 1. Error Handling

Always handle errors gracefully:

```php
public function process(array $input, array $context): array
{
    try {
        $result = $this->callExternalApi();
        return ['messages' => $input, 'result' => $result];
    } catch (Exception $e) {
        $this->logError('API call failed', ['error' => $e->getMessage()]);
        throw new AgentProcessingException('Failed to process request: ' . $e->getMessage());
    }
}
```

### 2. Logging

Use structured logging:

```php
$this->logInfo('Processing user request', [
    'user_intent' => $intent,
    'message_length' => strlen($userMessage)
]);
```

### 3. Configuration

Use environment variables for secrets:

```json
{
  "api_key": "${MY_SECRET_API_KEY}"
}
```

### 4. Validation

Validate all inputs and outputs:

```php
public function validateInput(array $messages, array $context): array
{
    $messages = parent::validateInput($messages, $context);

    $userMessage = $this->extractUserMessage($messages, -1);
    if (strlen($userMessage) > 10000) {
        throw new AgentValidationException('Message too long');
    }

    return $messages;
}
```

### 5. Testing

Test all major code paths:

```php
public function testHandlesError()
{
    $agent = new YourAgent();
    // ... setup mock that throws exception

    $this->expectException(AgentProcessingException::class);
    $agent->process($messages, $context);
}
```

## Troubleshooting

### Agent Not Discovered

- Ensure file name matches class name (e.g., `WordPressAgent.php` → `class WordPressAgent`)
- Verify agent directory is in `/agents/` (not `/agents/_template/`)
- Check that class implements `SpecializedAgentInterface` or extends `AbstractSpecializedAgent`

### Configuration Validation Fails

- Verify `config.schema.json` is valid JSON Schema
- Ensure required fields are provided
- Check data types match schema definitions

### Agent Not Executed

- Verify agent is registered in database: check `agent_type_metadata` table
- Ensure agent_type field in agents table matches your `getAgentType()` return value
- Check logs for discovery/initialization errors

## Resources

- [Main Documentation](../../SPECIALIZED_AGENTS_SPECIFICATION.md)
- [Agent Development Guide](../../docs/agent-development.md)
- [API Reference](../../docs/api.md)

## License

MIT License - See main project LICENSE file

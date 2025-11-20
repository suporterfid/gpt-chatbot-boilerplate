# Specialized Agents

This directory contains specialized agent implementations for the GPT Chatbot Boilerplate platform.

## Overview

Specialized agents extend the base chatbot functionality with domain-specific behaviors, custom tools, and specialized processing logic. Each agent is a self-contained plugin that implements the `SpecializedAgentInterface`.

## Directory Structure

```
agents/
├── README.md                    # This file
├── _template/                   # Template for creating new agents
│   ├── TemplateAgent.php       # Skeleton agent class
│   ├── config.schema.json      # Configuration schema template
│   ├── README.md               # Agent documentation template
│   └── tests/                  # Test template
│
└── {agent-type}/               # Individual agent directories
    ├── {AgentType}Agent.php    # Main agent class
    ├── config.schema.json      # JSON Schema for configuration
    ├── README.md               # Agent-specific documentation
    ├── tools/                  # Optional: Custom tool implementations
    ├── services/               # Optional: Supporting services
    ├── prompts/                # Optional: Prompt templates
    └── tests/                  # Agent-specific tests
```

## Creating a New Agent

### Quick Start

1. **Copy the template:**
   ```bash
   cp -r _template/ your-agent-type/
   cd your-agent-type/
   mv TemplateAgent.php YourAgentTypeAgent.php
   ```

2. **Update the agent class:**
   - Change the class name to `YourAgentTypeAgent`
   - Update `getAgentType()` to return your unique identifier (e.g., `'your-agent-type'`)
   - Update `getDisplayName()` and `getDescription()`
   - Implement the `process()` method with your custom logic

3. **Define configuration schema:**
   - Edit `config.schema.json` to define required configuration fields
   - Mark sensitive fields with `"sensitive": true` for encryption

4. **Write tests:**
   - Create unit tests in the `tests/` directory
   - Test all public methods and error scenarios

5. **Document your agent:**
   - Update `README.md` with usage instructions and examples

### Agent Lifecycle

When a message is processed by a specialized agent:

1. **Discovery** - AgentRegistry scans this directory and registers your agent
2. **Initialization** - Agent receives dependencies (DB, logger, config)
3. **Context Building** - `buildContext()` prepares processing context
4. **Validation** - `validateInput()` validates incoming messages
5. **Processing** - `process()` executes your custom logic
6. **LLM Interaction** - Optional LLM call via `prepareLLMMessages()`
7. **Formatting** - `formatOutput()` formats the response
8. **Validation** - `validateOutput()` ensures output is valid
9. **Cleanup** - `cleanup()` releases resources

## Available Agents

### WordPress Agent
**Type:** `wordpress`
**Description:** Manages WordPress content creation, updates, and queries
**Status:** ✅ Active

[View Documentation](wordpress/README.md)

## Requirements

- PHP 8.0 or higher
- All agents must implement `ChatbotBoilerplate\Interfaces\SpecializedAgentInterface`
- Agents should extend `ChatbotBoilerplate\Agents\AbstractSpecializedAgent` for default implementations

## Best Practices

### Code Organization
- Keep agent class focused on orchestration
- Extract complex logic into separate service classes (in `services/` directory)
- Use dependency injection for all external dependencies

### Error Handling
- Always catch and log exceptions
- Return user-friendly error messages via `handleError()`
- Implement graceful fallbacks for API failures

### Security
- Never store sensitive credentials in code
- Use environment variables for API keys: `${ENV_VAR_NAME}`
- Validate all user inputs before processing
- Implement rate limiting for external API calls

### Testing
- Write unit tests for all public methods
- Mock external dependencies (APIs, databases)
- Test error scenarios and edge cases
- Include integration tests for end-to-end workflows

### Performance
- Cache expensive operations
- Implement timeout limits for external API calls
- Use async processing for slow operations when possible
- Monitor and log performance metrics

## Configuration Management

Agents support multi-layered configuration:

1. **Global Defaults** - Defined in main `config.php`
2. **Agent Type Defaults** - Defined in agent class
3. **Instance Configuration** - Stored in `specialized_agent_configs` table
4. **Runtime Overrides** - Passed via API requests

### Configuration Schema

Use JSON Schema to define your agent's configuration:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["api_key"],
  "properties": {
    "api_key": {
      "type": "string",
      "description": "API authentication key",
      "minLength": 10,
      "sensitive": true
    },
    "endpoint": {
      "type": "string",
      "format": "uri",
      "description": "API endpoint URL"
    }
  }
}
```

## Custom Tools

Agents can define custom tools that the LLM can call:

```php
public function getCustomTools(): array
{
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_post',
                'description' => 'Create a new blog post',
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
    if ($toolName === 'create_post') {
        return $this->createPost($arguments, $context);
    }

    return parent::executeCustomTool($toolName, $arguments, $context);
}
```

## Troubleshooting

### Agent not discovered
- Ensure file name matches class name (e.g., `WordPressAgent.php` → `class WordPressAgent`)
- Verify class implements `SpecializedAgentInterface`
- Check namespace declaration matches expected pattern

### Configuration validation fails
- Validate JSON Schema syntax at https://www.jsonschemavalidator.net/
- Ensure all required fields are provided
- Check data types match schema definitions

### Agent falls back to generic processing
- Check application logs for error messages
- Verify `process()` method doesn't throw exceptions
- Ensure agent is enabled in `agent_type_metadata` table

## Support

For questions or issues:
- Review the specification: `SPECIALIZED_AGENTS_SPECIFICATION.md`
- Check existing agents for examples
- Open an issue on GitHub

## License

MIT License - See main project LICENSE file

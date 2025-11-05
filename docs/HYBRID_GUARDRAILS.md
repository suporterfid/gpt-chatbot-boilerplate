# Hybrid Guardrails Integration Guide

## Overview

The hybrid guardrails implementation allows you to combine multiple configuration sources for structured outputs and guardrails in your AI agents:

1. **OpenAI Stored Prompts** (`prompt_id`): Reference pre-stored prompts in your OpenAI account
2. **Local System Messages** (`system_message`): Define agent behavior instructions stored in your application database
3. **Response Format Guardrails** (`response_format`): Enforce structured outputs using JSON schemas

This hybrid approach provides maximum flexibility while maintaining compatibility with OpenAI's Responses API capabilities.

## Use Cases

### 1. Hybrid Guardrails (Prompt + Schema)

Enforce structured JSON outputs while using local system instructions:

```json
{
  "name": "Bedtime Story Generator",
  "api_type": "responses",
  "model": "gpt-4.1",
  "system_message": "Always respond strictly according to the JSON schema defined in the request. If unsure, output empty strings instead of free text.",
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "bedtime_story",
      "schema": {
        "type": "object",
        "properties": {
          "title": {"type": "string"},
          "story": {"type": "string"},
          "moral": {"type": "string"}
        },
        "required": ["title", "story", "moral"]
      }
    }
  }
}
```

**Example Request:**
```bash
curl -X POST http://localhost/chat-unified.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Create a bedtime story about a unicorn that learns to share.",
    "conversation_id": "test_001",
    "api_type": "responses",
    "agent_id": "agent_bedtime_story"
  }'
```

### 2. File Search with Response Format Constraints

Combine file search capabilities with structured output requirements:

```json
{
  "name": "Research Assistant",
  "api_type": "responses",
  "model": "gpt-4.1",
  "temperature": 0,
  "system_message": "You are a research assistant. You must answer ONLY using verified information from the provided files. If unsure, respond with 'insufficient_data'. Output must follow the JSON schema.",
  "tools": [
    {
      "type": "file_search",
      "vector_store_ids": ["vs_1234567890"],
      "max_num_results": 10
    }
  ],
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "file_search_answer",
      "schema": {
        "type": "object",
        "properties": {
          "answer": {"type": "string"},
          "citations": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "file_name": {"type": "string"},
                "snippet": {"type": "string"}
              },
              "required": ["file_name", "snippet"]
            }
          }
        },
        "required": ["answer", "citations"],
        "additionalProperties": false
      }
    }
  }
}
```

### 3. Hybrid Configuration (All Options Combined)

Use OpenAI stored prompts with local guardrails:

```json
{
  "name": "Advanced Hybrid Agent",
  "api_type": "responses",
  "prompt_id": "pmpt_abc123",
  "prompt_version": "1",
  "system_message": "Additional local instructions that complement the OpenAI prompt.",
  "model": "gpt-4.1",
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "structured_output",
      "schema": {
        "type": "object",
        "properties": {
          "result": {"type": "string"},
          "confidence": {"type": "number"}
        },
        "required": ["result"]
      }
    }
  }
}
```

## Configuration Precedence

The system uses the following precedence order when merging configurations:

**Request > Agent > Config > Defaults**

1. **Request Level**: Parameters passed directly in the API request
2. **Agent Level**: Configuration stored in the agent database record
3. **Config Level**: Values from `config.php` or environment variables
4. **Defaults**: Built-in fallback values

### Example of Precedence

If you have:
- Config: `RESPONSES_RESPONSE_FORMAT='{"type":"json_object"}'`
- Agent: `response_format: {"type":"json_schema", ...}`
- Request: `response_format: {"type":"text"}`

The system will use the **Request** value (`{"type":"text"}`).

## Response Format Types

The `response_format` field supports three types:

### 1. Text (Default)
```json
{
  "type": "text"
}
```

### 2. JSON Object
```json
{
  "type": "json_object"
}
```
Forces the model to return a valid JSON object without enforcing a specific schema.

### 3. JSON Schema
```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "schema_name",
    "schema": {
      "type": "object",
      "properties": {
        "field1": {"type": "string"},
        "field2": {"type": "number"}
      },
      "required": ["field1"],
      "additionalProperties": false
    }
  }
}
```
Enforces a strict JSON schema on the model's output.

## Database Schema

The `response_format` is stored in the `agents` table:

```sql
ALTER TABLE agents ADD COLUMN response_format_json TEXT NULL;
```

The field stores the JSON-encoded response_format configuration.

## API Integration

### Creating an Agent with Response Format

```bash
curl -X POST http://localhost/admin-api.php \
  -H "Content-Type: application/json" \
  -H "X-Admin-Token: your_admin_token" \
  -d '{
    "action": "create_agent",
    "data": {
      "name": "JSON Schema Agent",
      "api_type": "responses",
      "model": "gpt-4.1",
      "response_format": {
        "type": "json_schema",
        "json_schema": {
          "name": "example",
          "schema": {
            "type": "object",
            "properties": {
              "answer": {"type": "string"}
            },
            "required": ["answer"]
          }
        }
      }
    }
  }'
```

### Using Response Format in Chat Requests

You can override the agent's response_format in individual requests:

```bash
curl -X POST http://localhost/chat-unified.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is the capital of France?",
    "conversation_id": "conv_001",
    "api_type": "responses",
    "agent_id": "my_agent_id",
    "response_format": {
      "type": "json_schema",
      "json_schema": {
        "name": "qa_response",
        "schema": {
          "type": "object",
          "properties": {
            "answer": {"type": "string"},
            "source": {"type": "string"}
          },
          "required": ["answer"]
        }
      }
    }
  }'
```

## Environment Configuration

You can set a default response_format in your environment:

```bash
# .env file
RESPONSES_RESPONSE_FORMAT='{"type":"json_schema","json_schema":{"name":"default","schema":{"type":"object","properties":{"result":{"type":"string"}},"required":["result"]}}}'
```

Or in `config.php`:

```php
'responses' => [
    // ... other config
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'default_schema',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'result' => ['type' => 'string']
                ],
                'required' => ['result']
            ]
        ]
    ]
],
```

## Validation

The system validates response_format configurations:

### Type Validation
Only valid types are accepted: `text`, `json_object`, `json_schema`

### JSON Schema Validation
When using `type: json_schema`, the following fields are required:
- `json_schema.name`: A unique name for the schema
- `json_schema.schema`: The actual JSON schema definition

### Error Handling

Invalid configurations will return validation errors:

```json
{
  "error": "response_format type must be one of: text, json_object, json_schema",
  "code": 400
}
```

## Best Practices

1. **Schema Design**: Keep schemas simple and focused on the essential output structure
2. **Required Fields**: Only mark fields as required if they're truly essential
3. **Validation**: Test your schemas with various inputs to ensure they work as expected
4. **Performance**: Complex schemas may increase processing time slightly
5. **Error Messages**: Design schemas that allow the model to communicate errors within the structure

## Example Schemas

### Simple Q&A Format
```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "qa_format",
    "schema": {
      "type": "object",
      "properties": {
        "question": {"type": "string"},
        "answer": {"type": "string"},
        "confidence": {"type": "number", "minimum": 0, "maximum": 1}
      },
      "required": ["answer"]
    }
  }
}
```

### Categorized Response
```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "categorized_response",
    "schema": {
      "type": "object",
      "properties": {
        "category": {
          "type": "string",
          "enum": ["general", "technical", "support"]
        },
        "response": {"type": "string"},
        "suggested_actions": {
          "type": "array",
          "items": {"type": "string"}
        }
      },
      "required": ["category", "response"]
    }
  }
}
```

### Data Extraction Format
```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "data_extraction",
    "schema": {
      "type": "object",
      "properties": {
        "entities": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "type": {"type": "string"},
              "value": {"type": "string"},
              "confidence": {"type": "number"}
            },
            "required": ["type", "value"]
          }
        },
        "summary": {"type": "string"}
      },
      "required": ["entities"]
    }
  }
}
```

## Migration

To add response_format support to an existing installation:

```bash
# Run migrations to add the response_format_json column
php scripts/run_migrations.php
```

The migration script will automatically apply the schema changes.

## Testing

Run the comprehensive test suite:

```bash
php tests/test_hybrid_guardrails.php
```

This will verify:
- Agent creation with response_format
- Validation of response_format types
- Hybrid configurations (prompt_id + system_message + response_format)
- Response_format retrieval and deserialization
- File search + response_format combinations

## Troubleshooting

### Response Format Not Applied

**Check precedence**: Ensure your response_format isn't being overridden at a higher precedence level.

```bash
# Check agent configuration
curl http://localhost/admin-api.php?action=get_agent&id=YOUR_AGENT_ID \
  -H "X-Admin-Token: your_token"
```

### Invalid Schema Errors

**Validate your JSON schema**: Use a JSON schema validator before deployment.

```bash
# Test with a simple schema first
{
  "type": "json_object"
}
```

### Model Doesn't Follow Schema

**Check model compatibility**: Only use json_schema with supported models (gpt-4.1, gpt-4o, etc.).

**Strengthen system message**: Add explicit instructions about following the schema.

## Compatibility

- **Minimum PHP Version**: 8.0+
- **Supported Models**: gpt-4.1, gpt-4o, gpt-4o-mini (json_schema support)
- **OpenAI API**: Responses API required for response_format
- **Database**: SQLite 3.0+ or MySQL 5.7+

## Further Reading

- [OpenAI Responses API Documentation](https://platform.openai.com/docs/api-reference/responses)
- [JSON Schema Specification](https://json-schema.org/)
- [Agent Management Guide](./PHASE1_DB_AGENT.md)
- [Admin API Reference](./api.md)

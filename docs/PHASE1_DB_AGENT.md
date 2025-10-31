# Phase 1: Database Layer & Agent Model

## Overview

Phase 1 introduces a persistent database layer and an Agent model to the GPT Chatbot Boilerplate. This enables dynamic configuration of AI agents through a protected Admin API, laying the foundation for the Admin UI in future phases.

## Architecture

### Components

1. **Database Layer (`includes/DB.php`)**: Lightweight PDO wrapper supporting SQLite (with optional MySQL support)
2. **Agent Model (`db/migrations/001_create_agents.sql`)**: Schema for storing agent configurations
3. **Agent Service (`includes/AgentService.php`)**: Business logic for CRUD operations on agents
4. **Admin API (`admin-api.php`)**: Token-protected HTTP endpoint for managing agents
5. **Chat Integration**: Updated `chat-unified.php` and `ChatHandler.php` to support agent-based configuration

### Database Schema

The `agents` table stores all agent configurations:

```sql
CREATE TABLE agents (
    id TEXT PRIMARY KEY,                    -- UUID v4
    name TEXT NOT NULL UNIQUE,              -- Agent display name
    description TEXT NULL,                  -- Optional description
    api_type TEXT NOT NULL DEFAULT 'responses' CHECK(api_type IN ('responses','chat')),
    prompt_id TEXT NULL,                    -- OpenAI Prompt ID
    prompt_version TEXT NULL,               -- Prompt version
    system_message TEXT NULL,               -- System message (fallback)
    model TEXT NULL,                        -- Override model
    temperature REAL NULL,                  -- Override temperature (0-2)
    top_p REAL NULL,                        -- Override top_p (0-1)
    max_output_tokens INTEGER NULL,         -- Override max tokens
    tools_json TEXT NULL,                   -- JSON array of tools
    vector_store_ids_json TEXT NULL,        -- JSON array of vector store IDs
    max_num_results INTEGER NULL,           -- File search max results (1-200)
    is_default INTEGER NOT NULL DEFAULT 0,  -- Boolean flag for default agent
    created_at TEXT NOT NULL,               -- ISO 8601 timestamp
    updated_at TEXT NOT NULL                -- ISO 8601 timestamp
);

-- Indexes for performance
CREATE INDEX idx_agents_name ON agents(name);
CREATE INDEX idx_agents_is_default ON agents(is_default);
CREATE INDEX idx_agents_created_at ON agents(created_at);
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```bash
# Admin API Configuration
ADMIN_ENABLED=true
ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
DATABASE_URL=
DATABASE_PATH=./data/chatbot.db
```

- **ADMIN_ENABLED**: Enable/disable admin features (default: `false`)
- **ADMIN_TOKEN**: Bearer token for authenticating admin API requests (required when enabled)
- **DATABASE_URL**: Optional MySQL connection string (e.g., `mysql://user:pass@host/db`)
- **DATABASE_PATH**: Path to SQLite database file (default: `./data/chatbot.db`)

### Security Notes

- **ADMIN_TOKEN** must be at least 32 characters for production use
- Store the token securely and do not commit it to version control
- The Admin API rejects any request without a valid `Authorization: Bearer <token>` header

## Running Migrations

Migrations are automatically run when the Admin API or chat endpoint is first accessed (if admin is enabled). To run manually:

```php
<?php
require_once 'includes/DB.php';

$db = new DB([
    'database_path' => './data/chatbot.db'
]);

$count = $db->runMigrations(__DIR__ . '/db/migrations');
echo "Executed $count migrations\n";
```

## Admin API Endpoints

Base URL: `/admin-api.php`

All endpoints require the `Authorization: Bearer <ADMIN_TOKEN>` header.

### List Agents

```bash
GET /admin-api.php?action=list_agents
```

Optional query parameters:
- `name`: Filter by name (partial match)
- `api_type`: Filter by API type (`responses` or `chat`)

Response:
```json
{
  "data": [
    {
      "id": "uuid-string",
      "name": "Customer Support Agent",
      "description": "Handles customer inquiries",
      "api_type": "responses",
      "prompt_id": "pmpt_abc123",
      "prompt_version": "1",
      "model": "gpt-4o",
      "temperature": 0.7,
      "tools": [{"type": "file_search"}],
      "vector_store_ids": ["vs_1", "vs_2"],
      "is_default": true,
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

### Get Agent

```bash
GET /admin-api.php?action=get_agent&id=<agent_id>
```

Response: Single agent object

### Create Agent

```bash
POST /admin-api.php?action=create_agent
Content-Type: application/json
Authorization: Bearer <ADMIN_TOKEN>

{
  "name": "Customer Support Agent",
  "description": "Handles customer inquiries",
  "api_type": "responses",
  "prompt_id": "pmpt_abc123",
  "prompt_version": "1",
  "vector_store_ids": ["vs_1"],
  "tools": [{"type": "file_search"}],
  "model": "gpt-4o",
  "temperature": 0.7,
  "is_default": true
}
```

Required fields:
- `name`: Unique agent name

Optional fields:
- `description`, `api_type`, `prompt_id`, `prompt_version`, `system_message`
- `model`, `temperature`, `top_p`, `max_output_tokens`
- `tools`, `vector_store_ids`, `max_num_results`, `is_default`

Response: Created agent object with generated `id`

### Update Agent

```bash
POST /admin-api.php?action=update_agent&id=<agent_id>
Content-Type: application/json
Authorization: Bearer <ADMIN_TOKEN>

{
  "description": "Updated description",
  "temperature": 0.9
}
```

Only include fields to update. Response: Updated agent object

### Delete Agent

```bash
POST /admin-api.php?action=delete_agent&id=<agent_id>
Authorization: Bearer <ADMIN_TOKEN>
```

Response:
```json
{
  "data": {
    "success": true,
    "message": "Agent deleted"
  }
}
```

### Make Default

```bash
POST /admin-api.php?action=make_default&id=<agent_id>
Authorization: Bearer <ADMIN_TOKEN>
```

Atomically unsets all previous defaults and sets this agent as default.

Response:
```json
{
  "data": {
    "success": true,
    "message": "Default agent set"
  }
}
```

## Using Agents in Chat

### Via agent_id Parameter

Include `agent_id` in your chat request:

```bash
POST /chat-unified.php
Content-Type: application/json

{
  "message": "Help me with my order",
  "conversation_id": "conv_123",
  "agent_id": "uuid-of-agent",
  "stream": false
}
```

### Configuration Precedence

When an agent is specified, configuration merging follows this precedence:

**Request Parameters > Agent Config > config.php Defaults**

Example:
- Agent specifies `model: "gpt-4o"`
- Request specifies `prompt_id: "pmpt_xyz"`
- Final payload uses `gpt-4o` from agent and `pmpt_xyz` from request

### Agent Configuration Mapping

Agent fields map to Responses API payload as follows:

| Agent Field | Applied To |
|-------------|-----------|
| `prompt_id` + `prompt_version` | `payload['prompt']` |
| `model` | `payload['model']` |
| `temperature` | `payload['temperature']` |
| `top_p` | `payload['top_p']` |
| `max_output_tokens` | `payload['max_output_tokens']` |
| `tools` | Merged with config/request tools |
| `vector_store_ids` | Applied to `file_search` tool defaults |
| `max_num_results` | Applied to `file_search` tool |
| `system_message` | Added to conversation history (chat mode) |

### Fallback Behavior

- If `agent_id` is invalid or not found, the request proceeds without agent overrides
- If agent's `prompt_id` is invalid, the system retries without the prompt
- If agent's `model` is unsupported, the system falls back to `gpt-4o-mini`

## Testing

Run the test suite:

```bash
php tests/run_tests.php
```

Tests cover:
- Database connection and migration
- Agent CRUD operations
- Default agent atomicity
- Input validation
- JSON field serialization

## Example Workflows

### Create a Customer Support Agent

```bash
BASE_URL="http://localhost"
ADMIN_TOKEN="your_token_here"

curl -X POST "$BASE_URL/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support",
    "api_type": "responses",
    "prompt_id": "pmpt_support123",
    "vector_store_ids": ["vs_kb001"],
    "tools": [{"type": "file_search"}],
    "model": "gpt-4o",
    "temperature": 0.5,
    "is_default": true
  }'
```

### List All Agents

```bash
curl -X GET "$BASE_URL/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### Use Agent in Chat

```bash
curl -X POST "$BASE_URL/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "conversation_id": "conv_001",
    "agent_id": "uuid-from-create-response",
    "stream": false
  }'
```

## Limitations & Future Work

### Phase 1 Limitations

- Single admin token (no user accounts)
- No UI (command-line/API only)
- No audit logging
- No OpenAI Admin API integration (for Prompts/Vector Stores management)

### Planned for Future Phases

- Phase 2: OpenAI Admin Client for managing Prompts and Vector Stores
- Phase 3: Complete Admin API with health checks
- Phase 4: Web-based Admin UI
- Phase 5: Widget integration for agent selection

## Troubleshooting

### Database Connection Errors

If migrations fail to run:

```bash
# Check database file permissions
ls -la data/chatbot.db

# Ensure data directory exists and is writable
mkdir -p data
chmod 755 data
```

### Admin API 403 Errors

Verify your token:

```bash
# Check token is set in .env
grep ADMIN_TOKEN .env

# Ensure Authorization header is correct
curl -v -H "Authorization: Bearer wrong_token" ...
# Should return: {"error": {"message": "Invalid admin token", ...}}
```

### Agent Not Found Errors

Check that agent exists:

```bash
curl -X GET "$BASE_URL/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

## Migration from config.php

To migrate existing configuration to an agent:

```php
// Create a default agent from current config
$defaultAgent = [
    'name' => 'Default Agent',
    'api_type' => 'responses',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'is_default' => true
];

// POST to create_agent endpoint
```

Once created, chat requests without `agent_id` will use the default agent if one exists.

## Next Steps

1. Enable admin in `.env`: Set `ADMIN_ENABLED=true` and generate a secure `ADMIN_TOKEN`
2. Run migrations (automatic on first request)
3. Create your first agent via Admin API
4. Test agent in chat requests
5. Proceed to Phase 2 for OpenAI Admin Client integration

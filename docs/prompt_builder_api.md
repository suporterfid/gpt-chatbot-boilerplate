# Prompt Builder API Documentation

## Overview

The Prompt Builder API provides programmatic access to generate, manage, and activate AI agent specifications. All endpoints require admin authentication.

## Base URL

```
https://your-domain.com/admin-api.php
```

## Authentication

All requests must include an admin token in the Authorization header:

```http
Authorization: Bearer YOUR_ADMIN_TOKEN
```

Or as a custom header:

```http
X-Admin-Token: YOUR_ADMIN_TOKEN
```

## Endpoints

### Generate Prompt Specification

Generate a new prompt specification from an agent idea.

**Endpoint**: `POST /admin-api.php?action=prompt_builder_generate&agent_id={agent_id}`

**Request Body**:
```json
{
  "idea_text": "A customer support agent that helps users...",
  "guardrails": ["hallucination_prevention", "scope_restriction", "data_privacy"],
  "language": "en",
  "variables": {
    "brand_name": "Acme Corp",
    "support_email": "support@acme.com"
  }
}
```

**Parameters**:
- `idea_text` (required): Brief description of the agent (10-2000 characters)
- `guardrails` (optional): Array of guardrail keys to apply. If omitted, default guardrails are used.
- `language` (optional): Language code (`en`, `pt`, `es`, `fr`, `de`). Default: `en`
- `variables` (optional): Key-value pairs for template interpolation

**Response**:
```json
{
  "success": true,
  "data": {
    "version": 1,
    "prompt_md": "# Agent Specification\n\n## 1. Role\n...",
    "applied_guardrails": [
      "hallucination_prevention",
      "scope_restriction",
      "data_privacy"
    ],
    "usage": {
      "prompt_tokens": 450,
      "completion_tokens": 890,
      "total_tokens": 1340
    },
    "latency_ms": 3250
  }
}
```

**Error Responses**:
- `400 Bad Request`: Invalid input (idea too short/long, invalid guardrails)
- `404 Not Found`: Agent not found
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Generation failed

**Example**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=prompt_builder_generate&agent_id=abc123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "idea_text": "A sales qualification bot that scores leads",
    "guardrails": ["hallucination_prevention", "scope_restriction"],
    "language": "en"
  }'
```

---

### List Prompt Versions

List all saved versions for an agent.

**Endpoint**: `GET /admin-api.php?action=prompt_builder_list&agent_id={agent_id}`

**Response**:
```json
{
  "success": true,
  "data": {
    "versions": [
      {
        "id": "uuid-123",
        "version": 3,
        "created_by": "admin@example.com",
        "created_at": "2024-11-06T14:23:15Z",
        "updated_at": "2024-11-06T14:23:15Z",
        "guardrails": [
          {
            "key": "hallucination_prevention",
            "title": "Hallucination Prevention",
            "mandatory": true
          }
        ]
      }
    ],
    "active_version": 3
  }
}
```

**Example**:
```bash
curl "https://your-domain.com/admin-api.php?action=prompt_builder_list&agent_id=abc123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Get Specific Version

Retrieve a specific prompt version with full content.

**Endpoint**: `GET /admin-api.php?action=prompt_builder_get&agent_id={agent_id}&version={version}`

**Response**:
```json
{
  "success": true,
  "data": {
    "id": "uuid-123",
    "version": 2,
    "prompt_md": "# Agent Specification\n\n## 1. Role\n...",
    "guardrails": [
      {
        "key": "hallucination_prevention",
        "title": "Hallucination Prevention",
        "mandatory": true
      }
    ],
    "created_by": "admin@example.com",
    "created_at": "2024-11-06T13:10:22Z",
    "updated_at": "2024-11-06T13:10:22Z"
  }
}
```

**Example**:
```bash
curl "https://your-domain.com/admin-api.php?action=prompt_builder_get&agent_id=abc123&version=2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Activate Version

Activate a specific prompt version for an agent.

**Endpoint**: `POST /admin-api.php?action=prompt_builder_activate&agent_id={agent_id}&version={version}`

**Response**:
```json
{
  "success": true,
  "message": "Version 2 activated for agent abc123"
}
```

**Example**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=prompt_builder_activate&agent_id=abc123&version=2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Deactivate Prompt

Deactivate the current active prompt (agent falls back to system_message).

**Endpoint**: `POST /admin-api.php?action=prompt_builder_deactivate&agent_id={agent_id}`

**Response**:
```json
{
  "success": true,
  "message": "Prompt deactivated for agent abc123"
}
```

**Example**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=prompt_builder_deactivate&agent_id=abc123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Save Manual Prompt

Save a manually edited prompt as a new version.

**Endpoint**: `POST /admin-api.php?action=prompt_builder_save_manual&agent_id={agent_id}`

**Request Body**:
```json
{
  "prompt_md": "# My Custom Specification\n\n...",
  "guardrails": [
    {"key": "hallucination_prevention"},
    {"key": "scope_restriction"}
  ]
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "version": 4,
    "prompt_md": "# My Custom Specification\n\n..."
  }
}
```

**Example**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=prompt_builder_save_manual&agent_id=abc123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "prompt_md": "# My Custom Agent\n\n## Role\nCustom role...",
    "guardrails": [{"key": "hallucination_prevention"}]
  }'
```

---

### Delete Version

Delete a specific prompt version (cannot delete active version).

**Endpoint**: `POST /admin-api.php?action=prompt_builder_delete&agent_id={agent_id}&version={version}`

or

**Endpoint**: `DELETE /admin-api.php?action=prompt_builder_delete&agent_id={agent_id}&version={version}`

**Response**:
```json
{
  "success": true,
  "message": "Version 2 deleted"
}
```

**Error**:
```json
{
  "success": false,
  "error": {
    "message": "Cannot delete active version. Deactivate it first.",
    "code": 400
  }
}
```

**Example**:
```bash
curl -X DELETE "https://your-domain.com/admin-api.php?action=prompt_builder_delete&agent_id=abc123&version=2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Get Guardrails Catalog

Retrieve all available guardrail templates.

**Endpoint**: `GET /admin-api.php?action=prompt_builder_catalog`

**Response**:
```json
{
  "success": true,
  "data": {
    "guardrails": [
      {
        "key": "hallucination_prevention",
        "title": "Hallucination Prevention",
        "description": "Prevents the AI from generating false or unverifiable information",
        "mandatory": true,
        "priority": 1
      },
      {
        "key": "scope_restriction",
        "title": "Scope Restriction",
        "description": "Ensures the AI stays within its assigned role and domain",
        "mandatory": true,
        "priority": 2
      },
      {
        "key": "data_privacy",
        "title": "Data Privacy",
        "description": "Protects user privacy and prevents handling of sensitive data",
        "mandatory": false,
        "priority": 3
      }
    ]
  }
}
```

**Example**:
```bash
curl "https://your-domain.com/admin-api.php?action=prompt_builder_catalog" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Rate Limits

- **Default**: 10 requests per minute per admin user
- **Configurable**: Set `PROMPT_BUILDER_RATE_LIMIT` in `.env`
- **Response**: `429 Too Many Requests` when exceeded

## Error Handling

All errors follow this format:

```json
{
  "success": false,
  "error": {
    "message": "Descriptive error message",
    "code": 400
  }
}
```

**Common Error Codes**:
- `400`: Bad Request (invalid input)
- `401`: Unauthorized (missing/invalid token)
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found (agent/version doesn't exist)
- `429`: Too Many Requests (rate limit)
- `500`: Internal Server Error

## RBAC Permissions

| Action | Required Permission |
|--------|-------------------|
| Generate | `create` |
| List Versions | `read` |
| Get Version | `read` |
| Activate | `update` |
| Deactivate | `update` |
| Save Manual | `create` |
| Delete | `delete` |
| Get Catalog | `read` |

## Audit Logging

All Prompt Builder actions are logged when `audit_enabled` is true:

**Events**:
- `prompt_builder.generated` - New specification generated
- `prompt_builder.activated` - Version activated
- `prompt_builder.deactivated` - Prompt deactivated
- `prompt_builder.manual_save` - Manual edit saved
- `prompt_builder.deleted` - Version deleted

**Logged Metadata**:
- Agent ID
- Version number
- User ID/email
- IP address
- Timestamp
- Guardrails applied
- Generation latency and token usage

Query audit logs via the Admin UI or database:

```sql
SELECT * FROM audit_events 
WHERE event_type LIKE 'prompt_builder.%' 
ORDER BY created_at DESC;
```

## Integration Example

Complete workflow using the API:

```javascript
const API_BASE = 'https://your-domain.com/admin-api.php';
const TOKEN = 'your-admin-token';

async function buildAndActivatePrompt(agentId, idea) {
  // 1. Generate specification
  const generateRes = await fetch(
    `${API_BASE}?action=prompt_builder_generate&agent_id=${agentId}`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${TOKEN}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        idea_text: idea,
        guardrails: ['hallucination_prevention', 'scope_restriction'],
        language: 'en'
      })
    }
  );
  
  const generated = await generateRes.json();
  const version = generated.data.version;
  
  console.log(`Generated version ${version}`);
  console.log(generated.data.prompt_md);
  
  // 2. Activate the version
  const activateRes = await fetch(
    `${API_BASE}?action=prompt_builder_activate&agent_id=${agentId}&version=${version}`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${TOKEN}`
      }
    }
  );
  
  const activated = await activateRes.json();
  console.log(activated.message);
  
  return version;
}

// Usage
buildAndActivatePrompt('abc123', 'A customer support agent...')
  .then(v => console.log(`Active version: ${v}`));
```

## Best Practices

1. **Validate Input**: Always validate `idea_text` length on the client side
2. **Handle Rate Limits**: Implement exponential backoff for retries
3. **Cache Catalog**: Fetch guardrails catalog once and cache it
4. **Version Control**: List versions before activating to confirm existence
5. **Error Handling**: Always check `success` field before accessing `data`

## Changelog

### v1.0.0 (2024-11-06)
- Initial release
- Generate, list, get, activate, deactivate, save, delete endpoints
- Guardrails catalog endpoint
- RBAC and audit integration

---

**See Also**:
- [Prompt Builder Overview](prompt_builder_overview.md)
- [Guardrails Reference](prompt_builder_guardrails.md)

# Admin API Documentation

**Version:** 1.0.0
**Last Updated:** January 2025

This document provides comprehensive documentation for the Admin API endpoints used to manage the GPT Chatbot platform.

## Table of Contents

- [Authentication](#authentication)
- [Base URL and Headers](#base-url-and-headers)
- [Error Handling](#error-handling)
- [User Management](#user-management)
- [Agent Management](#agent-management)
- [Whitelabel Publishing](#whitelabel-publishing)
- [Channel Management](#channel-management)
- [Prompt Management](#prompt-management)
- [Vector Store Management](#vector-store-management)
- [File Management](#file-management)
- [Model Management](#model-management)
- [Multi-Tenancy](#multi-tenancy)
- [Usage Tracking & Quotas](#usage-tracking--quotas)
- [Billing & Subscriptions](#billing--subscriptions)
- [Consent Management](#consent-management)
- [LeadSense CRM](#leadsense-crm)
- [Prompt Builder](#prompt-builder)
- [Audit & Observability](#audit--observability)
- [Job Queue Management](#job-queue-management)
- [Resource Authorization](#resource-authorization)
- [Webhook Management](#webhook-management)
- [WordPress Blog Management](#wordpress-blog-management)
- [Agent Types & Discovery](#agent-types--discovery)
- [Health & Status](#health--status)

## Authentication

The Admin API supports three authentication methods:

### 1. Session-Based Authentication (Recommended)

Use email/password credentials to establish a secure session. Sessions are stored server-side with configurable TTL (default: 24 hours).

```bash
# Login and receive session cookie
curl -i -c cookies.txt -X POST "https://your-domain.com/admin-api.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "your-secure-password"
  }'

# Use session cookie for subsequent requests
curl -b cookies.txt "https://your-domain.com/admin-api.php?action=current_user"

# Logout
curl -b cookies.txt -X POST "https://your-domain.com/admin-api.php?action=logout"
```

### 2. API Key Authentication

Use API keys for headless integrations and programmatic access.

```bash
# Generate API key (requires existing session or admin token)
curl -X POST "https://your-domain.com/admin-api.php?action=generate_api_key" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key_name": "CI/CD Integration",
    "expires_at": "2025-12-31T23:59:59Z"
  }'

# Use API key
curl "https://your-domain.com/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer your-api-key-here"
```

### 3. Legacy Admin Token (Deprecated)

Single-token authentication via `ADMIN_TOKEN` environment variable. Being phased out in favor of API keys.

```bash
curl "https://your-domain.com/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

## Base URL and Headers

**Base URL:** `https://your-domain.com/admin-api.php`

**Required Headers:**
```
Content-Type: application/json
Authorization: Bearer {token|api_key}
```

**Optional Headers:**
```
X-Tenant-ID: {tenant_id}          # For multi-tenant operations
X-Correlation-ID: {request_id}    # For request tracing
```

## Error Handling

### Standard Error Response

```json
{
  "status": "error",
  "message": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {},
  "timestamp": "2025-01-20T10:30:00Z",
  "request_id": "req_abc123"
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (invalid/missing credentials) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 409 | Conflict (duplicate resource) |
| 429 | Too Many Requests (rate limited) |
| 500 | Internal Server Error |
| 503 | Service Unavailable |

### Common Error Codes

| Code | Description |
|------|-------------|
| `UNAUTHORIZED` | Authentication required |
| `FORBIDDEN` | Insufficient permissions |
| `VALIDATION_ERROR` | Invalid input parameters |
| `NOT_FOUND` | Resource not found |
| `DUPLICATE_RESOURCE` | Resource already exists |
| `RATE_LIMIT_EXCEEDED` | Too many requests |
| `QUOTA_EXCEEDED` | Usage quota exceeded |
| `TENANT_SUSPENDED` | Tenant account suspended |

## User Management

### Login

Authenticate with email/password and receive session cookie.

**Endpoint:** `POST /admin-api.php?action=login`

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "your-password"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "user": {
      "id": 1,
      "email": "admin@example.com",
      "name": "Admin User",
      "role": "super-admin",
      "tenant_id": null,
      "created_at": "2024-01-15T10:00:00Z"
    },
    "session": {
      "expires_at": "2025-01-21T10:00:00Z"
    }
  }
}
```

**Rate Limit:** 5 attempts per 5 minutes per IP

---

### Logout

Terminate current session.

**Endpoint:** `POST /admin-api.php?action=logout`

**Response:**
```json
{
  "status": "success",
  "message": "Logged out successfully"
}
```

---

### Get Current User

Retrieve authenticated user information.

**Endpoint:** `GET /admin-api.php?action=current_user`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "admin@example.com",
    "name": "Admin User",
    "role": "super-admin",
    "tenant_id": null,
    "permissions": ["*"],
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

---

### List Users

Retrieve all users (super-admins only).

**Endpoint:** `GET /admin-api.php?action=list_users`

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 20, max: 100)
- `role` (string): Filter by role (super-admin, admin, user)
- `tenant_id` (string): Filter by tenant

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "email": "admin@example.com",
      "name": "Admin User",
      "role": "super-admin",
      "tenant_id": null,
      "is_active": true,
      "last_login_at": "2025-01-20T09:00:00Z",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "total_pages": 1
  }
}
```

---

### Create User

Create a new admin user.

**Endpoint:** `POST /admin-api.php?action=create_user`

**Request:**
```json
{
  "email": "newadmin@example.com",
  "name": "New Admin",
  "password": "secure-password",
  "role": "admin",
  "tenant_id": "tenant_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 2,
    "email": "newadmin@example.com",
    "name": "New Admin",
    "role": "admin",
    "tenant_id": "tenant_abc123",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update User Role

Change a user's role.

**Endpoint:** `POST /admin-api.php?action=update_user_role`

**Request:**
```json
{
  "user_id": 2,
  "role": "super-admin"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "User role updated successfully"
}
```

---

### Deactivate User

Deactivate a user account.

**Endpoint:** `POST /admin-api.php?action=deactivate_user`

**Request:**
```json
{
  "user_id": 2
}
```

**Response:**
```json
{
  "status": "success",
  "message": "User deactivated successfully"
}
```

---

### Generate API Key

Create a new API key for programmatic access.

**Endpoint:** `POST /admin-api.php?action=generate_api_key`

**Request:**
```json
{
  "key_name": "CI/CD Integration",
  "expires_at": "2025-12-31T23:59:59Z"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "key_abc123",
    "key": "sk_live_1234567890abcdef",
    "key_name": "CI/CD Integration",
    "created_at": "2025-01-20T10:30:00Z",
    "expires_at": "2025-12-31T23:59:59Z"
  },
  "warning": "Store this key securely. It will not be shown again."
}
```

---

### List API Keys

Retrieve all API keys for current user.

**Endpoint:** `GET /admin-api.php?action=list_api_keys`

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "key_abc123",
      "key_name": "CI/CD Integration",
      "key_prefix": "sk_live_1234",
      "created_at": "2025-01-20T10:30:00Z",
      "expires_at": "2025-12-31T23:59:59Z",
      "last_used_at": "2025-01-20T11:00:00Z"
    }
  ]
}
```

---

### Revoke API Key

Revoke an API key.

**Endpoint:** `POST /admin-api.php?action=revoke_api_key`

**Request:**
```json
{
  "key_id": "key_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "API key revoked successfully"
}
```

---

## Agent Management

### List Agents

Retrieve all agents accessible to the authenticated user.

**Endpoint:** `GET /admin-api.php?action=list_agents`

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 20, max: 100)
- `tenant_id` (string): Filter by tenant
- `is_default` (boolean): Filter default agents

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "agent_abc123",
      "name": "Customer Support Bot",
      "slug": "customer-support",
      "description": "Handles customer inquiries and support tickets",
      "api_type": "responses",
      "model": "gpt-4",
      "temperature": 0.7,
      "is_default": true,
      "tenant_id": "tenant_xyz789",
      "whitelabel_enabled": true,
      "agent_public_id": "pub_agent_abc123",
      "created_at": "2024-01-15T10:00:00Z",
      "updated_at": "2025-01-20T09:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

### Get Agent

Retrieve detailed information about a specific agent.

**Endpoint:** `GET /admin-api.php?action=get_agent&agent_id={agent_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "agent_abc123",
    "name": "Customer Support Bot",
    "slug": "customer-support",
    "description": "Handles customer inquiries and support tickets",
    "api_type": "responses",
    "prompt_id": "prompt_def456",
    "prompt_version": "v2",
    "model": "gpt-4",
    "temperature": 0.7,
    "top_p": 1.0,
    "max_output_tokens": 2048,
    "system_message": "You are a helpful customer support assistant.",
    "tools": [
      {
        "type": "file_search"
      }
    ],
    "vector_store_ids": ["vs_xyz789"],
    "max_num_results": 5,
    "response_format": null,
    "is_default": true,
    "tenant_id": "tenant_xyz789",
    "whitelabel_enabled": true,
    "agent_public_id": "pub_agent_abc123",
    "vanity_path": "support",
    "custom_domain": "help.example.com",
    "wl_require_signed_requests": true,
    "wl_token_ttl_seconds": 3600,
    "allowed_origins": ["https://example.com"],
    "ui_config": {
      "title": "Customer Support",
      "logo_url": "https://example.com/logo.png",
      "primary_color": "#007bff",
      "welcome_message": "How can we help you today?"
    },
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2025-01-20T09:00:00Z"
  }
}
```

---

### Create Agent

Create a new agent.

**Endpoint:** `POST /admin-api.php?action=create_agent`

**Request:**
```json
{
  "name": "Sales Assistant",
  "slug": "sales-assistant",
  "description": "Assists with sales inquiries and product information",
  "api_type": "responses",
  "model": "gpt-4",
  "temperature": 0.7,
  "top_p": 1.0,
  "max_output_tokens": 2048,
  "prompt_id": "prompt_sales123",
  "tools": [
    {
      "type": "file_search"
    }
  ],
  "vector_store_ids": ["vs_products456"],
  "max_num_results": 10,
  "system_message": "You are a knowledgeable sales assistant."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "agent_new456",
    "name": "Sales Assistant",
    "slug": "sales-assistant",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

**Validation Rules:**
- `name`: Required, 1-255 characters, unique per tenant
- `slug`: Optional, lowercase letters/numbers/hyphens, 1-64 characters, unique per tenant
- `api_type`: Must be "chat" or "responses"
- `model`: Must be valid OpenAI model
- `temperature`: 0.0 - 2.0
- `top_p`: 0.0 - 1.0
- `max_output_tokens`: 1 - 16000 (model dependent)

---

### Update Agent

Update an existing agent.

**Endpoint:** `POST /admin-api.php?action=update_agent`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "name": "Updated Customer Support Bot",
  "temperature": 0.8,
  "model": "gpt-4-turbo"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Agent updated successfully"
}
```

---

### Delete Agent

Delete an agent.

**Endpoint:** `POST /admin-api.php?action=delete_agent`

**Request:**
```json
{
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Agent deleted successfully"
}
```

**Note:** Cannot delete default agent. Set another agent as default first.

---

### Make Default Agent

Set an agent as the default for the tenant.

**Endpoint:** `POST /admin-api.php?action=make_default`

**Request:**
```json
{
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Agent set as default successfully"
}
```

---

### Test Agent

Test an agent configuration with a sample message.

**Endpoint:** `POST /admin-api.php?action=test_agent`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "message": "Hello, can you help me with my order?",
  "conversation_id": "test_conv_123"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "response": "Of course! I'd be happy to help you with your order. Could you please provide your order number?",
    "conversation_id": "test_conv_123",
    "tokens_used": 28,
    "model": "gpt-4",
    "latency_ms": 1234
  }
}
```

---

## Whitelabel Publishing

Whitelabel publishing allows agents to be embedded on external websites with custom branding and optional HMAC authentication.

### Enable Whitelabel

Enable whitelabel mode for an agent.

**Endpoint:** `POST /admin-api.php?action=enable_whitelabel`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "vanity_path": "support",
  "custom_domain": "help.example.com",
  "require_signed_requests": true,
  "token_ttl_seconds": 3600,
  "allowed_origins": ["https://example.com", "https://www.example.com"],
  "enable_file_upload": true,
  "ui_config": {
    "title": "Customer Support",
    "logo_url": "https://example.com/logo.png",
    "primary_color": "#007bff",
    "welcome_message": "How can we help you today?",
    "placeholder": "Type your message...",
    "disclaimer": "By using this chat, you agree to our Terms of Service.",
    "show_footer_branding": false
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "agent_public_id": "pub_agent_abc123",
    "wl_hmac_secret": "secret_xyz789",
    "vanity_url": "https://your-domain.com/a/support",
    "custom_domain_url": "https://help.example.com",
    "embed_code": "<script src=\"https://your-domain.com/chatbot-enhanced.js\"></script>\n<script>ChatBot.init({apiEndpoint: '/chat-unified.php', agent: {publicId: 'pub_agent_abc123'}});</script>"
  }
}
```

**Configuration Options:**

| Field | Type | Description |
|-------|------|-------------|
| `vanity_path` | string | URL-friendly path (e.g., "support" → `/a/support`) |
| `custom_domain` | string | Full custom domain for agent |
| `require_signed_requests` | boolean | Require HMAC-signed tokens |
| `token_ttl_seconds` | integer | Token expiration (default: 3600) |
| `allowed_origins` | array | CORS whitelist |
| `enable_file_upload` | boolean | Allow file uploads |
| `ui_config.title` | string | Widget title |
| `ui_config.logo_url` | string | Logo image URL |
| `ui_config.primary_color` | string | Brand color (hex) |
| `ui_config.welcome_message` | string | Initial greeting |
| `ui_config.placeholder` | string | Input placeholder text |
| `ui_config.disclaimer` | string | Legal disclaimer |
| `ui_config.show_footer_branding` | boolean | Show "Powered by" footer |

---

### Disable Whitelabel

Disable whitelabel mode for an agent.

**Endpoint:** `POST /admin-api.php?action=disable_whitelabel`

**Request:**
```json
{
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Whitelabel disabled successfully"
}
```

---

### Rotate Whitelabel Secret

Generate a new HMAC secret for signed requests.

**Endpoint:** `POST /admin-api.php?action=rotate_whitelabel_secret`

**Request:**
```json
{
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "wl_hmac_secret": "secret_new123"
  },
  "warning": "Update all client integrations with the new secret."
}
```

---

### Update Whitelabel Config

Update whitelabel configuration without changing core agent settings.

**Endpoint:** `POST /admin-api.php?action=update_whitelabel_config`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "ui_config": {
    "primary_color": "#ff0000",
    "welcome_message": "Updated welcome message"
  },
  "allowed_origins": ["https://newdomain.com"]
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Whitelabel configuration updated successfully"
}
```

---

### Get Whitelabel URL

Retrieve public URLs for a whitelabel agent.

**Endpoint:** `GET /admin-api.php?action=get_whitelabel_url&agent_id={agent_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "vanity_url": "https://your-domain.com/a/support",
    "custom_domain_url": "https://help.example.com",
    "agent_public_id": "pub_agent_abc123",
    "requires_hmac": true,
    "sample_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

---

### HMAC Token Generation

For whitelabel agents with `require_signed_requests: true`, clients must generate HMAC tokens:

**Algorithm:** HMAC-SHA256

**Payload:**
```json
{
  "agent_public_id": "pub_agent_abc123",
  "timestamp": 1705747800,
  "nonce": "random_string_123"
}
```

**Example (JavaScript):**
```javascript
const crypto = require('crypto');

function generateWhitelabelToken(agentPublicId, secret) {
  const timestamp = Math.floor(Date.now() / 1000);
  const nonce = crypto.randomBytes(16).toString('hex');

  const payload = {
    agent_public_id: agentPublicId,
    timestamp: timestamp,
    nonce: nonce
  };

  const payloadString = JSON.stringify(payload);
  const signature = crypto
    .createHmac('sha256', secret)
    .update(payloadString)
    .digest('hex');

  return {
    payload: payload,
    signature: signature,
    token: `${Buffer.from(payloadString).toString('base64')}.${signature}`
  };
}

// Usage
const token = generateWhitelabelToken('pub_agent_abc123', 'secret_xyz789');
console.log(token.token);
```

**Example (PHP):**
```php
<?php
function generateWhitelabelToken($agentPublicId, $secret) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));

    $payload = [
        'agent_public_id' => $agentPublicId,
        'timestamp' => $timestamp,
        'nonce' => $nonce
    ];

    $payloadString = json_encode($payload);
    $signature = hash_hmac('sha256', $payloadString, $secret);

    return base64_encode($payloadString) . '.' . $signature;
}

// Usage
$token = generateWhitelabelToken('pub_agent_abc123', 'secret_xyz789');
echo $token;
?>
```

---

## Channel Management

Integrate agents with external messaging platforms (currently supports WhatsApp via Z-API).

### List Agent Channels

Retrieve all channels for an agent.

**Endpoint:** `GET /admin-api.php?action=list_agent_channels&agent_id={agent_id}`

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "agent_id": "agent_abc123",
      "channel_type": "whatsapp",
      "channel_name": "Support WhatsApp",
      "is_active": true,
      "config": {
        "instance_id": "your_zapi_instance",
        "business_phone": "+1234567890"
      },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

---

### Get Agent Channel

Retrieve specific channel details.

**Endpoint:** `GET /admin-api.php?action=get_agent_channel&channel_id={channel_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "agent_id": "agent_abc123",
    "channel_type": "whatsapp",
    "channel_name": "Support WhatsApp",
    "is_active": true,
    "config": {
      "instance_id": "your_zapi_instance",
      "instance_token": "your_token",
      "business_phone": "+1234567890",
      "webhook_url": "https://your-domain.com/webhooks/whatsapp",
      "timeout_seconds": 30,
      "retry_attempts": 3,
      "reply_chunk_size": 1000,
      "enable_media": true
    },
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2025-01-20T09:00:00Z"
  }
}
```

---

### Create/Update Agent Channel

Create or update a channel integration.

**Endpoint:** `POST /admin-api.php?action=upsert_agent_channel`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "channel_type": "whatsapp",
  "channel_name": "Support WhatsApp",
  "is_active": true,
  "config": {
    "instance_id": "your_zapi_instance",
    "instance_token": "your_zapi_token",
    "business_phone": "+1234567890",
    "timeout_seconds": 30,
    "retry_attempts": 3,
    "reply_chunk_size": 1000,
    "enable_media": true
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "agent_id": "agent_abc123",
    "channel_type": "whatsapp",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

**Configuration Options:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `instance_id` | string | Yes | Z-API instance ID |
| `instance_token` | string | Yes | Z-API authentication token |
| `business_phone` | string | Yes | WhatsApp Business phone number |
| `webhook_url` | string | No | Webhook URL for incoming messages |
| `timeout_seconds` | integer | No | Request timeout (default: 30) |
| `retry_attempts` | integer | No | Failed message retries (default: 3) |
| `reply_chunk_size` | integer | No | Max characters per message (default: 1000) |
| `enable_media` | boolean | No | Allow media attachments (default: true) |

---

### Delete Agent Channel

Remove a channel integration.

**Endpoint:** `POST /admin-api.php?action=delete_agent_channel`

**Request:**
```json
{
  "channel_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Channel deleted successfully"
}
```

---

### Test Channel Send

Send a test message through a channel.

**Endpoint:** `POST /admin-api.php?action=test_channel_send`

**Request:**
```json
{
  "channel_id": 1,
  "recipient": "+1234567890",
  "message": "This is a test message from your support bot."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "message_id": "msg_abc123",
    "sent_at": "2025-01-20T10:30:00Z",
    "delivery_status": "sent"
  }
}
```

---

### List Channel Sessions

View active channel sessions.

**Endpoint:** `GET /admin-api.php?action=list_channel_sessions`

**Query Parameters:**
- `channel_id` (integer): Filter by channel
- `is_active` (boolean): Filter active/inactive sessions
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "channel_id": 1,
      "external_user_id": "+1234567890",
      "conversation_id": "conv_abc123",
      "is_active": true,
      "last_message_at": "2025-01-20T10:30:00Z",
      "created_at": "2025-01-20T09:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

## Prompt Management

Manage prompts and versions for use with agents.

### List Prompts

Retrieve all prompts.

**Endpoint:** `GET /admin-api.php?action=list_prompts`

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page
- `tenant_id` (string): Filter by tenant

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "prompt_abc123",
      "name": "Customer Support Prompt",
      "description": "Standard prompt for customer support interactions",
      "openai_prompt_id": "asst_def456",
      "latest_version": "v3",
      "version_count": 3,
      "tenant_id": "tenant_xyz789",
      "created_at": "2024-01-15T10:00:00Z",
      "updated_at": "2025-01-20T09:00:00Z"
    }
  ]
}
```

---

### Get Prompt

Retrieve detailed prompt information.

**Endpoint:** `GET /admin-api.php?action=get_prompt&prompt_id={prompt_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "prompt_abc123",
    "name": "Customer Support Prompt",
    "description": "Standard prompt for customer support interactions",
    "openai_prompt_id": "asst_def456",
    "latest_version": "v3",
    "versions": [
      {
        "version": "v3",
        "content": "You are a helpful customer support assistant...",
        "created_at": "2025-01-20T09:00:00Z",
        "is_active": true
      },
      {
        "version": "v2",
        "content": "You are a customer support agent...",
        "created_at": "2024-12-15T10:00:00Z",
        "is_active": false
      }
    ],
    "tenant_id": "tenant_xyz789",
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

---

### Create Prompt

Create a new prompt.

**Endpoint:** `POST /admin-api.php?action=create_prompt`

**Request:**
```json
{
  "name": "Sales Assistant Prompt",
  "description": "Prompt for sales inquiries and product information",
  "openai_prompt_id": "asst_sales123",
  "initial_content": "You are a knowledgeable sales assistant..."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "prompt_new456",
    "name": "Sales Assistant Prompt",
    "version": "v1",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Prompt

Update prompt metadata (not content - use Create Version for content changes).

**Endpoint:** `POST /admin-api.php?action=update_prompt`

**Request:**
```json
{
  "prompt_id": "prompt_abc123",
  "name": "Updated Customer Support Prompt",
  "description": "Enhanced customer support interactions"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Prompt updated successfully"
}
```

---

### Delete Prompt

Delete a prompt and all its versions.

**Endpoint:** `POST /admin-api.php?action=delete_prompt`

**Request:**
```json
{
  "prompt_id": "prompt_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Prompt deleted successfully"
}
```

**Note:** Cannot delete prompts currently used by active agents.

---

### List Prompt Versions

Retrieve all versions of a prompt.

**Endpoint:** `GET /admin-api.php?action=list_prompt_versions&prompt_id={prompt_id}`

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "version": "v3",
      "content": "You are a helpful customer support assistant...",
      "created_at": "2025-01-20T09:00:00Z",
      "created_by": "admin@example.com",
      "is_active": true
    }
  ]
}
```

---

### Create Prompt Version

Create a new version of a prompt.

**Endpoint:** `POST /admin-api.php?action=create_prompt_version`

**Request:**
```json
{
  "prompt_id": "prompt_abc123",
  "content": "You are an expert customer support assistant with deep knowledge of our products and services...",
  "version_label": "v4",
  "notes": "Enhanced with product knowledge"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "prompt_id": "prompt_abc123",
    "version": "v4",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Sync Prompts

Sync prompts with OpenAI (fetch latest from OpenAI API).

**Endpoint:** `POST /admin-api.php?action=sync_prompts`

**Response:**
```json
{
  "status": "success",
  "data": {
    "synced": 5,
    "created": 2,
    "updated": 3,
    "errors": 0
  }
}
```

---

## Vector Store Management

Manage vector stores for file search and retrieval.

### List Vector Stores

Retrieve all vector stores.

**Endpoint:** `GET /admin-api.php?action=list_vector_stores`

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page
- `tenant_id` (string): Filter by tenant

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "vs_abc123",
      "name": "Product Documentation",
      "description": "Product manuals and documentation",
      "openai_vector_store_id": "vs_openai_def456",
      "file_count": 15,
      "total_bytes": 5242880,
      "status": "ready",
      "tenant_id": "tenant_xyz789",
      "created_at": "2024-01-15T10:00:00Z",
      "last_synced_at": "2025-01-20T09:00:00Z"
    }
  ]
}
```

---

### Get Vector Store

Retrieve detailed vector store information.

**Endpoint:** `GET /admin-api.php?action=get_vector_store&vector_store_id={vector_store_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "vs_abc123",
    "name": "Product Documentation",
    "description": "Product manuals and documentation",
    "openai_vector_store_id": "vs_openai_def456",
    "file_count": 15,
    "total_bytes": 5242880,
    "status": "ready",
    "metadata": {
      "category": "documentation",
      "language": "en"
    },
    "tenant_id": "tenant_xyz789",
    "created_at": "2024-01-15T10:00:00Z",
    "last_synced_at": "2025-01-20T09:00:00Z"
  }
}
```

---

### Create Vector Store

Create a new vector store.

**Endpoint:** `POST /admin-api.php?action=create_vector_store`

**Request:**
```json
{
  "name": "FAQ Database",
  "description": "Frequently asked questions and answers",
  "file_ids": ["file_abc123", "file_def456"],
  "metadata": {
    "category": "faq",
    "language": "en"
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "vs_new456",
    "name": "FAQ Database",
    "openai_vector_store_id": "vs_openai_ghi789",
    "status": "processing",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Vector Store

Update vector store metadata.

**Endpoint:** `POST /admin-api.php?action=update_vector_store`

**Request:**
```json
{
  "vector_store_id": "vs_abc123",
  "name": "Updated Product Documentation",
  "description": "Comprehensive product documentation and guides"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Vector store updated successfully"
}
```

---

### Delete Vector Store

Delete a vector store.

**Endpoint:** `POST /admin-api.php?action=delete_vector_store`

**Request:**
```json
{
  "vector_store_id": "vs_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Vector store deleted successfully"
}
```

**Note:** Cannot delete vector stores currently used by active agents.

---

### List Vector Store Files

Retrieve files in a vector store.

**Endpoint:** `GET /admin-api.php?action=list_vector_store_files&vector_store_id={vector_store_id}`

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "file_abc123",
      "filename": "product-manual.pdf",
      "purpose": "assistants",
      "bytes": 1048576,
      "status": "processed",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

---

### Add Vector Store File

Add a file to a vector store.

**Endpoint:** `POST /admin-api.php?action=add_vector_store_file`

**Request:**
```json
{
  "vector_store_id": "vs_abc123",
  "file_id": "file_new456"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "vector_store_id": "vs_abc123",
    "file_id": "file_new456",
    "status": "processing"
  }
}
```

---

### Delete Vector Store File

Remove a file from a vector store.

**Endpoint:** `POST /admin-api.php?action=delete_vector_store_file`

**Request:**
```json
{
  "vector_store_id": "vs_abc123",
  "file_id": "file_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "File removed from vector store successfully"
}
```

---

### Poll File Status

Check the processing status of a file.

**Endpoint:** `GET /admin-api.php?action=poll_file_status&vector_store_id={vs_id}&file_id={file_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "file_id": "file_abc123",
    "status": "processed",
    "error": null,
    "processed_at": "2025-01-20T10:35:00Z"
  }
}
```

**Status Values:**
- `uploading`: File is being uploaded
- `processing`: File is being processed
- `processed`: File is ready
- `error`: Processing failed

---

### Sync Vector Stores

Sync vector stores with OpenAI (fetch latest metadata and file lists).

**Endpoint:** `POST /admin-api.php?action=sync_vector_stores`

**Response:**
```json
{
  "status": "success",
  "data": {
    "synced": 3,
    "updated": 2,
    "errors": 0
  }
}
```

---

## File Management

Manage files for use with OpenAI APIs.

### List Files

Retrieve all uploaded files.

**Endpoint:** `GET /admin-api.php?action=list_files`

**Query Parameters:**
- `purpose` (string): Filter by purpose (assistants, fine-tune, etc.)
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "file_abc123",
      "filename": "product-manual.pdf",
      "purpose": "assistants",
      "bytes": 1048576,
      "status": "processed",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

---

### Upload File

Upload a new file to OpenAI.

**Endpoint:** `POST /admin-api.php?action=upload_file`

**Request:**
Multipart form data:
```
file: <binary file data>
purpose: assistants
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "file_new456",
    "filename": "new-document.pdf",
    "purpose": "assistants",
    "bytes": 524288,
    "status": "uploaded",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

**Supported File Types:**
- PDF (.pdf)
- Text (.txt, .md)
- Word (.doc, .docx)
- Excel (.xls, .xlsx)
- PowerPoint (.ppt, .pptx)
- JSON (.json)
- CSV (.csv)

**File Size Limits:**
- Maximum file size: 512 MB
- Maximum files per vector store: 10,000

---

### Delete File

Delete a file from OpenAI.

**Endpoint:** `POST /admin-api.php?action=delete_file`

**Request:**
```json
{
  "file_id": "file_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "File deleted successfully"
}
```

**Note:** Cannot delete files currently used in vector stores.

---

## Model Management

### List Models

Retrieve available OpenAI models.

**Endpoint:** `GET /admin-api.php?action=list_models`

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "gpt-4-turbo",
      "object": "model",
      "created": 1687882411,
      "owned_by": "openai",
      "capabilities": {
        "chat": true,
        "responses": true,
        "vision": true,
        "function_calling": true
      },
      "context_window": 128000,
      "max_output_tokens": 4096
    },
    {
      "id": "gpt-4",
      "object": "model",
      "created": 1687882410,
      "owned_by": "openai",
      "capabilities": {
        "chat": true,
        "responses": true,
        "function_calling": true
      },
      "context_window": 8192,
      "max_output_tokens": 4096
    },
    {
      "id": "gpt-3.5-turbo",
      "object": "model",
      "created": 1677610602,
      "owned_by": "openai",
      "capabilities": {
        "chat": true,
        "responses": true,
        "function_calling": true
      },
      "context_window": 16385,
      "max_output_tokens": 4096
    }
  ]
}
```

---

## Multi-Tenancy

Manage tenant organizations for isolated multi-tenant deployments.

### List Tenants

Retrieve all tenants (super-admins only).

**Endpoint:** `GET /admin-api.php?action=list_tenants`

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page
- `is_active` (boolean): Filter active/suspended tenants

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "tenant_abc123",
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "email": "admin@acme.com",
      "is_active": true,
      "subscription_tier": "enterprise",
      "agent_count": 5,
      "user_count": 12,
      "created_at": "2024-01-15T10:00:00Z",
      "suspended_at": null
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

### Get Tenant

Retrieve detailed tenant information.

**Endpoint:** `GET /admin-api.php?action=get_tenant&tenant_id={tenant_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "tenant_abc123",
    "name": "Acme Corporation",
    "slug": "acme-corp",
    "email": "admin@acme.com",
    "phone": "+1234567890",
    "website": "https://acme.com",
    "is_active": true,
    "subscription_tier": "enterprise",
    "subscription_expires_at": "2025-12-31T23:59:59Z",
    "settings": {
      "max_agents": 10,
      "max_users": 50,
      "enable_whitelabel": true,
      "enable_channels": true,
      "enable_crm": true
    },
    "quotas": {
      "monthly_messages": 100000,
      "monthly_tokens": 5000000
    },
    "usage_current_month": {
      "messages": 45678,
      "tokens": 2345678
    },
    "metadata": {
      "industry": "E-commerce",
      "company_size": "51-200"
    },
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2025-01-20T09:00:00Z"
  }
}
```

---

### Create Tenant

Create a new tenant organization.

**Endpoint:** `POST /admin-api.php?action=create_tenant`

**Request:**
```json
{
  "name": "New Company Inc",
  "slug": "new-company",
  "email": "admin@newcompany.com",
  "phone": "+1234567890",
  "website": "https://newcompany.com",
  "subscription_tier": "professional",
  "settings": {
    "max_agents": 5,
    "max_users": 20,
    "enable_whitelabel": true
  },
  "quotas": {
    "monthly_messages": 50000,
    "monthly_tokens": 2500000
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "tenant_new456",
    "name": "New Company Inc",
    "slug": "new-company",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Tenant

Update tenant information.

**Endpoint:** `POST /admin-api.php?action=update_tenant`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "name": "Acme Corporation Ltd",
  "subscription_tier": "enterprise-plus",
  "settings": {
    "max_agents": 20
  }
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Tenant updated successfully"
}
```

---

### Delete Tenant

Delete a tenant and all associated data.

**Endpoint:** `POST /admin-api.php?action=delete_tenant`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "confirm": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Tenant deleted successfully"
}
```

**Warning:** This is a destructive operation that deletes:
- All agents
- All users
- All conversations
- All audit logs
- All usage data

---

### Suspend Tenant

Temporarily suspend a tenant account.

**Endpoint:** `POST /admin-api.php?action=suspend_tenant`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "reason": "Payment overdue"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Tenant suspended successfully"
}
```

**Effects:**
- All API requests return 403 Forbidden
- Users cannot log in
- Agents are inaccessible
- Data is retained

---

### Activate Tenant

Reactivate a suspended tenant.

**Endpoint:** `POST /admin-api.php?action=activate_tenant`

**Request:**
```json
{
  "tenant_id": "tenant_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Tenant activated successfully"
}
```

---

### Get Tenant Stats

Retrieve usage statistics for a tenant.

**Endpoint:** `GET /admin-api.php?action=get_tenant_stats&tenant_id={tenant_id}`

**Query Parameters:**
- `period` (string): Time period (today, week, month, year)

**Response:**
```json
{
  "status": "success",
  "data": {
    "tenant_id": "tenant_abc123",
    "period": "month",
    "stats": {
      "total_messages": 45678,
      "total_tokens": 2345678,
      "total_conversations": 1234,
      "unique_users": 567,
      "average_response_time_ms": 1234,
      "error_rate": 0.02
    },
    "by_agent": [
      {
        "agent_id": "agent_abc123",
        "agent_name": "Customer Support Bot",
        "messages": 30000,
        "tokens": 1500000,
        "conversations": 800
      }
    ],
    "by_day": [
      {
        "date": "2025-01-20",
        "messages": 1500,
        "tokens": 75000
      }
    ],
    "quotas": {
      "monthly_messages": {
        "limit": 100000,
        "used": 45678,
        "remaining": 54322,
        "percentage": 45.68
      },
      "monthly_tokens": {
        "limit": 5000000,
        "used": 2345678,
        "remaining": 2654322,
        "percentage": 46.91
      }
    }
  }
}
```

---

## Usage Tracking & Quotas

Monitor and control resource usage across tenants and agents.

### Get Usage Stats

Retrieve usage statistics.

**Endpoint:** `GET /admin-api.php?action=get_usage_stats`

**Query Parameters:**
- `tenant_id` (string): Filter by tenant
- `agent_id` (string): Filter by agent
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date
- `granularity` (string): hour, day, week, month

**Response:**
```json
{
  "status": "success",
  "data": {
    "summary": {
      "total_messages": 123456,
      "total_tokens": 6789012,
      "total_conversations": 5678,
      "unique_users": 2345,
      "total_cost_usd": 123.45
    },
    "by_tenant": [
      {
        "tenant_id": "tenant_abc123",
        "tenant_name": "Acme Corporation",
        "messages": 45678,
        "tokens": 2345678,
        "cost_usd": 45.67
      }
    ],
    "by_agent": [
      {
        "agent_id": "agent_abc123",
        "agent_name": "Customer Support Bot",
        "messages": 30000,
        "tokens": 1500000,
        "cost_usd": 30.00
      }
    ]
  }
}
```

---

### Get Usage Timeseries

Retrieve time-series usage data for charting.

**Endpoint:** `GET /admin-api.php?action=get_usage_timeseries`

**Query Parameters:**
- `tenant_id` (string): Filter by tenant
- `agent_id` (string): Filter by agent
- `metric` (string): messages, tokens, conversations, cost
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date
- `granularity` (string): hour, day, week, month

**Response:**
```json
{
  "status": "success",
  "data": {
    "metric": "messages",
    "granularity": "day",
    "data_points": [
      {
        "timestamp": "2025-01-15T00:00:00Z",
        "value": 1234
      },
      {
        "timestamp": "2025-01-16T00:00:00Z",
        "value": 1456
      },
      {
        "timestamp": "2025-01-17T00:00:00Z",
        "value": 1389
      }
    ]
  }
}
```

---

### List Quotas

Retrieve all configured quotas.

**Endpoint:** `GET /admin-api.php?action=list_quotas`

**Query Parameters:**
- `tenant_id` (string): Filter by tenant
- `resource_type` (string): messages, tokens, conversations, agents

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "tenant_id": "tenant_abc123",
      "resource_type": "messages",
      "limit": 100000,
      "period": "month",
      "current_usage": 45678,
      "percentage_used": 45.68,
      "reset_at": "2025-02-01T00:00:00Z"
    },
    {
      "id": 2,
      "tenant_id": "tenant_abc123",
      "resource_type": "tokens",
      "limit": 5000000,
      "period": "month",
      "current_usage": 2345678,
      "percentage_used": 46.91,
      "reset_at": "2025-02-01T00:00:00Z"
    }
  ]
}
```

---

### Get Quota Status

Check quota status for a specific resource.

**Endpoint:** `GET /admin-api.php?action=get_quota_status`

**Query Parameters:**
- `tenant_id` (string): Tenant ID
- `resource_type` (string): messages, tokens, etc.

**Response:**
```json
{
  "status": "success",
  "data": {
    "resource_type": "messages",
    "limit": 100000,
    "used": 45678,
    "remaining": 54322,
    "percentage": 45.68,
    "is_exceeded": false,
    "reset_at": "2025-02-01T00:00:00Z"
  }
}
```

---

### Set Quota

Create or update a quota.

**Endpoint:** `POST /admin-api.php?action=set_quota`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "resource_type": "messages",
  "limit": 150000,
  "period": "month",
  "enforce": true
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "tenant_id": "tenant_abc123",
    "resource_type": "messages",
    "limit": 150000,
    "period": "month",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

**Resource Types:**
- `messages`: Total messages per period
- `tokens`: Total tokens consumed
- `conversations`: Total conversations
- `agents`: Maximum number of agents
- `users`: Maximum number of users
- `storage_bytes`: Total file storage
- `api_calls`: Total API requests

**Periods:**
- `hour`: Per hour
- `day`: Per day
- `week`: Per week
- `month`: Per calendar month
- `year`: Per calendar year

---

### Delete Quota

Remove a quota (unlimited usage).

**Endpoint:** `POST /admin-api.php?action=delete_quota`

**Request:**
```json
{
  "quota_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Quota deleted successfully"
}
```

---

### Get Tenant Usage Summary

Retrieve comprehensive usage summary for a tenant.

**Endpoint:** `GET /admin-api.php?action=get_tenant_usage_summary&tenant_id={tenant_id}`

**Query Parameters:**
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date

**Response:**
```json
{
  "status": "success",
  "data": {
    "tenant_id": "tenant_abc123",
    "period": {
      "start": "2025-01-01T00:00:00Z",
      "end": "2025-01-31T23:59:59Z"
    },
    "totals": {
      "messages": 45678,
      "tokens": 2345678,
      "conversations": 1234,
      "unique_users": 567,
      "cost_usd": 45.67
    },
    "by_agent": [
      {
        "agent_id": "agent_abc123",
        "agent_name": "Customer Support Bot",
        "messages": 30000,
        "tokens": 1500000,
        "cost_usd": 30.00
      }
    ],
    "quotas": {
      "messages": {
        "limit": 100000,
        "used": 45678,
        "percentage": 45.68
      }
    }
  }
}
```

---

### Get Tenant Usage Trends

Retrieve usage trends for forecasting.

**Endpoint:** `GET /admin-api.php?action=get_tenant_usage_trends&tenant_id={tenant_id}`

**Query Parameters:**
- `metric` (string): messages, tokens, cost
- `periods` (integer): Number of periods to analyze (default: 12)
- `granularity` (string): day, week, month

**Response:**
```json
{
  "status": "success",
  "data": {
    "metric": "messages",
    "granularity": "month",
    "historical": [
      {
        "period": "2024-11",
        "value": 38000
      },
      {
        "period": "2024-12",
        "value": 42000
      },
      {
        "period": "2025-01",
        "value": 45678
      }
    ],
    "trend": {
      "direction": "increasing",
      "percentage_change": 9.2,
      "forecast_next_period": 49800
    }
  }
}
```

---

### Aggregate Tenant Usage

Manually trigger usage aggregation (normally runs automatically).

**Endpoint:** `POST /admin-api.php?action=aggregate_tenant_usage`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "period": "2025-01"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "tenant_id": "tenant_abc123",
    "period": "2025-01",
    "aggregated_at": "2025-01-20T10:30:00Z",
    "records_processed": 45678
  }
}
```

---

## Billing & Subscriptions

Manage billing, subscriptions, and payment methods.

### Get Subscription

Retrieve tenant subscription details.

**Endpoint:** `GET /admin-api.php?action=get_subscription&tenant_id={tenant_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "sub_abc123",
    "tenant_id": "tenant_abc123",
    "plan": "enterprise",
    "status": "active",
    "current_period_start": "2025-01-01T00:00:00Z",
    "current_period_end": "2025-01-31T23:59:59Z",
    "trial_end": null,
    "cancel_at_period_end": false,
    "canceled_at": null,
    "pricing": {
      "base_price_usd": 499.00,
      "price_per_1k_messages": 0.05,
      "price_per_1m_tokens": 2.00,
      "included_messages": 100000,
      "included_tokens": 5000000
    },
    "payment_method": {
      "type": "credit_card",
      "last4": "4242",
      "exp_month": 12,
      "exp_year": 2025
    },
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

---

### Create Subscription

Create a new subscription for a tenant.

**Endpoint:** `POST /admin-api.php?action=create_subscription`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "plan": "professional",
  "billing_cycle": "monthly",
  "payment_method_id": "pm_abc123",
  "trial_days": 14
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "sub_new456",
    "tenant_id": "tenant_abc123",
    "plan": "professional",
    "status": "trialing",
    "trial_end": "2025-02-03T10:30:00Z",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Subscription

Update an existing subscription.

**Endpoint:** `POST /admin-api.php?action=update_subscription`

**Request:**
```json
{
  "subscription_id": "sub_abc123",
  "plan": "enterprise-plus",
  "proration_behavior": "always_invoice"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Subscription updated successfully",
  "data": {
    "effective_date": "2025-01-20T10:30:00Z",
    "proration_amount_usd": 123.45
  }
}
```

---

### Cancel Subscription

Cancel a subscription.

**Endpoint:** `POST /admin-api.php?action=cancel_subscription`

**Request:**
```json
{
  "subscription_id": "sub_abc123",
  "cancel_at_period_end": true,
  "reason": "Switching to different solution"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Subscription canceled successfully",
  "data": {
    "canceled_at": "2025-01-20T10:30:00Z",
    "ends_at": "2025-01-31T23:59:59Z"
  }
}
```

---

### List Invoices

Retrieve invoices for a tenant.

**Endpoint:** `GET /admin-api.php?action=list_invoices&tenant_id={tenant_id}`

**Query Parameters:**
- `status` (string): Filter by status (draft, open, paid, void, uncollectible)
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "inv_abc123",
      "tenant_id": "tenant_abc123",
      "invoice_number": "INV-2025-001",
      "status": "paid",
      "amount_due": 549.00,
      "amount_paid": 549.00,
      "currency": "USD",
      "period_start": "2025-01-01T00:00:00Z",
      "period_end": "2025-01-31T23:59:59Z",
      "due_date": "2025-02-01T00:00:00Z",
      "paid_at": "2025-01-02T08:15:00Z",
      "pdf_url": "https://your-domain.com/invoices/inv_abc123.pdf",
      "line_items": [
        {
          "description": "Enterprise Plan - Monthly",
          "amount": 499.00
        },
        {
          "description": "Additional messages (10,000)",
          "amount": 50.00
        }
      ],
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

---

### Get Invoice

Retrieve specific invoice details.

**Endpoint:** `GET /admin-api.php?action=get_invoice&invoice_id={invoice_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "inv_abc123",
    "tenant_id": "tenant_abc123",
    "invoice_number": "INV-2025-001",
    "status": "paid",
    "amount_due": 549.00,
    "amount_paid": 549.00,
    "currency": "USD",
    "line_items": [
      {
        "description": "Enterprise Plan - Monthly",
        "quantity": 1,
        "unit_price": 499.00,
        "amount": 499.00
      }
    ],
    "pdf_url": "https://your-domain.com/invoices/inv_abc123.pdf",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

---

### Create Invoice

Manually create an invoice.

**Endpoint:** `POST /admin-api.php?action=create_invoice`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "line_items": [
    {
      "description": "Custom Development",
      "quantity": 10,
      "unit_price": 150.00
    }
  ],
  "due_date": "2025-02-15T00:00:00Z",
  "notes": "Custom integration services"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "inv_new456",
    "invoice_number": "INV-2025-002",
    "status": "open",
    "amount_due": 1500.00,
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Invoice

Update invoice details (draft invoices only).

**Endpoint:** `POST /admin-api.php?action=update_invoice`

**Request:**
```json
{
  "invoice_id": "inv_abc123",
  "due_date": "2025-02-20T00:00:00Z",
  "notes": "Updated payment terms"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Invoice updated successfully"
}
```

---

## Consent Management

Manage user consents for GDPR/CCPA compliance.

### List Consents

Retrieve all consent records.

**Endpoint:** `GET /admin-api.php?action=list_consents`

**Query Parameters:**
- `user_identifier` (string): Filter by user
- `consent_type` (string): Filter by type
- `status` (string): granted, withdrawn
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "tenant_id": "tenant_abc123",
      "user_identifier": "user@example.com",
      "consent_type": "data_processing",
      "status": "granted",
      "granted_at": "2025-01-15T10:00:00Z",
      "withdrawn_at": null,
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "metadata": {
        "source": "registration_form",
        "version": "1.0"
      }
    }
  ]
}
```

---

### Get Consent

Retrieve specific consent record.

**Endpoint:** `GET /admin-api.php?action=get_consent&consent_id={consent_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "tenant_id": "tenant_abc123",
    "user_identifier": "user@example.com",
    "consent_type": "data_processing",
    "status": "granted",
    "granted_at": "2025-01-15T10:00:00Z",
    "withdrawn_at": null,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "consent_text": "I agree to the processing of my personal data...",
    "metadata": {
      "source": "registration_form",
      "version": "1.0"
    }
  }
}
```

---

### Grant Consent

Record a consent grant.

**Endpoint:** `POST /admin-api.php?action=grant_consent`

**Request:**
```json
{
  "user_identifier": "user@example.com",
  "consent_type": "marketing_emails",
  "consent_text": "I agree to receive marketing emails",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "metadata": {
    "source": "preferences_page",
    "version": "1.0"
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 2,
    "consent_type": "marketing_emails",
    "status": "granted",
    "granted_at": "2025-01-20T10:30:00Z"
  }
}
```

**Consent Types:**
- `data_processing`: General data processing
- `marketing_emails`: Marketing communications
- `analytics`: Usage analytics
- `third_party_sharing`: Third-party data sharing
- `cookies`: Cookie usage
- `custom`: Custom consent types

---

### Withdraw Consent

Withdraw a previously granted consent.

**Endpoint:** `POST /admin-api.php?action=withdraw_consent`

**Request:**
```json
{
  "consent_id": 1,
  "reason": "User request"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Consent withdrawn successfully",
  "data": {
    "withdrawn_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Check Consent

Check if user has granted specific consent.

**Endpoint:** `GET /admin-api.php?action=check_consent`

**Query Parameters:**
- `user_identifier` (string): User identifier
- `consent_type` (string): Consent type

**Response:**
```json
{
  "status": "success",
  "data": {
    "has_consent": true,
    "consent_type": "data_processing",
    "granted_at": "2025-01-15T10:00:00Z",
    "is_active": true
  }
}
```

---

### Get Consent Audit

Retrieve consent change history.

**Endpoint:** `GET /admin-api.php?action=get_consent_audit`

**Query Parameters:**
- `user_identifier` (string): User identifier
- `consent_type` (string): Filter by type

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "action": "granted",
      "consent_type": "data_processing",
      "timestamp": "2025-01-15T10:00:00Z",
      "ip_address": "192.168.1.100"
    },
    {
      "action": "withdrawn",
      "consent_type": "marketing_emails",
      "timestamp": "2025-01-18T14:30:00Z",
      "ip_address": "192.168.1.101"
    }
  ]
}
```

---

## LeadSense CRM

AI-powered lead detection, scoring, and pipeline management.

### List Leads

Retrieve detected leads.

**Endpoint:** `GET /admin-api.php?action=list_leads`

**Query Parameters:**
- `status` (string): new, contacted, qualified, unqualified, converted
- `score_min` (integer): Minimum lead score (0-100)
- `score_max` (integer): Maximum lead score
- `agent_id` (string): Filter by agent
- `assigned_to` (string): Filter by assigned user
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "tenant_id": "tenant_abc123",
      "conversation_id": "conv_abc123",
      "agent_id": "agent_abc123",
      "status": "qualified",
      "score": 85,
      "intent": "product_purchase",
      "contact_info": {
        "email": "lead@example.com",
        "phone": "+1234567890",
        "name": "John Doe",
        "company": "Acme Inc"
      },
      "detected_interests": [
        "enterprise_plan",
        "whitelabel_integration"
      ],
      "ai_summary": "High-intent lead interested in enterprise plan with whitelabel capabilities. Budget confirmed, decision maker.",
      "assigned_to": "sales@example.com",
      "pipeline_stage": "qualification",
      "estimated_value_usd": 5000.00,
      "last_interaction_at": "2025-01-20T10:00:00Z",
      "created_at": "2025-01-20T09:30:00Z",
      "updated_at": "2025-01-20T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "total_pages": 3
  }
}
```

---

### Get Lead

Retrieve detailed lead information.

**Endpoint:** `GET /admin-api.php?action=get_lead&lead_id={lead_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "tenant_id": "tenant_abc123",
    "conversation_id": "conv_abc123",
    "agent_id": "agent_abc123",
    "status": "qualified",
    "score": 85,
    "score_breakdown": {
      "intent_strength": 25,
      "budget_indication": 20,
      "decision_authority": 20,
      "timing_urgency": 15,
      "fit_score": 5
    },
    "intent": "product_purchase",
    "contact_info": {
      "email": "lead@example.com",
      "phone": "+1234567890",
      "name": "John Doe",
      "company": "Acme Inc",
      "title": "CTO"
    },
    "detected_interests": [
      "enterprise_plan",
      "whitelabel_integration",
      "api_access"
    ],
    "conversation_summary": {
      "total_messages": 15,
      "duration_minutes": 23,
      "key_questions": [
        "What are the enterprise features?",
        "Can we white-label the solution?",
        "What's the pricing for 100K messages/month?"
      ]
    },
    "ai_summary": "High-intent lead interested in enterprise plan...",
    "notes": [
      {
        "id": 1,
        "content": "Follow up next week",
        "created_by": "sales@example.com",
        "created_at": "2025-01-20T10:30:00Z"
      }
    ],
    "assigned_to": "sales@example.com",
    "pipeline_stage": "qualification",
    "estimated_value_usd": 5000.00,
    "conversion_probability": 0.75,
    "last_interaction_at": "2025-01-20T10:00:00Z",
    "created_at": "2025-01-20T09:30:00Z",
    "updated_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Update Lead

Update lead information.

**Endpoint:** `POST /admin-api.php?action=update_lead`

**Request:**
```json
{
  "lead_id": 1,
  "status": "contacted",
  "assigned_to": "sales2@example.com",
  "pipeline_stage": "negotiation",
  "estimated_value_usd": 7500.00,
  "contact_info": {
    "phone": "+1234567891"
  }
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Lead updated successfully"
}
```

---

### Add Lead Note

Add a note to a lead.

**Endpoint:** `POST /admin-api.php?action=add_lead_note`

**Request:**
```json
{
  "lead_id": 1,
  "content": "Had a great call. Moving forward with enterprise plan. Expect contract by Friday."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 2,
    "lead_id": 1,
    "content": "Had a great call...",
    "created_by": "sales@example.com",
    "created_at": "2025-01-20T11:00:00Z"
  }
}
```

---

### Rescore Lead

Recalculate lead score using latest AI model.

**Endpoint:** `POST /admin-api.php?action=rescore_lead`

**Request:**
```json
{
  "lead_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "old_score": 85,
    "new_score": 92,
    "score_breakdown": {
      "intent_strength": 30,
      "budget_indication": 25,
      "decision_authority": 20,
      "timing_urgency": 15,
      "fit_score": 2
    }
  }
}
```

---

## Prompt Builder

AI-powered prompt generation with guardrails.

### Generate Prompt

Generate a new prompt using AI with guardrails.

**Endpoint:** `POST /admin-api.php?action=prompt_builder_generate`

**Request:**
```json
{
  "description": "Create a prompt for a customer support bot that handles refund requests for an e-commerce store",
  "tone": "professional",
  "industry": "e-commerce",
  "guardrails": [
    "no_medical_advice",
    "no_financial_advice",
    "no_personal_data_collection",
    "scope_restriction"
  ],
  "constraints": {
    "max_refund_amount": 500,
    "require_order_number": true
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "pb_abc123",
    "generated_prompt": "You are a professional customer support assistant for an e-commerce company...\n\nGuidelines:\n- Always request order numbers before processing refunds\n- Do not process refunds exceeding $500 without manager approval\n- Do not provide medical or financial advice...",
    "metadata": {
      "tokens": 245,
      "guardrails_applied": [
        "no_medical_advice",
        "no_financial_advice",
        "scope_restriction"
      ],
      "generation_time_ms": 2340
    },
    "warnings": [],
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

**Available Guardrails:**
- `no_medical_advice`: Prevent medical advice
- `no_financial_advice`: Prevent financial advice
- `no_legal_advice`: Prevent legal advice
- `no_personal_data_collection`: Prevent PII collection
- `scope_restriction`: Limit to specified domain
- `no_hallucination`: Add anti-hallucination instructions
- `cite_sources`: Require source citations
- `professional_tone`: Enforce professional language

---

### List Generated Prompts

Retrieve previously generated prompts.

**Endpoint:** `GET /admin-api.php?action=prompt_builder_list`

**Query Parameters:**
- `is_active` (boolean): Filter active prompts
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "pb_abc123",
      "description": "Customer support bot for refund requests",
      "generated_prompt": "You are a professional customer support assistant...",
      "is_active": true,
      "guardrails": ["no_medical_advice", "scope_restriction"],
      "created_at": "2025-01-20T10:30:00Z"
    }
  ]
}
```

---

### Get Generated Prompt

Retrieve specific generated prompt.

**Endpoint:** `GET /admin-api.php?action=prompt_builder_get&prompt_builder_id={id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "pb_abc123",
    "description": "Customer support bot for refund requests",
    "generated_prompt": "You are a professional customer support assistant...",
    "is_active": true,
    "guardrails": ["no_medical_advice", "scope_restriction"],
    "metadata": {
      "tokens": 245,
      "generation_time_ms": 2340
    },
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Activate Prompt

Activate a generated prompt (deploy to agent).

**Endpoint:** `POST /admin-api.php?action=prompt_builder_activate`

**Request:**
```json
{
  "prompt_builder_id": "pb_abc123",
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Prompt activated and deployed to agent successfully"
}
```

---

### Deactivate Prompt

Deactivate a prompt.

**Endpoint:** `POST /admin-api.php?action=prompt_builder_deactivate`

**Request:**
```json
{
  "prompt_builder_id": "pb_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Prompt deactivated successfully"
}
```

---

### Save Manual Prompt

Save a manually created prompt.

**Endpoint:** `POST /admin-api.php?action=prompt_builder_save_manual`

**Request:**
```json
{
  "description": "Custom support prompt",
  "prompt_content": "You are a helpful assistant...",
  "guardrails": ["no_personal_data_collection"]
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "pb_new456",
    "description": "Custom support prompt",
    "created_at": "2025-01-20T10:30:00Z"
  }
}
```

---

### Delete Prompt

Delete a generated prompt.

**Endpoint:** `POST /admin-api.php?action=prompt_builder_delete`

**Request:**
```json
{
  "prompt_builder_id": "pb_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Prompt deleted successfully"
}
```

---

### Get Guardrail Catalog

Retrieve available guardrails.

**Endpoint:** `GET /admin-api.php?action=prompt_builder_catalog`

**Response:**
```json
{
  "status": "success",
  "data": {
    "guardrails": [
      {
        "id": "no_medical_advice",
        "name": "No Medical Advice",
        "description": "Prevents the AI from providing medical diagnoses or treatment recommendations",
        "category": "safety"
      },
      {
        "id": "scope_restriction",
        "name": "Scope Restriction",
        "description": "Limits responses to specified domain or topic",
        "category": "control"
      }
    ],
    "categories": ["safety", "control", "compliance", "quality"]
  }
}
```

---

## Audit & Observability

Track and monitor system activity.

### List Audit Log

Retrieve audit log entries.

**Endpoint:** `GET /admin-api.php?action=list_audit_log`

**Query Parameters:**
- `event_type` (string): Filter by event type
- `user_id` (string): Filter by user
- `tenant_id` (string): Filter by tenant
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "event_type": "agent.created",
      "actor_type": "user",
      "actor_id": "user_abc123",
      "tenant_id": "tenant_xyz789",
      "resource_type": "agent",
      "resource_id": "agent_new456",
      "action": "create",
      "details": {
        "agent_name": "New Support Bot",
        "model": "gpt-4"
      },
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "timestamp": "2025-01-20T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1234,
    "total_pages": 62
  }
}
```

**Event Types:**
- `agent.*`: Agent operations (created, updated, deleted)
- `user.*`: User operations
- `auth.*`: Authentication events
- `conversation.*`: Conversation events
- `quota.*`: Quota events
- `billing.*`: Billing events
- `consent.*`: Consent operations
- `lead.*`: Lead operations

---

### List Audit Conversations

Retrieve conversation audit records.

**Endpoint:** `GET /admin-api.php?action=list_audit_conversations`

**Query Parameters:**
- `agent_id` (string): Filter by agent
- `tenant_id` (string): Filter by tenant
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "conv_abc123",
      "tenant_id": "tenant_xyz789",
      "agent_id": "agent_abc123",
      "session_id": "sess_def456",
      "message_count": 15,
      "total_tokens": 1250,
      "started_at": "2025-01-20T09:00:00Z",
      "ended_at": "2025-01-20T09:23:00Z",
      "user_metadata": {
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0..."
      }
    }
  ]
}
```

---

### Get Audit Conversation

Retrieve detailed conversation audit.

**Endpoint:** `GET /admin-api.php?action=get_audit_conversation&conversation_id={conversation_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "conv_abc123",
    "tenant_id": "tenant_xyz789",
    "agent_id": "agent_abc123",
    "message_count": 15,
    "total_tokens": 1250,
    "started_at": "2025-01-20T09:00:00Z",
    "ended_at": "2025-01-20T09:23:00Z",
    "messages": [
      {
        "id": "msg_001",
        "role": "user",
        "content": "Hello, I need help with my order",
        "timestamp": "2025-01-20T09:00:00Z",
        "tokens": 8
      },
      {
        "id": "msg_002",
        "role": "assistant",
        "content": "I'd be happy to help! Could you provide your order number?",
        "timestamp": "2025-01-20T09:00:15Z",
        "tokens": 15
      }
    ],
    "summary": {
      "topic": "order_inquiry",
      "resolution": "resolved",
      "sentiment": "positive"
    }
  }
}
```

**Note:** PII may be redacted based on tenant configuration.

---

### Get Audit Message

Retrieve specific message details.

**Endpoint:** `GET /admin-api.php?action=get_audit_message&message_id={message_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "msg_abc123",
    "conversation_id": "conv_xyz789",
    "role": "assistant",
    "content": "I'd be happy to help...",
    "tokens": 15,
    "model": "gpt-4",
    "latency_ms": 1234,
    "finish_reason": "stop",
    "tool_calls": [],
    "timestamp": "2025-01-20T09:00:15Z"
  }
}
```

---

### Export Audit Data

Export audit data for compliance or analysis.

**Endpoint:** `POST /admin-api.php?action=export_audit_data`

**Request:**
```json
{
  "tenant_id": "tenant_abc123",
  "start_date": "2025-01-01T00:00:00Z",
  "end_date": "2025-01-31T23:59:59Z",
  "format": "json",
  "include_conversations": true,
  "include_messages": true,
  "redact_pii": true
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "export_id": "export_abc123",
    "status": "processing",
    "download_url": null,
    "estimated_completion": "2025-01-20T10:35:00Z"
  }
}
```

**Supported Formats:**
- `json`: JSON format
- `csv`: CSV format
- `jsonl`: JSON Lines format

---

### Delete Audit Data

Delete audit data (for GDPR/CCPA compliance).

**Endpoint:** `POST /admin-api.php?action=delete_audit_data`

**Request:**
```json
{
  "conversation_id": "conv_abc123",
  "delete_permanently": true,
  "reason": "User data deletion request (GDPR)"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Audit data deleted successfully",
  "data": {
    "conversations_deleted": 1,
    "messages_deleted": 15,
    "artifacts_deleted": 2
  }
}
```

---

### Get Metrics

Retrieve Prometheus-format metrics.

**Endpoint:** `GET /admin-api.php?action=metrics`

**Response:**
```
# HELP chatbot_requests_total Total number of chat requests
# TYPE chatbot_requests_total counter
chatbot_requests_total{tenant="tenant_abc123",agent="agent_abc123",status="success"} 12345

# HELP chatbot_latency_seconds Request latency in seconds
# TYPE chatbot_latency_seconds histogram
chatbot_latency_seconds_bucket{le="0.1"} 1000
chatbot_latency_seconds_bucket{le="0.5"} 5000
chatbot_latency_seconds_bucket{le="1.0"} 8000
chatbot_latency_seconds_sum 10234.5
chatbot_latency_seconds_count 10000

# HELP chatbot_tokens_total Total tokens consumed
# TYPE chatbot_tokens_total counter
chatbot_tokens_total{tenant="tenant_abc123",model="gpt-4"} 1234567
```

---

## Job Queue Management

Manage background jobs and dead letter queue.

### List Jobs

Retrieve job queue entries.

**Endpoint:** `GET /admin-api.php?action=list_jobs`

**Query Parameters:**
- `status` (string): pending, processing, completed, failed
- `job_type` (string): Filter by job type
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "job_type": "usage_aggregation",
      "status": "completed",
      "payload": {
        "tenant_id": "tenant_abc123",
        "period": "2025-01"
      },
      "attempts": 1,
      "max_attempts": 3,
      "error": null,
      "created_at": "2025-01-20T10:00:00Z",
      "started_at": "2025-01-20T10:00:05Z",
      "completed_at": "2025-01-20T10:00:30Z"
    }
  ]
}
```

---

### Get Job

Retrieve specific job details.

**Endpoint:** `GET /admin-api.php?action=get_job&job_id={job_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "job_type": "usage_aggregation",
    "status": "completed",
    "payload": {
      "tenant_id": "tenant_abc123",
      "period": "2025-01"
    },
    "result": {
      "records_processed": 45678,
      "execution_time_ms": 25000
    },
    "attempts": 1,
    "max_attempts": 3,
    "error": null,
    "created_at": "2025-01-20T10:00:00Z",
    "started_at": "2025-01-20T10:00:05Z",
    "completed_at": "2025-01-20T10:00:30Z"
  }
}
```

---

### Retry Job

Retry a failed job.

**Endpoint:** `POST /admin-api.php?action=retry_job`

**Request:**
```json
{
  "job_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Job queued for retry"
}
```

---

### Cancel Job

Cancel a pending or processing job.

**Endpoint:** `POST /admin-api.php?action=cancel_job`

**Request:**
```json
{
  "job_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Job canceled successfully"
}
```

---

### Job Stats

Retrieve job queue statistics.

**Endpoint:** `GET /admin-api.php?action=job_stats`

**Response:**
```json
{
  "status": "success",
  "data": {
    "total_jobs": 12345,
    "by_status": {
      "pending": 23,
      "processing": 5,
      "completed": 12000,
      "failed": 317
    },
    "by_type": {
      "usage_aggregation": 1234,
      "invoice_generation": 567,
      "webhook_delivery": 10544
    },
    "average_processing_time_ms": 2340,
    "failure_rate": 0.026
  }
}
```

---

### List Dead Letter Queue

Retrieve failed jobs in DLQ.

**Endpoint:** `GET /admin-api.php?action=list_dlq`

**Query Parameters:**
- `job_type` (string): Filter by job type
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "job_id": 456,
      "job_type": "webhook_delivery",
      "payload": {
        "url": "https://example.com/webhook",
        "event": "lead.created"
      },
      "error": "Connection timeout",
      "attempts": 3,
      "failed_at": "2025-01-20T10:00:00Z"
    }
  ]
}
```

---

### Get DLQ Entry

Retrieve specific DLQ entry.

**Endpoint:** `GET /admin-api.php?action=get_dlq_entry&dlq_id={dlq_id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "job_id": 456,
    "job_type": "webhook_delivery",
    "payload": {
      "url": "https://example.com/webhook",
      "event": "lead.created",
      "data": {}
    },
    "error": "Connection timeout",
    "attempts": 3,
    "last_attempt_at": "2025-01-20T09:55:00Z",
    "failed_at": "2025-01-20T10:00:00Z"
  }
}
```

---

### Requeue DLQ Entry

Requeue a DLQ entry for retry.

**Endpoint:** `POST /admin-api.php?action=requeue_dlq`

**Request:**
```json
{
  "dlq_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Job requeued successfully",
  "data": {
    "new_job_id": 789
  }
}
```

---

### Delete DLQ Entry

Permanently delete a DLQ entry.

**Endpoint:** `POST /admin-api.php?action=delete_dlq_entry`

**Request:**
```json
{
  "dlq_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "DLQ entry deleted successfully"
}
```

---

## Resource Authorization

Manage resource-level permissions (RBAC).

### Grant Resource Permission

Grant permission to access a resource.

**Endpoint:** `POST /admin-api.php?action=grant_resource_permission`

**Request:**
```json
{
  "user_id": 2,
  "resource_type": "agent",
  "resource_id": "agent_abc123",
  "permission": "read"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Permission granted successfully"
}
```

**Resource Types:**
- `agent`: Agents
- `prompt`: Prompts
- `vector_store`: Vector stores
- `conversation`: Conversations
- `lead`: Leads

**Permissions:**
- `read`: View resource
- `write`: Modify resource
- `delete`: Delete resource
- `admin`: Full control

---

### Revoke Resource Permission

Revoke permission from a resource.

**Endpoint:** `POST /admin-api.php?action=revoke_resource_permission`

**Request:**
```json
{
  "user_id": 2,
  "resource_type": "agent",
  "resource_id": "agent_abc123",
  "permission": "write"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Permission revoked successfully"
}
```

---

### List Resource Permissions

Retrieve permissions for a resource.

**Endpoint:** `GET /admin-api.php?action=list_resource_permissions`

**Query Parameters:**
- `resource_type` (string): Resource type
- `resource_id` (string): Resource ID
- `user_id` (integer): Filter by user

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "user_id": 2,
      "user_email": "user@example.com",
      "resource_type": "agent",
      "resource_id": "agent_abc123",
      "permission": "read",
      "granted_at": "2025-01-20T10:00:00Z",
      "granted_by": "admin@example.com"
    }
  ]
}
```

---

## Webhook Management

Manage webhook delivery logs.

### List Webhook Logs

Retrieve webhook delivery logs.

**Endpoint:** `GET /admin-api.php?action=list_webhook_logs`

**Query Parameters:**
- `event_type` (string): Filter by event type
- `status` (string): success, failed, pending
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "event_type": "lead.created",
      "webhook_url": "https://example.com/webhook",
      "status": "success",
      "http_status": 200,
      "attempts": 1,
      "payload": {
        "event": "lead.created",
        "data": {
          "lead_id": 1,
          "score": 85
        }
      },
      "response_body": "{\"status\":\"ok\"}",
      "delivered_at": "2025-01-20T10:30:00Z",
      "created_at": "2025-01-20T10:30:00Z"
    }
  ]
}
```

---

## WordPress Blog Management

Automate AI-powered blog content generation and publishing to WordPress sites. This comprehensive feature enables automated article creation with multi-chapter structure, AI-generated images, SEO optimization, and direct WordPress publishing.

**Related Documentation:**
- [WORDPRESS_BLOG_SETUP.md](WORDPRESS_BLOG_SETUP.md) - Setup and configuration guide
- [WORDPRESS_BLOG_API.md](WORDPRESS_BLOG_API.md) - Detailed API reference
- [WORDPRESS_BLOG_OPERATIONS.md](WORDPRESS_BLOG_OPERATIONS.md) - Operational procedures

### Create Blog Configuration

Create a new WordPress blog configuration for automated content generation.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_create_config`

**Authentication:** Required (`admin` or `super-admin`)

**Request:**
```json
{
  "name": "Tech Blog Automation",
  "wordpress_url": "https://myblog.com",
  "wordpress_username": "admin",
  "wordpress_password": "abcd efgh ijkl mnop qrst uvwx",
  "openai_api_key": "sk-1234567890abcdef",
  "openai_model": "gpt-4o",
  "replicate_api_key": "r8_abcdef123456",
  "target_word_count": 2500,
  "max_internal_links": 5,
  "google_drive_folder_id": "folder-id-here",
  "tenant_id": "tenant_123"
}
```

**Required Fields:**
- `name` (string): Configuration name
- `wordpress_url` (string): WordPress site URL with protocol
- `wordpress_password` (string): WordPress application password
- `openai_api_key` (string): OpenAI API key

**Optional Fields:**
- `wordpress_username` (string): WordPress username (default: "admin")
- `openai_model` (string): Model to use (default: "gpt-4o")
- `replicate_api_key` (string): For AI image generation
- `target_word_count` (integer): Target article length (default: 2000)
- `max_internal_links` (integer): Max internal links (default: 5)
- `google_drive_folder_id` (string): For asset storage
- `tenant_id` (string): For multi-tenant deployments

**Response:**
```json
{
  "status": "success",
  "data": {
    "config_id": "wpcfg_abc123",
    "name": "Tech Blog Automation",
    "wordpress_url": "https://myblog.com",
    "created_at": "2025-01-20T10:00:00Z"
  }
}
```

**Error Codes:**
- `VALIDATION_ERROR`: Invalid configuration parameters
- `DUPLICATE_CONFIG`: Configuration already exists
- `CONNECTION_ERROR`: Cannot connect to WordPress site
- `CREDENTIAL_ERROR`: Invalid WordPress credentials

---

### Get Blog Configuration

Retrieve a specific blog configuration by ID.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_config&config_id={id}`

**Query Parameters:**
- `config_id` (required): Configuration ID

**Response:**
```json
{
  "status": "success",
  "data": {
    "config_id": "wpcfg_abc123",
    "name": "Tech Blog Automation",
    "wordpress_url": "https://myblog.com",
    "wordpress_username": "admin",
    "openai_model": "gpt-4o",
    "target_word_count": 2500,
    "max_internal_links": 5,
    "articles_generated": 45,
    "status": "active",
    "created_at": "2025-01-10T10:00:00Z",
    "updated_at": "2025-01-20T10:00:00Z"
  }
}
```

---

### List Blog Configurations

Retrieve all WordPress blog configurations for the current tenant.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_list_configs`

**Query Parameters:**
- `tenant_id` (optional): Filter by tenant ID
- `status` (optional): active, inactive, error
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 20)

**Response:**
```json
{
  "status": "success",
  "data": {
    "configs": [
      {
        "config_id": "wpcfg_abc123",
        "name": "Tech Blog",
        "wordpress_url": "https://techblog.com",
        "status": "active",
        "articles_generated": 45,
        "last_article_at": "2025-01-19T15:30:00Z",
        "created_at": "2025-01-10T10:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 3,
      "total_pages": 1
    }
  }
}
```

---

### Update Blog Configuration

Update an existing blog configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_update_config`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "name": "Updated Tech Blog",
  "target_word_count": 3000,
  "max_internal_links": 8,
  "status": "active"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Configuration updated successfully",
  "data": {
    "config_id": "wpcfg_abc123",
    "updated_at": "2025-01-20T11:00:00Z"
  }
}
```

---

### Delete Blog Configuration

Delete a blog configuration (soft delete - articles remain).

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_delete_config`

**Request:**
```json
{
  "config_id": "wpcfg_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Configuration deleted successfully"
}
```

---

### Add Article to Queue

Add a new article to the generation queue.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_add_article`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "title": "Complete Guide to AI-Powered Content Generation",
  "primary_keywords": ["AI content", "automation", "blog writing"],
  "target_audience": "Content marketers and bloggers",
  "tone": "professional",
  "scheduled_for": "2025-01-25T09:00:00Z",
  "categories": ["Technology", "AI"],
  "tags": ["content generation", "automation", "AI writing"]
}
```

**Required Fields:**
- `config_id` (string): Configuration ID
- `title` (string): Article title
- `primary_keywords` (array): Main keywords for SEO

**Optional Fields:**
- `target_audience` (string): Target reader persona
- `tone` (string): professional, casual, technical
- `scheduled_for` (ISO 8601): Publish date/time
- `categories` (array): WordPress categories
- `tags` (array): WordPress tags

**Response:**
```json
{
  "status": "success",
  "data": {
    "article_id": "art_xyz789",
    "title": "Complete Guide to AI-Powered Content Generation",
    "status": "queued",
    "position_in_queue": 3,
    "estimated_completion": "2025-01-20T14:30:00Z",
    "created_at": "2025-01-20T10:00:00Z"
  }
}
```

---

### Get Article Status

Retrieve the status of a specific article in the queue.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_article&article_id={id}`

**Query Parameters:**
- `article_id` (required): Article ID

**Response:**
```json
{
  "status": "success",
  "data": {
    "article_id": "art_xyz789",
    "title": "Complete Guide to AI-Powered Content Generation",
    "status": "generating",
    "progress": {
      "current_step": "writing_chapter_3",
      "total_steps": 8,
      "percent_complete": 37
    },
    "wordpress_post_id": null,
    "wordpress_url": null,
    "created_at": "2025-01-20T10:00:00Z",
    "started_at": "2025-01-20T10:15:00Z",
    "completed_at": null
  }
}
```

**Status Values:**
- `queued`: Waiting in queue
- `generating`: Content generation in progress
- `publishing`: Publishing to WordPress
- `completed`: Successfully published
- `failed`: Generation or publishing failed

---

### List Articles

Retrieve all articles for a configuration or tenant.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_list_articles`

**Query Parameters:**
- `config_id` (optional): Filter by configuration
- `status` (optional): queued, generating, publishing, completed, failed
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 20)

**Response:**
```json
{
  "status": "success",
  "data": {
    "articles": [
      {
        "article_id": "art_xyz789",
        "config_id": "wpcfg_abc123",
        "title": "Complete Guide to AI-Powered Content",
        "status": "completed",
        "wordpress_post_id": 12345,
        "wordpress_url": "https://myblog.com/2025/01/guide-ai-content",
        "word_count": 2543,
        "images_generated": 4,
        "internal_links_added": 5,
        "created_at": "2025-01-20T10:00:00Z",
        "completed_at": "2025-01-20T11:30:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 45,
      "total_pages": 3
    }
  }
}
```

---

### Update Article

Update article metadata or republish.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_update_article`

**Request:**
```json
{
  "article_id": "art_xyz789",
  "title": "Updated: Complete Guide to AI Content",
  "categories": ["Technology", "AI", "Content Marketing"],
  "tags": ["AI", "automation", "content"]
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Article updated successfully"
}
```

---

### Delete Article

Delete an article from the queue (and optionally from WordPress).

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_delete_article`

**Request:**
```json
{
  "article_id": "art_xyz789",
  "delete_from_wordpress": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Article deleted from queue and WordPress"
}
```

---

### Requeue Article

Re-add a failed article to the generation queue.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_requeue_article`

**Request:**
```json
{
  "article_id": "art_xyz789",
  "reset_progress": true
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "article_id": "art_xyz789",
    "status": "queued",
    "position_in_queue": 2
  }
}
```

---

### Add Internal Link

Add an internal link configuration for automated linking.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_add_internal_link`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "anchor_text": "AI content generation",
  "target_url": "https://myblog.com/ai-content-guide",
  "keyword_triggers": ["AI content", "automated writing"]
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "link_id": "link_123",
    "created_at": "2025-01-20T10:00:00Z"
  }
}
```

---

### List Internal Links

Retrieve all internal link configurations.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_list_internal_links&config_id={id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "links": [
      {
        "link_id": "link_123",
        "anchor_text": "AI content generation",
        "target_url": "https://myblog.com/ai-content-guide",
        "keyword_triggers": ["AI content", "automated writing"],
        "times_used": 12,
        "created_at": "2025-01-15T10:00:00Z"
      }
    ],
    "total": 15
  }
}
```

---

### Update Internal Link

Update an internal link configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_update_internal_link`

**Request:**
```json
{
  "link_id": "link_123",
  "anchor_text": "Updated anchor text",
  "target_url": "https://myblog.com/new-url"
}
```

---

### Delete Internal Link

Remove an internal link configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_delete_internal_link`

**Request:**
```json
{
  "link_id": "link_123"
}
```

---

### Add Category

Add a category to a configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_add_category`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "category_name": "Technology"
}
```

---

### Get Categories

Retrieve all categories for a configuration.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_categories&config_id={id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "categories": ["Technology", "AI", "Content Marketing", "SEO"]
  }
}
```

---

### Remove Category

Remove a category from a configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_remove_category`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "category_name": "Technology"
}
```

---

### Add Tag

Add a tag to a configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_add_tag`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "tag_name": "automation"
}
```

---

### Get Tags

Retrieve all tags for a configuration.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_tags&config_id={id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "tags": ["automation", "AI", "content", "writing", "SEO"]
  }
}
```

---

### Remove Tag

Remove a tag from a configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_remove_tag`

**Request:**
```json
{
  "config_id": "wpcfg_abc123",
  "tag_name": "automation"
}
```

---

### Get Execution Log

Retrieve detailed execution logs for an article.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_execution_log&article_id={id}`

**Query Parameters:**
- `article_id` (required): Article ID

**Response:**
```json
{
  "status": "success",
  "data": {
    "article_id": "art_xyz789",
    "logs": [
      {
        "timestamp": "2025-01-20T10:15:00Z",
        "level": "info",
        "step": "structure_generation",
        "message": "Generated article structure with 6 chapters",
        "details": {
          "chapters": 6,
          "estimated_words": 2500
        }
      },
      {
        "timestamp": "2025-01-20T10:18:30Z",
        "level": "info",
        "step": "chapter_writing",
        "message": "Completed chapter 1: Introduction",
        "details": {
          "chapter": 1,
          "word_count": 420
        }
      },
      {
        "timestamp": "2025-01-20T11:25:00Z",
        "level": "info",
        "step": "publishing",
        "message": "Published to WordPress successfully",
        "details": {
          "post_id": 12345,
          "url": "https://myblog.com/2025/01/guide"
        }
      }
    ],
    "total_logs": 45
  }
}
```

---

### Get Queue Status

Get current queue status and statistics.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_queue_status`

**Query Parameters:**
- `config_id` (optional): Filter by configuration

**Response:**
```json
{
  "status": "success",
  "data": {
    "queue_depth": 5,
    "articles_by_status": {
      "queued": 3,
      "generating": 2,
      "publishing": 0,
      "completed": 42,
      "failed": 1
    },
    "estimated_wait_time": "45 minutes",
    "processor_status": "running",
    "last_processed": "2025-01-20T11:30:00Z"
  }
}
```

---

### Get Metrics

Retrieve performance metrics for blog automation.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_metrics`

**Query Parameters:**
- `config_id` (optional): Filter by configuration
- `start_date` (ISO 8601): Start date
- `end_date` (ISO 8601): End date

**Response:**
```json
{
  "status": "success",
  "data": {
    "period": {
      "start": "2025-01-01T00:00:00Z",
      "end": "2025-01-20T23:59:59Z"
    },
    "articles_generated": 45,
    "total_words": 112500,
    "average_word_count": 2500,
    "average_generation_time": "1.5 hours",
    "success_rate": 97.8,
    "images_generated": 180,
    "internal_links_added": 225,
    "openai_api_cost": 45.67,
    "replicate_api_cost": 12.30,
    "top_categories": [
      {"name": "Technology", "count": 20},
      {"name": "AI", "count": 15}
    ]
  }
}
```

---

### Health Check

Check WordPress Blog automation system health.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_health_check`

**Query Parameters:**
- `config_id` (optional): Check specific configuration

**Response:**
```json
{
  "status": "success",
  "data": {
    "overall_health": "healthy",
    "components": {
      "queue_processor": {
        "status": "running",
        "last_heartbeat": "2025-01-20T11:29:45Z"
      },
      "wordpress_connection": {
        "status": "connected",
        "latency_ms": 234
      },
      "openai_api": {
        "status": "available",
        "latency_ms": 567
      },
      "replicate_api": {
        "status": "available",
        "latency_ms": 890
      },
      "google_drive": {
        "status": "connected",
        "latency_ms": 145
      }
    },
    "warnings": [],
    "errors": []
  }
}
```

**Health Status Values:**
- `healthy`: All systems operational
- `degraded`: Some components slow or unavailable
- `unhealthy`: Critical components failing

---

## Agent Types & Discovery

Manage specialized agent types and discover available agent configurations for advanced multi-agent workflows.

**Related Documentation:**
- [SPECIALIZED_AGENTS_SPECIFICATION.md](specs/SPECIALIZED_AGENTS_SPECIFICATION.md) - Complete specification
- [agents/README.md](../agents/README.md) - Agent development guide

### List Agent Types

Retrieve all available specialized agent types.

**Endpoint:** `GET /admin-api.php?action=list_agent_types`

**Authentication:** Required

**Response:**
```json
{
  "status": "success",
  "data": {
    "agent_types": [
      {
        "type_id": "wordpress_blog",
        "name": "WordPress Blog Automation",
        "description": "AI-powered blog content generation and publishing",
        "version": "1.0.0",
        "capabilities": [
          "content_generation",
          "image_generation",
          "wordpress_publishing",
          "seo_optimization"
        ],
        "required_config": [
          "wordpress_url",
          "wordpress_credentials",
          "openai_api_key"
        ],
        "status": "active"
      },
      {
        "type_id": "data_analyst",
        "name": "Data Analysis Agent",
        "description": "Analyze datasets and generate insights",
        "version": "1.0.0",
        "capabilities": [
          "data_analysis",
          "visualization",
          "statistical_modeling"
        ],
        "required_config": [
          "openai_api_key"
        ],
        "status": "active"
      }
    ],
    "total": 5
  }
}
```

---

### Get Agent Type

Retrieve detailed information about a specific agent type.

**Endpoint:** `GET /admin-api.php?action=get_agent_type&type_id={type_id}`

**Query Parameters:**
- `type_id` (required): Agent type identifier

**Response:**
```json
{
  "status": "success",
  "data": {
    "type_id": "wordpress_blog",
    "name": "WordPress Blog Automation",
    "description": "AI-powered blog content generation and publishing",
    "version": "1.0.0",
    "capabilities": [
      "content_generation",
      "image_generation",
      "wordpress_publishing"
    ],
    "required_config": [
      "wordpress_url",
      "wordpress_credentials",
      "openai_api_key"
    ],
    "optional_config": [
      "replicate_api_key",
      "google_drive_folder_id",
      "target_word_count"
    ],
    "configuration_schema": {
      "wordpress_url": {
        "type": "string",
        "format": "url",
        "required": true,
        "description": "WordPress site URL"
      },
      "target_word_count": {
        "type": "integer",
        "default": 2000,
        "min": 500,
        "max": 10000,
        "description": "Target article length"
      }
    },
    "endpoints": [
      "wordpress_blog_create_config",
      "wordpress_blog_add_article"
    ],
    "documentation_url": "docs/WORDPRESS_BLOG_SETUP.md",
    "status": "active"
  }
}
```

---

### Validate Agent Configuration

Validate agent configuration before creating/updating.

**Endpoint:** `POST /admin-api.php?action=validate_agent_config`

**Request:**
```json
{
  "type_id": "wordpress_blog",
  "configuration": {
    "wordpress_url": "https://myblog.com",
    "wordpress_username": "admin",
    "wordpress_password": "app-password",
    "openai_api_key": "sk-1234567890",
    "target_word_count": 2500
  }
}
```

**Response (Valid):**
```json
{
  "status": "success",
  "data": {
    "valid": true,
    "warnings": [
      "replicate_api_key not provided - image generation will be disabled"
    ]
  }
}
```

**Response (Invalid):**
```json
{
  "status": "error",
  "data": {
    "valid": false,
    "errors": [
      {
        "field": "wordpress_url",
        "message": "Invalid URL format"
      },
      {
        "field": "openai_api_key",
        "message": "API key format invalid (must start with 'sk-')"
      }
    ],
    "warnings": []
  }
}
```

---

### Get Agent Configuration

Retrieve configuration for a specific agent instance.

**Endpoint:** `GET /admin-api.php?action=get_agent_config&agent_id={agent_id}`

**Query Parameters:**
- `agent_id` (required): Agent instance ID

**Response:**
```json
{
  "status": "success",
  "data": {
    "agent_id": "agent_abc123",
    "type_id": "wordpress_blog",
    "name": "My Tech Blog Agent",
    "configuration": {
      "wordpress_url": "https://techblog.com",
      "wordpress_username": "admin",
      "openai_model": "gpt-4o",
      "target_word_count": 2500,
      "max_internal_links": 5
    },
    "status": "active",
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-20T11:00:00Z"
  }
}
```

---

### Save Agent Configuration

Create or update agent configuration for a specialized agent type.

**Endpoint:** `POST /admin-api.php?action=save_agent_config`

**Request:**
```json
{
  "agent_id": "agent_abc123",
  "type_id": "wordpress_blog",
  "name": "My Tech Blog Agent",
  "configuration": {
    "wordpress_url": "https://techblog.com",
    "wordpress_username": "admin",
    "wordpress_password": "app-password",
    "openai_api_key": "sk-1234567890",
    "openai_model": "gpt-4o",
    "target_word_count": 2500
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "agent_id": "agent_abc123",
    "message": "Agent configuration saved successfully",
    "updated_at": "2025-01-20T11:00:00Z"
  }
}
```

---

### Delete Agent Configuration

Delete a specialized agent configuration.

**Endpoint:** `POST /admin-api.php?action=delete_agent_config`

**Request:**
```json
{
  "agent_id": "agent_abc123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Agent configuration deleted successfully"
}
```

---

### Discover Agents

Discover all available agent configurations across types.

**Endpoint:** `GET /admin-api.php?action=discover_agents`

**Query Parameters:**
- `type_id` (optional): Filter by agent type
- `status` (optional): active, inactive, error
- `tenant_id` (optional): Filter by tenant

**Response:**
```json
{
  "status": "success",
  "data": {
    "agents": [
      {
        "agent_id": "agent_abc123",
        "type_id": "wordpress_blog",
        "type_name": "WordPress Blog Automation",
        "name": "Tech Blog Agent",
        "status": "active",
        "tenant_id": "tenant_123",
        "last_activity": "2025-01-20T10:30:00Z",
        "created_at": "2025-01-15T10:00:00Z"
      },
      {
        "agent_id": "agent_xyz789",
        "type_id": "data_analyst",
        "type_name": "Data Analysis Agent",
        "name": "Sales Data Analyzer",
        "status": "active",
        "tenant_id": "tenant_123",
        "last_activity": "2025-01-19T14:20:00Z",
        "created_at": "2025-01-10T09:00:00Z"
      }
    ],
    "total": 8,
    "by_type": {
      "wordpress_blog": 3,
      "data_analyst": 2,
      "customer_support": 3
    }
  }
}
```

---

## Health & Status

### Health Check

Check API health status.

**Endpoint:** `GET /admin-api.php?action=health`

**Response:**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "timestamp": "2025-01-20T10:30:00Z",
  "services": {
    "database": {
      "status": "healthy",
      "latency_ms": 5
    },
    "openai": {
      "status": "healthy",
      "latency_ms": 234
    },
    "redis": {
      "status": "healthy",
      "latency_ms": 2
    }
  },
  "system": {
    "php_version": "8.2.0",
    "memory_usage": "45 MB",
    "cpu_load": 0.23
  }
}
```

**Status Values:**
- `healthy`: All systems operational
- `degraded`: Some systems experiencing issues
- `unhealthy`: Critical systems down

---

## Rate Limiting

All Admin API endpoints are rate-limited to prevent abuse.

**Default Limits:**
- **Authentication endpoints:** 5 requests per 5 minutes per IP
- **Admin API:** 300 requests per minute per tenant
- **Export operations:** 10 requests per hour per tenant

**Rate Limit Headers:**
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 245
X-RateLimit-Reset: 1705747800
```

**Rate Limit Response (429):**
```json
{
  "status": "error",
  "message": "Rate limit exceeded",
  "code": "RATE_LIMIT_EXCEEDED",
  "details": {
    "limit": 300,
    "window": 60,
    "retry_after": 30
  }
}
```

---

## Best Practices

### Security

1. **Never expose API keys** in client-side code
2. **Use HTTPS** for all API requests
3. **Rotate API keys** regularly (recommended: every 90 days)
4. **Enable IP whitelisting** for production API keys
5. **Implement request signing** for sensitive operations
6. **Monitor audit logs** for suspicious activity

### Performance

1. **Use pagination** for large result sets
2. **Cache responses** when appropriate
3. **Use webhooks** instead of polling
4. **Batch operations** when possible
5. **Enable compression** for API responses

### Error Handling

1. **Implement exponential backoff** for retries
2. **Check rate limit headers** before requests
3. **Log correlation IDs** for debugging
4. **Handle all error codes** gracefully
5. **Monitor error rates** in production

### Multi-Tenancy

1. **Always specify tenant_id** in requests
2. **Validate tenant isolation** in responses
3. **Monitor per-tenant quotas**
4. **Implement tenant-specific rate limiting**
5. **Audit cross-tenant access attempts**

---

## Support

For additional help:

- **Documentation:** [docs/](../README.md)
- **API Reference:** [api.md](api.md)
- **Issue Tracker:** GitHub Issues
- **Email Support:** support@your-domain.com

---

**Version:** 1.0.0
**Last Updated:** January 2025
**API Stability:** Stable (no breaking changes in v1.x)

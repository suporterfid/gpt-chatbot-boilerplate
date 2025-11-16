# Whitelabel Agent Publishing - Operator Guide

## Overview

Whitelabel Agent Publishing allows you to publish any configured Agent as a standalone, branded chatbot accessible via a direct URL. Each published agent operates with strict security scoping to prevent cross-tenant leakage.

## Key Features

- **Unique Public URLs**: Each agent gets a unique, non-guessable public ID
- **Custom Branding**: Configure title, logo, theme, welcome messages, and disclaimers
- **HMAC Security**: All requests cryptographically bound to the intended agent
- **Rate Limiting**: Per-agent rate limiting with configurable limits
- **No Fallbacks**: Missing or invalid URLs never fall back to other agents (strict 404)
- **Optional Custom Domains**: Map custom domains to specific agents

## Quick Start

### 1. Create and Configure an Agent

First, create an agent via the Admin UI or API:

```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Bot",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "system_message": "You are a helpful customer support assistant."
  }'
```

### 2. Enable Whitelabel Publishing

Enable whitelabel for your agent:

```bash
curl -X POST "http://localhost/admin-api.php?action=enable_whitelabel&id=AGENT_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "wl_title": "Customer Support",
    "wl_logo_url": "https://yourdomain.com/logo.png",
    "wl_welcome_message": "Hello! How can I help you today?",
    "wl_placeholder": "Type your question...",
    "wl_enable_file_upload": false,
    "wl_theme": {
      "primaryColor": "#1FB8CD",
      "backgroundColor": "#F5F5F5",
      "surfaceColor": "#FFFFFF",
      "textColor": "#333333",
      "borderRadius": "8px"
    }
  }'
```

Response includes:
- `agent_public_id`: The unique public identifier
- `wl_hmac_secret`: The HMAC secret (store securely, rotate periodically)

### 3. Get the Whitelabel URL

```bash
curl -X GET "http://localhost/admin-api.php?action=get_whitelabel_url&id=AGENT_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

Response:
```json
{
  "url": "http://localhost/public/whitelabel.php?id=PUB_abc123xyz",
  "vanity_url": "http://localhost/public/whitelabel.php?path=support-chat",
  "pretty_url": "http://localhost/chat/@support-chat",
  "custom_domain_url": null,
  "agent_public_id": "PUB_abc123xyz"
}
```

### 4. Share the URL

Share the `url` with your users. If you configured a vanity path, the `vanity_url` and `pretty_url` (`/chat/@{vanity_path}`) provide shorter, human-readable links that redirect to the same chatbot page.

## Configuration Options

### Branding Fields

| Field | Type | Description |
|-------|------|-------------|
| `wl_title` | string | Page title and chatbot name |
| `wl_logo_url` | string | URL to logo image (displayed in header) |
| `wl_welcome_message` | string | Initial greeting message |
| `wl_placeholder` | string | Input field placeholder text |
| `wl_footer_brand_md` | string | Footer text (supports Markdown links) |
| `wl_legal_disclaimer_md` | string | Legal disclaimer (supports basic Markdown) |

### Theme Configuration

```json
{
  "primaryColor": "#1FB8CD",
  "backgroundColor": "#F5F5F5",
  "surfaceColor": "#FFFFFF",
  "textColor": "#333333",
  "borderRadius": "8px"
}
```

### Security Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `wl_require_signed_requests` | boolean | true | Require HMAC token validation |
| `wl_token_ttl_seconds` | integer | 600 | Token validity duration (seconds) |
| `allowed_origins` | array | [] | CORS allowed origins (empty = same-origin only) |

### Feature Flags

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `wl_enable_file_upload` | boolean | false | Allow file uploads |

### Rate Limiting

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `wl_rate_limit_requests` | integer | null | Max requests per window (null = use global) |
| `wl_rate_limit_window_seconds` | integer | null | Time window in seconds (null = use global) |

## Admin API Endpoints

### Enable Whitelabel

```
POST /admin-api.php?action=enable_whitelabel&id={agent_id}
```

Enables whitelabel and auto-generates `agent_public_id` and `wl_hmac_secret` if not present.

### Disable Whitelabel

```
POST /admin-api.php?action=disable_whitelabel&id={agent_id}
```

Disables whitelabel (agent no longer accessible via public URL).

### Update Whitelabel Configuration

```
POST /admin-api.php?action=update_whitelabel_config&id={agent_id}
```

Update branding, theme, or settings without affecting public ID or secret.

### Rotate HMAC Secret

```
POST /admin-api.php?action=rotate_whitelabel_secret&id={agent_id}
```

Generates a new HMAC secret. **Important**: All existing tokens become invalid immediately.

### Get Whitelabel URL

```
GET /admin-api.php?action=get_whitelabel_url&id={agent_id}
```

Returns the public URLs for the agent.

## Public Routes

### Whitelabel Page

```
GET /public/whitelabel.php?id={agent_public_id}
GET /public/whitelabel.php?path={vanity_path}
GET /chat/@{vanity_path}
```

Renders the whitelabel chatbot page.

**Behavior**:
- Missing/invalid ID → 404
- Whitelabel disabled → 404
- Valid request → Renders branded chatbot

### Public Agent Config API

```
GET /api/public/agents.php?id={agent_public_id}
```

Returns sanitized public configuration (no secrets).

## Security Best Practices

### 1. Rotate HMAC Secrets Periodically

Schedule regular secret rotation (e.g., every 90 days):

```bash
# Cron job example
0 0 1 * * curl -X POST "http://localhost/admin-api.php?action=rotate_whitelabel_secret&id=AGENT_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 2. Monitor Rate Limits

Set appropriate per-agent rate limits:

```json
{
  "wl_rate_limit_requests": 20,
  "wl_rate_limit_window_seconds": 60
}
```

### 3. Use HTTPS

Always serve whitelabel pages over HTTPS in production.

### 4. Configure CORS Carefully

If using custom domains, configure `allowed_origins`:

```json
{
  "allowed_origins": ["https://chat.example.com"]
}
```

### 5. Clean Up Expired Nonces

Periodically clean up expired nonce records:

```php
// In a cron script
$tokenService->cleanupExpiredNonces();
```

## Troubleshooting

### 404 - Agent Not Found

**Possible causes**:
- Invalid `agent_public_id`
- Whitelabel disabled for agent
- Agent deleted

**Solution**: Verify the agent exists and whitelabel is enabled.

### 403 - Token Invalid

**Possible causes**:
- Token expired (TTL exceeded)
- HMAC secret rotated
- Token tampering
- Nonce replay

**Solution**: User should reload the page to get a fresh token.

### 429 - Rate Limit Exceeded

**Possible causes**:
- User exceeded per-agent rate limit

**Solution**: User should wait before sending more messages.

## Advanced: Custom Domains

### 1. Set Custom Domain

```bash
curl -X POST "http://localhost/admin-api.php?action=update_whitelabel_config&id=AGENT_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "custom_domain": "chat.example.com"
  }'
```

### 2. Configure DNS

Point the custom domain to your server:

```
chat.example.com.  IN  CNAME  yourserver.com.
```

### 3. Configure SSL

Ensure your server has a valid SSL certificate for the custom domain.

### 4. Update CORS

```json
{
  "allowed_origins": ["https://chat.example.com"]
}
```

### 5. Access

Users can now access the chatbot at:
```
https://chat.example.com/public/whitelabel.php
```

The system will auto-resolve the agent based on the `HTTP_HOST` header.

## Monitoring and Logs

All whitelabel events are logged with prefix `[Whitelabel]`:

```
[2024-01-15 10:30:45][info][Whitelabel][192.168.1.1] Whitelabel page loaded for agent: Customer Support (PUB_abc123)
[2024-01-15 10:30:46][info][Whitelabel][192.168.1.1] Resolved agent via public ID: PUB_abc123
[2024-01-15 10:30:47][error][Whitelabel][192.168.1.1] Whitelabel token validation failed for agent: PUB_abc123
```

Monitor these logs for:
- Failed token validations (potential attacks)
- 404 errors (broken links)
- Rate limit violations

## Example: Full Setup

```bash
#!/bin/bash
ADMIN_TOKEN="your_admin_token_here"
BASE_URL="http://localhost"

# 1. Create agent
AGENT_ID=$(curl -s -X POST "${BASE_URL}/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Support Bot",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7
  }' | jq -r '.id')

echo "Agent created: ${AGENT_ID}"

# 2. Enable whitelabel
curl -s -X POST "${BASE_URL}/admin-api.php?action=enable_whitelabel&id=${AGENT_ID}" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "wl_title": "24/7 Support Chat",
    "wl_welcome_message": "Hi! How can we help you today?",
    "wl_theme": {
      "primaryColor": "#007bff"
    }
  }' | jq .

# 3. Get URL
URL=$(curl -s -X GET "${BASE_URL}/admin-api.php?action=get_whitelabel_url&id=${AGENT_ID}" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" | jq -r '.url')

echo "Whitelabel URL: ${URL}"
```

## Summary

Whitelabel Agent Publishing provides a secure, branded, and isolated chatbot experience for each agent. Follow the security best practices and monitor your logs to ensure a safe deployment.

For questions or issues, refer to the main project documentation or open an issue on GitHub.

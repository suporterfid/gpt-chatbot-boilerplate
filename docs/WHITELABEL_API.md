# Whitelabel Publishing API Reference

## Admin API Endpoints

All admin endpoints require authentication via `Authorization: Bearer <ADMIN_TOKEN>` header.

### Enable Whitelabel

Enables whitelabel publishing for an agent and auto-generates required fields.

**Endpoint**: `POST /admin-api.php?action=enable_whitelabel&id={agent_id}`

**Request Body**:
```json
{
  "wl_title": "My Chatbot",
  "wl_logo_url": "https://example.com/logo.png",
  "wl_welcome_message": "Hello! How can I help?",
  "wl_placeholder": "Type your message...",
  "wl_enable_file_upload": false,
  "wl_theme": {
    "primaryColor": "#1FB8CD",
    "backgroundColor": "#F5F5F5",
    "surfaceColor": "#FFFFFF",
    "textColor": "#333333",
    "borderRadius": "8px"
  },
  "wl_legal_disclaimer_md": "This is a test chatbot. No data is stored.",
  "wl_footer_brand_md": "Powered by [YourCompany](https://example.com)",
  "wl_rate_limit_requests": 10,
  "wl_rate_limit_window_seconds": 60
}
```

**Response**:
```json
{
  "id": "agent-uuid",
  "name": "My Agent",
  "whitelabel_enabled": true,
  "agent_public_id": "PUB_abc123xyz",
  "wl_hmac_secret": "a1b2c3d4...",
  "wl_title": "My Chatbot",
  ...
}
```

---

### Disable Whitelabel

Disables whitelabel publishing for an agent. Agent becomes inaccessible via public URL.

**Endpoint**: `POST /admin-api.php?action=disable_whitelabel&id={agent_id}`

**Request Body**: None

**Response**:
```json
{
  "id": "agent-uuid",
  "name": "My Agent",
  "whitelabel_enabled": false,
  ...
}
```

---

### Update Whitelabel Configuration

Updates whitelabel settings without affecting public ID or HMAC secret.

**Endpoint**: `POST /admin-api.php?action=update_whitelabel_config&id={agent_id}`

**Request Body** (all fields optional):
```json
{
  "wl_title": "Updated Title",
  "wl_logo_url": "https://example.com/new-logo.png",
  "wl_welcome_message": "Updated welcome message",
  "wl_placeholder": "Updated placeholder",
  "wl_enable_file_upload": true,
  "wl_theme": {
    "primaryColor": "#FF5733"
  },
  "wl_legal_disclaimer_md": "Updated disclaimer",
  "wl_footer_brand_md": "Updated footer",
  "wl_rate_limit_requests": 20,
  "wl_rate_limit_window_seconds": 120,
  "vanity_path": "support-chat",
  "custom_domain": "chat.example.com",
  "allowed_origins": ["https://chat.example.com"],
  "wl_token_ttl_seconds": 900,
  "wl_require_signed_requests": true
}
```

**Response**:
```json
{
  "id": "agent-uuid",
  "wl_title": "Updated Title",
  ...
}
```

---

### Rotate HMAC Secret

Generates a new HMAC secret. All existing tokens become invalid.

**Endpoint**: `POST /admin-api.php?action=rotate_whitelabel_secret&id={agent_id}`

**Request Body**: None

**Response**:
```json
{
  "id": "agent-uuid",
  "wl_hmac_secret": "new-secret-abc123...",
  ...
}
```

**Warning**: This invalidates all active user sessions. Users must reload the page.

---

### Get Whitelabel URL

Retrieves the public URLs for a whitelabel agent.

**Endpoint**: `GET /admin-api.php?action=get_whitelabel_url&id={agent_id}`

**Response**:
```json
{
  "url": "http://localhost/public/whitelabel.php?id=PUB_abc123xyz",
  "vanity_url": "http://localhost/public/whitelabel.php?path=support-chat",
  "custom_domain_url": "https://chat.example.com",
  "agent_public_id": "PUB_abc123xyz"
}
```

Fields:
- `url`: Primary public URL (always available)
- `vanity_url`: Short vanity URL (if configured)
- `custom_domain_url`: Custom domain URL (if configured)
- `agent_public_id`: The public identifier

---

## Public Endpoints

These endpoints are accessible without authentication.

### Whitelabel Page

Renders the whitelabel chatbot page for a specific agent.

**Endpoint**: `GET /public/whitelabel.php?id={agent_public_id}`

**Alternative**: `GET /public/whitelabel.php?path={vanity_path}`

**Parameters**:
- `id`: Agent public ID (e.g., `PUB_abc123xyz`)
- `path`: Vanity path (if configured)

**Response**: HTML page with embedded chatbot widget

**Error Responses**:
- `404`: Agent not found, not published, or invalid ID
- `500`: Internal server error

**Example**:
```
GET /public/whitelabel.php?id=PUB_abc123xyz
```

---

### Public Agent Configuration

Returns sanitized, public configuration for a whitelabel agent.

**Endpoint**: `GET /api/public/agents.php?id={agent_public_id}`

**Parameters**:
- `id`: Agent public ID

**Response**:
```json
{
  "title": "My Chatbot",
  "logo_url": "https://example.com/logo.png",
  "theme": {
    "primaryColor": "#1FB8CD",
    "backgroundColor": "#F5F5F5",
    "surfaceColor": "#FFFFFF",
    "textColor": "#333333",
    "borderRadius": "8px"
  },
  "welcome_message": "Hello! How can I help?",
  "placeholder": "Type your message...",
  "enable_file_upload": false,
  "legal_disclaimer_md": "This is a test chatbot.",
  "footer_brand_md": "Powered by [YourCompany](https://example.com)",
  "api_type": "responses"
}
```

**Error Responses**:
```json
{
  "error": {
    "code": "AGENT_NOT_FOUND",
    "message": "Agent not found or not published"
  }
}
```

Error codes:
- `MISSING_AGENT_ID`: Agent ID not provided
- `AGENT_NOT_FOUND`: Agent not found or whitelabel disabled
- `INTERNAL_ERROR`: Server error

**Cache Headers**:
- `ETag`: MD5 hash of configuration
- `Cache-Control: public, max-age=300` (5 minutes)

---

## Chat API with Whitelabel

The existing chat endpoint `/chat-unified.php` accepts whitelabel parameters.

**Endpoint**: `POST /chat-unified.php`

**Request Headers**:
```
Content-Type: application/json
```

**Request Body**:
```json
{
  "message": "Hello, how are you?",
  "conversation_id": "conv_abc123",
  "api_type": "responses",
  "agent_public_id": "PUB_abc123xyz",
  "wl_token": "eyJhaWQiOiJQVUJfYWJjMTIzIiwidHMiOjE3MDAwMDAwMDAsIm5vbmNlIjoiYWJjZGVmIn0.abc123...",
  "stream": true
}
```

**Whitelabel Parameters**:
- `agent_public_id` (required in whitelabel mode): Public agent ID
- `wl_token` (required in whitelabel mode): HMAC-signed token

**Response** (SSE streaming):
```
event: message
data: {"type":"start","response_id":"resp_123"}

event: message
data: {"type":"chunk","text":"Hello"}

event: message
data: {"type":"chunk","text":"! How"}

event: message
data: {"type":"done","response_id":"resp_123"}
```

**Error Responses**:

```
event: error
data: {"code":"WL_TOKEN_MISSING","message":"Unauthorized: token required. Please reload the page."}
```

```
event: error
data: {"code":"WL_TOKEN_INVALID","message":"Unauthorized or expired link. Please reload the page."}
```

```
event: error
data: {"code":"WL_AGENT_NOT_FOUND","message":"Agent not found or not published"}
```

Error codes:
- `WL_TOKEN_MISSING`: Token not provided
- `WL_TOKEN_INVALID`: Token validation failed (expired, wrong signature, or replay)
- `WL_AGENT_NOT_FOUND`: Agent not found
- `WL_NOT_ENABLED`: Whitelabel not enabled

---

## Token Structure

Whitelabel tokens are HMAC-SHA256 signed JWT-like structures.

**Format**: `{base64url(payload)}.{base64url(signature)}`

**Payload**:
```json
{
  "aid": "PUB_abc123xyz",
  "ts": 1700000000,
  "nonce": "abc123def456",
  "exp": 1700000600
}
```

Fields:
- `aid`: Agent public ID
- `ts`: Issued timestamp (Unix)
- `nonce`: Random 16-char nonce (for replay protection)
- `exp`: Expiration timestamp (Unix)

**Signature**: `HMAC-SHA256(base64url(payload), wl_hmac_secret)`

**Validation Rules**:
1. Signature must match (timing-safe comparison)
2. `aid` must match provided `agent_public_id`
3. `ts` must be within TTL (`exp > now`)
4. `nonce` must not have been used before

**Security Notes**:
- Tokens are single-use (nonce replay protection)
- Generated server-side on page load
- Never exposed in logs
- Automatically validated on every chat request

---

## Rate Limiting

Whitelabel agents support per-agent rate limiting.

**Configuration**:
```json
{
  "wl_rate_limit_requests": 20,
  "wl_rate_limit_window_seconds": 60
}
```

**Behavior**:
- Rate limits are scoped per agent + IP
- Window is sliding (not fixed intervals)
- Exceeded limits return HTTP 429

**Error Response**:
```
HTTP/1.1 429 Too Many Requests
Content-Type: application/json

{
  "error": "Rate limit exceeded. Please wait before sending another message."
}
```

**Default Limits** (if not configured per-agent):
- Uses global `chat_config.rate_limit_requests`
- Uses global `chat_config.rate_limit_window`

---

## CORS Configuration

Whitelabel agents support custom CORS policies.

**Configuration**:
```json
{
  "allowed_origins": [
    "https://chat.example.com",
    "https://support.example.com"
  ]
}
```

**Behavior**:
- If `allowed_origins` is empty: same-origin only
- If `allowed_origins` is set: validates `Origin` header
- Matched origin is set in `Access-Control-Allow-Origin`
- Unmatched origins are blocked

**Headers Set**:
```
Access-Control-Allow-Origin: https://chat.example.com
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

---

## Field Reference

### Database Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `whitelabel_enabled` | boolean | false | Whitelabel publishing enabled |
| `agent_public_id` | string | null | Public identifier (PUB_xxx) |
| `vanity_path` | string | null | Short vanity path |
| `custom_domain` | string | null | Custom domain (verified) |
| `wl_require_signed_requests` | boolean | true | Require HMAC tokens |
| `wl_hmac_secret` | string | null | HMAC secret (64 hex chars) |
| `wl_token_ttl_seconds` | integer | 600 | Token validity (seconds) |
| `allowed_origins_json` | JSON | null | CORS allowed origins |
| `wl_title` | string | null | Page/chatbot title |
| `wl_logo_url` | string | null | Logo URL |
| `wl_theme_json` | JSON | null | Theme configuration |
| `wl_welcome_message` | string | null | Welcome message |
| `wl_placeholder` | string | null | Input placeholder |
| `wl_enable_file_upload` | boolean | false | File upload enabled |
| `wl_legal_disclaimer_md` | text | null | Legal disclaimer (Markdown) |
| `wl_footer_brand_md` | text | null | Footer branding (Markdown) |
| `wl_rate_limit_requests` | integer | null | Rate limit (requests) |
| `wl_rate_limit_window_seconds` | integer | null | Rate limit window (seconds) |

---

## Examples

### Complete Setup

```bash
# 1. Enable whitelabel
curl -X POST "http://localhost/admin-api.php?action=enable_whitelabel&id=agent-123" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "wl_title": "Support Chat",
    "wl_theme": {"primaryColor": "#007bff"}
  }'

# 2. Get URL
curl -X GET "http://localhost/admin-api.php?action=get_whitelabel_url&id=agent-123" \
  -H "Authorization: Bearer TOKEN"

# 3. Share URL with users
# Users visit: http://localhost/public/whitelabel.php?id=PUB_abc123
```

### Update Branding

```bash
curl -X POST "http://localhost/admin-api.php?action=update_whitelabel_config&id=agent-123" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "wl_title": "New Title",
    "wl_logo_url": "https://example.com/new-logo.png"
  }'
```

### Rotate Secret

```bash
curl -X POST "http://localhost/admin-api.php?action=rotate_whitelabel_secret&id=agent-123" \
  -H "Authorization: Bearer TOKEN"
```

---

## Security Considerations

1. **Never expose HMAC secrets** in client code or logs
2. **Always use HTTPS** in production
3. **Rotate secrets periodically** (e.g., every 90 days)
4. **Monitor failed token validations** for potential attacks
5. **Set appropriate rate limits** to prevent abuse
6. **Configure CORS carefully** for custom domains
7. **Clean up expired nonces** regularly

---

## Support

For issues or questions, refer to:
- [Operator Guide](WHITELABEL_PUBLISHING.md)
- Main project README
- GitHub Issues

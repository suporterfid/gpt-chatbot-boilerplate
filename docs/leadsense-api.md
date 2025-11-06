# LeadSense Admin API Reference

## Authentication

All endpoints require admin authentication via Bearer token:

```
Authorization: Bearer <ADMIN_TOKEN>
```

Set `ADMIN_TOKEN` in your `.env` file.

## Endpoints

### 1. List Leads

List and filter leads with pagination.

**Endpoint:** `GET /admin-api.php?action=list_leads`

**Query Parameters:**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `agent_id` | string | Filter by agent ID | `agent_123` |
| `status` | string | Filter by status | `new`, `open`, `won`, `lost`, `nurture` |
| `qualified` | boolean | Filter by qualification | `true`, `false` |
| `min_score` | integer | Minimum score | `70` |
| `from` | string | Start date (ISO 8601) | `2024-01-01T00:00:00Z` |
| `to` | string | End date (ISO 8601) | `2024-12-31T23:59:59Z` |
| `q` | string | Search query | `acme` |
| `limit` | integer | Results per page (max 100) | `50` |
| `offset` | integer | Pagination offset | `0` |

**Response:**

```json
{
  "leads": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "agent_id": "agent_123",
      "conversation_id": "conv_abc",
      "name": "Jo** Do*",
      "company": "Acme Inc",
      "role": "CTO",
      "email": "jo**@a***.com",
      "phone": "***-***-1234",
      "industry": "technology",
      "company_size": "enterprise",
      "interest": "Looking for pricing...",
      "intent_level": "high",
      "score": 85,
      "qualified": 1,
      "status": "new",
      "source_channel": "web",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ],
  "count": 1
}
```

**Note:** PII is redacted in list view if `pii_redaction` is enabled.

**Example:**

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/admin-api.php?action=list_leads&qualified=true&min_score=70&limit=20"
```

---

### 2. Get Lead

Retrieve detailed information about a specific lead.

**Endpoint:** `GET /admin-api.php?action=get_lead&id=<lead_id>`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Lead UUID |

**Response:**

```json
{
  "lead": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "agent_id": "agent_123",
    "conversation_id": "conv_abc",
    "name": "John Doe",
    "company": "Acme Inc",
    "role": "CTO",
    "email": "john@acme.com",
    "phone": "555-123-4567",
    "industry": "technology",
    "company_size": "enterprise",
    "interest": "Looking for pricing information and integration options",
    "intent_level": "high",
    "score": 85,
    "qualified": 1,
    "status": "new",
    "source_channel": "web",
    "extras": {
      "intent_signals": [...],
      "intent_confidence": 0.85,
      "model": "gpt-4o",
      "prompt_id": "prompt_abc"
    },
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00"
  },
  "events": [
    {
      "id": "event_1",
      "lead_id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "detected",
      "payload": {...},
      "created_at": "2024-01-15 10:30:00"
    },
    {
      "id": "event_2",
      "lead_id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "qualified",
      "payload": {...},
      "created_at": "2024-01-15 10:30:01"
    }
  ],
  "score_history": [
    {
      "id": "score_1",
      "lead_id": "550e8400-e29b-41d4-a716-446655440000",
      "score": 85,
      "rationale": [
        {
          "factor": "High commercial intent",
          "points": 75,
          "signals": [...]
        },
        {
          "factor": "Decision maker role: CTO",
          "points": 15
        }
      ],
      "created_at": "2024-01-15 10:30:01"
    }
  ]
}
```

**Note:** PII is NOT redacted in detail view (admin has full access).

**Example:**

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/admin-api.php?action=get_lead&id=550e8400-e29b-41d4-a716-446655440000"
```

---

### 3. Update Lead

Update lead information and status.

**Endpoint:** `POST /admin-api.php?action=update_lead`

**Request Body:**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "won",
  "qualified": true,
  "name": "John Doe",
  "company": "Acme Inc",
  "role": "CTO",
  "email": "john@acme.com",
  "phone": "555-123-4567"
}
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Required. Lead UUID |
| `status` | string | Optional. `new`, `open`, `won`, `lost`, `nurture` |
| `qualified` | boolean | Optional. Qualification status |
| `name` | string | Optional. Lead name |
| `company` | string | Optional. Company name |
| `role` | string | Optional. Job title |
| `email` | string | Optional. Email address |
| `phone` | string | Optional. Phone number |

**Response:**

```json
{
  "success": true,
  "lead_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Example:**

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"550e8400-e29b-41d4-a716-446655440000","status":"won"}' \
  "https://your-domain.com/admin-api.php?action=update_lead"
```

---

### 4. Add Lead Note

Add a note or comment to a lead.

**Endpoint:** `POST /admin-api.php?action=add_lead_note`

**Request Body:**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "note": "Called customer, very interested in enterprise plan"
}
```

**Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Lead UUID |
| `note` | string | Yes | Note content |

**Response:**

```json
{
  "success": true,
  "event_id": "event_xyz"
}
```

**Example:**

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"550e8400-e29b-41d4-a716-446655440000","note":"Scheduled demo for next week"}' \
  "https://your-domain.com/admin-api.php?action=add_lead_note"
```

---

### 5. Rescore Lead

Recalculate a lead's score using current scoring rules.

**Endpoint:** `POST /admin-api.php?action=rescore_lead`

**Request Body:**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Lead UUID |

**Response:**

```json
{
  "success": true,
  "score": 90,
  "qualified": true,
  "rationale": [
    {
      "factor": "High commercial intent",
      "points": 75
    },
    {
      "factor": "Decision maker role: CTO",
      "points": 15
    }
  ]
}
```

**Example:**

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"550e8400-e29b-41d4-a716-446655440000"}' \
  "https://your-domain.com/admin-api.php?action=rescore_lead"
```

---

## Error Responses

All endpoints return standard error responses:

```json
{
  "error": "Error message",
  "code": 400
}
```

**Common Error Codes:**

- `400` - Bad Request (missing/invalid parameters)
- `401` - Unauthorized (invalid/missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (lead not found)
- `405` - Method Not Allowed (wrong HTTP method)
- `503` - Service Unavailable (LeadSense disabled)

---

## Rate Limiting

Admin API endpoints respect the admin rate limits configured in `config.php`:

```php
'admin' => [
    'rate_limit_requests' => 300,  // Requests per window
    'rate_limit_window' => 60,     // Window in seconds
]
```

**Response when rate limited:**

```json
{
  "error": "Rate limit exceeded",
  "code": 429
}
```

---

## Permissions

Endpoints require specific permissions:

| Endpoint | Permission |
|----------|------------|
| `list_leads` | `read` |
| `get_lead` | `read` |
| `update_lead` | `update` |
| `add_lead_note` | `update` |
| `rescore_lead` | `update` |

Configure permissions in admin users table or via RBAC system.

---

## Examples

### Filter Qualified Leads from Last Week

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/admin-api.php?action=list_leads&qualified=true&from=2024-01-08T00:00:00Z&to=2024-01-15T23:59:59Z"
```

### Search for Leads by Company

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/admin-api.php?action=list_leads&q=acme"
```

### Update Lead to Won Status

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"550e8400-e29b-41d4-a716-446655440000","status":"won"}' \
  "https://your-domain.com/admin-api.php?action=update_lead"
```

### Get Lead Timeline

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/admin-api.php?action=get_lead&id=550e8400-e29b-41d4-a716-446655440000"
```

The response includes complete timeline in the `events` array.

---

## Webhooks

When LeadSense detects a qualified lead, it can send webhooks to external systems.

### Configuration

```php
'leadsense' => [
    'notify' => [
        'webhook_url' => 'https://your-crm.com/webhooks/leads',
        'webhook_secret' => 'your-secret-key'
    ]
]
```

### Webhook Payload

```json
{
  "event": "lead.qualified",
  "timestamp": "2024-01-15T10:30:00Z",
  "lead": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Jo** Do*",
    "email": "jo**@a***.com",
    "company": "Acme Inc",
    "role": "CTO",
    "score": 85,
    "qualified": true,
    "intent_level": "high"
  },
  "score": {
    "score": 85,
    "qualified": true,
    "rationale": [...]
  }
}
```

### Webhook Security

Webhooks include HMAC signature when `webhook_secret` is configured:

```
X-LeadSense-Signature: sha256=<hmac-hex>
```

**Verification (PHP):**

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LEADSENSE_SIGNATURE'];
$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (hash_equals($expectedSignature, $signature)) {
    // Valid webhook
}
```

---

## Best Practices

1. **Pagination**: Always use `limit` and `offset` for large result sets
2. **Filtering**: Use specific filters to reduce response size
3. **Caching**: Cache list results client-side when appropriate
4. **Rate Limits**: Implement exponential backoff on 429 errors
5. **Error Handling**: Always check for error responses
6. **Security**: Never expose admin tokens in client-side code
7. **Monitoring**: Log all API calls for audit trails

---

## Support

For issues or feature requests, see:
- Main documentation: `docs/leadsense-overview.md`
- Privacy guide: `docs/leadsense-privacy.md`
- GitHub Issues

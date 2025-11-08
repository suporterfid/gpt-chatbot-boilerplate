# WhatsApp Business & Consent Management API

This document covers the API endpoints for GDPR/LGPD-compliant WhatsApp Business integration and consent management in multi-tenant environments.

## Table of Contents

- [Authentication](#authentication)
- [WhatsApp Template Management](#whatsapp-template-management)
- [Consent Management](#consent-management)
- [Webhook Integration](#webhook-integration)
- [Multi-Tenant Considerations](#multi-tenant-considerations)
- [Examples](#examples)

## Authentication

All API endpoints require authentication using a Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_ADMIN_TOKEN
```

The admin token can be:
1. The `ADMIN_TOKEN` configured in your `.env` file (super admin)
2. An API key generated for a specific user/tenant

## WhatsApp Template Management

WhatsApp Business requires pre-approved message templates for proactive outreach. These endpoints manage template creation, submission, and tracking.

### List Templates

**Endpoint:** `GET /admin-api.php?action=list_templates`

**Query Parameters:**
- `agent_id` (optional): Filter by agent
- `status` (optional): Filter by status (`draft`, `pending`, `approved`, `rejected`)
- `category` (optional): Filter by category (`MARKETING`, `UTILITY`, `AUTHENTICATION`, `SERVICE`)
- `language` (optional): Filter by language code (`en`, `pt_BR`, `es`, etc.)
- `search` (optional): Search in template name or content
- `limit` (optional, default: 100): Results per page
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
[
  {
    "id": "tpl_123",
    "tenant_id": "tenant_abc",
    "agent_id": "agent_xyz",
    "template_name": "welcome_message",
    "template_category": "UTILITY",
    "language_code": "en",
    "status": "approved",
    "content_text": "Hi {{1}}! Welcome to our service.",
    "header_text": "Welcome!",
    "footer_text": "Reply STOP to unsubscribe",
    "quality_score": "HIGH",
    "usage_count": 42,
    "created_at": "2024-01-15T10:30:00Z",
    "approved_at": "2024-01-16T14:20:00Z"
  }
]
```

### Get Template

**Endpoint:** `GET /admin-api.php?action=get_template&id={template_id}`

**Response:** Single template object (same structure as list item)

### Create Template

**Endpoint:** `POST /admin-api.php?action=create_template`

**Request Body:**
```json
{
  "template_name": "welcome_message",
  "template_category": "UTILITY",
  "language_code": "en",
  "content_text": "Hi {{1}}! Welcome to {{2}}.",
  "header_text": "Welcome!",
  "footer_text": "Reply STOP to unsubscribe",
  "agent_id": "agent_xyz"
}
```

**Required Fields:**
- `template_name`: Unique identifier (lowercase, underscores only)
- `template_category`: Must be one of: `MARKETING`, `UTILITY`, `AUTHENTICATION`, `SERVICE`
- `language_code`: ISO language code
- `content_text`: Template body with variable placeholders `{{1}}`, `{{2}}`, etc.

**Response:** Created template object with `status: "draft"`

### Submit Template for Approval

**Endpoint:** `POST /admin-api.php?action=submit_template&id={template_id}`

Submits a draft template to WhatsApp for approval. Note: Actual WhatsApp submission must be done through WhatsApp Business Manager.

**Response:** Updated template with `status: "pending"`

### Approve Template

**Endpoint:** `POST /admin-api.php?action=approve_template&id={template_id}`

Marks a template as approved after WhatsApp approval is received.

**Request Body:**
```json
{
  "whatsapp_template_id": "wa_template_456",
  "quality_score": "HIGH"
}
```

**Response:** Updated template with `status: "approved"`

### Reject Template

**Endpoint:** `POST /admin-api.php?action=reject_template&id={template_id}`

**Request Body:**
```json
{
  "rejection_reason": "Template does not comply with WhatsApp policies"
}
```

**Response:** Updated template with `status: "rejected"`

### Delete Template

**Endpoint:** `DELETE /admin-api.php?action=delete_template&id={template_id}`

Only draft or rejected templates can be deleted. Approved templates must be disabled first.

**Response:**
```json
{
  "success": true,
  "deleted": "tpl_123"
}
```

## Consent Management

Consent management endpoints ensure GDPR/LGPD compliance for WhatsApp communications.

### List Consents

**Endpoint:** `GET /admin-api.php?action=list_consents`

**Query Parameters:**
- `agent_id` (optional): Filter by agent
- `channel` (optional): Filter by channel (`whatsapp`)
- `external_user_id` (optional): Filter by user phone number
- `consent_type` (optional): Filter by type (`service`, `marketing`, `analytics`, `all`)
- `consent_status` (optional): Filter by status (`granted`, `withdrawn`, `pending`, `denied`)
- `limit` (optional, default: 100): Results per page
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
[
  {
    "id": "consent_123",
    "tenant_id": "tenant_abc",
    "agent_id": "agent_xyz",
    "channel": "whatsapp",
    "external_user_id": "+5511999999999",
    "consent_type": "service",
    "consent_status": "granted",
    "consent_method": "first_contact",
    "consent_language": "en",
    "granted_at": "2024-01-15T10:30:00Z",
    "legal_basis": "legitimate_interest",
    "created_at": "2024-01-15T10:30:00Z"
  }
]
```

### Get Consent by ID

**Endpoint:** `GET /admin-api.php?action=get_consent_by_id&id={consent_id}`

**Response:** Single consent object (same structure as list item)

### Grant Consent

**Endpoint:** `POST /admin-api.php?action=grant_consent`

**Request Body:**
```json
{
  "agent_id": "agent_xyz",
  "channel": "whatsapp",
  "external_user_id": "+5511999999999",
  "consent_type": "service",
  "consent_method": "explicit_opt_in",
  "consent_text": "User opted in via web form",
  "consent_language": "en",
  "legal_basis": "consent",
  "ip_address": "203.0.113.42",
  "user_agent": "Mozilla/5.0..."
}
```

**Required Fields:**
- `agent_id`: Agent ID
- `channel`: Communication channel
- `external_user_id`: User identifier (phone number for WhatsApp)

**Response:** Created consent object

### Withdraw Consent

**Endpoint:** `POST /admin-api.php?action=withdraw_consent_by_id&id={consent_id}`

Withdraws consent (opt-out). User will no longer receive messages.

**Response:** Updated consent with `consent_status: "withdrawn"`

### Check Consent

**Endpoint:** `GET /admin-api.php?action=check_consent`

**Query Parameters:**
- `agent_id`: Agent ID
- `channel`: Channel
- `external_user_id`: User identifier
- `consent_type` (optional, default: `service`): Type to check

**Response:**
```json
{
  "has_consent": true
}
```

### Get Consent Audit Log

**Endpoint:** `GET /admin-api.php?action=get_consent_audit&id={consent_id}`

**Query Parameters:**
- `limit` (optional, default: 100): Number of audit entries

**Response:**
```json
[
  {
    "id": "audit_789",
    "consent_id": "consent_123",
    "action": "granted",
    "previous_status": null,
    "new_status": "granted",
    "reason": "Initial consent granted",
    "triggered_by": "user",
    "ip_address": "203.0.113.42",
    "created_at": "2024-01-15T10:30:00Z"
  }
]
```

## Webhook Integration

The WhatsApp webhook automatically handles consent:

**Endpoint:** `POST /channels/whatsapp/{agent_id}/webhook`

### Automatic Consent Flow

1. **First Contact**: When a user sends their first message, implicit consent is automatically granted with:
   - `consent_type`: `service`
   - `consent_method`: `first_contact`
   - `legal_basis`: `legitimate_interest`

2. **Opt-Out Keywords**: Users can send `STOP`, `PARAR`, `UNSUBSCRIBE`, etc. to opt out
   - Consent status changes to `withdrawn`
   - User stops receiving messages

3. **Opt-In Keywords**: Users can send `START`, `INICIAR`, `YES`, etc. to opt back in
   - Consent status changes to `granted`
   - User resumes receiving messages

### Webhook Payload Example

```json
{
  "from": "+5511999999999",
  "to": "+5511888888888",
  "text": "Hello, I need help",
  "timestamp": 1705320600,
  "messageId": "msg_abc123"
}
```

## Multi-Tenant Considerations

### Tenant Isolation

All consent and template records are automatically scoped to the tenant:

1. **Agent-Based Scoping**: Agent determines the tenant
2. **ConsentService**: Automatically filters by `tenant_id`
3. **WhatsAppTemplateService**: Automatically filters by `tenant_id`

### Cross-Tenant Operations

Super admins can:
- View all tenants' consents and templates
- Manage templates across tenants
- Access audit logs for all tenants

Regular admins are restricted to their tenant's data.

### Tenant Context in Services

```php
// Services automatically scope to tenant
$tenantId = $agent['tenant_id'];
$consentService = new ConsentService($db, $tenantId);
$templateService = new WhatsAppTemplateService($db, $tenantId);
```

## Examples

### Complete Onboarding Flow

```bash
# 1. Create tenant, agent, and configure WhatsApp
php scripts/whatsapp_onboarding.php \
  --customer-name "Acme Corp" \
  --whatsapp-number "+5511999999999" \
  --zapi-instance "instance123" \
  --zapi-token "token456" \
  --admin-email "admin@acme.com" \
  --admin-password "SecurePass123!"

# 2. Create welcome template
curl -X POST "https://your-domain.com/admin-api.php?action=create_template" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_name": "welcome_message",
    "template_category": "UTILITY",
    "language_code": "en",
    "content_text": "Hi {{1}}! Welcome to Acme Corp. How can we help you today?",
    "footer_text": "Reply STOP to unsubscribe"
  }'

# 3. Submit for approval
curl -X POST "https://your-domain.com/admin-api.php?action=submit_template&id=tpl_123" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. After WhatsApp approves, mark as approved
curl -X POST "https://your-domain.com/admin-api.php?action=approve_template&id=tpl_123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "whatsapp_template_id": "wa_456",
    "quality_score": "HIGH"
  }'
```

### Checking User Consent

```bash
# Check if user has consented
curl "https://your-domain.com/admin-api.php?action=check_consent&agent_id=agent_xyz&channel=whatsapp&external_user_id=%2B5511999999999" \
  -H "Authorization: Bearer YOUR_TOKEN"

# View consent details
curl "https://your-domain.com/admin-api.php?action=list_consents&external_user_id=%2B5511999999999" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Programmatic Consent Withdrawal

```bash
# Withdraw consent by ID
curl -X POST "https://your-domain.com/admin-api.php?action=withdraw_consent_by_id&id=consent_123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Audit Trail Export

```bash
# Get audit log for a specific consent
curl "https://your-domain.com/admin-api.php?action=get_consent_audit&id=consent_123&limit=50" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Best Practices

### Template Management

1. **Naming Convention**: Use descriptive, lowercase names with underscores
2. **Variables**: Keep variable count minimal (max 10)
3. **Content**: Be clear, concise, and compliant with WhatsApp policies
4. **Testing**: Test templates with real devices before mass deployment

### Consent Management

1. **First Contact**: Let the system auto-grant first-contact consent
2. **Opt-Out Handling**: Never suppress opt-out keywords
3. **Audit Logs**: Regularly review consent changes
4. **Data Retention**: Set appropriate retention periods per jurisdiction
5. **User Rights**: Honor GDPR/LGPD rights (access, erasure, portability)

### Multi-Tenant Operations

1. **Tenant Context**: Always verify tenant context in custom code
2. **Agent Scoping**: Use agent IDs to determine tenant boundaries
3. **Cross-Tenant**: Restrict cross-tenant operations to super admins only
4. **Audit**: Log all multi-tenant administrative actions

## Error Codes

| Code | Meaning |
|------|---------|
| 400  | Bad Request - Missing or invalid parameters |
| 401  | Unauthorized - Invalid or missing token |
| 403  | Forbidden - Insufficient permissions |
| 404  | Not Found - Resource doesn't exist |
| 409  | Conflict - Duplicate entry (e.g., template name already exists) |
| 500  | Internal Server Error - Contact support |

## Rate Limiting

- **Default**: 100 requests per minute per tenant
- **Webhook**: No rate limit (handles inbound messages)
- **Template Creation**: 10 templates per hour per tenant
- **Consent Operations**: No specific limit

## Support

For questions or issues:
- Review the [WhatsApp Onboarding Playbook](WHATSAPP_ONBOARDING_PLAYBOOK.md)
- Check the [Implementation Report](IMPLEMENTATION_REPORT.md)
- Open a GitHub issue for bugs or feature requests

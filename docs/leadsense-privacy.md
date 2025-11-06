# LeadSense Privacy & Security Guide

## Overview

LeadSense is designed with privacy and security as core principles. This guide covers data handling, PII protection, security measures, and compliance considerations.

## Data Collection

### What Data is Collected

LeadSense processes conversation data to detect commercial intent and extract lead information:

**Automatically Collected:**
- Conversation messages (user and assistant)
- Timestamps and session identifiers
- Model and prompt metadata

**Extracted from Conversations:**
- Contact information (email, phone)
- Personal information (name, role/title)
- Company information (name, size, industry)
- Intent signals and confidence scores
- Interest/project descriptions

**Generated Data:**
- Lead quality scores and rationale
- Qualification status
- Event timeline (detected, updated, notified, etc.)

### Data Storage

All lead data is stored in the embedded CRM dataset (SQLite or PostgreSQL):

- **leads** table: Core lead information
- **lead_events** table: Audit trail of all lead activities
- **lead_scores** table: Score history and rationale

**Location:** Configured via `DATABASE_PATH` or `DATABASE_URL` in environment variables.

**Retention:** Configurable via Admin API. No automatic purging by default.

## PII Protection

### Redaction

When `pii_redaction` is enabled (default: `true`), personally identifiable information is masked in:

1. **Slack notifications**
2. **Webhook notifications**
3. **Application logs**
4. **Admin API list view** (optional)

**NOT redacted in:**
- Admin API detail view (admins need full access)
- Database storage (required for lead management)

### Redaction Patterns

**Email Addresses:**
- Original: `john.doe@example.com`
- Redacted: `jo**@e***.com`
- Pattern: First 2 chars of username + masked domain

**Phone Numbers:**
- Original: `555-123-4567`
- Redacted: `***-***-4567`
- Pattern: Last 4 digits visible, rest masked

### Configuration

```php
'leadsense' => [
    'pii_redaction' => true,  // Enable/disable redaction
]
```

Or via environment:

```bash
LEADSENSE_PII_REDACTION=true
```

## Data Security

### At Rest

**Database Security:**
- Use encrypted database connections (SSL/TLS)
- Set proper file permissions on SQLite files (`chmod 600`)
- Consider full-disk encryption for production

**Future Enhancement:**
- Field-level encryption for sensitive data
- Encryption key management via environment variables

### In Transit

**API Communication:**
- Always use HTTPS in production
- Webhook notifications over HTTPS only
- HMAC signatures for webhook authenticity

**Admin API:**
- Bearer token authentication required
- Rate limiting to prevent abuse
- CORS headers for cross-origin requests

### Access Control

**Admin Authentication:**
- Token-based authentication (`ADMIN_TOKEN`)
- Role-based access control (RBAC) support
- Permission checks on all endpoints

**Database Access:**
- Application-only database access
- No direct database exposure
- Prepared statements to prevent SQL injection

## Compliance

### GDPR Considerations

**Data Subject Rights:**

1. **Right to Access:** Use Admin API to retrieve lead data
2. **Right to Rectification:** Use update endpoints to correct data
3. **Right to Erasure:** Delete leads and associated events
4. **Right to Restriction:** Update lead status to restrict processing
5. **Right to Data Portability:** Export via Admin API

**Consent:**
- LeadSense processes conversation data for legitimate business interest
- Ensure your privacy policy discloses lead detection and scoring
- Provide opt-out mechanisms if required

**Data Minimization:**
- Only extracts data present in conversations
- No external data enrichment by default
- Configurable retention periods

### CCPA Considerations

**Consumer Rights:**
- Right to know what data is collected
- Right to delete personal information
- Right to opt-out of sale (N/A - no data sales)

**Disclosures:**
- Update your privacy policy to include LeadSense processing
- Provide contact mechanisms for privacy requests

### PIPEDA / Other Jurisdictions

Consult local privacy laws and regulations. LeadSense provides:
- Transparency (via Admin API and logs)
- Access controls
- Data deletion capabilities
- Audit trails

## Data Retention

### Configuring Retention

**Manual Deletion:**
Use Admin API to delete specific leads or conversations:

```bash
# Delete specific lead
curl -X DELETE -H "Authorization: Bearer TOKEN" \
  "https://your-domain.com/admin-api.php?action=delete_lead&id=<lead_id>"
```

**Automated Retention (Future):**
Configure automatic purging:

```php
'leadsense' => [
    'retention_days' => 90,  // Auto-delete leads older than 90 days
]
```

**Current Implementation:**
Manual deletion only. Use cron jobs or scheduled tasks to call Admin API.

## Audit Logging

### Lead Events

All lead activities are logged in `lead_events`:

- **detected**: Lead initially identified
- **updated**: Lead information changed
- **qualified**: Lead reached qualification threshold
- **notified**: Notification sent to Slack/webhook
- **synced**: Synced to external CRM (future)
- **note**: Admin added note

### Application Logs

LeadSense logs to `logs/chatbot.log`:

```
[2024-01-15 10:30:00][info][LeadSense][127.0.0.1] Lead detected - ID: abc, Score: 85, Qualified: yes
[2024-01-15 10:30:01][info][LeadSense][127.0.0.1] Notification sent - Slack: success, Webhook: success
```

**Log Contents:**
- Redacted PII when `pii_redaction` enabled
- No sensitive credentials
- Structured for analysis

### Admin API Audit

Admin actions are logged:

```
[2024-01-15 10:35:00][info][Admin][192.168.1.1] Listed leads with filters: {"status":"new"}
[2024-01-15 10:36:00][info][Admin][192.168.1.1] Updated lead: abc
```

## Best Practices

### For Administrators

1. **Secure Tokens:** Rotate admin tokens regularly
2. **HTTPS Only:** Never use HTTP in production
3. **Limit Access:** Use RBAC to restrict permissions
4. **Monitor Logs:** Review logs for suspicious activity
5. **Backup Data:** Regular database backups
6. **Update Dependencies:** Keep PHP and libraries updated

### For Developers

1. **Sanitize Inputs:** All user inputs are validated
2. **Parameterized Queries:** Prevent SQL injection
3. **Error Handling:** Don't leak sensitive info in errors
4. **Rate Limiting:** Prevent abuse and DoS
5. **Secure Defaults:** PII redaction enabled by default

### For Users

1. **Privacy Policy:** Disclose LeadSense in your policy
2. **Consent:** Obtain consent if required by law
3. **Transparency:** Inform users about data processing
4. **Opt-Out:** Provide mechanisms to disable tracking
5. **Data Requests:** Handle DSARs (Data Subject Access Requests)

## Security Features

### Implemented

- ✅ PII redaction in notifications/logs
- ✅ HTTPS support
- ✅ Admin token authentication
- ✅ Rate limiting
- ✅ SQL injection protection
- ✅ CORS headers
- ✅ Webhook HMAC signatures
- ✅ Audit logging
- ✅ Debounce to prevent duplication

### Planned

- 🔜 Field-level encryption at rest
- 🔜 Automated retention policies
- 🔜 Enhanced RBAC
- 🔜 Multi-factor authentication
- 🔜 IP whitelisting
- 🔜 Anomaly detection

## Incident Response

### Data Breach Procedure

1. **Detect:** Monitor logs for unauthorized access
2. **Contain:** Revoke compromised tokens immediately
3. **Assess:** Determine scope of breach via audit logs
4. **Notify:** Inform affected parties per legal requirements
5. **Remediate:** Patch vulnerabilities, rotate secrets
6. **Review:** Update security procedures

### Security Contacts

Report vulnerabilities via:
- GitHub Security Advisories
- Private disclosure to maintainers

## Configuration Examples

### Production Setup

```php
'leadsense' => [
    'enabled' => true,
    'pii_redaction' => true,
    'storage' => [
        'encryption' => true,
        'encryption_key' => getenv('LEADSENSE_ENC_KEY'),
    ],
    'notify' => [
        'webhook_url' => 'https://crm.example.com/webhooks/leads',
        'webhook_secret' => getenv('LEADSENSE_WEBHOOK_SECRET'),
    ],
    'max_daily_notifications' => 100,
    'debounce_window' => 300,
]
```

### Development Setup

```php
'leadsense' => [
    'enabled' => true,
    'pii_redaction' => false,  // Full data for testing
    'notify' => [
        'slack_webhook_url' => 'https://hooks.slack.com/services/...',
    ],
    'score_threshold' => 50,  // Lower threshold for testing
]
```

## Privacy Policy Template

Add this section to your privacy policy:

---

**Lead Detection and Qualification**

We use automated systems to detect commercial interest in our chat conversations. When you express interest in our products or services, we may collect and process:

- Contact information (email, phone number)
- Professional information (name, role, company)
- Intent and interest signals from your messages

This information is used to:
- Qualify sales leads
- Personalize our sales approach
- Improve our services

You can request access, correction, or deletion of this data by contacting us at [privacy@example.com].

---

## Frequently Asked Questions

**Q: Is lead data shared with third parties?**
A: Only via configured webhooks to your CRM/sales tools. No data is sold or shared otherwise.

**Q: How long is lead data retained?**
A: Indefinitely by default. Configure retention via Admin API or contact support.

**Q: Can users opt out of lead detection?**
A: Yes. Disable LeadSense per agent or conversation via agent configuration.

**Q: Is the database encrypted?**
A: Connection encryption (SSL/TLS) is supported. Field-level encryption is planned.

**Q: Are logs PII-safe?**
A: Yes, when `pii_redaction` is enabled (default).

**Q: Can I export all lead data?**
A: Yes, via Admin API list endpoint with appropriate filters.

**Q: What happens if webhook delivery fails?**
A: Automatic retry with exponential backoff (3 attempts). Failed notifications are logged.

**Q: Is LeadSense GDPR compliant?**
A: LeadSense provides tools for compliance (access, deletion, export). Your implementation must follow GDPR principles.

## Additional Resources

- [LeadSense Overview](leadsense-overview.md)
- [Admin API Reference](leadsense-api.md)
- [GDPR Guidelines](https://gdpr.eu/)
- [CCPA Overview](https://oag.ca.gov/privacy/ccpa)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

## Version History

- **v1.0** (2024-01): Initial release
  - PII redaction
  - Admin API
  - Audit logging
  - Webhook signatures

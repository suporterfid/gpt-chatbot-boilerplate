# Compliance API Reference

## Overview

This document describes the GDPR/LGPD compliance endpoints available in the Admin API. These endpoints enable data subject rights including access, deletion, and portability.

**Base URL**: `/admin-api.php`  
**Authentication**: Required - Bearer token in Authorization header  
**Last Updated**: 2025-11-08

---

## Table of Contents

1. [Data Export (Right to Access)](#data-export)
2. [Data Deletion (Right to Erasure)](#data-deletion)
3. [Retention Policies](#retention-policies)
4. [Compliance Reporting](#compliance-reporting)
5. [PII Redaction](#pii-redaction)

---

## Data Export

### Export User Data

**Endpoint**: `GET /admin-api.php?action=export_user_data`

**Description**: Export all data for a specific user in compliance with GDPR Article 15 (Right to Access) and LGPD Article 18.

**Query Parameters**:
- `user_id` (string, required) - User identifier (phone number, email, etc.)
- `format` (string, optional) - Export format: `json` or `csv` (default: `json`)

**RBAC Required**: `read`

**Example Request**:
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=export_user_data&user_id=%2B5511999999999&format=json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Example Response (JSON)**:
```json
{
  "request_date": "2025-11-08 14:30:00",
  "user_id": "+5511999999999",
  "tenant_id": 1,
  "data": {
    "consents": [
      {
        "id": 123,
        "consent_type": "all",
        "status": "granted",
        "method": "explicit_opt_in",
        "created_at": "2025-01-15 10:00:00"
      }
    ],
    "consent_history": [
      {
        "consent_id": 123,
        "previous_status": null,
        "new_status": "granted",
        "changed_by": "user",
        "changed_at": "2025-01-15 10:00:00"
      }
    ],
    "sessions": [
      {
        "id": 456,
        "channel": "whatsapp",
        "created_at": "2025-01-15 10:05:00",
        "last_activity_at": "2025-01-15 10:45:00"
      }
    ],
    "messages": [
      {
        "id": 789,
        "message_type": "text",
        "direction": "inbound",
        "content": "Hello, I need help",
        "created_at": "2025-01-15 10:05:30"
      }
    ],
    "conversations": [],
    "usage_events": [],
    "leads": []
  }
}
```

**Example Response (CSV)**:
```csv
=== consents ===
"id","consent_type","status","method","created_at"
"123","all","granted","explicit_opt_in","2025-01-15 10:00:00"

=== messages ===
"id","message_type","direction","content","created_at"
"789","text","inbound","Hello, I need help","2025-01-15 10:05:30"
```

**Audit Trail**: All export requests are logged to `audit_events` table with `event_type='data_export'`.

---

## Data Deletion

### Delete User Data

**Endpoint**: `POST /admin-api.php?action=delete_user_data`

**Description**: Delete all data for a specific user in compliance with GDPR Article 17 (Right to Erasure) and LGPD Article 18.

**Request Body**:
```json
{
  "user_id": "+5511999999999",
  "soft_delete": false,
  "confirm": true
}
```

**Parameters**:
- `user_id` (string, required) - User identifier
- `soft_delete` (boolean, optional) - If `true`, marks as deleted but preserves for audit (default: `false`)
- `confirm` (boolean, required) - Must be `true` to proceed (safety mechanism)

**RBAC Required**: `delete`

**Example Request**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=delete_user_data" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "+5511999999999",
    "soft_delete": false,
    "confirm": true
  }'
```

**Example Response**:
```json
{
  "user_id": "+5511999999999",
  "tenant_id": 1,
  "deletion_date": "2025-11-08 14:35:00",
  "soft_delete": false,
  "records_deleted": {
    "consents": 2,
    "messages": 45,
    "sessions": 3,
    "conversations": 12,
    "leads": 1,
    "usage_logs_anonymized": 67
  },
  "status": "completed"
}
```

**What Gets Deleted**:
1. **Hard Delete** (`soft_delete=false`):
   - All channel messages
   - All channel sessions
   - All conversations
   - All consent records
   - All leads
   - Usage logs are **anonymized** (not deleted, for billing integrity)

2. **Soft Delete** (`soft_delete=true`):
   - Consent records marked as `status='deleted'`
   - Other records deleted normally

**Audit Trail**: Deletion is logged to `audit_events` table with `event_type='data_deletion'`.

**Warning**: This action is **irreversible** for hard deletes.

---

## Retention Policies

### Apply Retention Policy

**Endpoint**: `POST /admin-api.php?action=apply_retention_policy`

**Description**: Apply automated data retention policies to delete old data.

**Request Body**:
```json
{
  "conversation_days": 180,
  "audit_days": 365,
  "usage_days": 730
}
```

**Parameters**:
- `conversation_days` (integer, optional) - Delete conversations older than N days (default: 180)
- `audit_days` (integer, optional) - Delete audit logs older than N days (default: 365)
- `usage_days` (integer, optional) - Archive usage logs older than N days (default: 730)

**RBAC Required**: `super-admin` only

**Example Request**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=apply_retention_policy" \
  -H "Authorization: Bearer YOUR_SUPER_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_days": 180,
    "audit_days": 365,
    "usage_days": 730
  }'
```

**Example Response**:
```json
{
  "execution_date": "2025-11-08 14:40:00",
  "tenant_id": 1,
  "records_deleted": {
    "conversations": 1250,
    "messages": 8930,
    "audit_events": 45230,
    "usage_logs": 123000,
    "expired_consents": 12
  },
  "aggregated": 4560,
  "status": "completed"
}
```

**Automation**: This endpoint should be called via cron job:

```bash
# Add to crontab - Run weekly on Sunday at 2 AM
0 2 * * 0 /usr/bin/php /var/www/html/scripts/apply_retention_policies.php >> /var/log/retention.log 2>&1
```

**Script Usage**:
```bash
# Run for all active tenants
php scripts/apply_retention_policies.php --verbose

# Run for specific tenant
php scripts/apply_retention_policies.php --tenant-id=1 --verbose

# Dry run (see what would be deleted)
php scripts/apply_retention_policies.php --dry-run --verbose

# Custom retention periods
php scripts/apply_retention_policies.php --conversation-days=90 --audit-days=180 --usage-days=365
```

---

## Compliance Reporting

### Generate Compliance Report

**Endpoint**: `GET /admin-api.php?action=generate_compliance_report`

**Description**: Generate a compliance report showing data subject rights activity.

**Query Parameters**:
- `start_date` (string, optional) - Start date in YYYY-MM-DD format (default: 30 days ago)
- `end_date` (string, optional) - End date in YYYY-MM-DD format (default: today)

**RBAC Required**: `read`

**Example Request**:
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=generate_compliance_report&start_date=2025-10-01&end_date=2025-10-31" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Example Response**:
```json
{
  "period": {
    "start": "2025-10-01",
    "end": "2025-10-31"
  },
  "tenant_id": 1,
  "generated_at": "2025-11-08 14:45:00",
  "metrics": {
    "consent_status": [
      {
        "status": "granted",
        "count": 1250
      },
      {
        "status": "withdrawn",
        "count": 23
      },
      {
        "status": "denied",
        "count": 5
      }
    ],
    "deletion_requests": 12,
    "export_requests": 8,
    "active_consented_users": 1227,
    "pii_redaction_enabled": true
  }
}
```

**Use Cases**:
- Monthly compliance reviews
- Audit preparation
- DPO (Data Protection Officer) reporting
- GDPR/LGPD compliance documentation

---

## PII Redaction

### Enable/Disable PII Redaction

**Endpoint**: `POST /admin-api.php?action=set_pii_redaction`

**Description**: Enable or disable automatic PII redaction for tenant's logs and outputs.

**Request Body**:
```json
{
  "enabled": true
}
```

**Parameters**:
- `enabled` (boolean, required) - Enable (`true`) or disable (`false`) PII redaction

**RBAC Required**: `write`

**Example Request**:
```bash
curl -X POST "https://your-domain.com/admin-api.php?action=set_pii_redaction" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "enabled": true
  }'
```

**Example Response**:
```json
{
  "success": true,
  "pii_redaction_enabled": true
}
```

### Check PII Redaction Status

**Endpoint**: `GET /admin-api.php?action=get_pii_redaction_status`

**Description**: Check if PII redaction is enabled for the tenant.

**RBAC Required**: `read`

**Example Request**:
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=get_pii_redaction_status" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Example Response**:
```json
{
  "pii_redaction_enabled": true
}
```

**What Gets Redacted**:
When PII redaction is enabled, the following data is automatically redacted in logs:
- Phone numbers → `[PHONE]`
- Email addresses → `[EMAIL]`
- CPF numbers → `[CPF]`
- Credit card numbers → `[CARD]`
- IP addresses → `[IP]` (optional)

**Implementation**: Uses `PIIRedactor` class from `includes/PIIRedactor.php`.

---

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 400 | Bad Request | Missing or invalid parameters |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Insufficient permissions (RBAC check failed) |
| 404 | Not Found | User or resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error (check logs) |

---

## Best Practices

### Data Export

1. **Limit Frequency**: Implement rate limiting for export requests (e.g., max 5 per day per user)
2. **Secure Delivery**: Send exports via secure channels (encrypted email, portal download)
3. **Time Limits**: Respond to requests within 30 days (GDPR) or 15 days (LGPD)
4. **Audit Trail**: Always log who requested the export and when

### Data Deletion

1. **Confirmation**: Always require explicit confirmation (`confirm: true`)
2. **Grace Period**: Consider a grace period (e.g., 7 days) before permanent deletion
3. **Backup**: Export data before deletion for audit purposes
4. **Communication**: Notify user that deletion is complete
5. **Exceptions**: Don't delete data if legal hold or ongoing investigation

### Retention Policies

1. **Document Policies**: Clearly document retention periods for each data type
2. **Test First**: Use `--dry-run` flag before production execution
3. **Monitor**: Set up alerts for failed retention policy runs
4. **Gradual Rollout**: Start with longer retention periods and adjust
5. **Backup**: Ensure backups are retained separately with own retention policy

### PII Redaction

1. **Opt-In**: Enable PII redaction for tenants handling sensitive data
2. **Logging**: PII redaction should only apply to logs, not operational data
3. **Testing**: Test thoroughly as it may impact debugging
4. **Compliance**: Document which fields are redacted in compliance docs

---

## Compliance Checklist

### GDPR Compliance

- [ ] Data export endpoint implemented (Art. 15)
- [ ] Data deletion endpoint implemented (Art. 17)
- [ ] Consent management in place (Art. 6, 7)
- [ ] Audit logging for all operations (Art. 30)
- [ ] Data retention policies configured (Art. 5)
- [ ] PII redaction available (Art. 32)
- [ ] Data breach notification process (Art. 33)
- [ ] DPA (Data Processing Agreement) signed
- [ ] Privacy policy published
- [ ] Cookie consent implemented (if applicable)

### LGPD Compliance

- [ ] Data export endpoint implemented (Art. 18)
- [ ] Data deletion endpoint implemented (Art. 18)
- [ ] Consent management in place (Art. 8)
- [ ] Audit logging for all operations (Art. 37)
- [ ] Data retention policies configured (Art. 15)
- [ ] PII redaction available (Art. 46)
- [ ] Data breach notification process (Art. 48)
- [ ] DPO appointed (if required)
- [ ] Privacy policy in Portuguese
- [ ] User rights clearly communicated

---

## Support

For questions about compliance features:
- Review [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md)
- Check [SECURITY_MODEL.md](SECURITY_MODEL.md)
- Consult your Data Protection Officer (DPO)
- Legal team for specific compliance questions

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-08 | System | Initial creation |

**Review Cycle**: Quarterly  
**Next Review**: 2026-02-08  
**Owner**: Compliance Team

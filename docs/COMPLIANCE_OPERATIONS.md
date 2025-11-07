# Compliance Operations Guide
## GDPR, LGPD, and WhatsApp Business Compliance

## Overview

This guide provides operational procedures for maintaining GDPR and LGPD compliance in multi-tenant WhatsApp integrations. It covers PII redaction, consent management, data retention, and incident response.

## Table of Contents

- [PII Redaction](#pii-redaction)
- [Consent Management](#consent-management)
- [Data Subject Rights](#data-subject-rights)
- [Data Retention & Deletion](#data-retention--deletion)
- [Audit & Monitoring](#audit--monitoring)
- [Incident Response](#incident-response)
- [Compliance Checklist](#compliance-checklist)

## PII Redaction

### What is PII Redaction?

PII (Personally Identifiable Information) redaction is the process of masking or removing sensitive personal data from logs, notifications, and non-production environments to minimize data exposure risk.

### Redaction Scope

**Always Redacted** (when enabled):
- Application logs
- System notifications
- Slack/webhook alerts
- Error messages
- External analytics
- Development/staging databases

**Never Redacted**:
- Production database (required for functionality)
- Admin API detail views (authorized access needed)
- Encrypted backups
- Legal hold data

### Configuration

#### Enable PII Redaction

**Via Tenant Settings**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=update_tenant" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "tenant-uuid",
    "settings": {
      "compliance": {
        "pii_redaction": true,
        "pii_redaction_scope": ["logs", "notifications", "exports"],
        "redaction_patterns": {
          "email": true,
          "phone": true,
          "cpf": true,
          "ssn": false,
          "credit_card": false
        }
      }
    }
  }'
```

**Via Environment Variables** (global default):
```bash
# .env
LEADSENSE_PII_REDACTION=true
PII_REDACTION_ENABLED=true
```

### Redaction Patterns

#### Email Addresses
- **Original**: `john.doe@example.com`
- **Redacted**: `jo**@e***.com`
- **Pattern**: First 2 chars of username + masked domain

#### Phone Numbers
- **Original**: `+55 11 99999-9999`
- **Redacted**: `***-***-9999`
- **Pattern**: Last 4 digits visible, rest masked

#### CPF (Brazil)
- **Original**: `123.456.789-00`
- **Redacted**: `***.456.***-00`
- **Pattern**: Middle 3 digits + last 2 digits visible

#### Credit Card Numbers
- **Original**: `4111 1111 1111 1111`
- **Redacted**: `**** **** **** 1111`
- **Pattern**: Last 4 digits visible (when enabled)

### Implementation

The `ObservabilityLogger` class handles automatic PII redaction:

```php
// In includes/ObservabilityLogger.php
$logger = new ObservabilityLogger($config);

// Automatically redacts PII if enabled
$logger->info('User contacted', [
    'email' => 'john.doe@example.com',  // Will be redacted in logs
    'phone' => '+5511999999999'          // Will be redacted in logs
]);
```

### Testing Redaction

```bash
# Test redaction patterns
php tests/test_pii_redaction.php

# Expected output:
# Email: john.doe@example.com → jo**@e***.com
# Phone: +5511999999999 → ***-***-9999
# CPF: 123.456.789-00 → ***.456.***-00
```

### Monitoring Redaction

Check logs to ensure redaction is working:

```bash
# Logs should show redacted PII
tail -f logs/chatbot.log | grep -E "(jo\*\*@|***-***-)"

# Should NOT show full email or phone
tail -f logs/chatbot.log | grep -E "(\w+@\w+\.\w+|55\s*11\s*9\d{8})"
```

## Consent Management

### Consent Requirements

Under GDPR Article 6 and LGPD Article 7, valid consent must be:
- **Freely given**: No forced acceptance
- **Specific**: Clear purpose stated
- **Informed**: User understands what they consent to
- **Unambiguous**: Explicit action required (not pre-checked boxes)
- **Revocable**: Easy opt-out mechanism

### Opt-In Flow

**First Contact**:
```
User: Hello
Bot: Hi! 👋 Welcome to [Business Name]. 

By continuing this conversation, you consent to receive automated 
messages from us. Reply STOP anytime to opt out.

How can we help you today?
```

**Explicit Opt-In** (for marketing):
```
Bot: Would you like to receive updates about new products and offers?

Reply YES to subscribe or NO to decline.
```

### Opt-Out Flow

**User Opt-Out**:
```
User: STOP
Bot: You've been unsubscribed from [Business Name].

To resubscribe, send START anytime.
```

**System Action**:
1. Update consent status to `withdrawn`
2. Log opt-out in audit trail
3. Stop sending proactive messages
4. Respond only to direct user messages (if allowed)

### Consent Keywords

**Opt-Out Keywords** (case-insensitive):
- `STOP`
- `UNSUBSCRIBE`
- `CANCEL`
- `OPTOUT`
- `SAIR` (Portuguese)
- `PARAR` (Portuguese)

**Opt-In Keywords** (case-insensitive):
- `START`
- `SUBSCRIBE`
- `YES`
- `OPTIN`
- `SIM` (Portuguese)
- `INICIAR` (Portuguese)

### Consent API

**Check Consent Status**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=get_consent" \
  -H "Authorization: Bearer TOKEN" \
  -d "agent_id=agent-uuid" \
  -d "channel=whatsapp" \
  -d "external_user_id=+5511999999999" \
  -d "consent_type=service"
```

**Grant Consent**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=grant_consent" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-uuid",
    "channel": "whatsapp",
    "external_user_id": "+5511999999999",
    "consent_type": "service",
    "consent_method": "explicit_opt_in",
    "consent_text": "User sent START keyword"
  }'
```

**Withdraw Consent**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=withdraw_consent" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-uuid",
    "channel": "whatsapp",
    "external_user_id": "+5511999999999",
    "consent_type": "all",
    "reason": "User requested opt-out"
  }'
```

### Consent Audit Trail

Every consent change is logged:

```sql
SELECT 
  ca.action,
  ca.previous_status,
  ca.new_status,
  ca.reason,
  ca.triggered_by,
  ca.created_at
FROM consent_audit_log ca
JOIN user_consents uc ON ca.consent_id = uc.id
WHERE uc.external_user_id = '+5511999999999'
ORDER BY ca.created_at DESC;
```

## Data Subject Rights

### Right of Access (GDPR Art. 15, LGPD Art. 18.II)

**Request**: User requests all data stored about them

**Response Time**: 30 days (GDPR), 15 days (LGPD)

**Procedure**:
1. Verify identity of requester
2. Export all data via Admin API
3. Provide structured, machine-readable format
4. Include data processing purposes

**Export Data**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=export_user_data" \
  -H "Authorization: Bearer TOKEN" \
  -d "tenant_id=tenant-uuid" \
  -d "external_user_id=+5511999999999" \
  -d "format=json"
```

**Data Included**:
- Conversation messages
- Session metadata
- Consent records
- Template usage
- Audit logs (limited)

### Right to Rectification (GDPR Art. 16, LGPD Art. 18.III)

**Request**: User requests correction of inaccurate data

**Procedure**:
1. Verify identity
2. Update data via Admin API
3. Log correction in audit trail
4. Notify user of completion

**Update Session Metadata**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=update_session_metadata" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-uuid",
    "channel": "whatsapp",
    "external_user_id": "+5511999999999",
    "metadata": {
      "preferred_name": "John",
      "corrected_at": "2024-11-07T22:00:00Z"
    }
  }'
```

### Right to Erasure (GDPR Art. 17, LGPD Art. 18.VI)

**Request**: User requests deletion of all data (Right to be Forgotten)

**Response Time**: 30 days (GDPR), 15 days (LGPD)

**Procedure**:
1. Verify identity and legal basis for erasure
2. Check for legal obligations to retain data
3. Delete all data or anonymize if retention required
4. Notify sub-processors (OpenAI, Z-API) if needed
5. Confirm deletion to user

**Delete User Data**:
```bash
curl -X DELETE "https://platform.example.com/admin-api.php?action=delete_user_data" \
  -H "Authorization: Bearer TOKEN" \
  -d "tenant_id=tenant-uuid" \
  -d "external_user_id=+5511999999999" \
  -d "confirm=yes"
```

**Deletion Scope**:
- ✅ Conversation messages
- ✅ Session records
- ✅ Consent records
- ✅ Template usage logs
- ⚠️ Audit logs (anonymized, not deleted)
- ⚠️ Legal hold data (retained per regulation)

### Right to Restriction (GDPR Art. 18, LGPD Art. 18.IV)

**Request**: User requests temporary halt of processing

**Procedure**:
1. Update session metadata with restriction flag
2. Stop proactive processing
3. Only respond to direct user messages
4. Log restriction in audit trail

**Restrict Processing**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=restrict_processing" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-uuid",
    "channel": "whatsapp",
    "external_user_id": "+5511999999999",
    "reason": "User requested restriction pending verification"
  }'
```

### Right to Data Portability (GDPR Art. 20, LGPD Art. 18.V)

**Request**: User requests data in portable format

**Procedure**:
1. Export data in JSON or CSV format
2. Provide data within 30 days
3. Ensure format is machine-readable
4. Include metadata and timestamps

**Export Format**:
```json
{
  "user_id": "+5511999999999",
  "tenant": "acme-corp",
  "export_date": "2024-11-07T22:00:00Z",
  "conversations": [...],
  "consents": [...],
  "sessions": [...]
}
```

## Data Retention & Deletion

### Retention Policies

**Default Retention Periods**:
- Conversation messages: 90 days
- Session data: 30 days after last activity
- Consent records: 3 years (legal requirement)
- Audit logs: 12 months
- Backup data: 30 days

**Configure Retention**:
```bash
curl -X POST "https://platform.example.com/admin-api.php?action=update_tenant" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "tenant-uuid",
    "settings": {
      "compliance": {
        "retention_days": 90,
        "auto_delete_enabled": true,
        "legal_hold_enabled": false
      }
    }
  }'
```

### Automated Cleanup

Run the cleanup script regularly (cron job):

```bash
# Daily at 2 AM
0 2 * * * php /path/to/scripts/compliance_cleanup.php --days=90 --verbose

# Or manually
php scripts/compliance_cleanup.php --days=90 --dry-run
```

### Legal Hold

For data under legal investigation or dispute:

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=enable_legal_hold" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "tenant-uuid",
    "external_user_id": "+5511999999999",
    "reason": "Pending legal investigation",
    "case_number": "CASE-2024-001"
  }'
```

Data under legal hold:
- Will NOT be automatically deleted
- Remains accessible to authorized admins
- Flagged in audit logs
- Requires explicit removal of hold before deletion

## Audit & Monitoring

### Compliance Dashboards

**Key Metrics**:
- Opt-out rate (target: <5%)
- Data subject request response time (target: <30 days)
- PII exposure incidents (target: 0)
- Consent refresh rate
- Data retention compliance (target: 100%)

**Access Metrics**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=compliance_metrics" \
  -H "Authorization: Bearer TOKEN" \
  -d "tenant_id=tenant-uuid" \
  -d "period=30d"
```

### Regular Audits

**Daily**:
- Check PII redaction logs
- Review opt-out requests
- Monitor data export requests

**Weekly**:
- Audit consent status changes
- Review data deletion requests
- Check retention policy compliance

**Monthly**:
- Full compliance audit
- Review DPA compliance
- Update privacy documentation
- Train team on changes

**Quarterly**:
- External compliance audit
- DPA review and renewal
- Regulatory update check
- Disaster recovery drill

### Compliance Reports

Generate compliance reports:

```bash
php scripts/generate_compliance_report.php \
  --tenant=tenant-uuid \
  --period=quarterly \
  --output=reports/compliance_Q4_2024.pdf
```

## Incident Response

### Data Breach Response

**Within 1 Hour**:
1. Identify breach scope and affected users
2. Contain breach (revoke credentials, block access)
3. Notify security team
4. Begin incident documentation

**Within 24 Hours**:
1. Notify platform admins
2. Assess data sensitivity
3. Determine notification requirements
4. Prepare breach notification

**Within 72 Hours** (GDPR requirement):
1. Notify supervisory authority (if high risk)
2. Notify affected Data Subjects (if required)
3. Implement remediation measures
4. Update incident documentation

**Breach Notification Template**: See `docs/templates/WHATSAPP_DPA_TEMPLATE.md` Appendix C

### Incident Types

**Type 1: Unauthorized Access**
- Revoke compromised credentials
- Audit access logs
- Notify affected tenants
- Implement additional security measures

**Type 2: PII Exposure**
- Identify exposed data
- Remove from public access
- Notify affected individuals
- Update redaction policies

**Type 3: Data Loss**
- Restore from backups
- Verify data integrity
- Notify if recovery incomplete
- Review backup procedures

**Type 4: Consent Violation**
- Stop unauthorized processing
- Notify affected users
- Update consent management
- Audit consent records

## Compliance Checklist

### Onboarding Compliance

- [ ] DPA signed with customer
- [ ] DPA signed with WhatsApp/Meta
- [ ] Privacy policy updated
- [ ] Consent management configured
- [ ] PII redaction enabled
- [ ] Data retention policy set
- [ ] Incident response plan documented
- [ ] Admin users trained

### Ongoing Compliance

**Daily**:
- [ ] Monitor opt-out requests
- [ ] Check PII redaction logs
- [ ] Review error logs for data exposure

**Weekly**:
- [ ] Audit consent changes
- [ ] Review data subject requests
- [ ] Check retention policy execution

**Monthly**:
- [ ] Generate compliance report
- [ ] Review and update policies
- [ ] Train team on updates
- [ ] Test data export/deletion

**Quarterly**:
- [ ] External compliance audit
- [ ] DPA review
- [ ] Regulatory update check
- [ ] Disaster recovery test

### Documentation Requirements

- [x] Data Processing Agreement (DPA)
- [x] Privacy Policy with WhatsApp disclosure
- [x] Consent management procedures
- [x] Data retention policy
- [x] Incident response plan
- [x] Security assessment report
- [x] Employee training records
- [x] Audit logs and reports

## Support & Resources

### Internal Resources

- **Compliance Documentation**: `docs/`
- **DPA Template**: `docs/templates/WHATSAPP_DPA_TEMPLATE.md`
- **Onboarding Playbook**: `docs/WHATSAPP_ONBOARDING_PLAYBOOK.md`
- **Admin API**: `docs/api.md`

### External Resources

- **GDPR Full Text**: https://gdpr-info.eu/
- **LGPD Full Text**: https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- **WhatsApp Business Policy**: https://www.whatsapp.com/legal/business-policy
- **Meta Business Terms**: https://www.facebook.com/legal/terms/businesstools

### Contacts

- **Data Protection Officer**: dpo@platform.example.com
- **Security Team**: security@platform.example.com
- **Legal Team**: legal@platform.example.com
- **Compliance Support**: compliance@platform.example.com

---

**Document Version**: 1.0  
**Last Updated**: November 2024  
**Next Review**: February 2025  
**Owner**: Compliance Team

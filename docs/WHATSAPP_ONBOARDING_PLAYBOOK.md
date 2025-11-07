# WhatsApp Business Onboarding Playbook

## Overview

This playbook provides step-by-step guidance for onboarding new WhatsApp Business numbers into the multi-tenant GPT Chatbot platform. It covers technical setup, compliance requirements, and operational procedures.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Onboarding Checklist](#onboarding-checklist)
- [Step-by-Step Guide](#step-by-step-guide)
- [Compliance Requirements](#compliance-requirements)
- [Testing & Validation](#testing--validation)
- [Troubleshooting](#troubleshooting)
- [Post-Onboarding](#post-onboarding)

## Prerequisites

### Business Requirements

- [ ] **WhatsApp Business Account**: Active WhatsApp Business account
- [ ] **Phone Number**: Dedicated phone number (not used on personal WhatsApp)
- [ ] **Business Verification**: Completed Meta Business verification (for WhatsApp Business API)
- [ ] **Legal Entity**: Registered business entity with valid documentation
- [ ] **Privacy Policy**: Published privacy policy with WhatsApp data processing disclosure

### Technical Requirements

- [ ] **Z-API Account**: Active Z-API subscription (or other WhatsApp Business API provider)
- [ ] **Webhook URL**: HTTPS-enabled webhook endpoint (SSL certificate required)
- [ ] **Database Access**: Tenant created in the platform
- [ ] **Admin Credentials**: Admin API token with appropriate permissions

### Compliance Requirements

- [ ] **Data Processing Agreement (DPA)**: Signed DPA with Meta/WhatsApp
- [ ] **Consent Management**: Opt-in mechanism for users
- [ ] **Privacy Notice**: Updated privacy policy including WhatsApp communications
- [ ] **Data Retention Policy**: Defined retention periods for WhatsApp messages
- [ ] **GDPR/LGPD Compliance**: Compliance documentation for applicable jurisdictions

## Onboarding Checklist

### Phase 1: Business Setup (1-2 weeks)

- [ ] Register WhatsApp Business number with Meta
- [ ] Complete business verification process
- [ ] Sign Data Processing Agreement (DPA) with Meta
- [ ] Set up Z-API instance for the number
- [ ] Configure business profile (name, description, website, logo)
- [ ] Prepare message templates for approval

### Phase 2: Platform Configuration (1-2 days)

- [ ] Create tenant in platform (if new customer)
- [ ] Create agent for WhatsApp channel
- [ ] Configure agent with AI parameters
- [ ] Set up WhatsApp channel configuration
- [ ] Configure webhook endpoints
- [ ] Test message sending and receiving

### Phase 3: Compliance Setup (1 day)

- [ ] Enable consent management
- [ ] Configure opt-in/opt-out workflows
- [ ] Set up PII redaction rules
- [ ] Configure data retention policies
- [ ] Document incident response procedures
- [ ] Train team on compliance requirements

### Phase 4: Template Management (2-3 days)

- [ ] Submit message templates to WhatsApp for approval
- [ ] Configure template-based workflows
- [ ] Test template rendering
- [ ] Set up template fallbacks

### Phase 5: Testing & Go-Live (1-2 days)

- [ ] Run end-to-end tests
- [ ] Validate compliance controls
- [ ] Perform load testing
- [ ] Schedule go-live
- [ ] Monitor initial conversations

## Step-by-Step Guide

### Step 1: WhatsApp Business Registration

**Duration**: 3-7 business days

1. **Create Meta Business Account**
   - Visit: https://business.facebook.com
   - Click "Create Account"
   - Complete business verification with documents

2. **Register Phone Number**
   - Navigate to WhatsApp Manager
   - Click "Add Phone Number"
   - Verify ownership via SMS or voice call
   - Complete phone number setup wizard

3. **Business Profile Setup**
   ```
   - Business Name: [Customer Business Name]
   - Business Category: [Select appropriate category]
   - Business Description: [Brief description]
   - Website: [Customer website]
   - Email: [Support email]
   - Address: [Business address]
   ```

### Step 2: Z-API Configuration

**Duration**: 30 minutes

1. **Create Z-API Instance**
   - Login to Z-API dashboard: https://developer.z-api.io
   - Click "Create New Instance"
   - Select appropriate plan based on message volume
   - Note the Instance ID and Token

2. **Link WhatsApp Number**
   - Scan QR code with WhatsApp Business app
   - Confirm successful connection
   - Test with a simple message

3. **Configure Instance Settings**
   ```json
   {
     "webhook_url": "https://yourdomain.com/channels/whatsapp/{agent_id}/webhook",
     "webhook_events": ["message.received", "message.status"],
     "webhook_authentication": "bearer_token",
     "retry_configuration": {
       "max_retries": 3,
       "retry_delay_ms": 1000
     }
   }
   ```

### Step 3: Platform Tenant Setup

**Duration**: 15 minutes

1. **Create Tenant** (if new customer)

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=create_tenant" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Business Name",
    "slug": "customer-slug",
    "status": "active",
    "billing_email": "billing@customer.com",
    "plan": "enterprise",
    "settings": {
      "features": ["whatsapp", "leadsense", "audit_trails"],
      "limits": {
        "max_agents": 10,
        "max_conversations_per_month": 50000
      },
      "compliance": {
        "data_residency": "us-east-1",
        "retention_days": 90,
        "pii_redaction": true
      }
    }
  }'
```

2. **Create Tenant Admin User**

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=create_user" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@customer.com",
    "password": "SecurePassword123!",
    "role": "admin",
    "tenant_id": "tenant-uuid-from-above"
  }'
```

### Step 4: Agent Creation and WhatsApp Channel Configuration

**Duration**: 20 minutes

1. **Create Agent**

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer TENANT_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "WhatsApp Support Agent",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "system_message": "You are a helpful customer support agent for [Customer Business]. Always be polite, professional, and concise in your responses.",
    "tools": [{"type": "file_search"}],
    "is_default": true
  }'
```

2. **Configure WhatsApp Channel**

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=upsert_agent_channel" \
  -H "Authorization: Bearer TENANT_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-uuid-from-above",
    "channel": "whatsapp",
    "enabled": true,
    "whatsapp_business_number": "+5511999999999",
    "zapi_instance_id": "instance-id-from-zapi",
    "zapi_token": "token-from-zapi",
    "zapi_base_url": "https://api.z-api.io",
    "reply_chunk_size": 4000,
    "allow_media_upload": true,
    "max_media_size_bytes": 10485760,
    "allowed_media_types": ["image/jpeg", "image/png", "application/pdf", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"]
  }'
```

3. **Update Z-API Webhook**
   - In Z-API dashboard, set webhook URL to:
     `https://platform.example.com/channels/whatsapp/{agent_id}/webhook`

### Step 5: Consent Management Configuration

**Duration**: 30 minutes

1. **Enable Consent Tracking**

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=update_tenant" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "tenant-uuid",
    "settings": {
      "compliance": {
        "consent_required": true,
        "opt_in_message": "Welcome! By continuing this conversation, you consent to receive automated messages. Reply STOP to opt out anytime.",
        "opt_out_keywords": ["STOP", "UNSUBSCRIBE", "CANCEL"],
        "opt_in_keywords": ["START", "SUBSCRIBE", "YES"],
        "double_opt_in": false
      }
    }
  }'
```

2. **Configure Welcome Message Template**
   - Submit template to WhatsApp for approval
   - Template should include opt-in notice
   - Example: "Hi {{1}}! Thanks for contacting us. By continuing, you agree to receive automated responses. Reply STOP to opt out."

### Step 6: Template Management

**Duration**: 2-3 business days (approval time)

1. **Create Message Templates**

WhatsApp requires pre-approved templates for proactive messages. Common templates:

**Welcome Template**:
```
Category: UTILITY
Template Name: welcome_message
Languages: [en, pt_BR, es]
Content:
Hi {{1}}! 👋

Thank you for contacting {{2}}. Our AI assistant is here to help you.

By continuing, you consent to automated messages.
Reply STOP to opt out anytime.

How can we help you today?
```

**Opt-In Confirmation Template**:
```
Category: UTILITY
Template Name: opt_in_confirmation
Languages: [en, pt_BR, es]
Content:
✅ You're now subscribed to updates from {{1}}.

You can opt out anytime by sending STOP.
```

**Opt-Out Confirmation Template**:
```
Category: UTILITY
Template Name: opt_out_confirmation
Languages: [en, pt_BR, es]
Content:
You've been unsubscribed from {{1}}.

To resubscribe, send START anytime.
```

2. **Submit Templates via Meta Business Manager**
   - Go to WhatsApp Manager > Message Templates
   - Click "Create Template"
   - Fill in template details
   - Submit for review (typically approved in 24-48 hours)

3. **Store Template IDs in Platform**

```bash
# After approval, note template IDs and store in agent configuration
curl -X POST "https://platform.example.com/admin-api.php?action=update_agent" \
  -H "Authorization: Bearer TENANT_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "agent-uuid",
    "metadata": {
      "whatsapp_templates": {
        "welcome": "template-id-1",
        "opt_in_confirmation": "template-id-2",
        "opt_out_confirmation": "template-id-3"
      }
    }
  }'
```

### Step 7: PII Redaction Configuration

**Duration**: 15 minutes

1. **Enable PII Redaction**

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
          "credit_card": false
        }
      }
    }
  }'
```

2. **Configure Data Retention**

```bash
curl -X POST "https://platform.example.com/admin-api.php?action=update_tenant" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
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

### Step 8: Testing & Validation

**Duration**: 2 hours

1. **Test Message Flow**
   - Send test message from external WhatsApp: "Hello"
   - Verify agent receives and responds
   - Check session created in database
   - Verify message logged with redacted PII

2. **Test Opt-Out Flow**
   - Send "STOP" message
   - Verify opt-out confirmation received
   - Verify session metadata updated
   - Attempt to send another message (should be ignored or prompt re-opt-in)

3. **Test Opt-In Flow**
   - After opt-out, send "START"
   - Verify opt-in confirmation received
   - Verify session reactivated
   - Send test message to confirm agent responds

4. **Test Media Upload**
   - Send image file
   - Verify agent processes image
   - Check file size and type validation

5. **Test Template Sending** (if applicable)
   - Use Admin API to send templated message
   - Verify template rendered correctly
   - Check delivery status

6. **Compliance Checks**
   - Review logs for PII redaction
   - Verify consent records created
   - Check data retention policies applied
   - Validate audit trail completeness

## Compliance Requirements

### Data Processing Agreement (DPA)

**Required Documents**:
1. Meta/WhatsApp DPA (signed)
2. Platform Provider DPA (signed)
3. Customer DPA (if sub-processor)

**Key Clauses to Include**:
- Data processing purpose and scope
- Data subject rights procedures
- Security measures and incident notification
- Sub-processor agreements
- Data transfer mechanisms (if cross-border)
- Data retention and deletion obligations

**Template Location**: `docs/templates/WHATSAPP_DPA_TEMPLATE.md`

### GDPR Compliance

**Article 6 - Lawful Basis**:
- Consent (opt-in) for marketing messages
- Legitimate interest for customer service
- Contract performance for order updates

**Article 13/14 - Information Requirements**:
- Privacy notice at first contact
- Purpose of data processing
- Data retention period
- Rights of data subjects

**Article 32 - Security Measures**:
- Encryption in transit (HTTPS)
- PII redaction in logs
- Access controls and authentication
- Regular security assessments

### LGPD Compliance (Brazil)

**Article 7 - Legal Bases**:
- Consent for non-essential communications
- Legitimate interest for customer support
- Contract execution for service delivery

**Article 18 - Data Subject Rights**:
- Access: Provide data export via Admin API
- Correction: Update via Admin API
- Deletion: Delete via Admin API
- Portability: Export in structured format

**Article 46 - Security Measures**:
- Technical safeguards implemented
- Administrative procedures documented
- Incident response plan in place

### Opt-In/Opt-Out Management

**Opt-In Requirements**:
- Clear and conspicuous consent request
- Separate consent for different purposes
- Record timestamp and method of consent
- Allow easy opt-out mechanism

**Opt-Out Requirements**:
- Honor STOP keywords immediately
- Confirm opt-out with final message
- Maintain opt-out list (do-not-contact)
- Do not re-contact without explicit re-opt-in

**Audit Trail**:
- Log all consent changes
- Store consent method and timestamp
- Track opt-in source (web, WhatsApp, phone)
- Maintain immutable audit log

## Testing & Validation

### Pre-Launch Checklist

- [ ] **Functional Tests**
  - [ ] Send and receive text messages
  - [ ] Send and receive media files
  - [ ] Message chunking for long responses
  - [ ] Error handling and retries
  - [ ] Session persistence

- [ ] **Compliance Tests**
  - [ ] Opt-in message sent on first contact
  - [ ] Opt-out keywords recognized
  - [ ] PII redacted in logs
  - [ ] Consent records created
  - [ ] Data retention enforced

- [ ] **Performance Tests**
  - [ ] Response time < 3 seconds
  - [ ] Handle concurrent conversations
  - [ ] Webhook reliability (99.9%)
  - [ ] Message queue not backing up

- [ ] **Security Tests**
  - [ ] HTTPS enabled on all endpoints
  - [ ] Webhook signature verification
  - [ ] Admin API authentication
  - [ ] Rate limiting active
  - [ ] SQL injection protection

### Testing Scripts

**Test Opt-In Flow**:
```bash
# Simulate new user contact
curl -X POST "https://platform.example.com/channels/whatsapp/test-webhook" \
  -H "Content-Type: application/json" \
  -d '{
    "messageId": "test-msg-001",
    "from": "+5511988887777",
    "text": "Hello"
  }'

# Expected: Opt-in message sent to user
# Verify in database:
sqlite3 data/chatbot.db "SELECT * FROM channel_sessions WHERE external_user_id = '+5511988887777'"
```

**Test Opt-Out Flow**:
```bash
# Simulate opt-out
curl -X POST "https://platform.example.com/channels/whatsapp/test-webhook" \
  -H "Content-Type: application/json" \
  -d '{
    "messageId": "test-msg-002",
    "from": "+5511988887777",
    "text": "STOP"
  }'

# Expected: Opt-out confirmation sent
# Verify metadata updated:
sqlite3 data/chatbot.db "SELECT metadata_json FROM channel_sessions WHERE external_user_id = '+5511988887777'"
# Should show: {"opted_out": true, "opted_out_at": "2024-..."}
```

**Test PII Redaction**:
```bash
# Send message with PII
curl -X POST "https://platform.example.com/channels/whatsapp/test-webhook" \
  -H "Content-Type: application/json" \
  -d '{
    "messageId": "test-msg-003",
    "from": "+5511988887777",
    "text": "My email is john.doe@example.com"
  }'

# Check logs for redaction
tail -f logs/chatbot.log | grep "jo\*\*@e\*\*\*.com"
```

## Troubleshooting

### Common Issues

**Issue: Messages not received by platform**
- Check webhook URL configured in Z-API
- Verify HTTPS certificate is valid
- Check firewall rules allow Z-API IPs
- Review webhook logs in Z-API dashboard

**Issue: Messages not sent to users**
- Verify Z-API token is valid
- Check Z-API instance is connected
- Ensure phone number format is E.164
- Review Z-API API limits

**Issue: Opt-out not working**
- Verify opt-out keywords configured
- Check case-insensitive matching
- Review session metadata updates
- Ensure opt-out confirmation sent

**Issue: Templates not working**
- Verify templates approved by WhatsApp
- Check template IDs correct in config
- Ensure template parameters matched
- Review template quality rating

**Issue: PII visible in logs**
- Verify pii_redaction enabled in settings
- Check redaction patterns configured
- Review ObservabilityLogger implementation
- Ensure all log calls use logger instance

### Support Contacts

- **Platform Support**: support@platform.example.com
- **Z-API Support**: https://developer.z-api.io/support
- **Meta WhatsApp Support**: https://business.facebook.com/help/support

## Post-Onboarding

### Monitoring & Maintenance

**Daily**:
- [ ] Monitor message delivery rate
- [ ] Check error logs for issues
- [ ] Review opt-out requests
- [ ] Validate response times

**Weekly**:
- [ ] Review conversation quality
- [ ] Check template approval status
- [ ] Analyze user feedback
- [ ] Update AI prompts as needed

**Monthly**:
- [ ] Review compliance audit logs
- [ ] Validate data retention policies
- [ ] Update templates based on usage
- [ ] Conduct security review

**Quarterly**:
- [ ] Full compliance audit
- [ ] Update DPA if needed
- [ ] Review and update privacy policy
- [ ] Train team on new features

### Continuous Improvement

1. **Monitor KPIs**:
   - Message delivery rate (target: >99%)
   - Average response time (target: <3s)
   - User satisfaction (CSAT score)
   - Opt-out rate (target: <5%)

2. **Optimize AI Responses**:
   - Review conversation transcripts
   - Identify common pain points
   - Update system prompts
   - Add knowledge base articles

3. **Update Templates**:
   - Test template variations
   - A/B test messaging
   - Submit new templates as needed
   - Deprecate unused templates

4. **Compliance Updates**:
   - Monitor regulatory changes
   - Update policies as needed
   - Train team on new requirements
   - Document compliance changes

## Appendices

### Appendix A: Required Documents Checklist

- [ ] Meta/WhatsApp Business verification documents
- [ ] Signed DPA with Meta
- [ ] Signed DPA with platform provider
- [ ] Privacy policy with WhatsApp disclosure
- [ ] Consent management procedures
- [ ] Data retention policy
- [ ] Incident response plan
- [ ] Security assessment report
- [ ] Staff training records

### Appendix B: Contact Information Template

```
Customer: [Customer Name]
Tenant ID: [tenant-uuid]
Tenant Slug: [customer-slug]

WhatsApp Details:
- Business Number: [+country-code-number]
- Z-API Instance ID: [instance-id]
- Agent ID: [agent-uuid]

Admin Contacts:
- Primary Admin: [name] <email>
- Technical Contact: [name] <email>
- Legal Contact: [name] <email>

Compliance:
- DPA Signed: [date]
- Privacy Policy Updated: [date]
- Go-Live Date: [date]

Support:
- Platform Tenant Admin: [email]
- Z-API Dashboard: [url]
- Webhook URL: [full-webhook-url]
```

### Appendix C: Useful Commands

**Check Webhook Status**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=get_agent_channel&agent_id={agent_id}&channel=whatsapp" \
  -H "Authorization: Bearer TOKEN"
```

**List Active Sessions**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=list_channel_sessions&agent_id={agent_id}&channel=whatsapp" \
  -H "Authorization: Bearer TOKEN"
```

**Export Conversation Data**:
```bash
curl -X GET "https://platform.example.com/admin-api.php?action=export_conversations&agent_id={agent_id}&format=json" \
  -H "Authorization: Bearer TOKEN"
```

**Delete User Data (GDPR/LGPD Request)**:
```bash
curl -X DELETE "https://platform.example.com/admin-api.php?action=delete_user_data&external_user_id={phone}&tenant_id={tenant_id}" \
  -H "Authorization: Bearer TOKEN"
```

## Version History

- **v1.0** (2024-11): Initial playbook
  - WhatsApp onboarding procedures
  - Compliance requirements
  - Template management
  - Testing protocols

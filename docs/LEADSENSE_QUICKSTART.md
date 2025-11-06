# LeadSense Quick Start Guide

## What is LeadSense?

LeadSense is an AI-powered lead detection and qualification system that automatically identifies commercial opportunities in chatbot conversations. It runs in the background, detecting when users show buying intent, extracting their contact information, scoring their quality, and notifying your sales team—all without interrupting the conversation.

## ⚡ 5-Minute Setup

### 1. Enable LeadSense

Add to your `.env` file:

```bash
LEADSENSE_ENABLED=true
LEADSENSE_SCORE_THRESHOLD=70
LEADSENSE_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### 2. Run Database Migration

```bash
sqlite3 data/chatbot.db < db/migrations/018_create_leadsense_tables.sql
```

For PostgreSQL:
```bash
psql $DATABASE_URL < db/migrations/018_create_leadsense_tables.sql
```

### 3. Test It

Start a conversation with your chatbot:

```
User: "How much does your service cost? I'm the CTO at Acme Inc 
       and we're looking for a solution. My email is john@acme.com"

Bot: "Our pricing starts at $99/month..."
```

Check your logs - you should see:
```
LeadSense: Lead detected - ID: xxx, Score: 85, Qualified: yes
```

Check your Slack - you should receive a notification! 🎉

### 4. View Leads via Admin API

```bash
curl -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  "https://your-domain.com/admin-api.php?action=list_leads&qualified=true"
```

## 🎯 How It Works

1. **User asks about pricing/trial/integration** → Intent detected
2. **Bot responds naturally** → Conversation continues normally  
3. **LeadSense extracts data** → Name, email, company, role
4. **Lead is scored** → Based on intent + extracted data
5. **If qualified** → Notification sent to Slack/webhook
6. **Stored in CRM** → Available via Admin API

**All of this happens automatically in the background!**

## 🔧 Configuration

### Essential Settings

```bash
# Core Settings
LEADSENSE_ENABLED=true              # Master toggle
LEADSENSE_SCORE_THRESHOLD=70        # 0-100 (higher = stricter)
LEADSENSE_INTENT_THRESHOLD=0.6      # 0-1 (higher = stricter)

# Notifications
LEADSENSE_SLACK_WEBHOOK=https://...
LEADSENSE_WEBHOOK_URL=https://your-crm.com/webhooks/leads
LEADSENSE_WEBHOOK_SECRET=your-secret

# Privacy
LEADSENSE_PII_REDACTION=true        # Mask emails/phones in notifications
```

### Advanced Settings

```bash
# Scoring Behavior
LEADSENSE_SCORING_MODE=rules        # 'rules' or 'ml' (future)

# Rate Limiting
LEADSENSE_DEBOUNCE_WINDOW=300       # Seconds between processing same conversation
LEADSENSE_MAX_DAILY_NOTIFICATIONS=100

# Extraction
LEADSENSE_CONTEXT_WINDOW=10         # Messages to analyze for context
LEADSENSE_MAX_TOKENS=1000
LEADSENSE_MAX_FIELDS=20

# Security
LEADSENSE_ENCRYPTION=false          # Encrypt sensitive fields (future)
LEADSENSE_ENC_KEY=                  # Encryption key
```

## 📊 Understanding Lead Scores

Leads are scored 0-100 based on:

| Factor | Points |
|--------|--------|
| **Intent Level** | |
| - Low (1 signal) | +20 |
| - Medium (2-3 signals) | +50 |
| - High (4+ signals) | +75 |
| **Decision Maker** (CTO, VP, etc.) | +15 |
| **Urgency** (ASAP, deadline) | +10 |
| **ICP Fit** (enterprise, tech) | +10 |
| **Contact Info** provided | +5 |
| **Company** identified | +5 |
| **No Contact Info** | -10 |

**Qualified**: Score ≥ threshold (default 70)

### Example Scores

**Score 95 - Hot Lead** ✅
- "Need pricing ASAP, I'm the CTO, email: cto@bigcorp.com"
- High intent (75) + Decision maker (15) + Contact (5) = 95

**Score 70 - Qualified** ✅
- "Looking for a trial, work at StartupCo, phone: 555-1234"
- Medium intent (50) + Contact (5) + Company (5) + Urgency (10) = 70

**Score 40 - Not Qualified** ❌
- "Maybe interested, no rush"
- Low intent (20) + No contact (-10) = 30

## 🔔 Notification Examples

### Slack Message

<img src="https://via.placeholder.com/500x300/36a64f/ffffff?text=Slack+Notification" alt="Slack notification example" />

```
🌟 New Qualified Lead Detected

Name: Jo** Do*
Company: Acme Inc
Role: CTO
Email: jo**@a***.com
Phone: ***-***-4567
Lead Score: 85/100
Intent Level: High
Interest: Looking for pricing and integration options...
```

### Webhook Payload

```json
{
  "event": "lead.qualified",
  "timestamp": "2024-01-15T10:30:00Z",
  "lead": {
    "id": "550e8400-...",
    "name": "Jo** Do*",
    "company": "Acme Inc",
    "role": "CTO",
    "email": "jo**@a***.com",
    "score": 85,
    "qualified": true,
    "intent_level": "high"
  }
}
```

## 📱 Admin API Quick Reference

### List All Qualified Leads

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://your-domain.com/admin-api.php?action=list_leads&qualified=true"
```

### Get Lead Details

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://your-domain.com/admin-api.php?action=get_lead&id=LEAD_ID"
```

### Update Lead Status

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"LEAD_ID","status":"won"}' \
  "https://your-domain.com/admin-api.php?action=update_lead"
```

### Add Note

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":"LEAD_ID","note":"Called customer, scheduling demo"}' \
  "https://your-domain.com/admin-api.php?action=add_lead_note"
```

## 🧪 Testing

Run unit tests to verify everything works:

```bash
php tests/test_leadsense_intent.php      # ✓ 12 tests
php tests/test_leadsense_extractor.php   # ✓ 17 tests  
php tests/test_leadsense_scorer.php      # ✓ 10 tests
```

## 🔒 Privacy & Compliance

### PII Redaction

When enabled (default), sensitive data is masked:
- Emails: `john.doe@example.com` → `jo**@e***.com`
- Phones: `555-123-4567` → `***-***-4567`

Applied to:
- ✅ Slack notifications
- ✅ Webhook payloads
- ✅ Application logs
- ❌ Admin API (admins need full access)
- ❌ Database (required for CRM)

### GDPR/CCPA

LeadSense provides tools for compliance:
- **Access**: Admin API to retrieve lead data
- **Rectification**: Update endpoints
- **Erasure**: Delete leads and events
- **Export**: CSV/JSON export via API

Update your privacy policy to disclose lead detection.

## 📚 Full Documentation

- [LeadSense Overview](leadsense-overview.md) - Complete feature guide
- [Admin API Reference](leadsense-api.md) - All endpoints & examples
- [Privacy & Security](leadsense-privacy.md) - Compliance & best practices

## 💡 Tips & Best Practices

### Tuning Detection

**Too many false positives?**
- Increase `LEADSENSE_INTENT_THRESHOLD` (try 0.7 or 0.8)
- Increase `LEADSENSE_SCORE_THRESHOLD` (try 80 or 90)

**Missing real leads?**
- Decrease `LEADSENSE_INTENT_THRESHOLD` (try 0.5)
- Decrease `LEADSENSE_SCORE_THRESHOLD` (try 60)

**Duplicate leads?**
- Increase `LEADSENSE_DEBOUNCE_WINDOW` (try 600 = 10 min)

### Performance

- ✅ Non-blocking: Runs after stream completion
- ✅ Efficient: Regex-based, no external API calls
- ✅ Scalable: Handles thousands of conversations/day

### Integration

**Connect to Your CRM:**
1. Set `LEADSENSE_WEBHOOK_URL` to your CRM webhook
2. Add `LEADSENSE_WEBHOOK_SECRET` for security
3. Parse incoming webhook payloads in your CRM

**Popular Integrations:**
- Zapier: Create Zap from webhook
- Make.com: HTTP module
- HubSpot: Custom integration
- Salesforce: Platform Events
- Pipedrive: Webhooks

## 🐛 Troubleshooting

### Leads Not Being Detected

1. **Check if enabled:**
   ```bash
   grep LEADSENSE_ENABLED .env
   ```

2. **Check logs:**
   ```bash
   tail -f logs/chatbot.log | grep LeadSense
   ```

3. **Lower thresholds:**
   ```bash
   LEADSENSE_INTENT_THRESHOLD=0.5
   LEADSENSE_SCORE_THRESHOLD=50
   ```

### Notifications Not Sending

1. **Verify webhook URL:**
   ```bash
   curl -X POST -H "Content-Type: application/json" \
     -d '{"test":"message"}' \
     $LEADSENSE_SLACK_WEBHOOK
   ```

2. **Check logs for errors:**
   ```bash
   tail -f logs/chatbot.log | grep "notification"
   ```

3. **Test with low threshold:**
   ```bash
   LEADSENSE_SCORE_THRESHOLD=10
   ```

### Database Errors

1. **Run migration:**
   ```bash
   sqlite3 data/chatbot.db < db/migrations/018_create_leadsense_tables.sql
   ```

2. **Check permissions:**
   ```bash
   ls -la data/chatbot.db
   chmod 666 data/chatbot.db  # If needed
   ```

## 🚀 Next Steps

1. **Customize scoring weights** in `config.php`
2. **Set up CRM integration** via webhooks
3. **Review leads daily** via Admin API
4. **Tune thresholds** based on results
5. **Add to privacy policy**

## 💬 Support

- **Documentation**: See docs folder
- **Issues**: GitHub Issues
- **Tests**: Run unit tests for diagnostics

## 🎉 You're All Set!

LeadSense is now watching your conversations for commercial opportunities. Every qualified lead will be automatically detected, scored, stored, and forwarded to your sales team.

Happy selling! 🚀

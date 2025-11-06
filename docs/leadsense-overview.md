# LeadSense - Commercial Opportunity Detection

## Overview

LeadSense is an AI-powered lead detection and qualification system integrated into the GPT Chatbot Boilerplate. It automatically identifies commercial intent in conversations, extracts lead information, scores prospects, and notifies sales teams—all without disrupting the user experience.

## Features

- **Intent Detection**: Identifies commercial signals like pricing inquiries, trial requests, integration questions
- **Entity Extraction**: Automatically captures contact info, roles, company data from conversations
- **Lead Scoring**: Rules-based scoring with configurable weights and thresholds
- **Privacy-First**: PII redaction in logs and notifications
- **Non-Intrusive**: Runs at stream completion without blocking responses
- **Notifications**: Slack and webhook integration with retry logic
- **Admin Interface**: Full CRUD API for lead management

## Architecture

### Pipeline Flow

```
User Message → Assistant Response → LeadSense Pipeline:
  1. Intent Detection (keyword heuristics)
  2. Entity Extraction (regex + context analysis)
  3. Lead Scoring (rules-based)
  4. Persistence (embedded CRM dataset)
  5. Notifications (if qualified)
```

### Components

- **IntentDetector**: Analyzes conversation for commercial signals
- **EntityExtractor**: Extracts structured data (name, email, role, company, etc.)
- **LeadScorer**: Calculates lead quality score (0-100)
- **LeadRepository**: Database CRUD operations
- **Notifier**: Sends alerts via Slack/webhooks
- **Redactor**: Masks PII for privacy
- **LeadSenseService**: Orchestrates the entire pipeline

## Configuration

All settings are in `config.php` under the `leadsense` section:

```php
'leadsense' => [
    'enabled' => false,                    // Master toggle
    'intent_threshold' => 0.6,             // 0-1 confidence threshold
    'score_threshold' => 70,               // 0-100 qualification threshold
    'followup_enabled' => true,            // Enable contextual follow-ups
    'pii_redaction' => true,               // Mask PII in notifications/logs
    
    'notify' => [
        'slack_webhook_url' => '',         // Slack incoming webhook
        'webhook_url' => '',               // Generic webhook endpoint
        'webhook_secret' => '',            // HMAC secret for webhooks
    ],
    
    'scoring' => [
        'mode' => 'rules',                 // 'rules' or 'ml' (future)
        'weights' => [
            'intent_low' => 20,
            'intent_medium' => 50,
            'intent_high' => 75,
            'decision_maker' => 15,
            'urgency' => 10,
            'icp_fit' => 10,
            'no_contact' => -10,
        ]
    ],
    
    'debounce_window' => 300,              // Seconds between processing same conversation
    'max_daily_notifications' => 100,      // Rate limit on notifications
]
```

### Environment Variables

```bash
LEADSENSE_ENABLED=false
LEADSENSE_INTENT_THRESHOLD=0.6
LEADSENSE_SCORE_THRESHOLD=70
LEADSENSE_SLACK_WEBHOOK=https://hooks.slack.com/...
LEADSENSE_WEBHOOK_URL=https://your-crm.com/webhooks/leads
LEADSENSE_WEBHOOK_SECRET=your-secret
LEADSENSE_PII_REDACTION=true
```

## Database Schema

### leads
- `id`: UUID primary key
- `agent_id`: Optional agent identifier
- `conversation_id`: Chat session ID
- Contact: `name`, `email`, `phone`, `role`
- Company: `company`, `industry`, `company_size`
- Lead Quality: `intent_level`, `score`, `qualified`, `status`
- Metadata: `interest`, `source_channel`, `extras_json`
- Timestamps: `created_at`, `updated_at`

### lead_events
- `id`: UUID primary key
- `lead_id`: Foreign key to leads
- `type`: `detected`, `updated`, `qualified`, `notified`, `synced`, `note`
- `payload_json`: Event-specific data
- `created_at`: Timestamp

### lead_scores
- `id`: UUID primary key
- `lead_id`: Foreign key to leads
- `score`: Integer 0-100
- `rationale_json`: Scoring breakdown
- `created_at`: Timestamp

## Intent Detection

### Supported Signals

LeadSense recognizes these commercial intent categories:

- **Pricing**: cost, pricing, budget, quote, estimate
- **Trial**: trial, demo, test, evaluate, POC
- **Integration**: API, webhook, integrate, connect, setup
- **Decision Makers**: CTO, CEO, Director, VP, Head of
- **Evaluation**: compare, alternative, features, capabilities
- **Urgency**: ASAP, urgent, deadline, immediately
- **Commitment**: purchase, contract, sign up, order
- **Budget**: funding, approved, investment, ROI

### Confidence Levels

- **None** (< 0.3): No commercial signals
- **Low** (0.3 - 0.6): Single weak signal
- **Medium** (0.6 - 0.8): Multiple signals or strong single signal
- **High** (> 0.8): Multiple strong signals + context

## Entity Extraction

### Extracted Fields

- **Name**: From "My name is..." or "I'm [Name]"
- **Email**: RFC-compliant email addresses
- **Phone**: Various formats (555-1234, (555) 123-4567, +1-555-123-4567)
- **Role**: Job titles (CTO, VP, Director, Manager, etc.)
- **Company**: Company names with suffixes (Inc, LLC, Corp)
- **Company Size**: Enterprise, mid-market, small, solopreneur
- **Industry**: Technology, healthcare, finance, etc.
- **Interest**: Aggregated user messages
- **Urgency**: High, medium, low

## Lead Scoring

### Scoring Rules

Base score from intent:
- Low intent: +20 points
- Medium intent: +50 points
- High intent: +75 points

Bonuses:
- Decision maker role: +15
- Urgency (high/medium): +10
- ICP fit (enterprise/tech): +10-15
- Contact info provided: +5
- Company identified: +5

Penalties:
- No contact info: -10

### Qualification

A lead is qualified when `score >= score_threshold` (default 70).

Typical qualified lead:
- High intent (75) + Decision maker (15) + Contact info (5) = 95 points ✓
- Medium intent (50) + Contact (5) + Company (5) + Urgency (10) = 70 points ✓

## Notifications

### Slack Format

```json
{
  "text": "🌟 New Qualified Lead Detected",
  "attachments": [{
    "color": "#36a64f",
    "fields": [
      {"title": "Name", "value": "Jo** Do*", "short": true},
      {"title": "Company", "value": "Acme Inc", "short": true},
      {"title": "Role", "value": "CTO", "short": true},
      {"title": "Email", "value": "jo**@a***.com", "short": true},
      {"title": "Lead Score", "value": "85/100", "short": true},
      {"title": "Intent Level", "value": "High", "short": true},
      {"title": "Interest", "value": "Looking for pricing...", "short": false}
    ],
    "footer": "LeadSense",
    "ts": 1234567890
  }]
}
```

### Webhook Format

```json
{
  "event": "lead.qualified",
  "timestamp": "2024-01-15T10:30:00Z",
  "lead": {
    "id": "uuid",
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

Headers:
- `Content-Type: application/json`
- `X-LeadSense-Signature: sha256=<hmac>` (if secret configured)

## Admin API

### List Leads

```bash
GET /admin-api.php?action=list_leads&agent_id=xxx&status=new&qualified=true&min_score=70
```

Query parameters:
- `agent_id`: Filter by agent
- `status`: `new`, `open`, `won`, `lost`, `nurture`
- `qualified`: `true` or `false`
- `min_score`: Minimum score
- `from`, `to`: Date range (ISO 8601)
- `q`: Search query
- `limit`, `offset`: Pagination

Response:
```json
{
  "leads": [...],
  "count": 42
}
```

### Get Lead

```bash
GET /admin-api.php?action=get_lead&id=uuid
```

Response includes lead data, events, and score history.

### Update Lead

```bash
POST /admin-api.php?action=update_lead
{
  "id": "uuid",
  "status": "won",
  "qualified": true
}
```

### Add Note

```bash
POST /admin-api.php?action=add_lead_note
{
  "id": "uuid",
  "note": "Called customer, very interested"
}
```

### Rescore Lead

```bash
POST /admin-api.php?action=rescore_lead
{
  "id": "uuid"
}
```

Recalculates score based on current scoring rules.

## Privacy & Security

### PII Redaction

When enabled (`pii_redaction: true`):
- Emails: `jo**@a***.com`
- Phones: `***-***-1234`
- Applied to: notifications, logs (NOT admin API responses)

### Data Retention

Configure retention in `config.php` or use Admin API to purge old leads.

### Encryption

Optional at-rest encryption for sensitive fields (future feature).

### Rate Limiting

- Debounce window: Prevents duplicate processing within X seconds
- Daily notification limit: Caps notifications per agent per day

## Integration with ChatHandler

LeadSense automatically processes all conversation completions:

1. **Streaming Completions** (Chat API): Triggers at `finish_reason` event
2. **Sync Completions** (Chat API): Triggers after `saveConversationHistory()`
3. **Streaming Responses** (Responses API): Triggers at `response.completed`
4. **Sync Responses** (Responses API): Triggers after save

No code changes needed—enabled via configuration.

## Per-Agent Configuration

Agents can override LeadSense settings via `custom` field:

```json
{
  "custom": {
    "leadsense": {
      "enabled": true,
      "score_threshold": 80,
      "notify": {
        "slack_webhook_url": "https://..."
      }
    }
  }
}
```

## Testing

Run unit tests:

```bash
php tests/test_leadsense_intent.php
php tests/test_leadsense_extractor.php
php tests/test_leadsense_scorer.php
```

## Future Enhancements

- ML-based scoring (`scoring.mode: ml`)
- External CRM connectors (HubSpot, Salesforce)
- Email notifications
- Admin UI for lead review
- Semantic intent detection (embeddings)
- Multi-language support
- A/B testing for scoring rules
- Lead enrichment (Clearbit, etc.)

## Troubleshooting

**Leads not detected:**
- Check `leadsense.enabled = true` in config
- Verify intent threshold (lower for more sensitivity)
- Check logs for processing errors

**Notifications not sending:**
- Verify webhook URLs are configured
- Check network connectivity
- Review retry logs
- Check daily notification limit

**Duplicate leads:**
- Adjust `debounce_window` higher
- Verify conversation IDs are stable

**Low scores:**
- Review scoring weights in config
- Check entity extraction quality
- Adjust `score_threshold` if needed

## Support

For issues or questions:
1. Check logs in `logs/chatbot.log`
2. Review Admin API for lead details
3. Run unit tests to verify components
4. Check GitHub issues/discussions

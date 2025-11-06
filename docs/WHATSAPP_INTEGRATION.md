# WhatsApp Channel Integration via Z-API

This document describes the WhatsApp channel integration using Z-API for the GPT Chatbot Boilerplate. This implementation allows agents to receive and respond to messages via WhatsApp Business.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Setup and Configuration](#setup-and-configuration)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [Webhook Flow](#webhook-flow)
- [Security Considerations](#security-considerations)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Overview

The WhatsApp channel integration enables:

- **Bidirectional messaging**: Receive and send WhatsApp messages
- **Per-agent configuration**: Each agent can have its own WhatsApp channel
- **Session management**: Track conversations and maintain context
- **Media support**: Handle images, documents, and other media types
- **Idempotency**: Prevent duplicate message processing
- **Message chunking**: Automatically split long responses
- **Opt-out support**: Handle STOP/START commands

## Architecture

### Components

1. **ChannelInterface** (`includes/channels/ChannelInterface.php`)
   - Abstract interface for all channel types
   - Defines standard methods for sending/receiving messages

2. **WhatsAppZApi** (`includes/channels/WhatsAppZApi.php`)
   - Implements ChannelInterface for Z-API
   - Handles message normalization and sending
   - Manages message chunking for long responses

3. **ZApiClient** (`includes/ZApiClient.php`)
   - HTTP client for Z-API communication
   - Handles retries and timeouts
   - Provides methods for sending text, images, and documents

4. **ChannelManager** (`includes/ChannelManager.php`)
   - Orchestrates channel operations
   - Routes messages to appropriate agents
   - Manages channel adapter instances

5. **ChannelSessionService** (`includes/ChannelSessionService.php`)
   - Manages user sessions across channels
   - Maps external user IDs to conversation IDs
   - Tracks last activity timestamps

6. **ChannelMessageService** (`includes/ChannelMessageService.php`)
   - Records all inbound and outbound messages
   - Provides idempotency checking
   - Supports message audit and analytics

7. **Webhook Endpoint** (`channels/whatsapp/webhook.php`)
   - Receives Z-API webhook events
   - Processes incoming messages
   - Routes to ChatHandler for AI responses

## Setup and Configuration

### 1. Run Database Migrations

```bash
php scripts/run_migrations.php
```

This creates the following tables:
- `agent_channels` - Channel configurations per agent
- `channel_sessions` - User session mappings
- `channel_messages` - Message audit log

### 2. Configure Z-API Instance

1. Create a Z-API account at https://z-api.io
2. Create a new instance
3. Get your Instance ID and Token
4. Note your WhatsApp Business number

### 3. Configure Agent via Admin UI

1. Access the Admin UI at `/public/admin/`
2. Navigate to Agents
3. Click "Channels" button for your desired agent
4. Click "Configure WhatsApp"
5. Fill in the following fields:

   **Required:**
   - ✅ Enable WhatsApp Channel
   - WhatsApp Business Number (E.164 format, e.g., +5511999999999)
   - Z-API Instance ID
   - Z-API Token

   **Optional:**
   - Z-API Base URL (default: https://api.z-api.io)
   - Timeout (default: 30000ms)
   - Retries (default: 3)
   - Reply Chunk Size (default: 4000 chars)
   - Allow Media Upload (default: true)
   - Max Media Size (default: 10MB)
   - Allowed Media Types (default: image/jpeg, image/png, application/pdf)

6. Click "Save Configuration"

### 4. Configure Z-API Webhook

1. In your Z-API dashboard, go to Webhooks settings
2. Set the webhook URL to:
   ```
   https://your-domain.com/channels/whatsapp/{agentId}/webhook
   ```
   Or use the auto-generated webhook URL shown in the Admin UI

3. Enable the following webhook events:
   - Message Received
   - Message Status (optional)

4. (Optional) Set a webhook secret for additional security

### 5. Environment Variables

Add to your `.env` file (optional, uses defaults):

```env
# WhatsApp Channel Configuration
CHANNELS_WHATSAPP_ENABLED=true
ZAPI_BASE_URL=https://api.z-api.io
ZAPI_TIMEOUT_MS=30000
ZAPI_RETRIES=3
WHATSAPP_REPLY_CHUNK_SIZE=4000
WHATSAPP_ALLOW_MEDIA=true
WHATSAPP_MAX_MEDIA_SIZE=10485760
```

## Database Schema

### agent_channels

Stores channel configurations for each agent.

| Column | Type | Description |
|--------|------|-------------|
| id | TEXT | Primary key (UUID) |
| agent_id | TEXT | Foreign key to agents table |
| channel | TEXT | Channel type ('whatsapp') |
| enabled | INTEGER | 1 if enabled, 0 if disabled |
| config_json | TEXT | JSON configuration object |
| created_at | TEXT | ISO 8601 timestamp |
| updated_at | TEXT | ISO 8601 timestamp |

**Config JSON structure for WhatsApp:**
```json
{
  "whatsapp_business_number": "+5511999999999",
  "zapi_instance_id": "instance123",
  "zapi_token": "token456",
  "zapi_base_url": "https://api.z-api.io",
  "zapi_timeout_ms": 30000,
  "zapi_retries": 3,
  "reply_chunk_size": 4000,
  "allow_media_upload": true,
  "max_media_size_bytes": 10485760,
  "allowed_media_types": ["image/jpeg", "image/png", "application/pdf"]
}
```

### channel_sessions

Maps external user identifiers to conversation IDs.

| Column | Type | Description |
|--------|------|-------------|
| id | TEXT | Primary key (UUID) |
| agent_id | TEXT | Foreign key to agents table |
| channel | TEXT | Channel type |
| external_user_id | TEXT | User identifier (phone number) |
| conversation_id | TEXT | Stable conversation ID |
| last_seen_at | TEXT | Last activity timestamp |
| metadata_json | TEXT | Session metadata (opt-out status, etc.) |
| created_at | TEXT | ISO 8601 timestamp |
| updated_at | TEXT | ISO 8601 timestamp |

**Unique constraint:** (agent_id, channel, external_user_id)

### channel_messages

Audit log for all channel messages.

| Column | Type | Description |
|--------|------|-------------|
| id | TEXT | Primary key (UUID) |
| agent_id | TEXT | Foreign key to agents table |
| channel | TEXT | Channel type |
| direction | TEXT | 'inbound' or 'outbound' |
| external_message_id | TEXT | Provider message ID (unique) |
| external_user_id | TEXT | User identifier |
| conversation_id | TEXT | Related conversation |
| payload_json | TEXT | Message payload |
| status | TEXT | 'received', 'processed', 'sent', 'failed' |
| error_text | TEXT | Error description (if failed) |
| created_at | TEXT | ISO 8601 timestamp |
| updated_at | TEXT | ISO 8601 timestamp |

## API Endpoints

All Admin API endpoints require Bearer token authentication.

### List Agent Channels

```
GET /admin-api.php?action=list_agent_channels&agent_id={agentId}
```

**Response:**
```json
[
  {
    "id": "channel-uuid",
    "agent_id": "agent-uuid",
    "channel": "whatsapp",
    "enabled": true,
    "config": { ... },
    "created_at": "2025-11-05T23:00:00+00:00",
    "updated_at": "2025-11-05T23:00:00+00:00"
  }
]
```

### Get Agent Channel

```
GET /admin-api.php?action=get_agent_channel&agent_id={agentId}&channel=whatsapp
```

### Upsert Agent Channel

```
POST /admin-api.php?action=upsert_agent_channel&agent_id={agentId}&channel=whatsapp
Content-Type: application/json

{
  "enabled": true,
  "whatsapp_business_number": "+5511999999999",
  "zapi_instance_id": "instance123",
  "zapi_token": "token456",
  ...
}
```

### Delete Agent Channel

```
DELETE /admin-api.php?action=delete_agent_channel&agent_id={agentId}&channel=whatsapp
```

### Test Channel Send

```
POST /admin-api.php?action=test_channel_send&agent_id={agentId}&channel=whatsapp
Content-Type: application/json

{
  "to": "+5511988887777",
  "message": "Test message"
}
```

### List Channel Sessions

```
GET /admin-api.php?action=list_channel_sessions&agent_id={agentId}&channel=whatsapp&limit=50&offset=0
```

## Webhook Flow

### Incoming Message Flow

1. **Webhook Received**
   - Z-API sends POST to `/channels/whatsapp/{agentId}/webhook`
   - Webhook endpoint validates signature (if configured)

2. **Agent Identification**
   - Agent ID extracted from URL path or
   - Agent looked up by WhatsApp business number

3. **Message Normalization**
   - WhatsAppZApi adapter normalizes the payload
   - Extracts: message_id, from, text, media_url, mime_type

4. **Idempotency Check**
   - ChannelMessageService checks if message_id already exists
   - Duplicate messages are skipped

5. **Session Management**
   - ChannelSessionService gets or creates session
   - Maps user phone → conversation_id
   - Updates last_seen_at timestamp

6. **Message Recording**
   - Inbound message recorded in channel_messages
   - Status set to 'received'

7. **Opt-out Handling**
   - Check for STOP/START commands
   - Update session metadata
   - Send confirmation message

8. **Media Processing** (if present)
   - Validate MIME type and size
   - Download media file
   - Encode as base64 for ChatHandler

9. **AI Processing**
   - ChatHandler processes message (sync mode)
   - Uses Responses API or Chat Completions API
   - Loads conversation history from storage

10. **Response Sending**
    - ChannelManager sends response via WhatsAppZApi
    - Long messages automatically chunked
    - Outbound message recorded

11. **Status Update**
    - Inbound message status updated to 'processed'

## Security Considerations

### Webhook Security

1. **TLS Required**: Always use HTTPS for webhooks
2. **Signature Verification**: Configure `zapi_webhook_secret` to validate webhook authenticity
3. **IP Whitelisting**: Restrict webhook endpoint to Z-API IP ranges (application-level)
4. **Rate Limiting**: Webhook endpoint respects global rate limits

### Token Security

1. **Storage**: Z-API tokens stored encrypted in database
2. **Display**: Tokens masked in Admin UI and logs
3. **Transmission**: Sent only over HTTPS
4. **Rotation**: Support for token rotation without downtime

### Data Privacy

1. **PII Handling**: Phone numbers treated as PII
2. **Retention Policy**: Apply data retention policies to channel_messages
3. **Opt-out Support**: Users can send "STOP" to opt out
4. **GDPR Compliance**: Support for data deletion requests

### Input Validation

1. **Phone Numbers**: Validated against E.164 format
2. **Media Types**: Whitelist of allowed MIME types
3. **File Size**: Configurable maximum file size
4. **Message Length**: Automatic chunking for long messages

## Testing

### Automated Tests

Run the integration tests:

```bash
php tests/test_whatsapp_channel.php
```

This tests:
- ✅ Agent and channel creation
- ✅ Channel configuration CRUD
- ✅ Session creation and retrieval
- ✅ Message recording and duplicate detection
- ✅ Agent lookup by business number

### Manual Testing

#### Test Message Sending

1. In Admin UI, go to Agents → Channels
2. Click "Send Test Message"
3. Enter recipient phone number
4. Check delivery in WhatsApp

#### Test Message Receiving

1. Send a WhatsApp message to your business number
2. Check logs: `logs/chatbot.log`
3. Verify response received in WhatsApp
4. Check session created in Admin UI → Channels → View Sessions

#### Test Media Upload

1. Send an image to your business number
2. Check if agent processes and responds
3. Verify media downloaded and passed to AI

#### Test Message Chunking

1. Configure a low `reply_chunk_size` (e.g., 500)
2. Ask a question that generates a long response
3. Verify multiple messages received in order

## Troubleshooting

### No Response to Messages

**Possible causes:**
1. Channel not enabled - Check Admin UI
2. Wrong webhook URL - Verify in Z-API dashboard
3. Agent not found - Check agent_id in webhook URL
4. API key invalid - Test in Admin UI

**Debug:**
```bash
tail -f logs/chatbot.log | grep "WhatsApp Webhook"
```

### Duplicate Messages

**Possible causes:**
1. Z-API sending duplicate webhooks
2. External message ID not unique

**Fix:**
- Idempotency is built-in; duplicates are automatically skipped
- Check `channel_messages` table for `external_message_id`

### Media Not Processing

**Possible causes:**
1. `allow_media_upload` disabled
2. File too large
3. MIME type not allowed
4. Download failed

**Debug:**
- Check logs for "media processing error"
- Verify `max_media_size_bytes` and `allowed_media_types`

### Long Messages Not Chunked

**Possible causes:**
1. `reply_chunk_size` too high
2. Message exactly at boundary

**Fix:**
- Adjust `reply_chunk_size` in channel config
- Default 4000 chars is safe for WhatsApp

### Webhook Signature Verification Failed

**Possible causes:**
1. Wrong `zapi_webhook_secret` configured
2. Z-API not sending signature header

**Fix:**
- Leave `zapi_webhook_secret` empty if Z-API doesn't support signatures
- Check Z-API documentation for signature header format

### Session Not Found

**Possible causes:**
1. Database migration not run
2. Session expired/deleted

**Fix:**
```bash
php scripts/run_migrations.php
```

## Advanced Configuration

### Custom Message Handler

Extend the webhook to add custom logic:

```php
// In channels/whatsapp/webhook.php, modify the processInbound callback
$result = $channelManager->processInbound($agentId, 'whatsapp', $payload, 
    function($message, $conversationId, $session) use ($chatHandler, $channelManager, $agentId) {
        
        // Add custom logic here
        if (strpos($message['text'], '#support') === 0) {
            // Route to support team
            return handleSupportTicket($message, $conversationId);
        }
        
        // Default AI processing
        // ...
    }
);
```

### Multiple Agents per Number

To route based on keywords instead of separate numbers:

```php
// Modify webhook.php to check message content
$text = strtolower($message['text'] ?? '');
if (strpos($text, 'sales') !== false) {
    $agentId = SALES_AGENT_ID;
} elseif (strpos($text, 'support') !== false) {
    $agentId = SUPPORT_AGENT_ID;
}
```

### Background Job Processing

For high-volume scenarios, queue messages for background processing:

```php
// In webhook.php, instead of processing immediately
require_once __DIR__ . '/../../includes/JobQueue.php';
$jobQueue = new JobQueue($db);

$jobQueue->enqueue('process_whatsapp_message', [
    'agent_id' => $agentId,
    'payload' => $payload
]);

// Return 200 immediately
http_response_code(200);
echo json_encode(['success' => true, 'queued' => true]);
exit();
```

## Future Enhancements

- [ ] Support for WhatsApp Business Templates
- [ ] Delivery and read receipts
- [ ] Interactive buttons and lists
- [ ] Group message support
- [ ] Multiple channels per agent (Telegram, Email, etc.)
- [ ] Analytics dashboard for channel performance
- [ ] Automated sentiment analysis
- [ ] Multi-language support detection

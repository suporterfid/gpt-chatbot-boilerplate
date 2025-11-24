# Public API Documentation

**Version:** 1.0.1
**Last Updated:** January 2025

This document provides comprehensive documentation for the public-facing APIs used by end-users and client applications.

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Chat API](#chat-api)
- [Public Agent API](#public-agent-api)
- [Whitelabel Agent Access](#whitelabel-agent-access)
- [WebSocket API](#websocket-api)
- [File Upload](#file-upload)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Examples](#examples)
- [SDKs & Libraries](#sdks--libraries)

## Overview

The Public API provides endpoints for:
- **Chat Interactions**: Send messages and receive AI responses
- **Agent Discovery**: List and retrieve public agents
- **Whitelabel Access**: Access branded agent interfaces
- **Real-time Communication**: WebSocket streaming
- **File Upload**: Attach files to conversations

**Base URL:** `https://your-domain.com`

**Supported Features:**
- Server-Sent Events (SSE) streaming
- WebSocket real-time communication
- File uploads (PDFs, documents, images)
- Structured JSON responses
- Tool/function calling
- Vector store file search
- Multi-turn conversations

## Authentication

### Public Endpoints (No Authentication)

Most public endpoints require no authentication:
- `/chat-unified.php` (basic chat)
- `/api/public/agents.php` (agent listing)
- `/agent-chat.php` (full-page chat interface)

### Whitelabel Endpoints (HMAC Authentication)

Whitelabel agents with `require_signed_requests: true` need HMAC tokens:

**Header:**
```
Authorization: Bearer {wl_token}
```

See [Whitelabel Agent Access](#whitelabel-agent-access) for token generation.

### Tenant Identification (Optional)

For multi-tenant deployments, include:

**Header:**
```
X-Tenant-ID: {tenant_id}
```

Or URL parameter:
```
?tenant_id={tenant_id}
```

## Chat API

The primary endpoint for chat interactions.

### POST /chat-unified.php

Send a message and receive AI response.

**Request Headers:**
```
Content-Type: application/json
Accept: text/event-stream (for SSE) or application/json (for AJAX)
```

**Request Body:**
```json
{
  "message": "Hello, how can you help me?",
  "conversation_id": "conv_abc123",
  "agent_id": "agent_xyz789",
  "stream": true,
  "api_type": "responses",
  "file_data": [{
    "filename": "document.pdf",
    "content": "base64_encoded_content",
    "mime_type": "application/pdf"
  }]
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | User's message (max 4000 chars) |
| `conversation_id` | string | No | Unique conversation ID (auto-generated if omitted) |
| `agent_id` | string | No | Specific agent UUID (uses default if omitted) |
| `agent_public_id` | string | No | Public agent ID for whitelabel access |
| `stream` | boolean | No | Enable streaming (default: true) |
| `api_type` | string | No | "chat" or "responses" (default: "responses") |
| `file_data` | array | No | File attachments (base64 encoded) |
| `tenant_id` | string | No | Tenant identifier |
| `wl_token` | string | No | Whitelabel HMAC token |

**Response (SSE Streaming):**

```
event: start
data: {"conversation_id": "conv_abc123", "agent_id": "agent_xyz789", "api_type": "responses"}

event: message
data: {"type": "start"}

event: message
data: {"type": "chunk", "content": "Hello"}

event: message
data: {"type": "chunk", "content": "! I"}

event: message
data: {"type": "chunk", "content": " can"}

event: message
data: {"type": "chunk", "content": " help"}

event: message
data: {"type": "chunk", "content": " you"}

event: message
data: {"type": "chunk", "content": " with"}

event: message
data: {"type": "chunk", "content": "..."}

event: message
data: {"type": "done"}

event: close
data: null
```

**Response (AJAX Non-Streaming):**

```json
{
  "response": "Hello! I can help you with information, answer questions, and assist with various tasks. What would you like to know?",
  "conversation_id": "conv_abc123",
  "tokens_used": 32,
  "model": "gpt-4",
  "finish_reason": "stop"
}
```

**Tool Calls (SSE):**

When the AI uses tools:

```
event: message
data: {"type": "tool_call_start", "tool_name": "file_search", "tool_call_id": "call_abc123"}

event: message
data: {"type": "tool_call_result", "tool_call_id": "call_abc123", "result": "Found 3 relevant documents..."}

event: message
data: {"type": "chunk", "content": "Based on the documentation..."}
```

**Error Response:**

```json
{
  "error": "Message is required",
  "code": "MISSING_MESSAGE",
  "details": {},
  "timestamp": "2025-01-20T10:30:00Z"
}
```

---

### GET /chat-unified.php

Alternative GET endpoint for SSE streaming (useful for EventSource API).

**Query Parameters:**
```
?message=Hello&conversation_id=conv_abc123&agent_id=agent_xyz789
```

**Example:**
```javascript
const eventSource = new EventSource('/chat-unified.php?message=Hello&conversation_id=conv_123');

eventSource.addEventListener('message', (e) => {
  const data = JSON.parse(e.data);
  console.log(data);
});

eventSource.addEventListener('error', (e) => {
  console.error('Connection error:', e);
});
```

---

### Agent Selection Behavior

The chat endpoint determines which agent configuration to use based on this priority:

1. **Explicit `agent_id`**: Uses specified agent configuration
2. **Explicit `agent_public_id`**: Uses whitelabel agent configuration
3. **Default Agent**: Uses tenant's default agent
4. **Config Fallback**: Uses global config.php settings

**Configuration Merging:**

Request parameters > Agent configuration > config.php defaults

**Example:**

```json
{
  "message": "Hello",
  "agent_id": "agent_support_bot",
  "conversation_id": "conv_123"
}
```

This will:
1. Load agent "agent_support_bot" configuration
2. Use agent's model, temperature, prompt, etc.
3. Maintain conversation context using "conv_123"

---

## Public Agent API

Discover and retrieve public agents.

### GET /api/public/agents.php

List all public agents.

**Query Parameters:**
- `tenant_id` (string): Filter by tenant
- `search` (string): Search by name/description
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 20, max: 100)

**Request:**
```bash
curl "https://your-domain.com/api/public/agents.php?search=support"
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "agent_abc123",
      "public_id": "pub_agent_abc123",
      "name": "Customer Support Bot",
      "slug": "customer-support",
      "description": "24/7 customer support assistant",
      "capabilities": [
        "file_search",
        "multi-turn_conversation"
      ],
      "model": "gpt-4",
      "whitelabel": {
        "enabled": true,
        "vanity_path": "/a/support",
        "custom_domain": "help.example.com",
        "requires_auth": false,
        "ui_config": {
          "title": "Customer Support",
          "logo_url": "https://example.com/logo.png",
          "primary_color": "#007bff"
        }
      },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

### GET /api/public/agents.php?agent_id={id}

Retrieve specific agent details.

**Request:**
```bash
curl "https://your-domain.com/api/public/agents.php?agent_id=agent_abc123"
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": "agent_abc123",
    "public_id": "pub_agent_abc123",
    "name": "Customer Support Bot",
    "slug": "customer-support",
    "description": "24/7 customer support assistant for handling inquiries, troubleshooting, and general support",
    "capabilities": [
      "file_search",
      "multi-turn_conversation",
      "context_retention"
    ],
    "model": "gpt-4",
    "supported_file_types": [
      "pdf",
      "txt",
      "doc",
      "docx"
    ],
    "max_file_size_mb": 10,
    "whitelabel": {
      "enabled": true,
      "vanity_path": "/a/support",
      "vanity_url": "https://your-domain.com/a/support",
      "custom_domain": "help.example.com",
      "custom_domain_url": "https://help.example.com",
      "requires_auth": false,
      "ui_config": {
        "title": "Customer Support",
        "logo_url": "https://example.com/logo.png",
        "primary_color": "#007bff",
        "welcome_message": "Hello! How can we help you today?",
        "placeholder": "Type your message...",
        "show_footer_branding": false
      }
    },
    "rate_limits": {
      "requests_per_minute": 60,
      "requests_per_hour": 1000
    },
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

---

### GET /api/public/agents.php?slug={slug}

Retrieve agent by slug.

**Request:**
```bash
curl "https://your-domain.com/api/public/agents.php?slug=customer-support"
```

**Response:** Same as agent_id lookup

---

## Whitelabel Agent Access

Branded agent interfaces with custom domains and optional HMAC authentication.

### Access Methods

1. **Vanity Path:** `https://your-domain.com/a/{slug}`
2. **Custom Domain:** `https://custom-domain.com`
3. **Direct URL:** `https://your-domain.com/agent-chat.php?agent_public_id={id}`

### HMAC Token Generation

For agents with `require_signed_requests: true`:

**Token Structure:**
```
{base64(payload)}.{hmac_signature}
```

**Payload:**
```json
{
  "agent_public_id": "pub_agent_abc123",
  "timestamp": 1705747800,
  "nonce": "random_string_123"
}
```

**Algorithm:** HMAC-SHA256

**Example (JavaScript):**

```javascript
const crypto = require('crypto');

function generateWhitelabelToken(agentPublicId, secret) {
  const timestamp = Math.floor(Date.now() / 1000);
  const nonce = crypto.randomBytes(16).toString('hex');

  const payload = {
    agent_public_id: agentPublicId,
    timestamp: timestamp,
    nonce: nonce
  };

  const payloadString = JSON.stringify(payload);
  const signature = crypto
    .createHmac('sha256', secret)
    .update(payloadString)
    .digest('hex');

  const base64Payload = Buffer.from(payloadString).toString('base64');

  return `${base64Payload}.${signature}`;
}

// Usage
const token = generateWhitelabelToken(
  'pub_agent_abc123',
  'your_secret_key'
);

// Send with request
fetch('/chat-unified.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    message: 'Hello',
    agent_public_id: 'pub_agent_abc123'
  })
});
```

**Example (Python):**

```python
import hmac
import hashlib
import json
import base64
import time
import secrets

def generate_whitelabel_token(agent_public_id, secret):
    timestamp = int(time.time())
    nonce = secrets.token_hex(16)

    payload = {
        'agent_public_id': agent_public_id,
        'timestamp': timestamp,
        'nonce': nonce
    }

    payload_string = json.dumps(payload)
    signature = hmac.new(
        secret.encode(),
        payload_string.encode(),
        hashlib.sha256
    ).hexdigest()

    base64_payload = base64.b64encode(payload_string.encode()).decode()

    return f"{base64_payload}.{signature}"

# Usage
token = generate_whitelabel_token('pub_agent_abc123', 'your_secret_key')
```

**Token Validation:**

- **Timestamp:** Must be within TTL window (default: 3600 seconds)
- **Nonce:** Must be unique (prevents replay attacks)
- **Signature:** Must match HMAC-SHA256(payload, secret)

**Token Expiration:**

Tokens expire after `wl_token_ttl_seconds` (configurable per agent).

**Error Response (Invalid Token):**

```json
{
  "error": "Invalid or expired whitelabel token",
  "code": "INVALID_WL_TOKEN",
  "details": {
    "reason": "Token expired"
  }
}
```

---

### Full-Page Chat Interface

**Endpoint:** `/agent-chat.php`

Provides a complete chat interface for a specific agent.

**Query Parameters:**
- `agent_public_id` (string): Public agent ID
- `slug` (string): Agent slug
- `wl_token` (string): HMAC token (if required)

**Example:**
```
https://your-domain.com/agent-chat.php?slug=customer-support
```

**Features:**
- Responsive design
- Full conversation history
- File upload support
- Markdown rendering
- Customizable branding
- Mobile-friendly

---

## WebSocket API

Real-time bidirectional communication for lower latency.

### Connection

**Endpoint:** `ws://your-domain.com:8080` (or `wss://` for SSL)

**Connect:**
```javascript
const ws = new WebSocket('wss://your-domain.com:8080');

ws.onopen = function() {
  console.log('Connected to WebSocket');
};

ws.onmessage = function(event) {
  const data = JSON.parse(event.data);
  handleMessage(data);
};

ws.onerror = function(error) {
  console.error('WebSocket error:', error);
};

ws.onclose = function() {
  console.log('WebSocket disconnected');
};
```

---

### Send Message

**Client → Server:**

```json
{
  "message": "Hello, how are you?",
  "conversation_id": "conv_abc123",
  "agent_id": "agent_xyz789"
}
```

**Example:**
```javascript
ws.send(JSON.stringify({
  message: 'Hello, how are you?',
  conversation_id: 'conv_123',
  agent_id: 'agent_abc123'
}));
```

---

### Receive Messages

**Server → Client:**

**Connection Confirmed:**
```json
{
  "type": "connected",
  "message": "Connected to ChatBot WebSocket server"
}
```

**Response Start:**
```json
{
  "type": "start",
  "conversation_id": "conv_abc123"
}
```

**Response Chunks:**
```json
{
  "type": "chunk",
  "content": "Hello! I'm"
}
```

```json
{
  "type": "chunk",
  "content": " doing well"
}
```

**Response Complete:**
```json
{
  "type": "done"
}
```

**Error:**
```json
{
  "type": "error",
  "message": "Message too long",
  "code": "MESSAGE_TOO_LONG"
}
```

**Tool Call:**
```json
{
  "type": "tool_call",
  "tool_name": "file_search",
  "tool_call_id": "call_abc123"
}
```

---

### Heartbeat/Ping

The server sends periodic ping messages to keep connections alive:

**Server → Client:**
```json
{
  "type": "ping"
}
```

**Client → Server:**
```json
{
  "type": "pong"
}
```

**Example:**
```javascript
ws.onmessage = function(event) {
  const data = JSON.parse(event.data);

  if (data.type === 'ping') {
    ws.send(JSON.stringify({ type: 'pong' }));
    return;
  }

  // Handle other message types
  handleMessage(data);
};
```

---

### Reconnection

WebSocket connections may drop. Implement reconnection logic:

```javascript
let ws;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;
const reconnectDelay = 1000; // Start with 1 second

function connect() {
  ws = new WebSocket('wss://your-domain.com:8080');

  ws.onopen = function() {
    console.log('Connected');
    reconnectAttempts = 0;
  };

  ws.onclose = function() {
    if (reconnectAttempts < maxReconnectAttempts) {
      const delay = reconnectDelay * Math.pow(2, reconnectAttempts);
      console.log(`Reconnecting in ${delay}ms...`);

      setTimeout(() => {
        reconnectAttempts++;
        connect();
      }, delay);
    } else {
      console.error('Max reconnection attempts reached');
    }
  };
}

connect();
```

---

## File Upload

Upload files to include in conversations (requires agent with file search enabled).

### Upload via HTTP

**Method 1: Base64 in JSON**

```javascript
// Convert file to base64
function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result.split(',')[1]);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

// Upload
const fileInput = document.getElementById('file-input');
const file = fileInput.files[0];
const base64Content = await fileToBase64(file);

const response = await fetch('/chat-unified.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    message: 'Please analyze this document',
    file_data: [{
      filename: file.name,
      content: base64Content,
      mime_type: file.type
    }],
    conversation_id: 'conv_123'
  })
});
```

**Method 2: Multipart Form Data**

```javascript
const formData = new FormData();
formData.append('message', 'Please analyze this document');
formData.append('file', file);
formData.append('conversation_id', 'conv_123');

const response = await fetch('/chat-unified.php', {
  method: 'POST',
  body: formData
});
```

---

### Upload via WebSocket

Base64 encode files before sending:

```javascript
const base64Content = await fileToBase64(file);

ws.send(JSON.stringify({
  message: 'Please analyze this document',
  conversation_id: 'conv_123',
  file_data: [{
    filename: file.name,
    content: base64Content,
    mime_type: file.type
  }]
}));
```

---

### Supported File Types

- **Documents:** PDF, TXT, DOC, DOCX, RTF
- **Spreadsheets:** XLS, XLSX, CSV
- **Presentations:** PPT, PPTX
- **Code:** JSON, XML, HTML, JS, PY, etc.
- **Images:** PNG, JPG, JPEG, GIF, WEBP (vision models only)

---

### File Size Limits

- **Maximum file size:** 10 MB (configurable per agent)
- **Maximum files per message:** 5
- **Total upload size per message:** 50 MB

---

### File Processing

1. File is uploaded with message
2. Server validates file type and size
3. File is uploaded to OpenAI
4. OpenAI processes file (may take seconds)
5. File is attached to vector store (if applicable)
6. AI can search file contents in responses

**Processing Time:**
- Small files (< 1 MB): 1-5 seconds
- Medium files (1-5 MB): 5-15 seconds
- Large files (5-10 MB): 15-60 seconds

---

## Error Handling

### Error Response Format

```json
{
  "error": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {
    "field": "Additional context"
  },
  "timestamp": "2025-01-20T10:30:00Z",
  "request_id": "req_abc123"
}
```

---

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request (invalid parameters) |
| 401 | Unauthorized (invalid/missing auth) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found (resource doesn't exist) |
| 429 | Too Many Requests (rate limited) |
| 500 | Internal Server Error |
| 503 | Service Unavailable |

---

### Error Codes

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `MISSING_MESSAGE` | Message parameter is required | 400 |
| `MESSAGE_TOO_LONG` | Message exceeds maximum length | 400 |
| `INVALID_CONVERSATION_ID` | Invalid conversation ID format | 400 |
| `INVALID_AGENT_ID` | Agent not found | 404 |
| `INVALID_WL_TOKEN` | Invalid whitelabel token | 401 |
| `TOKEN_EXPIRED` | Whitelabel token expired | 401 |
| `RATE_LIMIT_EXCEEDED` | Too many requests | 429 |
| `QUOTA_EXCEEDED` | Usage quota exceeded | 429 |
| `FILE_TOO_LARGE` | File exceeds size limit | 400 |
| `UNSUPPORTED_FILE_TYPE` | File type not supported | 400 |
| `AGENT_NOT_FOUND` | Specified agent doesn't exist | 404 |
| `TENANT_SUSPENDED` | Tenant account suspended | 403 |
| `SERVICE_UNAVAILABLE` | OpenAI API unavailable | 503 |
| `INTERNAL_ERROR` | Internal server error | 500 |

---

### Error Handling Best Practices

**1. Implement Retry Logic**

```javascript
async function sendMessageWithRetry(message, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch('/chat-unified.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
      });

      if (response.ok) {
        return await response.json();
      }

      if (response.status === 429) {
        // Rate limited - wait and retry
        const retryAfter = response.headers.get('Retry-After') || 5;
        await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
        continue;
      }

      if (response.status >= 500) {
        // Server error - retry with exponential backoff
        await new Promise(resolve =>
          setTimeout(resolve, Math.pow(2, i) * 1000)
        );
        continue;
      }

      // Client error - don't retry
      throw new Error(await response.text());

    } catch (error) {
      if (i === maxRetries - 1) throw error;
    }
  }
}
```

**2. Handle Specific Error Codes**

```javascript
async function handleChatError(error) {
  switch (error.code) {
    case 'RATE_LIMIT_EXCEEDED':
      showNotification('Please wait a moment before sending another message');
      break;

    case 'MESSAGE_TOO_LONG':
      showNotification('Your message is too long. Please shorten it.');
      break;

    case 'QUOTA_EXCEEDED':
      showNotification('Usage quota exceeded. Please upgrade your plan.');
      break;

    case 'SERVICE_UNAVAILABLE':
      showNotification('Service is temporarily unavailable. Please try again later.');
      enableRetryButton();
      break;

    case 'INVALID_WL_TOKEN':
      // Token expired - regenerate
      const newToken = await generateWhitelabelToken();
      retryWithNewToken(newToken);
      break;

    default:
      showNotification('An error occurred. Please try again.');
  }
}
```

**3. Log Errors with Context**

```javascript
function logError(error, context) {
  console.error('Chat API Error:', {
    error: error,
    context: context,
    timestamp: new Date().toISOString(),
    request_id: error.request_id,
    user_agent: navigator.userAgent
  });

  // Send to error tracking service
  if (window.errorTracker) {
    window.errorTracker.captureException(error, { extra: context });
  }
}
```

---

## Rate Limiting

### Default Limits

- **Requests per minute:** 60
- **Requests per hour:** 1000
- **Concurrent connections:** 10 per IP
- **Message length:** 4000 characters
- **File uploads:** 5 per minute

### Rate Limit Headers

The API includes rate limit information in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1705747800
Retry-After: 30
```

### Rate Limit Response

**Status:** 429 Too Many Requests

```json
{
  "error": "Rate limit exceeded",
  "code": "RATE_LIMIT_EXCEEDED",
  "details": {
    "limit": 60,
    "window_seconds": 60,
    "retry_after": 30
  },
  "timestamp": "2025-01-20T10:30:00Z"
}
```

### Handling Rate Limits

```javascript
async function sendMessage(message) {
  const response = await fetch('/chat-unified.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message })
  });

  // Check rate limit headers
  const remaining = response.headers.get('X-RateLimit-Remaining');
  const reset = response.headers.get('X-RateLimit-Reset');

  updateRateLimitUI(remaining, reset);

  if (response.status === 429) {
    const retryAfter = response.headers.get('Retry-After');
    showRateLimitWarning(retryAfter);

    // Wait and retry
    await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
    return sendMessage(message);
  }

  return await response.json();
}
```

---

## Examples

### Basic Chat (JavaScript)

```javascript
async function sendChatMessage(message) {
  try {
    const response = await fetch('/chat-unified.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        message: message,
        conversation_id: localStorage.getItem('conversation_id') || null
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message);
    }

    const data = await response.json();

    // Save conversation ID
    localStorage.setItem('conversation_id', data.conversation_id);

    return data.response;

  } catch (error) {
    console.error('Chat error:', error);
    throw error;
  }
}

// Usage
const reply = await sendChatMessage('Hello, how are you?');
console.log('AI:', reply);
```

---

### Streaming Chat (SSE)

```javascript
function streamChatMessage(message, onChunk, onComplete, onError) {
  const eventSource = new EventSource(
    `/chat-unified.php?message=${encodeURIComponent(message)}&conversation_id=${localStorage.getItem('conversation_id') || ''}`
  );

  let fullResponse = '';

  eventSource.addEventListener('message', (e) => {
    const data = JSON.parse(e.data);

    switch (data.type) {
      case 'start':
        console.log('Response starting...');
        break;

      case 'chunk':
        fullResponse += data.content;
        onChunk(data.content);
        break;

      case 'done':
        eventSource.close();
        onComplete(fullResponse);
        break;
    }
  });

  eventSource.addEventListener('start', (e) => {
    const data = JSON.parse(e.data);
    localStorage.setItem('conversation_id', data.conversation_id);
  });

  eventSource.addEventListener('error', (e) => {
    eventSource.close();
    onError(new Error('Stream error'));
  });

  return eventSource;
}

// Usage
streamChatMessage(
  'Tell me a story',
  (chunk) => {
    // Append chunk to UI
    document.getElementById('response').textContent += chunk;
  },
  (fullResponse) => {
    console.log('Complete response:', fullResponse);
  },
  (error) => {
    console.error('Error:', error);
  }
);
```

---

### Streaming with Fetch API (SSE)

```javascript
async function streamChatWithFetch(message) {
  const response = await fetch('/chat-unified.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'text/event-stream'
    },
    body: JSON.stringify({
      message: message,
      conversation_id: localStorage.getItem('conversation_id')
    })
  });

  const reader = response.body.getReader();
  const decoder = new TextDecoder();

  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();

    if (done) break;

    buffer += decoder.decode(value, { stream: true });

    const lines = buffer.split('\n');
    buffer = lines.pop(); // Keep incomplete line in buffer

    for (const line of lines) {
      if (line.startsWith('data: ')) {
        const data = JSON.parse(line.slice(6));

        if (data.type === 'chunk') {
          document.getElementById('response').textContent += data.content;
        }
      }
    }
  }
}
```

---

### WebSocket Chat

```javascript
class ChatWebSocket {
  constructor(url) {
    this.url = url;
    this.ws = null;
    this.messageQueue = [];
    this.connected = false;
  }

  connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.url);

      this.ws.onopen = () => {
        this.connected = true;
        console.log('WebSocket connected');

        // Send queued messages
        while (this.messageQueue.length > 0) {
          this.ws.send(this.messageQueue.shift());
        }

        resolve();
      };

      this.ws.onerror = (error) => {
        console.error('WebSocket error:', error);
        reject(error);
      };

      this.ws.onclose = () => {
        this.connected = false;
        console.log('WebSocket disconnected');

        // Attempt reconnection
        setTimeout(() => this.connect(), 3000);
      };
    });
  }

  sendMessage(message, conversationId) {
    const payload = JSON.stringify({
      message: message,
      conversation_id: conversationId
    });

    if (this.connected) {
      this.ws.send(payload);
    } else {
      this.messageQueue.push(payload);
    }
  }

  onMessage(callback) {
    this.ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      callback(data);
    };
  }

  close() {
    this.ws.close();
  }
}

// Usage
const chat = new ChatWebSocket('wss://your-domain.com:8080');
await chat.connect();

chat.onMessage((data) => {
  if (data.type === 'chunk') {
    document.getElementById('response').textContent += data.content;
  }
});

chat.sendMessage('Hello!', 'conv_123');
```

---

### File Upload

```javascript
async function uploadFileAndChat(file, message) {
  // Convert file to base64
  const base64 = await new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result.split(',')[1]);
    reader.readAsDataURL(file);
  });

  // Send with message
  const response = await fetch('/chat-unified.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      message: message,
      file_data: [{
        filename: file.name,
        content: base64,
        mime_type: file.type
      }],
      conversation_id: localStorage.getItem('conversation_id')
    })
  });

  const data = await response.json();
  return data.response;
}

// Usage
const fileInput = document.getElementById('file-input');
const file = fileInput.files[0];

const reply = await uploadFileAndChat(file, 'Please summarize this document');
console.log('AI:', reply);
```

---

### Whitelabel Agent with HMAC

```javascript
// Generate HMAC token
async function generateToken(agentPublicId, secret) {
  const timestamp = Math.floor(Date.now() / 1000);
  const nonce = Array.from(crypto.getRandomValues(new Uint8Array(16)))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');

  const payload = {
    agent_public_id: agentPublicId,
    timestamp: timestamp,
    nonce: nonce
  };

  const payloadString = JSON.stringify(payload);

  // Create HMAC signature
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    'raw',
    encoder.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );

  const signature = await crypto.subtle.sign(
    'HMAC',
    key,
    encoder.encode(payloadString)
  );

  const signatureHex = Array.from(new Uint8Array(signature))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');

  const base64Payload = btoa(payloadString);

  return `${base64Payload}.${signatureHex}`;
}

// Use token
async function sendWhitelabelMessage(message, agentPublicId, secret) {
  const token = await generateToken(agentPublicId, secret);

  const response = await fetch('/chat-unified.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      message: message,
      agent_public_id: agentPublicId
    })
  });

  return await response.json();
}

// Usage
const reply = await sendWhitelabelMessage(
  'Hello!',
  'pub_agent_abc123',
  'your_secret_key'
);
```

---

### React Hook Example

```javascript
import { useState, useEffect, useRef } from 'react';

function useChat(agentId) {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const conversationIdRef = useRef(null);

  const sendMessage = async (message) => {
    try {
      setLoading(true);
      setError(null);

      // Add user message
      setMessages(prev => [...prev, {
        role: 'user',
        content: message,
        timestamp: new Date()
      }]);

      const response = await fetch('/chat-unified.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: message,
          conversation_id: conversationIdRef.current,
          agent_id: agentId
        })
      });

      if (!response.ok) {
        throw new Error('Failed to send message');
      }

      const data = await response.json();

      // Save conversation ID
      conversationIdRef.current = data.conversation_id;

      // Add assistant message
      setMessages(prev => [...prev, {
        role: 'assistant',
        content: data.response,
        timestamp: new Date()
      }]);

    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const clearHistory = () => {
    setMessages([]);
    conversationIdRef.current = null;
  };

  return {
    messages,
    loading,
    error,
    sendMessage,
    clearHistory
  };
}

// Usage in component
function ChatComponent() {
  const { messages, loading, error, sendMessage, clearHistory } = useChat('agent_abc123');
  const [input, setInput] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (input.trim()) {
      sendMessage(input);
      setInput('');
    }
  };

  return (
    <div>
      <div className="messages">
        {messages.map((msg, i) => (
          <div key={i} className={`message ${msg.role}`}>
            {msg.content}
          </div>
        ))}
        {loading && <div>Loading...</div>}
        {error && <div>Error: {error}</div>}
      </div>

      <form onSubmit={handleSubmit}>
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          disabled={loading}
        />
        <button type="submit" disabled={loading}>Send</button>
      </form>

      <button onClick={clearHistory}>Clear</button>
    </div>
  );
}
```

---

## SDKs & Libraries

### Official JavaScript SDK

```bash
npm install @your-company/chatbot-sdk
```

```javascript
import { ChatBot } from '@your-company/chatbot-sdk';

const bot = new ChatBot({
  apiEndpoint: 'https://your-domain.com/chat-unified.php',
  agentId: 'agent_abc123'
});

const response = await bot.sendMessage('Hello!');
console.log(response);
```

### Community Libraries

- **Python:** `pip install chatbot-client`
- **Ruby:** `gem install chatbot-client`
- **PHP:** `composer require your-company/chatbot-sdk`
- **Java:** Maven/Gradle dependency

---

## Best Practices

### Performance

1. **Use streaming** for better perceived latency
2. **Cache conversation IDs** to maintain context
3. **Implement connection pooling** for WebSocket
4. **Compress requests** when possible
5. **Use CDN** for static assets

### Security

1. **Never expose secrets** in client code
2. **Use HTTPS** for all requests
3. **Validate file uploads** client-side
4. **Implement CSP headers**
5. **Sanitize user input**

### User Experience

1. **Show typing indicators** during streaming
2. **Handle errors gracefully**
3. **Provide retry options**
4. **Display rate limit warnings**
5. **Save conversation history**

### Reliability

1. **Implement exponential backoff** for retries
2. **Handle network failures**
3. **Queue messages** during disconnection
4. **Monitor error rates**
5. **Log with request IDs**

---

## Support

For additional help:

- **Documentation:** [docs/](../README.md)
- **API Reference:** [api.md](api.md)
- **Admin API:** [admin-api.md](admin-api.md)
- **JavaScript Client:** [client-api.md](client-api.md)
- **Issue Tracker:** GitHub Issues
- **Email Support:** support@your-domain.com

---

**Version:** 1.0.1
**Last Updated:** January 2025
**API Stability:** Stable (no breaking changes in v1.x)

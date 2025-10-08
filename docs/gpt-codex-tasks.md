# GPT Codex OpenAI Responses API Migration Tasks

## Tasks:
1. **Add Responses API configuration and prompt support**  
   Expand configuration handling to support `API_TYPE=responses`, expose new environment variables (including the saved prompt ID), and ensure the frontend defaults to the Responses workflow when configured.

2. **Implement Responses API streaming in `OpenAIClient`**  
   Introduce a streaming client for `/responses`, adjust headers, align file-upload purposes, and retire assistant-specific helpers when the Responses path is active.

3. **Swap assistant workflow for Responses in `ChatHandler`**  
   Build a Responses-aware chat handler that constructs the payload from conversation history, relays SSE events using the existing envelope, manages tool calls, and drops assistant-only dependencies.

4. **Update frontend streaming for Responses events**  
   Adapt the chat widget to interpret the backend's Responses-style SSE payloads while remaining compatible with chat completions.

5. **Refresh docs and remove obsolete assistant scaffolding**  
   Update documentation for Responses mode, remove unused assistant helpers, and provide guidance for managing prompt IDs and fallbacks.

   Perfeito 👍 — essa é uma atualização importante do novo modelo de uso da **Responses API**, pois ela introduz o conceito de **Prompt Templates** (salvos previamente via `/v1/prompts`) e referenciados por **ID** em chamadas subsequentes.
Abaixo está uma **versão revisada e expandida** da especificação anterior, **atualizada** para incluir o suporte a **pre-saved prompts (prompt IDs)**.

---
# Migration guide:

# Specification: Migrating from Assistants API to Responses API with Prompt References (PHP)

## Overview
This document defines how to migrate a PHP project from the deprecated **OpenAI Assistants API** to the new **Responses API**, while adding support for **pre-saved prompts**.  
The new API provides a more flexible, unified interface for text generation, streaming, and tool integration.

---

## 1. Objective
- Replace all usage of the **Assistants API** with the **Responses API**.  
- Enable the use of **prompt templates** referenced by ID for consistent response behavior.  
- Maintain backward compatibility with existing frontend chat components.  
- Support both **inline prompts** and **pre-saved prompt references**.  

---

## 2. API Overview

| Functionality | Assistants API | Responses API |
|----------------|----------------|----------------|
| Endpoint | `/v1/assistants`, `/v1/threads` | `/v1/responses` |
| State | Threaded, server-managed | Stateless, client-managed |
| Prompt Handling | Messages under a thread | Supports inline messages **or prompt references** |
| Streaming | `/runs/stream` | Single endpoint with `stream=true` |
| Tools | Defined per assistant | Defined per request |
| File Uploads | Vector store links | Direct via `file_ids` |
| Reusability | Limited to assistant definition | Reusable via saved `prompt` objects |

---

## 3. Using Pre-Saved Prompts

The new API allows using **saved prompts** via `/v1/prompts`.  
These prompts can store pre-defined system instructions, parameters, or tool definitions — ideal for standardizing assistant behavior.

### Example: cURL Request Using a Prompt Reference
```bash
curl https://api.openai.com/v1/responses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -d '{
    "prompt": {
      "id": "pmpt_68e6beb6f5d48196af664ee617bef1fd0808ad312484a525",
      "version": "1"
    }
  }'
````

This uses a **previously saved prompt** identified by `id` and an optional `version`.

---

## 4. PHP Implementation

### 4.1 Inline Input (Basic Use Case)

```php
$response = $client->responses()->create([
  'model' => 'gpt-4.1',
  'input' => [
    ['role' => 'user', 'content' => 'Summarize this text in one sentence.']
  ]
]);
```

### 4.2 Referencing a Saved Prompt

```php
$response = $client->responses()->create([
  'prompt' => [
    'id' => 'pmpt_68e6beb6f5d48196af664ee617bef1fd0808ad312484a525',
    'version' => '1'
  ],
  'input' => [
    ['role' => 'user', 'content' => 'Explain how RFID works in supply chains.']
  ]
]);
```

This allows the assistant to apply all behaviors, tools, and configurations defined in that saved prompt.

---

## 5. How to Create and Manage Prompts

### 5.1 Creating a New Prompt

```bash
curl https://api.openai.com/v1/prompts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -d '{
    "name": "rfid_expert_agent",
    "description": "An expert assistant for RFID and IoT topics",
    "model": "gpt-4.1",
    "instructions": "You are a senior RFID solutions engineer. Provide technical and practical answers.",
    "metadata": { "version": "1.0" }
  }'
```

### 5.2 Listing Prompts

```bash
curl https://api.openai.com/v1/prompts \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

### 5.3 Updating an Existing Prompt

```bash
curl -X PATCH https://api.openai.com/v1/prompts/pmpt_68e6beb6f5d48196af664ee617bef1fd0808ad312484a525 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -d '{"instructions": "Update to include RAIN RFID applications in logistics."}'
```

---

## 6. Streaming Support

### Example: PHP Streaming Implementation

```php
$stream = $client->responses()->stream([
  'prompt' => [
    'id' => 'pmpt_68e6beb6f5d48196af664ee617bef1fd0808ad312484a525',
    'version' => '1'
  ],
  'input' => [
    ['role' => 'user', 'content' => 'Generate a summary report for today’s tag reads.']
  ],
  'stream' => true
]);

foreach ($stream as $event) {
    if ($event->type === 'response.output_text.delta') {
        echo $event->delta;
        ob_flush();
        flush();
    }
}
```

---

## 7. Migration Steps

1. **Remove all Assistant API dependencies**
   Delete legacy logic using `/assistants`, `/threads`, or `/runs`.

2. **Replace endpoints with `/v1/responses`**
   Update SDK or HTTP calls accordingly.

3. **Implement prompt reference support**
   Add optional `$prompt_id` parameter in your PHP wrapper for reuse.

4. **Manage conversation context manually**
   Store messages locally; reconstruct input arrays per request.

5. **Test all endpoints**
   Verify inline and prompt-based requests both return expected completions.

---

## 8. Extended Example — Wrapper Class

```php
class OpenAIResponseService {
    private $client;

    public function __construct($apiKey) {
        $this->client = OpenAI::client($apiKey);
    }

    public function sendPrompt(string $promptId, string $userMessage, string $version = '1') {
        return $this->client->responses()->create([
            'prompt' => [
                'id' => $promptId,
                'version' => $version
            ],
            'input' => [
                ['role' => 'user', 'content' => $userMessage]
            ]
        ]);
    }
}
```

Usage:

```php
$service = new OpenAIResponseService(getenv('OPENAI_API_KEY'));
$result = $service->sendPrompt('pmpt_68e6beb6f5d48196af664ee617bef1fd0808ad312484a525', 'Describe RFID in logistics.');
echo $result['output'][0]['content'][0]['text'];
```

---

## 9. Migration Validation Checklist

| Task                                  | Status |
| ------------------------------------- | ------ |
| Assistants API removed                | ☐      |
| `/responses` endpoint integrated      | ☐      |
| Prompt reference support implemented  | ☐      |
| Inline input fallback supported       | ☐      |
| Streaming responses validated         | ☐      |
| Prompt creation and versioning tested | ☐      |
| PHP class refactored and documented   | ☐      |

---

## 10. References

* [OpenAI Responses API Documentation](https://platform.openai.com/docs/api-reference/responses)
* [OpenAI Prompts API](https://platform.openai.com/docs/api-reference/prompts)
* [Official OpenAI PHP SDK](https://github.com/openai-php/client)
* [Assistants API Deprecation Notice](https://platform.openai.com/docs/assistants/deprecation)

```




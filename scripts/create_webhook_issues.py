#!/usr/bin/env python3
"""
Script to create GitHub issues for webhook infrastructure implementation.
This script generates markdown files and a shell script to create all issues using GitHub CLI.
"""

import os
import json
import yaml

# Issue definitions from the problem statement
issues = [
    {
        "tag": "wh-001a-task",
        "title": "Bootstrap /webhook/inbound entrypoint",
        "labels": ["task", "webhook", "phase-1"],
        "description": """Create `public/webhook/inbound.php` to expose the POST JSON contract mandated by SPEC §4, replacing ad-hoc listeners with a canonical endpoint for agents and integrators.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §4

**Deliverables:**
- New script loading config/autoloaders
- Validates HTTP method
- Forwards requests to the gateway service
- Returns standardized JSON responses

**Implementation Guidance:**
The existing `chat-unified.php` script serves as a good architectural precedent for an HTTP entrypoint. It handles request validation, content type negotiation, and routing to a handler class. The new `inbound.php` should follow a similar pattern: validate the request is `POST`, load `config.php`, instantiate the `WebhookGateway`, pass the request body and headers to it, and render the JSON response.

```php
// From: chat-unified.php
// This pattern of loading config, validating, and routing to a handler is a good model.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ChatHandler.php';

$chatHandler = new ChatHandler($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... similar validation and routing logic ...
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ...
}
```"""
    },
    {
        "tag": "wh-001b-task",
        "title": "Implement WebhookGateway orchestration service",
        "labels": ["task", "webhook", "phase-1"],
        "description": """Create `includes/WebhookGateway.php` to encapsulate JSON parsing, schema validation, payload normalization, downstream event routing, and consistent responses per SPEC §4.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §4

**Deliverables:**
- Class with `handleRequest($headers, $body)`
- Returns structured arrays/errors
- Reusable across HTTP entrypoints

**Implementation Guidance:**
The `ChatHandler` class is the primary orchestration service in the existing architecture. The new `WebhookGateway` should adopt a similar design, with a main public method (`handleRequest`) that coordinates validation, data processing, and calls to other services. It should manage the flow and return structured data, similar to how `ChatHandler::handleChatRequest` does.

```php
// From: includes/ChatHandler.php
// The new WebhookGateway should have a similar structure.
class WebhookGateway {
    public function __construct($config) {
        // ...
    }

    public function handleRequest($headers, $body) {
        // 1. Validate JSON schema
        // 2. Call WebhookSecurityService
        // 3. Normalize payload
        // 4. Route to downstream handlers (e.g., JobQueue)
        // 5. Return structured response
    }
}
```"""
    },
    {
        "tag": "wh-001c-task",
        "title": "Connect gateway to agent/queue pipeline",
        "labels": ["task", "webhook", "phase-1"],
        "description": """Integrate normalized events from the gateway into `includes/JobQueue.php` or direct agent handlers, enabling synchronous/async processing aligned with SPEC §4.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§2–4

**Deliverables:**
- Adapter or processor class
- Maps normalized events to agent jobs
- Idempotency hooks using `webhook_events`

**Implementation Guidance:**
The `ChatHandler` currently dispatches tasks directly to the `OpenAIClient`. The `WebhookGateway` will need to dispatch events to a job queue for asynchronous processing. This will involve creating a job payload and enqueuing it. The WebSocket server provides an example of handling different types of events and dispatching actions based on them.

```php
// From: websocket-server.php
// This logic can be adapted for queuing jobs from the WebhookGateway.
public function onMessage(ConnectionInterface $from, $msg) {
    $data = json_decode($msg, true);
    switch ($data['type']) {
        case 'chat':
            // In the webhook gateway, this would be where a job is created
            // and added to a queue like RabbitMQ or a DB-based queue.
            $jobPayload = ['event_type' => 'inbound_webhook', 'data' => $normalizedData];
            // $this->jobQueue->enqueue($jobPayload);
            break;
    }
}
```"""
    },
    {
        "tag": "wh-002a-task",
        "title": "Build WebhookSecurityService",
        "labels": ["task", "webhook", "security", "phase-2"],
        "description": """Centralize HMAC validation, timestamp skew enforcement, and IP/ASN whitelist checks in `includes/WebhookSecurityService.php`, replacing scattered signature logic.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §6

**Deliverables:**
- Methods: `validateSignature`, `enforceClockSkew`, `checkWhitelist`
- Configurable via webhooks config entries

**Implementation Guidance:**
The current system centralizes configuration in `config.php`, which is then used by services like `ChatHandler` for rate limiting. The `WebhookSecurityService` should follow this pattern, consuming security-related settings (e.g., secrets, whitelist IPs) from the global config. The methods in this service will encapsulate security logic that is currently absent or would otherwise be scattered.

```php
// From: includes/ChatHandler.php
// The security service should be instantiated with config, similar to ChatHandler.
class WebhookSecurityService {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function validateSignature($header, $body, $secret) {
        // ... HMAC validation logic ...
    }

    public function enforceClockSkew($timestamp) {
        // ... Timestamp validation logic ...
    }

    public function checkWhitelist($ip) {
        // ... IP whitelist check logic from config ...
    }
}
```"""
    },
    {
        "tag": "wh-002b-task",
        "title": "Adopt shared security in all inbound routes",
        "labels": ["task", "webhook", "security", "phase-2"],
        "description": """Refactor `public/webhook/inbound.php`, `webhooks/openai.php`, and `channels/whatsapp/webhook.php` to use the new centralized security service.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §6

**Deliverables:**
- Dependency injection or helper usage
- Unified signature/whitelist/skew enforcement
- Harmonized error responses

**Implementation Guidance:**
The `WebhookGateway` (and other inbound routes) should instantiate and use the `WebhookSecurityService` at the beginning of the request handling process. This is similar to how `ChatHandler` is used in `chat-unified.php`. Early termination with a standardized error response is critical if security checks fail.

```php
// In public/webhook/inbound.php and other entrypoints
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/WebhookSecurityService.php';
require_once __DIR__ . '/../includes/WebhookGateway.php';

$security = new WebhookSecurityService($config);

// Perform security checks before processing
if (!$security->validateSignature(...) || !$security->checkWhitelist(...)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed']);
    exit;
}

$gateway = new WebhookGateway($config);
$response = $gateway->handleRequest($headers, $body);
// ... return response
```"""
    },
    {
        "tag": "wh-003a-task",
        "title": "Author webhook_subscribers migrations (SQLite/MySQL/PostgreSQL)",
        "labels": ["task", "webhook", "database", "phase-3"],
        "description": """Create migrations defining the subscriber schema for all supported DBs exactly as required in SPEC §8.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §8 (subscriber table)

**Deliverables:**
- Three dialect-specific SQL migration files
- Stored under `db/migrations/`

**Implementation Guidance:**
The project already contains a `db/migrations` directory with SQL files for different database dialects. The new migrations for `webhook_subscribers` should follow the existing file naming convention and structure. The schema should adhere strictly to SPEC §8.

**Example (conceptual):**
```sql
-- In db/migrations/036_create_webhook_subscribers.sql (SQLite)
CREATE TABLE IF NOT EXISTS webhook_subscribers (
  id TEXT PRIMARY KEY,
  client_id TEXT NOT NULL,
  url TEXT NOT NULL,
  secret TEXT NOT NULL,
  events TEXT NOT NULL, -- JSON string
  active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```"""
    },
    {
        "tag": "wh-003b-task",
        "title": "Implement WebhookSubscriberRepository",
        "labels": ["task", "webhook", "repository", "phase-3"],
        "description": """Implement CRUD/query operations for webhook subscribers, enabling dispatcher fan-out logic.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§5 & 8

**Deliverables:**  
- PHP class under `includes/`  
- Methods: `listActiveByEvent($eventType)`, `save($subscriber)`

**Implementation Guidance:**
A repository class should encapsulate all database interactions for the `webhook_subscribers` table. The existing architecture does not appear to have a dedicated repository layer, but the `ChatHandler` performs direct database operations for conversation history. The `WebhookSubscriberRepository` should be a new, dedicated class for this purpose.

```php
// in includes/WebhookSubscriberRepository.php
class WebhookSubscriberRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function listActiveByEvent($eventType) {
        $sql = "SELECT * FROM webhook_subscribers WHERE events LIKE ? AND active = 1";
        return $this->db->query($sql, ['%"' . $eventType . '"%']);
    }

    public function save($subscriber) {
        // ... INSERT or UPDATE logic ...
    }
}
```"""
    },
    {
        "tag": "wh-003c-task",
        "title": "Extend admin API/UI for subscriber management",
        "labels": ["task", "webhook", "admin", "ui", "phase-3"],
        "description": """Allow operators to create, update, list, and deactivate subscribers via admin UI/API.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§2 & 8

**Deliverables:**  
- API endpoints  
- Form validation  
- UI components using the repository

**Implementation Guidance:**
The `admin-api.php` already provides a RESTful interface for managing agents. New actions for `create_subscriber`, `update_subscriber`, etc., should be added here, following the existing pattern of using a switch on the `action` parameter. The Admin UI will then use these new API endpoints.

```php
// In admin-api.php
switch ($_GET['action']) {
    case 'create_agent':
        // ... existing logic
        break;
    case 'create_subscriber':
        // 1. Get data from POST body
        // 2. Validate input
        // 3. Call WebhookSubscriberRepository->save()
        // 4. Return JSON response
        break;
    // ... other subscriber actions
}
```
The front-end changes will be in `/public/admin/` to add a new section for webhook subscriber management."""
    },
    {
        "tag": "wh-004a-task",
        "title": "Add webhook_logs migrations (SQLite/MySQL/PostgreSQL)",
        "labels": ["task", "webhook", "database", "phase-4"],
        "description": """Create log table migrations matching SPEC §8 for recording delivery attempts.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §8 (logs)

**Deliverables:**  
- Three SQL migrations  
- Capture request/response/attempt metadata

**Implementation Guidance:**
Similar to the `webhook_subscribers` table, create dialect-specific migration files in `db/migrations/` for the `webhook_logs` table. The schema must align with SPEC §8 to capture all necessary details for logging and retries."""
    },
    {
        "tag": "wh-004b-task",
        "title": "Implement WebhookLogRepository",
        "labels": ["task", "webhook", "repository", "phase-4"],
        "description": """Provide helpers to persist and query webhook logs, enabling analytics and retries.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§5 & 8

**Deliverables:**  
- PHP class for log writes/queries  
- Support for pagination and history lookups

**Implementation Guidance:**
Create a `WebhookLogRepository` class under `includes/`. This class will be responsible for all interactions with the `webhook_logs` table. It will be used by the `WebhookDispatcher` to log each delivery attempt.

```php
// in includes/WebhookLogRepository.php
class WebhookLogRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createLog($logData) {
        // Logic to INSERT a new log entry
    }

    public function updateLog($logId, $updateData) {
        // Logic to UPDATE a log entry (e.g., with response)
    }
}
```"""
    },
    {
        "tag": "wh-004c-task",
        "title": "Surface delivery history in observability/admin UI",
        "labels": ["task", "webhook", "admin", "ui", "observability", "phase-4"],
        "description": """Expose webhook delivery history with attempts, statuses, and latencies.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§8 & 10

**Deliverables:**  
- Admin/API dashboards  
- Filters by subscriber/event/outcome

**Implementation Guidance:**
New API endpoints will be needed in `admin-api.php` to fetch log data from the `WebhookLogRepository`. The Admin UI (`/public/admin/`) will then be extended with a new view to display this data, likely in a paginated table with filtering options."""
    },
    {
        "tag": "wh-005a-task",
        "title": "Implement WebhookDispatcher core service",
        "labels": ["task", "webhook", "dispatcher", "phase-5"],
        "description": """Create `includes/WebhookDispatcher.php` to load subscribers, apply transforms, sign headers, and queue outbound jobs.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**  
- Method: `dispatch($event, $payload, $agentId)`  
- Returns job IDs/log handles

**Implementation Guidance:**
This new service will be the core of the outbound webhook system. It will use the `WebhookSubscriberRepository` to find relevant subscribers for an event and then create jobs for each one. This decouples event generation from delivery.

```php
// in includes/WebhookDispatcher.php
class WebhookDispatcher {
    private $subscriberRepo;
    private $jobQueue;

    public function dispatch($eventType, $payload) {
        $subscribers = $this->subscriberRepo->listActiveByEvent($eventType);
        foreach ($subscribers as $subscriber) {
            $job = ['subscriber' => $subscriber, 'payload' => $payload];
            // This would add the job to RabbitMQ, Redis, or a DB queue
            $this->jobQueue->enqueue('webhook_delivery', $job);
        }
    }
}
```"""
    },
    {
        "tag": "wh-005b-task",
        "title": "Refactor worker job handler for subscriber-aware deliveries",
        "labels": ["task", "webhook", "worker", "phase-5"],
        "description": """Update `scripts/worker.php` to support per-subscriber secrets, attempts, and logs.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**  
- Enhanced job payload schema  
- Standardized headers  
- Log integration

**Implementation Guidance:**
A worker script will be needed to process jobs from the queue. This script will be responsible for the actual HTTP POST to the subscriber's URL, handling signing, and logging the outcome. The `websocket-server.php` logic for making outbound calls can serve as a reference for the HTTP client implementation.

```php
// In scripts/worker.php - add webhook_delivery job handler
case 'webhook_delivery':
    $subscriber = $job['payload']['subscriber'];
    $payload = $job['payload']['payload'];
    
    // 1. Log attempt in WebhookLogRepository
    // 2. Sign payload with subscriber's secret
    // 3. Make HTTP POST request
    // 4. Log response and status
    // 5. If failed, schedule retry
    break;
```"""
    },
    {
        "tag": "wh-005c-task",
        "title": "Migrate existing webhook callers to dispatcher",
        "labels": ["task", "webhook", "refactor", "phase-5"],
        "description": """Ensure all components (e.g., `includes/LeadSense/Notifier.php`) that currently send webhooks directly are refactored to use the `WebhookDispatcher` service. This will centralize outbound webhook logic, ensuring consistent signing, logging, and retries across the application.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Refactored existing webhook sending code
- All outbound webhooks use the dispatcher
- Deprecated direct webhook sending"""
    },
    {
        "tag": "wh-006a-task",
        "title": "Extend JobQueue for attempt/backoff metadata",
        "labels": ["task", "webhook", "queue", "phase-6"],
        "description": """The job queuing system needs to be enhanced to support retry logic. This involves adding metadata to job payloads, such as `attempt_count` and `scheduled_at`, to enable exponential backoff as defined in the spec.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Extended job schema with retry metadata
- Support for scheduling jobs in the future

**Implementation Guidance:**
Review `includes/JobQueue.php` and add fields to the jobs table schema if needed. The existing `available_at` field should support delayed execution. The `attempts` field already exists and tracks retry count."""
    },
    {
        "tag": "wh-006b-task",
        "title": "Implement six-step exponential retry scheduler",
        "labels": ["task", "webhook", "retry", "phase-6"],
        "description": """Implement the retry logic within the webhook worker. When a delivery fails, the worker should calculate the next attempt's delay based on the current attempt number (1s, 5s, 30s, etc.) and re-enqueue the job with an updated `scheduled_at` timestamp.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Exponential backoff calculation: 1s, 5s, 30s, 2min, 10min, 30min
- Re-enqueue failed jobs with correct delay
- Maximum 6 attempts before moving to DLQ

**Implementation Guidance:**
```php
// Backoff schedule
$backoffSchedule = [1, 5, 30, 120, 600, 1800]; // seconds
$attemptNumber = $job['attempts'];
$delay = $backoffSchedule[$attemptNumber - 1] ?? 1800;
```"""
    },
    {
        "tag": "wh-007a-task",
        "title": "Add webhooks configuration block",
        "labels": ["task", "webhook", "config", "phase-7"],
        "description": """Expand `config.php` to include a new section for webhook settings. This will centralize configuration for inbound security (e.g., secrets, whitelists) and outbound behavior (e.g., retry policies), making it easily manageable via environment variables.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §9

**Deliverables:**
- New webhooks configuration section in config.php
- Environment variable parsing

**Implementation Guidance:**
Follow the existing pattern in `config.php` to parse environment variables and populate the `$config` array.

```php
// In config.php
$config['webhooks'] = [
    'inbound' => [
        'enabled' => getEnvValue('WEBHOOK_INBOUND_ENABLED') === 'true',
        'path' => getEnvValue('WEBHOOK_INBOUND_PATH') ?: '/webhook/inbound',
        'validate_signature' => getEnvValue('WEBHOOK_VALIDATE_SIGNATURE') !== 'false',
        'max_clock_skew' => (int)(getEnvValue('WEBHOOK_MAX_CLOCK_SKEW') ?: 120),
        'ip_whitelist' => parseFlexibleEnvArray(getEnvValue('WEBHOOK_IP_WHITELIST')),
    ],
    'outbound' => [
        'enabled' => getEnvValue('WEBHOOK_OUTBOUND_ENABLED') === 'true',
        'max_attempts' => (int)(getEnvValue('WEBHOOK_MAX_ATTEMPTS') ?: 6),
        'timeout' => (int)(getEnvValue('WEBHOOK_TIMEOUT') ?: 5),
        'concurrency' => (int)(getEnvValue('WEBHOOK_CONCURRENCY') ?: 10),
    ],
];
```"""
    },
    {
        "tag": "wh-007b-task",
        "title": "Document new config in .env.example and deployment guides",
        "labels": ["task", "webhook", "documentation", "phase-7"],
        "description": """Update `.env.example` with all new environment variables related to the webhooks system. Add documentation to the README or other relevant guides explaining how to configure and use the new features.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§9-10

**Deliverables:**
- Updated .env.example with webhook variables
- Documentation in README.md or docs/
- Deployment guide updates

**Environment Variables to Add:**
```bash
# Webhook Configuration
WEBHOOK_INBOUND_ENABLED=true
WEBHOOK_INBOUND_PATH=/webhook/inbound
WEBHOOK_VALIDATE_SIGNATURE=true
WEBHOOK_MAX_CLOCK_SKEW=120
WEBHOOK_IP_WHITELIST=

# Outbound Webhooks
WEBHOOK_OUTBOUND_ENABLED=true
WEBHOOK_MAX_ATTEMPTS=6
WEBHOOK_TIMEOUT=5
WEBHOOK_CONCURRENCY=10
```"""
    },
    {
        "tag": "wh-008a-task",
        "title": "Introduce payload-transform and queue hooks",
        "labels": ["enhancement", "webhook", "extensibility", "phase-8"],
        "description": """To allow for greater extensibility, introduce a hook or plugin system. This would enable tenants to register custom payload transformers before dispatch, or to swap out the default job queue driver with an alternative implementation (e.g., from a DB queue to RabbitMQ).

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §10

**Deliverables:**
- Hook system for payload transformation
- Pluggable queue driver interface

**Implementation Guidance:**
```php
// Example hook system
class WebhookDispatcher {
    private $transformHooks = [];
    
    public function registerTransform($eventType, callable $transformer) {
        $this->transformHooks[$eventType][] = $transformer;
    }
    
    private function applyTransforms($eventType, $payload) {
        foreach ($this->transformHooks[$eventType] ?? [] as $hook) {
            $payload = $hook($payload);
        }
        return $payload;
    }
}
```"""
    },
    {
        "tag": "wh-008b-task",
        "title": "Build webhook sandbox/testing utilities",
        "labels": ["enhancement", "webhook", "testing", "phase-8"],
        "description": """Provide tools in the admin UI or via a CLI script to help developers test and debug webhooks. This should include a way to manually trigger events, inspect dispatched payloads, and view delivery logs.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §10

**Deliverables:**
- Admin UI webhook testing interface
- CLI tool for webhook testing
- Mock webhook receiver for testing

**Features:**
- Send test webhook to a URL
- Inspect request/response
- Validate webhook signatures
- Mock webhook endpoint for development"""
    },
    {
        "tag": "wh-008c-task",
        "title": "Enhance observability dashboards for webhook metrics",
        "labels": ["enhancement", "webhook", "observability", "phase-8"],
        "description": """Instrument the webhook system to emit metrics on delivery success/failure rates, latencies, and retry counts. These metrics should be exposed in a format compatible with common monitoring tools and visualized in admin dashboards.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §10

**Deliverables:**
- Metrics collection for webhook events
- Dashboard visualizations
- Alerting on webhook failures

**Metrics to Track:**
- `webhook_deliveries_total` (counter by event_type, status)
- `webhook_delivery_duration_seconds` (histogram)
- `webhook_retry_count` (counter by attempt_number)
- `webhook_queue_depth` (gauge)"""
    },
    {
        "tag": "wh-009a-task",
        "title": "Add PHPUnit tests for inbound gateway & security",
        "labels": ["task", "webhook", "testing", "phase-9"],
        "description": """Create unit and integration tests for the new inbound webhook components. This includes testing the `WebhookSecurityService` with mock secrets and headers, and ensuring the `WebhookGateway` correctly validates and processes incoming requests.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md`

**Deliverables:**
- Test suite for WebhookSecurityService
- Test suite for WebhookGateway
- Integration tests for inbound endpoint

**Test Cases:**
- Valid signature verification
- Invalid signature rejection
- Clock skew enforcement
- IP whitelist validation
- Malformed JSON handling
- Duplicate event detection"""
    },
    {
        "tag": "wh-009b-task",
        "title": "Add PHPUnit tests for dispatcher, retries, and logging",
        "labels": ["task", "webhook", "testing", "phase-9"],
        "description": """Write tests covering the outbound webhook flow. This should include testing the `WebhookDispatcher`'s fan-out logic, ensuring the retry scheduler correctly calculates backoff periods, and verifying that all delivery attempts are accurately recorded by the `WebhookLogRepository`.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md`

**Deliverables:**
- Test suite for WebhookDispatcher
- Test suite for retry logic
- Test suite for WebhookLogRepository

**Test Cases:**
- Fan-out to multiple subscribers
- Exponential backoff calculation
- Maximum retry limit
- DLQ processing
- Log persistence
- Delivery success/failure handling"""
    },
]

def generate_issue_files():
    """Generate individual markdown files for each issue"""
    output_dir = "../docs/webhook-issues"
    os.makedirs(output_dir, exist_ok=True)
    
    for issue in issues:
        filename = f"{output_dir}/{issue['tag']}.md"
        with open(filename, 'w') as f:
            f.write(issue['description'])
        print(f"Created {filename}")

def generate_gh_cli_script():
    """Generate a shell script to create all issues using GitHub CLI"""
    script_path = "create_webhook_issues.sh"
    
    with open(script_path, 'w') as f:
        f.write("#!/bin/bash\n")
        f.write("# Script to create GitHub issues for webhook infrastructure\n")
        f.write("# Prerequisites: GitHub CLI (gh) must be installed and authenticated\n")
        f.write("# Usage: ./create_webhook_issues.sh\n\n")
        f.write('REPO="suporterfid/gpt-chatbot-boilerplate"\n\n')
        
        for issue in issues:
            labels = ",".join(issue['labels'])
            f.write(f"# Creating issue: {issue['title']}\n")
            f.write(f"gh issue create \\\n")
            f.write(f"  --repo \"$REPO\" \\\n")
            f.write(f"  --title \"{issue['title']}\" \\\n")
            f.write(f"  --label \"{labels}\" \\\n")
            f.write(f"  --body-file \"docs/webhook-issues/{issue['tag']}.md\"\n\n")
    
    os.chmod(script_path, 0o755)
    print(f"Created {script_path}")

def generate_json_export():
    """Generate JSON export for programmatic use"""
    output_path = "../docs/webhook-issues.json"
    
    with open(output_path, 'w') as f:
        json.dump(issues, f, indent=2)
    
    print(f"Created {output_path}")

if __name__ == "__main__":
    print("Generating webhook issue files...")
    generate_issue_files()
    print("\nGenerating GitHub CLI script...")
    generate_gh_cli_script()
    print("\nGenerating JSON export...")
    generate_json_export()
    print("\nDone! You can now:")
    print("1. Run ./scripts/create_webhook_issues.sh to create all issues via GitHub CLI")
    print("2. Use docs/webhook-issues/*.md files to manually create issues")
    print("3. Use docs/webhook-issues.json for programmatic access")

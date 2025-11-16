Integrate normalized events from the gateway into `includes/JobQueue.php` or direct agent handlers, enabling synchronous/async processing aligned with SPEC §4.

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
```
Create `includes/WebhookDispatcher.php` to load subscribers, apply transforms, sign headers, and queue outbound jobs.

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
```
Expand `config.php` to include a new section for webhook settings. This will centralize configuration for inbound security (e.g., secrets, whitelists) and outbound behavior (e.g., retry policies), making it easily manageable via environment variables.

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
```
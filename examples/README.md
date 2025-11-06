# Observability Examples

This directory contains examples and demonstrations of the comprehensive observability framework.

## Files

- `observability_integration.php` - Examples of integrating structured logging and distributed tracing

## Running Examples

```bash
# Run observability integration example
php examples/observability_integration.php
```

## Integration Patterns

### 1. Structured Logging

```php
require_once 'includes/ObservabilityLogger.php';

$logger = new ObservabilityLogger($config);

// Log at different levels
$logger->info('component', 'event_name', ['key' => 'value']);
$logger->error('component', 'error_occurred', ['error' => $message]);
```

### 2. Distributed Tracing

```php
require_once 'includes/TracingService.php';

$tracing = new TracingService($logger, $config);

// Start and end spans
$spanId = $tracing->startSpan('operation_name', ['attribute' => 'value']);
// ... do work ...
$tracing->endSpan($spanId, 'ok');
```

### 3. Alerting

```php
require_once 'includes/AlertManager.php';

$alertManager = new AlertManager($config, $logger);

// Send alerts
$alertManager->sendAlert(
    'Alert Title',
    'Alert Message',
    AlertManager::SEVERITY_WARNING,
    ['context' => 'data']
);
```

## See Also

- [Observability Guide](../docs/OBSERVABILITY.md) - Complete documentation
- [Monitoring Configuration](../docs/ops/monitoring/) - Dashboards and alerts
- [Incident Runbook](../docs/ops/incident_runbook.md) - Operational procedures

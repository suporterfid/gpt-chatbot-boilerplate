# Load & Capacity Testing

This directory contains load testing scripts for the GPT Chatbot application using k6.

## Prerequisites

Install k6:

```bash
# macOS
brew install k6

# Ubuntu/Debian
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker pull grafana/k6
```

## Running Tests

### Basic Load Test

Test with 10 virtual users for 30 seconds:

```bash
k6 run --vus 10 --duration 30s tests/load/chat_api.js
```

### Custom Configuration

```bash
# Test against specific URL
k6 run --vus 10 --duration 1m --env BASE_URL=https://chatbot.example.com tests/load/chat_api.js

# With admin token for authenticated endpoints
k6 run --vus 5 --duration 30s \
  --env BASE_URL=https://chatbot.example.com \
  --env ADMIN_TOKEN=your_admin_token \
  tests/load/chat_api.js
```

### Staged Ramp Test

The default test includes stages:
- 30s ramp to 10 users
- 1m sustain at 10 users  
- 30s ramp to 20 users
- 1m sustain at 20 users
- 30s ramp down to 0

### Stress Test

Push the system to its limits:

```bash
k6 run --vus 50 --duration 2m tests/load/chat_api.js
```

### Spike Test

Sudden traffic spike:

```bash
k6 run --vus 100 --duration 30s tests/load/chat_api.js
```

## Test Scenarios

### chat_api.js

Tests the following endpoints with realistic traffic distribution:

- **70%** - Chat completions (non-streaming)
- **20%** - Agent testing (requires ADMIN_TOKEN)
- **10%** - Admin API endpoints (requires ADMIN_TOKEN)

## Interpreting Results

### Key Metrics

```
http_req_duration...: avg=850ms  min=120ms  med=750ms  max=2.5s   p(90)=1.2s  p(95)=1.8s
http_req_failed.....: 2.5% (50 of 2000)
iterations..........: 1950
errors..............: 5.0% (100 of 2000)
chat_completion_time: avg=800ms  min=150ms  med=700ms  max=2s
```

**Good indicators:**
- http_req_duration p(95) < 2000ms
- http_req_failed < 5%
- errors < 10%

**Warning signs:**
- p(95) > 2000ms - Server struggling
- http_req_failed > 5% - Connection issues
- errors > 10% - Application errors

### Performance Targets

Based on Phase 4 testing, the system should handle:

**Chat API:**
- 30 req/s sustained
- 50 req/s burst
- p(95) latency < 2s

**Admin API:**
- 10 req/s sustained
- 20 req/s burst
- p(95) latency < 1s

**Background Jobs:**
- 100+ jobs/minute processing
- Queue depth < 100 under normal load

## Generating Reports

### HTML Report

```bash
k6 run --out json=test_results.json tests/load/chat_api.js
k6 report test_results.json --out html > report.html
```

### CSV Export

```bash
k6 run --out csv=test_results.csv tests/load/chat_api.js
```

### Send to InfluxDB + Grafana

```bash
k6 run --out influxdb=http://localhost:8086/k6 tests/load/chat_api.js
```

## Recommendations

### Before Load Testing

1. **Warm up the system:**
   ```bash
   k6 run --vus 2 --duration 30s tests/load/chat_api.js
   ```

2. **Check baseline metrics:**
   ```bash
   curl http://localhost/metrics.php
   curl http://localhost/admin-api.php/health
   ```

3. **Clear old jobs:**
   ```bash
   # Via database
   DELETE FROM jobs WHERE status = 'completed' AND updated_at < datetime('now', '-1 day');
   ```

### During Load Testing

1. **Monitor in real-time:**
   ```bash
   # Metrics
   watch -n 2 'curl -s http://localhost/metrics.php | grep chatbot_jobs'
   
   # Health
   watch -n 5 'curl -s http://localhost/admin-api.php/health | jq .'
   
   # System resources
   htop
   ```

2. **Watch logs:**
   ```bash
   tail -f /var/log/chatbot/application.log | grep error
   ```

### After Load Testing

1. **Analyze results:**
   - Review k6 summary output
   - Check error rates and types
   - Identify bottlenecks

2. **Generate capacity report:**
   - Document max throughput achieved
   - Note breaking points
   - List recommendations

3. **Tune and retest:**
   - Apply performance improvements
   - Rerun tests to validate
   - Document improvements

## Example Capacity Report

```markdown
# Load Test Results - GPT Chatbot v1.0

**Test Date:** 2025-11-04
**Environment:** Production-like staging
**Configuration:** 4 vCPU, 8GB RAM, PostgreSQL

## Results

### Peak Capacity
- **Max sustained throughput:** 45 req/s
- **Peak throughput:** 65 req/s (30s burst)
- **Concurrent users:** 20 sustained, 50 peak

### Latency
- **p(50):** 650ms
- **p(95):** 1.8s
- **p(99):** 2.5s

### Resource Utilization
- **CPU:** 65% avg, 85% peak
- **Memory:** 4.2GB avg, 5.5GB peak
- **Database connections:** 45 avg, 80 peak

### Error Rate
- **Total:** 2.3%
- **5xx errors:** 1.5%
- **Timeouts:** 0.8%

## Recommendations

1. **Scale workers:** Add 2 more worker processes for 50+ req/s
2. **Database pooling:** Increase max connections to 150
3. **PHP-FPM:** Increase pm.max_children to 75
4. **Caching:** Add Redis for session/rate limiting

## Next Steps
- [ ] Implement Redis caching
- [ ] Add horizontal scaling (2+ app servers)
- [ ] Retest at 100 req/s target
```

## Troubleshooting

### Connection Refused

```bash
# Check if server is running
curl http://localhost/

# Check firewall
sudo ufw status

# Check nginx
sudo systemctl status nginx
```

### High Error Rate

```bash
# Check application logs
tail -100 /var/log/chatbot/application.log

# Check PHP-FPM logs
sudo tail -100 /var/log/php8.2-fpm.log

# Check database
psql $ADMIN_DATABASE_URL -c "SELECT count(*) FROM pg_stat_activity;"
```

### k6 Errors

```bash
# Increase timeout
k6 run --http-debug tests/load/chat_api.js

# Reduce virtual users
k6 run --vus 5 tests/load/chat_api.js
```

## See Also

- [Production Deployment Guide](../../docs/ops/production-deploy.md)
- [Monitoring Alerts](../../docs/ops/monitoring/alerts.yml)
- [Incident Runbook](../../docs/ops/incident_runbook.md)

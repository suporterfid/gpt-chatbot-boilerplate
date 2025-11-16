Update `.env.example` with all new environment variables related to the webhooks system. Add documentation to the README or other relevant guides explaining how to configure and use the new features.

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
```
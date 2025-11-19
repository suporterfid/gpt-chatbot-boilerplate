# Production Security Checklist

Use this checklist before deploying to production. **All items marked as CRITICAL must be completed.**

## Pre-Deployment Checklist

### 🔴 CRITICAL - Must Complete

- [ ] **Environment Configuration**
  - [ ] Set `APP_ENV=production` in `.env`
  - [ ] Set `DEBUG=false`
  - [ ] Verify `.env` is listed in `.gitignore`
  - [ ] Never commit `.env` or `.env.production` to version control

- [ ] **Secrets Management**
  - [ ] Generate strong `AUDIT_ENC_KEY` (32 bytes): `openssl rand -base64 32`
  - [ ] Generate `WEBHOOK_GATEWAY_SECRET`: `openssl rand -hex 32`
  - [ ] Use unique, strong passwords for all database credentials (min 20 chars)
  - [ ] Store all secrets in a secrets manager (AWS Secrets Manager, HashiCorp Vault, etc.)
  - [ ] Remove `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` after first login

- [ ] **CORS Configuration**
  - [ ] Replace `CORS_ORIGINS=*` with specific domains
  - [ ] Example: `CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com`
  - [ ] Never use `*` in production

- [ ] **Database Security**
  - [ ] Use managed database service (AWS RDS, Google Cloud SQL, Azure Database)
  - [ ] Do NOT use SQLite in production
  - [ ] Enable SSL/TLS for database connections
  - [ ] Use separate database user with minimal privileges
  - [ ] Enable automated backups
  - [ ] Set up point-in-time recovery

- [ ] **SSL/TLS Configuration**
  - [ ] Obtain valid SSL certificate (Let's Encrypt, commercial CA)
  - [ ] Configure reverse proxy (Nginx, Apache, Traefik) with SSL
  - [ ] Enforce HTTPS-only (redirect HTTP to HTTPS)
  - [ ] Enable HSTS (HTTP Strict Transport Security)
  - [ ] Set secure cookie flags when using HTTPS

- [ ] **API Keys**
  - [ ] Protect OpenAI API key (never expose in client-side code)
  - [ ] Rotate API keys regularly (every 90 days minimum)
  - [ ] Monitor API usage for anomalies
  - [ ] Set up billing alerts

### 🟡 HIGH PRIORITY - Strongly Recommended

- [ ] **Rate Limiting**
  - [ ] Configure appropriate rate limits for your traffic
  - [ ] Set `CHAT_RATE_LIMIT` (default: 30 requests per minute)
  - [ ] Set `ADMIN_RATE_LIMIT_REQUESTS` (default: 100 per minute)
  - [ ] Enable rate limiting at reverse proxy level

- [ ] **Logging & Monitoring**
  - [ ] Set `LOG_LEVEL=warning` or `error` in production
  - [ ] Use JSON log format: `LOG_FORMAT=json`
  - [ ] Set up log aggregation (ELK Stack, CloudWatch, Datadog)
  - [ ] Configure alerting for errors and security events
  - [ ] Enable audit logging: `AUDIT_ENABLED=true`

- [ ] **Backup & Recovery**
  - [ ] Set up automated database backups
  - [ ] Store backups in separate region/location
  - [ ] Test backup restoration regularly
  - [ ] Document recovery procedures
  - [ ] Set retention policy: `AUDIT_RETENTION_DAYS=90`

- [ ] **File Upload Security** (if enabled)
  - [ ] Set reasonable `MAX_FILE_SIZE` limit (default: 10MB)
  - [ ] Restrict `ALLOWED_FILE_TYPES` to necessary types only
  - [ ] Store uploads outside web root or use object storage (S3, GCS)
  - [ ] Implement virus scanning for uploaded files
  - [ ] Set up upload quota per user/tenant

- [ ] **Network Security**
  - [ ] Use internal networking for database (no public exposure)
  - [ ] Configure firewall rules (allow only necessary ports)
  - [ ] Use VPC/private network for service communication
  - [ ] Enable DDoS protection (CloudFlare, AWS Shield)

### 🟢 RECOMMENDED - Best Practices

- [ ] **Container Security**
  - [ ] Use official, minimal base images
  - [ ] Scan images for vulnerabilities (Trivy, Snyk)
  - [ ] Run containers as non-root user
  - [ ] Set resource limits (CPU, memory)
  - [ ] Enable security options: `no-new-privileges:true`

- [ ] **Access Control**
  - [ ] Create separate admin accounts (no shared credentials)
  - [ ] Use RBAC for multi-user access
  - [ ] Enable MFA for admin accounts
  - [ ] Implement IP whitelisting for admin panel
  - [ ] Regular access reviews and privilege audits

- [ ] **Performance**
  - [ ] Enable caching: `CACHE_ENABLED=true`
  - [ ] Enable compression: `COMPRESSION_ENABLED=true`
  - [ ] Use CDN for static assets
  - [ ] Implement connection pooling for database

- [ ] **Compliance** (if applicable)
  - [ ] Enable PII redaction: `LEADSENSE_PII_REDACTION=true`
  - [ ] Configure data retention policies
  - [ ] Enable audit encryption: `AUDIT_ENCRYPT=true`
  - [ ] Document data processing activities
  - [ ] Implement consent management

- [ ] **Observability**
  - [ ] Enable distributed tracing: `TRACING_ENABLED=true`
  - [ ] Set up Prometheus metrics: `METRICS_ENABLED=true`
  - [ ] Configure Grafana dashboards
  - [ ] Set up uptime monitoring (Pingdom, UptimeRobot)

## Post-Deployment Verification

- [ ] **Functional Tests**
  - [ ] Verify chatbot responds correctly
  - [ ] Test file upload functionality (if enabled)
  - [ ] Verify admin panel access
  - [ ] Test agent configuration changes
  - [ ] Verify webhook delivery (if configured)

- [ ] **Security Tests**
  - [ ] Verify HTTPS is enforced
  - [ ] Test CORS restrictions
  - [ ] Verify rate limiting works
  - [ ] Test authentication/authorization
  - [ ] Run security scanner (OWASP ZAP, Burp Suite)

- [ ] **Performance Tests**
  - [ ] Run load tests (see `tests/load/`)
  - [ ] Monitor response times under load
  - [ ] Verify database query performance
  - [ ] Check resource utilization (CPU, memory)

- [ ] **Monitoring Verification**
  - [ ] Verify logs are being collected
  - [ ] Test alerting (trigger test alert)
  - [ ] Verify metrics are being recorded
  - [ ] Check backup completion

## Regular Maintenance (Ongoing)

### Weekly
- [ ] Review error logs
- [ ] Monitor resource usage
- [ ] Check for failed backups

### Monthly
- [ ] Review security alerts
- [ ] Update dependencies (security patches)
- [ ] Review access logs for anomalies
- [ ] Test backup restoration

### Quarterly
- [ ] Rotate credentials (API keys, passwords)
- [ ] Review and update rate limits
- [ ] Audit user access and permissions
- [ ] Update SSL certificates (if needed)
- [ ] Review and update security policies

## Emergency Procedures

### Security Incident Response
1. Isolate affected systems
2. Rotate all credentials immediately
3. Review audit logs
4. Notify affected users (if data breach)
5. Document incident details
6. Implement corrective measures
7. Post-mortem analysis

### Data Breach
1. Activate incident response plan
2. Preserve evidence
3. Notify legal/compliance team
4. Follow regulatory requirements (GDPR, CCPA, etc.)
5. Communicate with affected parties

## Compliance Notes

### GDPR (EU)
- Enable audit logging
- Implement data retention policies
- Provide data export capabilities
- Enable consent management
- Appoint DPO (if required)

### CCPA (California)
- Implement "Do Not Sell" option
- Provide data access rights
- Enable data deletion
- Maintain privacy policy

### HIPAA (Healthcare - if applicable)
- Enable encryption at rest and in transit
- Implement access controls
- Enable comprehensive audit logging
- Sign BAA with service providers
- Implement breach notification procedures

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CIS Docker Benchmark](https://www.cisecurity.org/benchmark/docker)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)
- [Deployment Guide](docs/deployment.md)
- [Security Model](docs/SECURITY_MODEL.md)
- [Operations Guide](docs/OPERATIONS_GUIDE.md)

---

**Last Updated:** [Current Date]
**Reviewed By:** [Name]
**Next Review:** [Date + 90 days]

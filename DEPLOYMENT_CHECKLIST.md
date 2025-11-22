# 🚀 Production Deployment Checklist

## Post-Fix Deployment Guide

This checklist covers the deployment steps after applying all code review fixes.

---

## ✅ Pre-Deployment Verification

### 1. Environment Configuration

- [ ] **APP_BASE_URL** set to production domain
  ```bash
  # In .env file
  APP_BASE_URL=https://yourdomain.com
  ```

- [ ] **ALLOWED_HOSTS** configured with all valid domains
  ```bash
  # Example
  ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,api.yourdomain.com
  ```

- [ ] **Encryption Keys** generated and set
  ```bash
  # Generate new keys for production
  openssl rand -hex 32  # For BLOG_ENCRYPTION_KEY
  openssl rand -hex 32  # For ENCRYPTION_KEY
  ```

- [ ] **All sensitive credentials** stored in secure secrets manager
  - Do NOT commit encryption keys to git
  - Use environment-specific secrets (AWS Secrets Manager, Azure Key Vault, etc.)

### 2. Run Verification Tests

```bash
# Run the verification script
php tests/verify_fixes.php
```

Expected output:
```
✅ ALL TESTS PASSED - All fixes verified successfully!
```

If tests fail, review the error messages and fix issues before proceeding.

---

## 🔒 Security Hardening

### 3. Review Security Settings

- [ ] **CORS_ORIGINS** restricted to specific domains
  ```bash
  # Change from * to specific domains
  CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
  ```

- [ ] **DEBUG** mode disabled
  ```bash
  DEBUG=false
  ```

- [ ] **APP_ENV** set to production
  ```bash
  APP_ENV=production
  ```

- [ ] **Admin credentials** changed from defaults
  - Remove DEFAULT_ADMIN_EMAIL and DEFAULT_ADMIN_PASSWORD after initial setup
  - Create permanent admin users via Admin UI
  - Use strong passwords (min 16 characters)

### 4. Database Security

- [ ] Database credentials use strong passwords (min 20 chars)
- [ ] Database credentials stored in secrets manager
- [ ] Database backups configured and tested
- [ ] Database encryption at rest enabled (if available)

---

## 🔧 WordPress Blog Module Setup

### 5. Database Migrations

If WordPress Blog tables don't exist, run the migration:

```bash
# Check if migration file exists
ls db/migrations/048_add_wordpress_blog_tables.sql

# If migration exists, run it
php db/run_migration.php
```

Expected tables:
- `blog_configurations`
- `blog_articles_queue`
- `blog_article_categories`
- `blog_article_tags`
- `blog_internal_links`
- `blog_execution_logs`

### 6. Verify WordPress Blog Module

- [ ] All WordPress Blog service classes load without errors
- [ ] Encryption keys are configured
- [ ] Database tables created successfully
- [ ] Test basic CRUD operations via Admin UI

---

## 📊 Testing & Monitoring

### 7. Integration Testing

Run comprehensive tests:

```bash
# Run all test suites
php tests/run_tests.php

# Specific WordPress Blog tests
php tests/WordPressBlog/ConfigurationServiceTest.php
php tests/WordPressBlog/QueueServiceTest.php
```

### 8. API Endpoint Testing

Test critical fixed endpoints:

```bash
# Test permission checks (should require authentication)
curl -X GET http://localhost/admin-api.php?action=list_prompts
# Expected: 401 Unauthorized or authentication required

# Test with authentication
curl -X GET http://localhost/admin-api.php?action=list_prompts \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
# Expected: 200 OK with prompts list

# Test host header injection fix
curl -X GET http://localhost/admin-api.php?action=get_whitelabel_url&id=AGENT_ID \
  -H "Host: malicious.com" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
# Expected: Should use APP_BASE_URL, not malicious.com
```

### 9. Monitor Application Logs

```bash
# Watch application logs for errors
tail -f logs/chatbot.log

# Watch WordPress Blog logs
tail -f logs/wordpress_blog/*.log

# Check for PHP errors
tail -f logs/php_errors.log
```

Expected: No fatal errors, no warnings related to:
- Undefined methods (requeueArticle, getQueueStatistics)
- Missing classes
- Database fetch() errors
- Host header issues

---

## 🎯 Post-Deployment Validation

### 10. Production Smoke Tests

- [ ] **Health Check Endpoint**
  ```bash
  curl http://yourdomain.com/admin-api.php?action=health
  ```
  Expected: Status 200, all checks passing

- [ ] **Admin Login**
  - Can log in to admin panel
  - Session persists correctly
  - CSRF protection working

- [ ] **Agent Management**
  - Can create/edit agents
  - Whitelabel URLs generate correctly (using APP_BASE_URL)
  - No host header injection possible

- [ ] **WordPress Blog Module**
  - Can create configurations
  - Can add articles to queue
  - Queue service operational
  - Encryption/decryption working

### 11. Performance Verification

- [ ] Response times within acceptable range (< 200ms for API calls)
- [ ] No memory leaks (monitor for 24 hours)
- [ ] Database query performance acceptable
- [ ] Background jobs processing correctly

---

## 📝 Documentation Updates

### 12. Update Documentation

- [ ] Update README.md with new environment variables
- [ ] Document security configuration changes
- [ ] Update API documentation if endpoints changed
- [ ] Create runbook for WordPress Blog operations

---

## 🔄 Rollback Plan

### 13. Prepare Rollback Procedure

In case issues are discovered post-deployment:

```bash
# 1. Backup current database
mysqldump chatbot > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Keep previous version code available
git tag v1.0-pre-fixes

# 3. Rollback command
git checkout v1.0-pre-fixes

# 4. Restore database if needed
mysql chatbot < backup_TIMESTAMP.sql
```

---

## 📈 Monitoring & Alerts

### 14. Set Up Monitoring

- [ ] Application error rate alerts (< 0.1%)
- [ ] API response time alerts (> 500ms)
- [ ] Database connection failures
- [ ] Background job failures
- [ ] Security events (failed auth attempts, etc.)

### 15. Key Metrics to Track

- **WordPress Blog Module:**
  - Articles queued per hour
  - Articles processed successfully
  - Articles failed (with error rates)
  - Average processing time
  - Queue depth

- **API Endpoints:**
  - Request rate per endpoint
  - Error rate per endpoint
  - Permission check failures
  - Authentication failures

- **Security:**
  - Host header validation failures
  - Invalid host attempts
  - Permission denied events
  - Encryption/decryption failures

---

## ✅ Sign-Off Checklist

Before marking deployment as complete:

- [ ] All tests passing
- [ ] No critical errors in logs (24-hour observation period)
- [ ] Performance metrics within acceptable range
- [ ] Security hardening verified
- [ ] Monitoring and alerts configured
- [ ] Documentation updated
- [ ] Rollback plan tested
- [ ] Team notified of deployment
- [ ] Stakeholders informed

---

## 🆘 Troubleshooting

### Common Issues

**Issue:** Host header injection error
```
Error: Invalid host header. Please configure APP_BASE_URL in .env
```
**Solution:** Set APP_BASE_URL in .env to your production domain

**Issue:** Encryption key missing
```
Error: Encryption key is required for WordPressBlogConfigurationService
```
**Solution:** Set BLOG_ENCRYPTION_KEY and ENCRYPTION_KEY in .env

**Issue:** Permission denied errors
```
Error: Permission denied for action
```
**Solution:** Verify user has required permissions (read, create, update, delete)

**Issue:** WordPress Blog tables not found
```
Error: Table 'blog_configurations' doesn't exist
```
**Solution:** Run database migration: `php db/run_migration.php`

---

## 📞 Support

For issues or questions:
1. Check application logs: `logs/chatbot.log`
2. Check WordPress Blog logs: `logs/wordpress_blog/*.log`
3. Run verification script: `php tests/verify_fixes.php`
4. Review error details in this checklist
5. Contact development team if issues persist

---

**Deployment Date:** _______________
**Deployed By:** _______________
**Version:** _______________
**Sign-Off:** _______________

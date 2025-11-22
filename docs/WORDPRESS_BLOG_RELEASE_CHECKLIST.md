# WordPress Blog Automation - Release Checklist

## Overview

This comprehensive checklist ensures all critical steps are completed before deploying the WordPress Blog Automation system to production.

**Release Version**: 1.0
**Release Date**: _______________
**Release Manager**: _______________
**Environment**: Production

---

## Table of Contents

1. [Pre-Release Verification](#1-pre-release-verification)
2. [Database Preparation](#2-database-preparation)
3. [Configuration](#3-configuration)
4. [Security Hardening](#4-security-hardening)
5. [Deployment](#5-deployment)
6. [Post-Release Verification](#6-post-release-verification)
7. [Monitoring Setup](#7-monitoring-setup)
8. [Rollback Plan](#8-rollback-plan)

---

## 1. Pre-Release Verification

### 1.1 Code Quality

- [ ] All unit tests pass (100%)
  ```bash
  ./vendor/bin/phpunit tests/WordPressBlog/
  ```

- [ ] All integration tests pass
  ```bash
  ./vendor/bin/phpunit tests/Integration/
  ```

- [ ] Code coverage ≥80%
  ```bash
  ./vendor/bin/phpunit --coverage-text tests/
  ```

- [ ] No critical bugs in issue tracker

- [ ] Code review completed and approved

- [ ] No TODO or FIXME comments in production code
  ```bash
  grep -r "TODO\|FIXME" includes/ public/ --exclude-dir=vendor
  ```

**Sign-off**: _______________ Date: _______________

---

### 1.2 Documentation

- [ ] Setup guide complete and tested
  - File: `docs/WORDPRESS_BLOG_SETUP.md`

- [ ] Operations runbook complete
  - File: `docs/WORDPRESS_BLOG_OPERATIONS.md`

- [ ] API documentation complete
  - File: `docs/WORDPRESS_BLOG_API.md`

- [ ] Implementation documentation complete
  - File: `docs/WORDPRESS_BLOG_IMPLEMENTATION.md`

- [ ] Release notes prepared
  - File: `RELEASE_NOTES_v1.0.md`

- [ ] README updated with current information

**Sign-off**: _______________ Date: _______________

---

### 1.3 Security Audit

- [ ] Security audit completed
  - File: `docs/issues/wordpress-agent-20251120/SECURITY_AUDIT_REPORT.md`

- [ ] All critical security issues resolved

- [ ] All high-priority security issues resolved or documented

- [ ] Credentials never exposed in logs confirmed

- [ ] Input validation tested

- [ ] Authentication and authorization verified

- [ ] OWASP Top 10 compliance checked

**Sign-off**: _______________ Date: _______________

---

### 1.4 Performance Testing

- [ ] Performance benchmarks met
  - File: `docs/issues/wordpress-agent-20251120/PERFORMANCE_TESTING_REPORT.md`

- [ ] Database operations < target times

- [ ] API endpoints < target response times

- [ ] Full workflow completes in < 5 minutes

- [ ] Load testing completed (10+ concurrent articles)

- [ ] No memory leaks detected

- [ ] Database optimizations applied (indexes, WAL mode)

**Sign-off**: _______________ Date: _______________

---

### 1.5 Manual Testing

- [ ] Manual E2E testing completed
  - File: `docs/issues/wordpress-agent-20251120/MANUAL_E2E_TESTING_CHECKLIST.md`

- [ ] All test cases passed

- [ ] UI tested in all target browsers (Chrome, Firefox, Safari, Edge)

- [ ] Responsive design verified (desktop, tablet, mobile)

- [ ] No console errors in browser

- [ ] Error handling works as expected

**Sign-off**: _______________ Date: _______________

---

## 2. Database Preparation

### 2.1 Database Setup

- [ ] Production database created
  ```sql
  CREATE DATABASE wordpress_blog_automation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

- [ ] Database user created with minimal privileges
  ```sql
  CREATE USER 'wp_blog_prod'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
  GRANT SELECT, INSERT, UPDATE, DELETE ON wordpress_blog_automation.* TO 'wp_blog_prod'@'localhost';
  FLUSH PRIVILEGES;
  ```

- [ ] Database connection tested
  ```bash
  mysql -u wp_blog_prod -p -e "USE wordpress_blog_automation; SELECT 1;"
  ```

**Sign-off**: _______________ Date: _______________

---

### 2.2 Schema Migration

- [ ] Backup current database (if upgrading)
  ```bash
  mysqldump -u root -p wordpress_blog_automation > backup_pre_migration_$(date +%Y%m%d).sql
  ```

- [ ] Run migration script
  ```bash
  php db/run_migration.php db/migrations/048_add_wordpress_blog_tables.sql
  ```

- [ ] Validate schema
  ```bash
  php db/validate_blog_schema.php
  ```

- [ ] Verify all tables created
  ```sql
  SHOW TABLES FROM wordpress_blog_automation;
  ```

  Expected tables:
  - wp_blog_configurations
  - wp_blog_internal_links
  - wp_blog_article_queue
  - wp_blog_execution_log

- [ ] Verify indexes created
  ```sql
  SHOW INDEX FROM wp_blog_article_queue;
  ```

**Sign-off**: _______________ Date: _______________

---

### 2.3 Database Optimization

- [ ] Indexes created for performance
  ```sql
  CREATE INDEX idx_article_status ON wp_blog_article_queue(status);
  CREATE INDEX idx_article_config ON wp_blog_article_queue(config_id);
  CREATE INDEX idx_article_created ON wp_blog_article_queue(created_at DESC);
  CREATE INDEX idx_exec_log_article ON wp_blog_execution_log(article_id);
  CREATE INDEX idx_exec_log_stage_status ON wp_blog_execution_log(stage, status);
  ```

- [ ] Analyze tables
  ```sql
  ANALYZE TABLE wp_blog_configurations, wp_blog_internal_links, wp_blog_article_queue, wp_blog_execution_log;
  ```

- [ ] Optimize tables
  ```sql
  OPTIMIZE TABLE wp_blog_configurations, wp_blog_internal_links, wp_blog_article_queue, wp_blog_execution_log;
  ```

**Sign-off**: _______________ Date: _______________

---

## 3. Configuration

### 3.1 Environment Variables

- [ ] `.env` file created from template
  ```bash
  cp .env.example .env
  ```

- [ ] Production settings configured in `.env`:
  ```bash
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://your-production-domain.com

  DB_TYPE=mysql
  DB_HOST=localhost
  DB_NAME=wordpress_blog_automation
  DB_USER=wp_blog_prod
  DB_PASSWORD=*** (secure password)

  ENCRYPTION_KEY=*** (32+ character random key)

  LOG_LEVEL=warning
  LOG_PATH=/var/log/wordpress-blog/application.log
  ```

- [ ] Encryption key generated (32+ characters)
  ```bash
  php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
  ```

- [ ] `.env` file permissions set to 600
  ```bash
  chmod 600 .env
  ```

- [ ] `.env` not in version control (verify .gitignore)

**Sign-off**: _______________ Date: _______________

---

### 3.2 Web Server Configuration

**Apache Configuration**:

- [ ] Virtual host configured
  ```apache
  <VirtualHost *:443>
      ServerName your-domain.com
      DocumentRoot /var/www/wordpress-blog/public

      SSLEngine on
      SSLCertificateFile /path/to/cert.pem
      SSLCertificateKeyFile /path/to/key.pem

      <Directory /var/www/wordpress-blog/public>
          Options -Indexes +FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>

      ErrorLog ${APACHE_LOG_DIR}/wp-blog-error.log
      CustomLog ${APACHE_LOG_DIR}/wp-blog-access.log combined
  </VirtualHost>
  ```

- [ ] HTTP to HTTPS redirect configured
  ```apache
  <VirtualHost *:80>
      ServerName your-domain.com
      Redirect permanent / https://your-domain.com/
  </VirtualHost>
  ```

- [ ] Apache modules enabled
  ```bash
  sudo a2enmod rewrite ssl headers expires
  sudo systemctl restart apache2
  ```

**OR Nginx Configuration**:

- [ ] Server block configured
  ```nginx
  server {
      listen 443 ssl http2;
      server_name your-domain.com;
      root /var/www/wordpress-blog/public;

      ssl_certificate /path/to/cert.pem;
      ssl_certificate_key /path/to/key.pem;

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
          include fastcgi_params;
      }

      access_log /var/log/nginx/wp-blog-access.log;
      error_log /var/log/nginx/wp-blog-error.log;
  }
  ```

- [ ] HTTP to HTTPS redirect
  ```nginx
  server {
      listen 80;
      server_name your-domain.com;
      return 301 https://$server_name$request_uri;
  }
  ```

**Sign-off**: _______________ Date: _______________

---

### 3.3 PHP Configuration

- [ ] PHP version verified (7.4+)
  ```bash
  php --version
  ```

- [ ] Required extensions enabled
  ```bash
  php -m | grep -E 'pdo|pdo_mysql|curl|json|mbstring|openssl'
  ```

- [ ] Production php.ini settings:
  ```ini
  display_errors = Off
  log_errors = On
  error_log = /var/log/php_errors.log
  memory_limit = 512M
  max_execution_time = 300
  upload_max_filesize = 10M
  post_max_size = 10M
  ```

- [ ] OPcache enabled and configured:
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=4000
  opcache.revalidate_freq=60
  ```

- [ ] PHP-FPM configured (if using Nginx)
  ```ini
  pm = dynamic
  pm.max_children = 50
  pm.start_servers = 10
  pm.min_spare_servers = 5
  pm.max_spare_servers = 20
  ```

**Sign-off**: _______________ Date: _______________

---

### 3.4 SSL/TLS Certificate

- [ ] SSL certificate installed
  - Certificate type: _______________ (Let's Encrypt, Commercial, etc.)

- [ ] Certificate validity verified
  ```bash
  openssl x509 -in /path/to/cert.pem -noout -dates
  ```

- [ ] Certificate chain complete

- [ ] HTTPS working (visit https://your-domain.com)

- [ ] SSL Labs test passed (A rating)
  - https://www.ssllabs.com/ssltest/

- [ ] HSTS header configured
  ```apache
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  ```

**Sign-off**: _______________ Date: _______________

---

## 4. Security Hardening

### 4.1 File Permissions

- [ ] Ownership set correctly
  ```bash
  sudo chown -R www-data:www-data /var/www/wordpress-blog
  ```

- [ ] Directory permissions: 755
  ```bash
  find /var/www/wordpress-blog -type d -exec chmod 755 {} \;
  ```

- [ ] File permissions: 644
  ```bash
  find /var/www/wordpress-blog -type f -exec chmod 644 {} \;
  ```

- [ ] .env file: 600
  ```bash
  chmod 600 /var/www/wordpress-blog/.env
  ```

- [ ] Scripts executable: 755
  ```bash
  chmod 755 /var/www/wordpress-blog/scripts/*.php
  ```

- [ ] Database file: 644 (if SQLite)
  ```bash
  chmod 644 /var/www/wordpress-blog/db/database.db
  ```

**Sign-off**: _______________ Date: _______________

---

### 4.2 Security Headers

- [ ] Security headers configured:
  ```apache
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';"
  ```

- [ ] Headers verified in browser DevTools

**Sign-off**: _______________ Date: _______________

---

### 4.3 Firewall Configuration

- [ ] UFW/iptables configured
  ```bash
  sudo ufw allow 22/tcp    # SSH
  sudo ufw allow 80/tcp    # HTTP
  sudo ufw allow 443/tcp   # HTTPS
  sudo ufw enable
  ```

- [ ] Unnecessary ports closed

- [ ] Database port (3306) not exposed externally

**Sign-off**: _______________ Date: _______________

---

### 4.4 Access Control

- [ ] Admin panel access restricted (if applicable)
  ```apache
  <Directory /var/www/wordpress-blog/public/admin>
      Require ip 203.0.113.0/24   # Office IP range
  </Directory>
  ```

- [ ] API rate limiting configured

- [ ] Strong admin password set

- [ ] SSH key-based authentication (no password login)

**Sign-off**: _______________ Date: _______________

---

## 5. Deployment

### 5.1 Code Deployment

- [ ] Code deployed to production server
  ```bash
  git clone https://github.com/your-org/wordpress-blog.git /var/www/wordpress-blog
  cd /var/www/wordpress-blog
  git checkout v1.0  # Or main/master
  ```

- [ ] Dependencies installed
  ```bash
  composer install --no-dev --optimize-autoloader
  ```

- [ ] File permissions verified (see section 4.1)

- [ ] Symlink created (if applicable)
  ```bash
  ln -s /var/www/wordpress-blog /var/www/html/blog-automation
  ```

**Sign-off**: _______________ Date: _______________

---

### 5.2 Configuration Deployment

- [ ] `.env` file deployed with production values

- [ ] Web server configuration deployed

- [ ] Web server reloaded/restarted
  ```bash
  sudo systemctl reload apache2
  # OR
  sudo systemctl reload nginx
  sudo systemctl restart php7.4-fpm
  ```

- [ ] Log directories created
  ```bash
  sudo mkdir -p /var/log/wordpress-blog
  sudo chown www-data:www-data /var/log/wordpress-blog
  ```

**Sign-off**: _______________ Date: _______________

---

### 5.3 Cron Jobs

- [ ] Cron jobs configured for automated processing
  ```bash
  crontab -e -u www-data
  ```

  ```cron
  # Process queue every 30 minutes
  */30 * * * * cd /var/www/wordpress-blog && php scripts/wordpress_blog_processor.php --mode=all >> /var/log/wordpress-blog/processor.log 2>&1

  # Daily health check at 6 AM
  0 6 * * * curl -s https://your-domain.com/admin-api.php?action=wordpress_blog_system_health -H "Authorization: Bearer TOKEN" | grep -q "healthy" || echo "Health check failed" | mail -s "WP Blog Alert" admin@example.com

  # Weekly backup at 2 AM Sunday
  0 2 * * 0 /var/www/wordpress-blog/scripts/backup.sh
  ```

- [ ] Cron execution tested
  ```bash
  # Run manually first
  sudo -u www-data php /var/www/wordpress-blog/scripts/wordpress_blog_processor.php --mode=single
  ```

**Sign-off**: _______________ Date: _______________

---

## 6. Post-Release Verification

### 6.1 Smoke Tests

- [ ] Homepage/admin panel loads
  - URL: https://your-domain.com/admin

- [ ] System health check passes
  ```bash
  curl https://your-domain.com/admin-api.php?action=wordpress_blog_system_health \
    -H "Authorization: Bearer PROD_TOKEN"
  ```

- [ ] Can create configuration via UI

- [ ] Can queue article via UI

- [ ] Can view queue via UI

- [ ] Can view metrics via UI

**Sign-off**: _______________ Date: _______________

---

### 6.2 Integration Tests

- [ ] Queue test article
  ```bash
  curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_queue_article \
    -H "Authorization: Bearer PROD_TOKEN" \
    -d '{"config_id": 1, "topic": "Production Test Article"}'
  ```

- [ ] Process test article
  ```bash
  php /var/www/wordpress-blog/scripts/wordpress_blog_processor.php --article-id=TEST_ID
  ```

- [ ] Verify article published to WordPress
  - Check WordPress admin panel

- [ ] Verify execution log created

- [ ] Verify metrics updated

**Sign-off**: _______________ Date: _______________

---

### 6.3 API Endpoint Tests

- [ ] All API endpoints responding
  ```bash
  # Test each endpoint
  curl https://your-domain.com/admin-api.php?action=wordpress_blog_get_configurations
  curl https://your-domain.com/admin-api.php?action=wordpress_blog_get_queue
  curl https://your-domain.com/admin-api.php?action=wordpress_blog_get_metrics
  # ... etc
  ```

- [ ] Authentication working correctly

- [ ] Rate limiting functional

- [ ] Error responses formatted correctly

**Sign-off**: _______________ Date: _______________

---

## 7. Monitoring Setup

### 7.1 Log Monitoring

- [ ] Log rotation configured
  ```bash
  # /etc/logrotate.d/wordpress-blog
  /var/log/wordpress-blog/*.log {
      daily
      missingok
      rotate 14
      compress
      delaycompress
      notifempty
      create 0640 www-data www-data
  }
  ```

- [ ] Error log monitoring active
  ```bash
  # Monitor for errors
  tail -f /var/log/wordpress-blog/application.log | grep -i error
  ```

- [ ] Application log accessible

**Sign-off**: _______________ Date: _______________

---

### 7.2 Performance Monitoring

- [ ] Server resource monitoring
  - CPU usage
  - Memory usage
  - Disk space
  - Database connections

- [ ] Application metrics dashboard accessible

- [ ] Slow query log enabled (MySQL)
  ```sql
  SET GLOBAL slow_query_log = 'ON';
  SET GLOBAL long_query_time = 2;
  ```

**Sign-off**: _______________ Date: _______________

---

### 7.3 Alerting

- [ ] Critical alerts configured:
  - System health check failures
  - High error rate (>10%)
  - Disk space low (<10%)
  - Database connection failures
  - Stuck articles (>2 hours in processing)

- [ ] Alert recipients configured:
  - Email: _______________
  - Slack/Discord (if applicable): _______________

- [ ] Test alert sent and received

**Sign-off**: _______________ Date: _______________

---

## 8. Rollback Plan

### 8.1 Rollback Preparation

- [ ] Pre-deployment backup created
  ```bash
  # Database backup
  mysqldump -u root -p wordpress_blog_automation > backup_pre_release_$(date +%Y%m%d).sql

  # Code backup
  tar -czf /backups/code_pre_release_$(date +%Y%m%d).tar.gz /var/www/wordpress-blog
  ```

- [ ] Rollback script tested
  ```bash
  # File: scripts/rollback.sh
  #!/bin/bash
  echo "Rolling back to previous version..."

  # Restore database
  mysql -u root -p wordpress_blog_automation < backup_pre_release.sql

  # Restore code
  git checkout previous-stable-tag

  # Restart services
  sudo systemctl restart apache2  # or nginx
  ```

**Sign-off**: _______________ Date: _______________

---

### 8.2 Rollback Procedure

**If critical issues found post-deployment:**

1. **Immediate Actions**:
   - [ ] Stop cron jobs
     ```bash
     crontab -e -u www-data
     # Comment out all WordPress blog cron jobs
     ```

   - [ ] Stop current processing
     ```bash
     killall php  # Or more targeted: pkill -f wordpress_blog_processor
     ```

2. **Database Rollback**:
   - [ ] Restore database backup
     ```bash
     mysql -u root -p wordpress_blog_automation < backup_pre_release_YYYYMMDD.sql
     ```

3. **Code Rollback**:
   - [ ] Revert to previous version
     ```bash
     cd /var/www/wordpress-blog
     git checkout previous-stable-version
     composer install --no-dev --optimize-autoloader
     ```

4. **Service Restart**:
   - [ ] Restart web server
     ```bash
     sudo systemctl restart apache2  # or nginx + php-fpm
     ```

5. **Verification**:
   - [ ] System health check passes
   - [ ] Admin panel accessible
   - [ ] API endpoints responding

6. **Communication**:
   - [ ] Notify stakeholders of rollback
   - [ ] Document rollback reason
   - [ ] Schedule post-mortem

**Sign-off**: _______________ Date: _______________

---

## 9. Final Sign-Off

### Release Approval

**All checklist items completed**: ☐ Yes  ☐ No

**Outstanding issues**: _______________________________________

**Approved for production deployment**:

- Technical Lead: _______________ Date: _______________
- Release Manager: _______________ Date: _______________
- Security Officer: _______________ Date: _______________

### Post-Deployment

**Deployment completed**: _______________ (Date/Time)
**Deployed by**: _______________
**Deployment duration**: _______________ minutes
**Issues encountered**: _______________________________________________

**Production status after 24 hours**:
- [ ] No critical errors
- [ ] Performance acceptable
- [ ] No user complaints
- [ ] Monitoring shows healthy status

**Post-deployment review scheduled**: _______________ (Date)

---

## Appendix

### Emergency Contacts

**Technical Lead**: _______________ (Phone: _______________)
**DevOps Engineer**: _______________ (Phone: _______________)
**Database Administrator**: _______________ (Phone: _______________)
**Security Officer**: _______________ (Phone: _______________)

### Important URLs

- Production Admin: https://your-domain.com/admin
- API Base URL: https://your-domain.com/admin-api.php
- WordPress Admin: https://your-wordpress-site.com/wp-admin
- Monitoring Dashboard: _______________
- Issue Tracker: _______________

### Backup Locations

- Database Backups: /backups/wordpress-blog/database/
- Code Backups: /backups/wordpress-blog/code/
- Configuration Backups: /backups/wordpress-blog/config/

---

**Checklist Version**: 1.0
**Last Updated**: November 21, 2025
**Next Review**: After first production deployment

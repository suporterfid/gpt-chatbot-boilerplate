# Production Deployment Guide

## Overview

This guide covers deploying the GPT Chatbot application to a production environment with best practices for security, performance, and reliability.

## Prerequisites

### System Requirements

**Minimum:**
- 2 CPU cores
- 4 GB RAM
- 20 GB disk space
- Ubuntu 20.04+ / Debian 11+ or equivalent

**Recommended:**
- 4 CPU cores
- 8 GB RAM
- 50 GB SSD storage
- Ubuntu 22.04 LTS

### Software Requirements

- PHP 8.0 or higher
- Nginx or Apache
- PostgreSQL 13+ (recommended) or SQLite 3.35+
- Composer
- Node.js 16+ (for frontend assets)
- SSL certificate (Let's Encrypt recommended)

## Environment Variables

Create a `.env` file in the application root with the following variables:

```bash
# OpenAI Configuration
OPENAI_API_KEY=sk-proj-...
OPENAI_ORG_ID=org-...

# Admin Configuration
ADMIN_ENABLED=true
ADMIN_TOKEN=generate_secure_random_token_min_32_chars
ADMIN_DB_TYPE=postgres
ADMIN_DATABASE_URL=postgresql://chatbot_user:secure_password@localhost:5432/chatbot_production

# Rate Limiting
ADMIN_RATE_LIMIT_REQUESTS=300
ADMIN_RATE_LIMIT_WINDOW=60

# Background Jobs
JOBS_ENABLED=true

# Logging
LOG_LEVEL=info
LOG_FILE=/var/log/chatbot/application.log

# Security
SESSION_SECURE=true
COOKIE_SECURE=true
COOKIE_HTTPONLY=true
COOKIE_SAMESITE=Strict

# Application
APP_ENV=production
APP_DEBUG=false
```

**Security Notes:**
- Generate ADMIN_TOKEN using: `openssl rand -base64 32`
- Store sensitive values in a secrets manager (see Secrets Management section)
- Never commit `.env` to version control

## Deployment Steps

### 1. Prepare the Server

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y \
    nginx \
    php8.2-fpm \
    php8.2-cli \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-pgsql \
    php8.2-sqlite3 \
    postgresql \
    postgresql-contrib \
    git \
    unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Set Up Database

#### PostgreSQL (Recommended)

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE chatbot_production;
CREATE USER chatbot_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE chatbot_production TO chatbot_user;

# Grant schema permissions
\c chatbot_production
GRANT ALL ON SCHEMA public TO chatbot_user;
\q
```

#### SQLite (Development/Small Scale)

```bash
# Create data directory
mkdir -p /var/www/chatbot/data
chown www-data:www-data /var/www/chatbot/data
chmod 755 /var/www/chatbot/data
```

### 3. Deploy Application Code

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/yourusername/gpt-chatbot-boilerplate.git chatbot
cd chatbot

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data /var/www/chatbot
sudo chmod -R 755 /var/www/chatbot
sudo chmod -R 775 /var/www/chatbot/data
sudo chmod -R 775 /var/www/chatbot/logs
```

### 4. Configure Environment

```bash
# Copy example env file
cp .env.example .env

# Edit with your values
sudo nano .env

# Secure the env file
sudo chown root:www-data .env
sudo chmod 640 .env
```

### 5. Run Database Migrations

```bash
# Run migrations
php -r "require 'includes/DB.php'; \$db = new DB(require 'config.php'); \$db->runMigrations();"

# Verify tables were created
# PostgreSQL:
psql $ADMIN_DATABASE_URL -c "\dt"

# SQLite:
sqlite3 data/admin.db ".tables"
```

### 6. Configure Web Server

#### Nginx

```bash
# Copy nginx config
sudo cp docs/ops/nginx-production.conf /etc/nginx/sites-available/chatbot

# Update server_name and paths
sudo nano /etc/nginx/sites-available/chatbot

# Enable site
sudo ln -s /etc/nginx/sites-available/chatbot /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

#### Apache

```bash
# Enable required modules
sudo a2enmod rewrite headers ssl

# Copy apache config
sudo cp docs/ops/apache-production.conf /etc/apache2/sites-available/chatbot.conf

# Edit configuration
sudo nano /etc/apache2/sites-available/chatbot.conf

# Enable site
sudo a2ensite chatbot

# Test configuration
sudo apache2ctl configtest

# Reload apache
sudo systemctl reload apache2
```

### 7. Set Up SSL Certificate

#### Using Let's Encrypt

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d chatbot.example.com -d admin.chatbot.example.com

# Test auto-renewal
sudo certbot renew --dry-run
```

### 8. Configure Background Worker

Create systemd service for the worker:

```bash
# Create service file
sudo nano /etc/systemd/system/chatbot-worker.service
```

Add the following content:

```ini
[Unit]
Description=GPT Chatbot Background Worker
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/chatbot
ExecStart=/usr/bin/php /var/www/chatbot/scripts/worker.php --mode=daemon
Restart=always
RestartSec=10
StandardOutput=append:/var/log/chatbot/worker.log
StandardError=append:/var/log/chatbot/worker.log

# Resource limits
LimitNOFILE=4096
MemoryLimit=512M

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
# Create log directory
sudo mkdir -p /var/log/chatbot
sudo chown www-data:www-data /var/log/chatbot

# Reload systemd
sudo systemctl daemon-reload

# Enable and start worker
sudo systemctl enable chatbot-worker
sudo systemctl start chatbot-worker

# Check status
sudo systemctl status chatbot-worker
```

### 9. Set Up Automated Backups

```bash
# Create backup directory
sudo mkdir -p /var/backups/chatbot
sudo chown www-data:www-data /var/backups/chatbot

# Create cron job
sudo crontab -e -u www-data
```

Add the following line:

```cron
# Daily backup at 2 AM
0 2 * * * cd /var/www/chatbot && BACKUP_DIR=/var/backups/chatbot ./scripts/db_backup.sh >> /var/log/chatbot/backup.log 2>&1
```

### 10. Configure Monitoring

#### Set up Prometheus scraping

Add to `/etc/prometheus/prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'chatbot'
    static_configs:
      - targets: ['localhost:80']
    metrics_path: '/metrics.php'
    scheme: https
    tls_config:
      insecure_skip_verify: false
```

#### Set up Log Aggregation

See [Logging Guide](logs.md) for ELK/CloudWatch/LogDNA setup.

### 11. Verify Deployment

```bash
# Check web server
curl -I https://chatbot.example.com

# Check health endpoint
curl https://chatbot.example.com/admin-api.php/health

# Check metrics
curl http://localhost/metrics.php

# Check worker
sudo systemctl status chatbot-worker

# Check logs
tail -f /var/log/chatbot/application.log
```

## Post-Deployment

### Create Initial Admin User

```bash
cd /var/www/chatbot
php -r "
require 'config.php';
require 'includes/DB.php';
require 'includes/AdminAuth.php';

\$db = new DB(\$config);
\$auth = new AdminAuth(\$db, \$config);

\$user = \$auth->createUser(
    'admin@example.com',
    'SecureP@ssw0rd!',
    'super-admin'
);

echo 'Admin user created: ' . \$user['email'] . PHP_EOL;
echo 'Generate API key: ' . \$auth->generateApiKey(\$user['id']) . PHP_EOL;
"
```

### Create Default Agent

Access the Admin UI at `https://admin.chatbot.example.com` and create your first agent, or use the API:

```bash
curl -X POST https://chatbot.example.com/admin-api.php/agents \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Default Assistant",
    "api_type": "responses",
    "model": "gpt-4o",
    "temperature": 0.7,
    "is_default": true
  }'
```

## Rollback Procedure

If deployment fails, rollback to the previous version:

```bash
# Stop worker
sudo systemctl stop chatbot-worker

# Restore previous code
cd /var/www
sudo mv chatbot chatbot-failed
sudo mv chatbot-backup chatbot

# Restore database
cd /var/www/chatbot
./scripts/db_restore.sh /var/backups/chatbot/admin_postgres_YYYYMMDD_HHMMSS.sql.gz

# Restart services
sudo systemctl start chatbot-worker
sudo systemctl reload nginx
```

## Performance Tuning

### PHP-FPM

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### PostgreSQL

Edit `/etc/postgresql/*/main/postgresql.conf`:

```ini
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 4MB
min_wal_size = 1GB
max_wal_size = 4GB
```

### Nginx

Edit `/etc/nginx/nginx.conf`:

```nginx
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
}

http {
    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    
    # Buffering
    client_body_buffer_size 128k;
    client_max_body_size 10m;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 4k;
    output_buffers 1 32k;
    postpone_output 1460;
}
```

## Scaling

### Horizontal Scaling

For high traffic:

1. **Load Balancer**: Add nginx/HAProxy in front
2. **Multiple App Servers**: Deploy to multiple servers
3. **Shared Database**: Use PostgreSQL with read replicas
4. **Shared Storage**: Use NFS/S3 for file uploads
5. **Cache Layer**: Add Redis for session/rate limiting

### Worker Scaling

Run multiple workers:

```bash
# Copy service file for multiple workers
sudo cp /etc/systemd/system/chatbot-worker.service /etc/systemd/system/chatbot-worker@.service

# Start multiple instances
sudo systemctl start chatbot-worker@1
sudo systemctl start chatbot-worker@2
sudo systemctl start chatbot-worker@3
```

## Security Checklist

- [ ] HTTPS enforced with valid SSL certificate
- [ ] Security headers configured (HSTS, CSP, X-Frame-Options)
- [ ] Admin UI behind separate subdomain or IP whitelist
- [ ] Database credentials in secrets manager
- [ ] File upload validation enabled
- [ ] Rate limiting configured
- [ ] Automated backups working
- [ ] Log rotation configured
- [ ] Worker running as non-root user
- [ ] Firewall configured (only 80/443 open)
- [ ] Intrusion detection system (fail2ban) configured
- [ ] Regular security updates scheduled

## Troubleshooting

See [Incident Runbook](incident_runbook.md) for common issues and solutions.

## See Also

- [Backup & Restore Guide](backup_restore.md)
- [Logging Guide](logs.md)
- [Monitoring Alerts](monitoring/alerts.yml)
- [Security Configuration](nginx-production.conf)

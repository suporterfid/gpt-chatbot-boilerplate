# WordPress Blog Automation - Setup Guide

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Testing](#testing)
5. [Production Deployment](#production-deployment)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

**Server Requirements:**
- PHP 7.4 or higher
- SQLite 3.x or MySQL 5.7+
- 2GB RAM minimum (4GB recommended)
- 10GB disk space minimum
- cURL support enabled
- OpenSSL extension enabled

**Required PHP Extensions:**
```bash
# Verify required extensions
php -m | grep -E 'pdo|pdo_sqlite|curl|json|mbstring|openssl|fileinfo'
```

Expected output:
```
curl
fileinfo
json
mbstring
openssl
pdo
pdo_sqlite
```

**Install Missing Extensions (Ubuntu/Debian):**
```bash
sudo apt-get update
sudo apt-get install php-cli php-curl php-mbstring php-sqlite3 php-xml
```

**Install Missing Extensions (CentOS/RHEL):**
```bash
sudo yum install php-cli php-curl php-mbstring php-pdo php-xml
```

### External API Accounts

**1. OpenAI Account**
- Sign up at: https://platform.openai.com/signup
- Add payment method
- Set usage limits (recommended: $50/month hard limit)
- Create API key at: https://platform.openai.com/api-keys

**Cost Estimate:**
- GPT-4: ~$0.15 per 2000-word article
- GPT-3.5-turbo: ~$0.02 per 2000-word article

**2. Replicate Account (Optional - for image generation)**
- Sign up at: https://replicate.com
- Add payment method
- Create API token at: https://replicate.com/account/api-tokens

**Cost Estimate:**
- SDXL image generation: ~$0.005 per image

**3. WordPress Site**
- WordPress 5.0 or higher
- REST API enabled (default)
- Application Passwords plugin (built-in since WP 5.6)
- Admin or Editor account

**4. Google Drive Account (Optional - for asset storage)**
- Google account with Drive API enabled
- OAuth 2.0 credentials configured

### Development Tools

**Required:**
- Git
- Composer (PHP dependency manager)
- Text editor (VS Code, Sublime, etc.)

**Optional but Recommended:**
- PHPUnit (for testing)
- SQLite Browser (for database inspection)
- Postman (for API testing)

---

## Installation

### Step 1: Clone the Repository

```bash
# Clone the project
git clone https://github.com/your-org/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
```

### Step 2: Install Dependencies

```bash
# Install PHP dependencies via Composer
composer install

# If composer is not installed:
# wget https://getcomposer.org/installer
# php installer
# php composer.phar install
```

### Step 3: Database Setup

**Option A: SQLite (Recommended for Development)**

```bash
# Create database directory
mkdir -p db

# Copy database file if not exists
cp db/database.db.example db/database.db

# Set permissions
chmod 644 db/database.db
chmod 755 db/
```

**Option B: MySQL (Recommended for Production)**

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE wordpress_blog_automation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -p -e "CREATE USER 'wp_blog_user'@'localhost' IDENTIFIED BY 'strong_password';"

# Grant privileges
mysql -u root -p -e "GRANT ALL PRIVILEGES ON wordpress_blog_automation.* TO 'wp_blog_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### Step 4: Run Database Migrations

```bash
# Navigate to database migrations
cd db/

# Run migration script
php run_migration.php 048_add_wordpress_blog_tables.sql

# Validate schema
php validate_blog_schema.php
```

Expected output:
```
Running migration: 048_add_wordpress_blog_tables.sql
✓ Table wp_blog_configurations created
✓ Table wp_blog_internal_links created
✓ Table wp_blog_article_queue created
✓ Table wp_blog_execution_log created
Migration completed successfully
```

### Step 5: File Permissions

```bash
# Set correct ownership (adjust user as needed)
sudo chown -R www-data:www-data /path/to/gpt-chatbot-boilerplate

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x scripts/*.php
chmod +x db/*.php
```

---

## Configuration

### Step 1: Environment Variables

Create `.env` file from template:

```bash
cp .env.example .env
```

Edit `.env`:

```bash
# Database Configuration
DB_TYPE=sqlite
DB_PATH=db/database.db

# For MySQL:
# DB_TYPE=mysql
# DB_HOST=localhost
# DB_NAME=wordpress_blog_automation
# DB_USER=wp_blog_user
# DB_PASSWORD=strong_password

# Encryption Key (generate random 32-character string)
ENCRYPTION_KEY=your-random-32-character-encryption-key-here

# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=https://your-domain.com

# Admin Panel
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=hashed_password_here

# API Rate Limiting
API_RATE_LIMIT=100
API_RATE_LIMIT_WINDOW=60

# Logging
LOG_LEVEL=info
LOG_PATH=logs/application.log
```

**Generate Encryption Key:**

```bash
# Generate random 32-character key
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

**Hash Admin Password:**

```bash
# Generate password hash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT) . PHP_EOL;"
```

### Step 2: WordPress Application Password Setup

**On WordPress Site:**

1. Log in to WordPress admin panel
2. Navigate to: **Users → Profile**
3. Scroll to: **Application Passwords** section
4. Application Name: `Blog Automation`
5. Click: **Add New Application Password**
6. Copy the generated password (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)
7. Save securely (shown only once)

**Test WordPress API Access:**

```bash
# Replace with your credentials
SITE_URL="https://your-wordpress-site.com"
USERNAME="your_username"
APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"

# Test authentication
curl -X POST "${SITE_URL}/wp-json/wp/v2/posts" \
  -H "Authorization: Basic $(echo -n "${USERNAME}:${APP_PASSWORD}" | base64)" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Post",
    "content": "Test content",
    "status": "draft"
  }'
```

Expected response: HTTP 201 with post data (or 401 if credentials invalid)

### Step 3: Create First Configuration

**Via Admin UI:**

1. Start web server (see Step 4)
2. Navigate to: `https://your-domain.com/admin`
3. Log in with admin credentials
4. Go to: **Blog Configurations**
5. Click: **Create New Configuration**
6. Fill in the form:

```
Configuration Name: My First Blog
WordPress Site URL: https://your-wordpress-site.com
WordPress Username: your_username
WordPress API Key: xxxx xxxx xxxx xxxx xxxx xxxx
OpenAI API Key: sk-xxxxxxxxxxxxxxxx
OpenAI Model: gpt-4 (or gpt-3.5-turbo for lower cost)
Replicate API Key: r8_xxxxxxxxxxxxxxxx (optional)
Target Word Count: 2000
Max Internal Links: 5
Google Drive Folder ID: (optional)
```

7. Click: **Save Configuration**

**Via API:**

```bash
# Set variables
TOKEN="your_api_token"
API_URL="https://your-domain.com/admin-api.php"

# Create configuration
curl -X POST "${API_URL}?action=wordpress_blog_create_configuration" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "config_name": "My First Blog",
    "wordpress_site_url": "https://your-wordpress-site.com",
    "wordpress_username": "your_username",
    "wordpress_api_key": "xxxx xxxx xxxx xxxx xxxx xxxx",
    "openai_api_key": "sk-xxxxxxxxxxxxxxxx",
    "openai_model": "gpt-4",
    "replicate_api_key": "r8_xxxxxxxxxxxxxxxx",
    "target_word_count": 2000,
    "max_internal_links": 5
  }'
```

### Step 4: Web Server Configuration

**Option A: PHP Built-in Server (Development Only)**

```bash
# Start server
php -S localhost:8000 -t public/

# Access admin panel
# Open browser: http://localhost:8000/admin
```

**Option B: Apache Configuration (Production)**

Create Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/gpt-chatbot-boilerplate/public

    <Directory /var/www/gpt-chatbot-boilerplate/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Error and access logs
    ErrorLog ${APACHE_LOG_DIR}/wp-blog-error.log
    CustomLog ${APACHE_LOG_DIR}/wp-blog-access.log combined

    # Enable .htaccess
    <Directory /var/www/gpt-chatbot-boilerplate>
        AllowOverride All
    </Directory>
</VirtualHost>
```

Enable site and restart Apache:

```bash
sudo a2ensite your-domain.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Option C: Nginx Configuration (Production)**

Create Nginx server block:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/gpt-chatbot-boilerplate/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/wp-blog-access.log;
    error_log /var/log/nginx/wp-blog-error.log;
}
```

Enable and restart:

```bash
sudo ln -s /etc/nginx/sites-available/your-domain /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## Testing

### Step 1: Verify Installation

**Run Health Check:**

```bash
curl http://localhost:8000/admin-api.php?action=wordpress_blog_system_health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Expected response:
```json
{
  "success": true,
  "health": {
    "status": "healthy",
    "checks": {
      "database": {"status": "pass"},
      "disk_space": {"status": "pass"},
      "api_keys": {"status": "pass"}
    }
  }
}
```

### Step 2: Run Unit Tests

```bash
# Install PHPUnit if not installed
composer require --dev phpunit/phpunit ^9.0

# Run all tests
./vendor/bin/phpunit tests/

# Run specific test suites
./vendor/bin/phpunit tests/WordPressBlog/ConfigurationServiceTest.php
./vendor/bin/phpunit tests/WordPressBlog/ErrorHandlingTest.php
./vendor/bin/phpunit tests/Integration/WordPressBlogE2ETest.php
```

Expected output:
```
PHPUnit 9.x.x by Sebastian Bergmann

...................................................  63 / 100 ( 63%)
......................................               100 / 100 (100%)

Time: 00:05.234, Memory: 24.00 MB

OK (100 tests, 350 assertions)
```

### Step 3: Queue Test Article

**Via CLI:**

```bash
# Queue a test article
CONFIG_ID=1  # Use your configuration ID
TOPIC="Test Article: Introduction to Machine Learning"

# Queue via API
curl -X POST "http://localhost:8000/admin-api.php?action=wordpress_blog_queue_article" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"config_id\": ${CONFIG_ID},
    \"topic\": \"${TOPIC}\"
  }"
```

Expected response:
```json
{
  "success": true,
  "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "message": "Article queued successfully",
  "status": "pending"
}
```

### Step 4: Process Test Article

**Manual Processing:**

```bash
# Process the queued article
ARTICLE_ID="a1b2c3d4-e5f6-7890-abcd-ef1234567890"

php scripts/wordpress_blog_processor.php --article-id=${ARTICLE_ID}
```

Expected output:
```
[2025-11-21 10:30:00] Starting article processing: a1b2c3d4...
[2025-11-21 10:30:01] Configuration loaded: My First Blog
[2025-11-21 10:30:02] Building content structure...
[2025-11-21 10:30:15] Structure complete: 5 chapters
[2025-11-21 10:31:30] Generating chapter content...
[2025-11-21 10:35:45] Content generation complete: 2150 words
[2025-11-21 10:36:00] Generating featured image...
[2025-11-21 10:36:30] Image generated: https://replicate.delivery/...
[2025-11-21 10:36:45] Publishing to WordPress...
[2025-11-21 10:37:00] Published successfully! Post ID: 12345
[2025-11-21 10:37:00] Processing complete
```

### Step 5: Verify WordPress Publication

**Check WordPress:**

1. Log into your WordPress admin panel
2. Navigate to: **Posts → All Posts**
3. Verify test post exists with:
   - Correct title
   - Generated content
   - Featured image
   - Draft status (or published, based on configuration)

**Via API:**

```bash
# Get WordPress post
POST_ID=12345
SITE_URL="https://your-wordpress-site.com"
USERNAME="your_username"
APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"

curl -X GET "${SITE_URL}/wp-json/wp/v2/posts/${POST_ID}" \
  -H "Authorization: Basic $(echo -n "${USERNAME}:${APP_PASSWORD}" | base64)"
```

### Step 6: Review Execution Log

```bash
# Get execution log for article
ARTICLE_ID="a1b2c3d4-e5f6-7890-abcd-ef1234567890"

curl -X GET "http://localhost:8000/admin-api.php?action=wordpress_blog_get_execution_log&article_id=${ARTICLE_ID}" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Expected response shows all stages:
```json
{
  "success": true,
  "execution_log": [
    {"stage": "queue", "status": "completed", "execution_time_ms": 5},
    {"stage": "structure", "status": "completed", "execution_time_ms": 1250},
    {"stage": "content", "status": "completed", "execution_time_ms": 8500},
    {"stage": "image", "status": "completed", "execution_time_ms": 3000},
    {"stage": "publish", "status": "completed", "execution_time_ms": 1500}
  ],
  "total_execution_time_ms": 14255
}
```

### Step 7: Test Error Handling

**Test Invalid Configuration:**

```bash
# Try to create config with invalid data
curl -X POST "http://localhost:8000/admin-api.php?action=wordpress_blog_create_configuration" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_name": "",
    "wordpress_site_url": "invalid-url",
    "openai_api_key": "wrong-format"
  }'
```

Expected response:
```json
{
  "success": false,
  "error": "Validation failed",
  "details": {
    "errors": [
      "Configuration name is required",
      "Invalid WordPress site URL format",
      "OpenAI API key must start with 'sk-'"
    ]
  }
}
```

---

## Production Deployment

### Security Checklist

**1. Environment Configuration**

```bash
# Set production environment
# In .env:
APP_ENV=production
APP_DEBUG=false
```

**2. Disable Directory Listing**

Apache `.htaccess`:
```apache
Options -Indexes
```

Nginx:
```nginx
autoindex off;
```

**3. Secure API Keys**

```bash
# Verify encryption is enabled
# Check config.php or .env
grep ENCRYPTION_KEY .env

# Verify keys are encrypted in database
sqlite3 db/database.db "SELECT
  config_name,
  substr(openai_api_key, 1, 10) as encrypted_key
FROM wp_blog_configurations LIMIT 1;"

# Should show encrypted/hashed value, not plaintext
```

**4. HTTPS Configuration**

Install SSL certificate:

```bash
# Using Let's Encrypt (Certbot)
sudo apt-get install certbot python3-certbot-apache

# For Apache
sudo certbot --apache -d your-domain.com

# For Nginx
sudo certbot --nginx -d your-domain.com
```

Update Apache/Nginx config to redirect HTTP → HTTPS:

Apache:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>
```

Nginx:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

**5. Firewall Configuration**

```bash
# Allow only necessary ports
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp  # SSH
sudo ufw enable
```

**6. File Permissions Hardening**

```bash
# Restrict sensitive files
chmod 600 .env
chmod 600 config.php
chmod 644 db/database.db
chmod 755 db/

# Prevent execution of uploaded files
# Add to Apache .htaccess or Nginx config
```

### Optimization

**1. Enable OPcache**

Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

Restart PHP-FPM:
```bash
sudo systemctl restart php7.4-fpm
```

**2. Database Optimization**

SQLite:
```bash
# Enable WAL mode
sqlite3 db/database.db "PRAGMA journal_mode=WAL;"

# Optimize database
sqlite3 db/database.db "VACUUM; ANALYZE;"
```

MySQL:
```sql
-- Add indexes
CREATE INDEX idx_article_status ON wp_blog_article_queue(status);
CREATE INDEX idx_article_created ON wp_blog_article_queue(created_at);
CREATE INDEX idx_exec_log_article ON wp_blog_execution_log(article_id);

-- Optimize tables
OPTIMIZE TABLE wp_blog_article_queue;
OPTIMIZE TABLE wp_blog_execution_log;
```

**3. Caching**

Install Redis (optional):
```bash
sudo apt-get install redis-server php-redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

**4. Log Rotation**

Create `/etc/logrotate.d/wordpress-blog`:
```
/var/www/gpt-chatbot-boilerplate/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php7.4-fpm > /dev/null
    endscript
}
```

### Monitoring

**1. Setup Automated Health Checks**

Create cron job:
```bash
crontab -e
```

Add:
```cron
# Health check every 15 minutes
*/15 * * * * curl -s https://your-domain.com/admin-api.php?action=wordpress_blog_system_health -H "Authorization: Bearer YOUR_TOKEN" | grep -q '"status":"healthy"' || echo "Health check failed" | mail -s "WP Blog Alert" admin@example.com
```

**2. Setup Metrics Collection**

Create daily metrics report:
```bash
# /usr/local/bin/wp-blog-daily-report.sh
#!/bin/bash
TOKEN="YOUR_TOKEN"
API_URL="https://your-domain.com/admin-api.php"

METRICS=$(curl -s "${API_URL}?action=wordpress_blog_get_metrics&days=1" \
  -H "Authorization: Bearer ${TOKEN}")

echo "Daily WordPress Blog Report - $(date)" > /tmp/report.txt
echo "$METRICS" | jq '.metrics' >> /tmp/report.txt

mail -s "WP Blog Daily Report" admin@example.com < /tmp/report.txt
```

Add to crontab:
```cron
# Daily report at 6 AM
0 6 * * * /usr/local/bin/wp-blog-daily-report.sh
```

### Backup Strategy

**1. Database Backups**

SQLite:
```bash
# Create backup script
cat > /usr/local/bin/wp-blog-backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/backups/wp-blog"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
sqlite3 db/database.db ".backup '$BACKUP_DIR/database_$TIMESTAMP.db'"

# Compress
gzip "$BACKUP_DIR/database_$TIMESTAMP.db"

# Remove backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $TIMESTAMP"
EOF

chmod +x /usr/local/bin/wp-blog-backup.sh
```

Add to crontab:
```cron
# Daily backup at 2 AM
0 2 * * * /usr/local/bin/wp-blog-backup.sh
```

MySQL:
```bash
#!/bin/bash
mysqldump -u wp_blog_user -p'password' wordpress_blog_automation | \
  gzip > "/backups/wp-blog/database_$(date +%Y%m%d_%H%M%S).sql.gz"
```

**2. Configuration Backups**

```bash
# Backup .env and config files
tar -czf /backups/wp-blog/config_$(date +%Y%m%d).tar.gz \
  .env config.php public/admin/
```

### Scaling Considerations

**For High-Volume Production:**

1. **Use MySQL instead of SQLite**
   - Better concurrent access
   - Improved performance

2. **Implement Queue Workers**
   - Run multiple processor instances
   - Use job queue (Redis, RabbitMQ)

3. **Load Balancing**
   - Multiple app servers
   - Shared database
   - Centralized logging

4. **API Rate Limiting**
   - Implement per-user quotas
   - Use Redis for distributed rate limiting

---

## Troubleshooting

### Installation Issues

**Problem: Composer dependencies fail to install**

```bash
# Clear Composer cache
composer clear-cache

# Try install with verbose output
composer install -vvv

# Update Composer itself
composer self-update
```

**Problem: Database migration fails**

```bash
# Check file permissions
ls -la db/database.db

# Verify SQLite is installed
sqlite3 --version

# Try manual migration
sqlite3 db/database.db < db/migrations/048_add_wordpress_blog_tables.sql
```

**Problem: PHP extensions missing**

```bash
# Check which extensions are loaded
php -m

# Install all required extensions at once (Ubuntu)
sudo apt-get install php7.4-cli php7.4-curl php7.4-mbstring \
  php7.4-sqlite3 php7.4-xml php7.4-zip

# Restart PHP-FPM
sudo systemctl restart php7.4-fpm
```

### Configuration Issues

**Problem: API keys not encrypting**

```bash
# Verify encryption key is set
grep ENCRYPTION_KEY .env

# Test encryption
php -r "
require 'includes/CryptoAdapter.php';
\$crypto = new CryptoAdapter(['encryption_key' => 'your-key-here']);
echo \$crypto->encrypt('test') . PHP_EOL;
"
```

**Problem: WordPress API authentication fails**

```bash
# Test WordPress API directly
curl -X GET "https://your-site.com/wp-json/wp/v2/users/me" \
  -H "Authorization: Basic $(echo -n 'username:app_password' | base64)"

# Should return user data, not 401 error
```

**Problem: Database connection fails**

SQLite:
```bash
# Check database file exists and is writable
ls -la db/database.db

# Test connection
sqlite3 db/database.db "SELECT 1;"
```

MySQL:
```bash
# Test MySQL connection
mysql -u wp_blog_user -p -e "USE wordpress_blog_automation; SELECT 1;"
```

### Runtime Issues

**Problem: Article processing hangs**

```bash
# Check running processes
ps aux | grep wordpress_blog_processor

# Kill stuck process
kill -9 <PID>

# Reset article to pending
sqlite3 db/database.db "UPDATE wp_blog_article_queue
SET status='pending', processing_started_at=NULL
WHERE id='ARTICLE_ID';"
```

**Problem: Out of memory**

```bash
# Increase PHP memory limit
# Edit php.ini:
memory_limit = 512M

# Or set per-script in processor
php -d memory_limit=512M scripts/wordpress_blog_processor.php
```

**Problem: OpenAI rate limits**

```bash
# Check error logs
grep "rate limit" logs/application.log

# Reduce processing frequency in cron
# Change from every 30 min to every hour
```

### Getting Help

**Check Logs:**
```bash
# Application logs
tail -100 logs/application.log

# Web server logs
tail -100 /var/log/apache2/error.log
tail -100 /var/log/nginx/error.log

# PHP-FPM logs
tail -100 /var/log/php7.4-fpm.log
```

**Run Diagnostics:**
```bash
# System info
php -i | grep -E "Version|extension_dir|Configuration File"

# Check disk space
df -h

# Check memory
free -h

# Check database size
du -h db/database.db
```

**Contact Support:**
- GitHub Issues: https://github.com/your-repo/issues
- Email: support@yourdomain.com
- Documentation: https://docs.yourdomain.com

---

## Next Steps

After successful setup:

1. **Read the Operations Guide**: [WORDPRESS_BLOG_OPERATIONS.md](WORDPRESS_BLOG_OPERATIONS.md)
2. **Review the API Documentation**: [WORDPRESS_BLOG_API.md](WORDPRESS_BLOG_API.md)
3. **Setup Automated Processing**: Configure cron jobs
4. **Configure Monitoring**: Setup health checks and alerts
5. **Plan Content Strategy**: Build internal links repository

---

**Setup Guide Version:** 1.0
**Last Updated:** November 21, 2025
**Next Review:** February 21, 2026

# Deployment Guide - GPT Chatbot Boilerplate

This guide covers various deployment scenarios for the GPT Chatbot Boilerplate, from development to production environments.

## 🚨 Production Deployment - Quick Links

**Before deploying to production, review these critical documents:**

- **[PRODUCTION_SECURITY_CHECKLIST.md](../PRODUCTION_SECURITY_CHECKLIST.md)** - ⚠️ **MANDATORY** - Complete this checklist first
- **[PRODUCTION_RELEASE_NOTES.md](../PRODUCTION_RELEASE_NOTES.md)** - Production deployment guide
- **[.env.production](../.env.production)** - Production environment template
- **[docker-compose.prod.yml](../docker-compose.prod.yml)** - Production Docker configuration

### Production Quick Start (10 minutes)

```bash
# 1. Copy production template
cp .env.production .env

# 2. Generate encryption keys
echo "AUDIT_ENC_KEY=$(openssl rand -base64 32)" >> .env
echo "WEBHOOK_GATEWAY_SECRET=$(openssl rand -hex 32)" >> .env

# 3. Configure required variables in .env:
#    - OPENAI_API_KEY (required)
#    - APP_ENV=production (required)
#    - CORS_ORIGINS=https://yourdomain.com (required)
#    - DATABASE_URL (use managed database service)
#    - Strong database passwords (min 20 chars)

# 4. Deploy with production configuration
docker-compose -f docker-compose.prod.yml up -d

# 5. Complete security checklist
# See: PRODUCTION_SECURITY_CHECKLIST.md
```

**⚠️ WARNING:** The standard `docker-compose.yml` file is for **DEVELOPMENT ONLY**. Always use `docker-compose.prod.yml` for production deployments.

---

## Table of Contents

- [Production Deployment Quick Start](#production-deployment-quick-start)
- [Web-Based Installation](#web-based-installation)
- [Development Setup](#development-setup)
- [Production Deployment (Detailed)](#production-deployment)
- [Docker Deployment](#docker-deployment)
- [MySQL Database Setup](#mysql-database-setup)
- [Cloud Deployment](#cloud-deployment)
- [Server Configuration](#server-configuration)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)
- [Monitoring](#monitoring)
- [Optional Features Configuration](#optional-features-configuration)
- [Troubleshooting](#troubleshooting)

## Web-Based Installation

The easiest way to deploy the chatbot is using our web-based installation wizard.

### Quick Start

1. **Clone and start the application**:
   ```bash
   git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
   cd gpt-chatbot-boilerplate
   
   # Option A: Docker (recommended, includes MySQL)
   docker-compose up -d
   
   # Option B: PHP built-in server
   php -S localhost:8000
   ```

2. **Open installation wizard**:
   ```
   http://localhost:8088/setup/install.php
   # or http://localhost:8000/setup/install.php
   ```

3. **Follow the wizard steps**:
   - ✅ Verify system requirements (PHP, extensions, permissions)
   - ⚙️ Configure OpenAI API and settings
   - 🗄️ Choose and configure database (SQLite or MySQL)
   - 🔐 Set up admin credentials and security
   - 🎯 Enable optional features (file upload, audit trail, jobs)
   - 🚀 Initialize database and complete installation

4. **Access your chatbot**:
   - Admin Panel: `http://localhost:8088/public/admin/`
   - Chatbot Interface: `http://localhost:8088/`

### Installation Features

The wizard automatically:
- Generates `.env` configuration file
- Validates all required parameters
- Tests database connectivity
- Runs database migrations
- Creates installation lock (`.install.lock`)
- Provides secure admin token

### Re-installation

To reinstall or reconfigure:
1. Delete `.install.lock` file, or
2. Use the unlock link in the installation wizard
3. Re-run the installation process

⚠️ **Note**: Re-installation will not delete existing data, only reconfigure settings.

## Development Setup

### Local Development with PHP Built-in Server

1. **Install PHP 8.0+** with required extensions:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php8.2 php8.2-curl php8.2-json php8.2-session
   
   # macOS with Homebrew
   brew install php@8.2
   
   # Windows - Download from php.net
   ```

2. **Clone and configure**:
   ```bash
   git clone https://github.com/your-repo/gpt-chatbot-boilerplate.git
   cd gpt-chatbot-boilerplate
   cp .env.example .env
   # Edit .env with your OpenAI API key
   ```

3. **Start development server**:
   ```bash
   php -S localhost:8080
   ```

4. **Access the application**:
   Open http://localhost:8080 in your browser

### Local Development with Apache/Nginx

#### Apache Setup

1. **Create virtual host** (`/etc/apache2/sites-available/chatbot.conf`):
   ```apache
   <VirtualHost *:80>
       ServerName chatbot.local
       DocumentRoot /path/to/gpt-chatbot-boilerplate
       
       <Directory /path/to/gpt-chatbot-boilerplate>
           AllowOverride All
           Require all granted
       </Directory>
       
       # Enable SSE streaming
       <Location "/chat-unified.php">
           SetEnv no-gzip 1
           SetEnv no-buffer 1
       </Location>
       
       ErrorLog ${APACHE_LOG_DIR}/chatbot_error.log
       CustomLog ${APACHE_LOG_DIR}/chatbot_access.log combined
   </VirtualHost>
   ```

2. **Enable site and modules**:
   ```bash
   sudo a2ensite chatbot
   sudo a2enmod rewrite headers setenvif
   sudo systemctl restart apache2
   ```

3. **Add to hosts file**:
   ```bash
   echo "127.0.0.1 chatbot.local" | sudo tee -a /etc/hosts
   ```

#### Nginx Setup

1. **Create server block** (`/etc/nginx/sites-available/chatbot`):
   ```nginx
   server {
       listen 80;
       server_name chatbot.local;
       root /path/to/gpt-chatbot-boilerplate;
       index default.php index.php;
       
       location / {
           try_files $uri $uri/ /index.php$is_args$args;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
           
           # Enable SSE streaming
           fastcgi_buffering off;
           proxy_buffering off;
       }
       
       location /chat-unified.php {
           add_header X-Accel-Buffering no;
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

2. **Enable site**:
   ```bash
   sudo ln -s /etc/nginx/sites-available/chatbot /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

## Production Deployment

### Requirements

- **Server**: Linux VPS/Dedicated server
- **PHP**: 8.0+ with extensions: curl, json, session, sockets (for WebSocket)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **SSL Certificate**: Let's Encrypt or commercial
- **Memory**: Minimum 512MB RAM
- **Storage**: Minimum 1GB available space

### GitHub Actions Secrets for CI/CD

The GitHub Actions pipeline expects the following secrets before a production deployment can be approved through the `production` environment gate:

- `PRODUCTION_ENV` – multi-line contents for the `.env` file that will be injected into the release artifact.
- `DEPLOY_HOST` – hostname or IP address of the target SFTP server.
- `DEPLOY_USER` – SFTP username with write permissions to the deployment directory.
- `DEPLOY_PASSWORD` or `DEPLOY_KEY` – authentication credentials for the SFTP account (only one is required).
- `DEPLOY_PORT` – optional override for the SFTP port (defaults to `22` when omitted).
- `DEPLOY_PATH` – remote directory where the packaged chatbot should be published.

Add these secrets under **Settings → Secrets and variables → Actions** in GitHub, and require manual approval for the `production` environment so deployments are reviewed before they execute.

### Step-by-Step Production Setup

1. **Server Preparation**:
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Install required packages
   sudo apt install -y php8.2 php8.2-fpm php8.2-curl php8.2-json \
                       nginx certbot python3-certbot-nginx \
                       git composer
   
   # Create application directory
   sudo mkdir -p /var/www/chatbot
   sudo chown $USER:www-data /var/www/chatbot
   ```

2. **Deploy Application**:
   ```bash
   cd /var/www/chatbot
   git clone https://github.com/your-repo/gpt-chatbot-boilerplate.git .
   cp .env.example .env
   # Configure .env with production settings
   
   # Set permissions
   sudo chown -R www-data:www-data .
   sudo chmod -R 755 .
   sudo chmod -R 777 logs/
   
   # Install dependencies (if using WebSocket)
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure Nginx**:
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com www.yourdomain.com;
       root /var/www/chatbot;
       index default.php index.php;
       
       # Security headers
       add_header X-Frame-Options "SAMEORIGIN" always;
       add_header X-XSS-Protection "1; mode=block" always;
       add_header X-Content-Type-Options "nosniff" always;
       add_header Referrer-Policy "no-referrer-when-downgrade" always;
       add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
       
       # Deny access to sensitive files
       location ~ /\. {
           deny all;
       }
       
       location ~ \.(env|log)$ {
           deny all;
       }
       
       # Main location
       location / {
           try_files $uri $uri/ /index.php$is_args$args;
       }
       
       # PHP processing
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
           
           # Security
           fastcgi_param SERVER_NAME $http_host;
           fastcgi_param HTTPS $https if_not_empty;
       }
       
       # SSE endpoint
       location /chat-unified.php {
           add_header X-Accel-Buffering no;
           add_header Cache-Control "no-cache, no-store, must-revalidate";
           add_header Pragma "no-cache";
           add_header Expires "0";
           
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           fastcgi_buffering off;
           include fastcgi_params;
       }
       
       # Static files caching
       location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
           expires 1y;
           add_header Cache-Control "public, immutable";
       }
   }
   ```

4. **SSL Certificate**:
   ```bash
   # Get Let's Encrypt certificate
   sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
   
   # Test auto-renewal
   sudo certbot renew --dry-run
   ```

5. **Configure PHP**:
   Edit `/etc/php/8.2/fpm/php.ini`:
   ```ini
   # For SSE streaming
   output_buffering = Off
   zlib.output_compression = Off
   implicit_flush = On
   max_execution_time = 300
   
   # Security
   expose_php = Off
   allow_url_fopen = Off
   allow_url_include = Off
   
   # Performance
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.interned_strings_buffer=8
   opcache.max_accelerated_files=4000
   opcache.revalidate_freq=60
   opcache.fast_shutdown=1
   ```

6. **Start Services**:
   ```bash
   sudo systemctl enable nginx php8.2-fpm
   sudo systemctl restart nginx php8.2-fpm
   ```

## Docker Deployment

### Development with Docker

The application includes Docker Compose configuration with MySQL database support:

```bash
# Clone repository
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Option 1: Use web-based installer (recommended)
docker-compose up -d
# Then visit http://localhost:8088/setup/install.php

# Option 2: Manual configuration
cp .env.example .env
# Edit .env with your settings
# IMPORTANT: Set ADMIN_TOKEN to a secure random string (min 32 chars)

# Start development environment (includes MySQL)
docker-compose up -d

# View logs
docker-compose logs -f

# Access application
curl http://localhost:8088

# Access admin panel
# Open http://localhost:8088/public/admin/ in your browser
# Enter the ADMIN_TOKEN from your .env file when prompted
```

### Docker Services

The default `docker-compose.yml` includes:

1. **chatbot** - Main PHP/Apache application
   - Port: 8088
   - Includes all required PHP extensions
   - Auto-runs composer install
   - Mounts logs and data volumes

2. **mysql** - MySQL 8.0 database (recommended for production)
   - Port: 3306
   - Persistent volume storage
   - Health checks enabled
   - Configurable via environment variables

### MySQL Configuration with Docker

The application automatically uses MySQL when configured. Environment variables:

```bash
# In .env file
DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot;charset=utf8mb4
DB_HOST=mysql
DB_PORT=3306
DB_NAME=chatbot
DB_USER=chatbot
DB_PASSWORD=your_secure_password

# MySQL root password (for administration)
MYSQL_ROOT_PASSWORD=your_root_password

# Leave DATABASE_PATH empty when using MySQL
DATABASE_PATH=
```

To access MySQL directly:

```bash
# Connect to MySQL container
docker-compose exec mysql mysql -u chatbot -p

# Backup database
docker-compose exec mysql mysqldump -u chatbot -p chatbot > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u chatbot -p chatbot < backup.sql

# View MySQL logs
docker-compose logs mysql
```

### Admin Panel Access in Docker

The admin panel requires proper Authorization header handling. The application includes:

1. **Root `.htaccess` configuration** to pass Authorization headers to PHP
2. **Dockerfile configuration** with required Apache modules (`mod_rewrite`, `mod_headers`, `mod_setenvif`)
3. **Multi-source header detection** in `admin-api.php` to handle different Apache configurations

If you encounter "403 Forbidden - Authorization header required" errors:

1. Ensure your `.env` file contains a valid `ADMIN_TOKEN`:
   ```bash
   ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
   ```

2. Rebuild the Docker container to apply configuration changes:
   ```bash
   docker-compose down
   docker-compose build --no-cache
   docker-compose up -d
   ```

3. Check Apache logs for authorization issues:
   ```bash
   docker-compose logs chatbot | grep -i authorization
   ```

4. Verify the Authorization header is being sent by checking browser Network tab (DevTools)

**Security Note**: The default `.htaccess` configuration includes `Access-Control-Allow-Origin "*"` for development convenience. For production deployments, update this to specify your domain:
```apache
Header always set Access-Control-Allow-Origin "https://yourdomain.com"
```

### Production with Docker

1. **Create production docker-compose.yml**:
   ```yaml
   version: '3.8'
   
   services:
     chatbot:
       build: .
       restart: unless-stopped
       ports:
         - "80:80"
         - "443:443"
       environment:
         - OPENAI_API_KEY=${OPENAI_API_KEY}
         - OPENAI_MODEL=gpt-3.5-turbo
         - DEBUG=false
         - LOG_LEVEL=warning
       volumes:
         - ./logs:/var/www/html/logs
         - ./ssl:/etc/ssl/chatbot:ro
         - ./.env:/var/www/html/.env:ro
       healthcheck:
         test: ["CMD", "curl", "-f", "http://localhost/"]
         interval: 30s
         timeout: 10s
         retries: 3
         start_period: 40s
       networks:
         - chatbot_network
   
   networks:
     chatbot_network:
       driver: bridge
   
   volumes:
     logs:
       driver: local
   ```

2. **Deploy**:
   ```bash
   # Build and start
   docker-compose -f docker-compose.prod.yml up -d
   
   # Check status
   docker-compose ps
   
   # Update application
   docker-compose pull && docker-compose up -d
   ```

## MySQL Database Setup

### Why MySQL?

While SQLite is convenient for development and small deployments, MySQL is recommended for production use:

- **Concurrent Access**: Better handling of multiple simultaneous connections
- **Performance**: Optimized for high-traffic scenarios
- **Scalability**: Easier to scale horizontally with read replicas
- **Backup & Recovery**: Robust backup tools and point-in-time recovery
- **Administration**: Comprehensive management tools and monitoring

### Installation Options

#### Option 1: Docker (Recommended)

The included `docker-compose.yml` automatically sets up MySQL:

```yaml
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-rootpassword}
      MYSQL_DATABASE: ${DB_NAME:-chatbot}
      MYSQL_USER: ${DB_USER:-chatbot}
      MYSQL_PASSWORD: ${DB_PASSWORD:-chatbot}
    volumes:
      - mysql_data:/var/lib/mysql
```

Simply configure your `.env` and run:
```bash
docker-compose up -d
```

#### Option 2: Native MySQL Installation

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install mysql-server
sudo mysql_secure_installation
```

**macOS:**
```bash
brew install mysql
brew services start mysql
```

**Create Database:**
```sql
CREATE DATABASE chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chatbot'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON chatbot.* TO 'chatbot'@'localhost';
FLUSH PRIVILEGES;
```

### Configuration

Update your `.env` file:

```bash
# MySQL Configuration
DATABASE_URL=mysql:host=localhost;port=3306;dbname=chatbot;charset=utf8mb4
DB_HOST=localhost
DB_PORT=3306
DB_NAME=chatbot
DB_USER=chatbot
DB_PASSWORD=your_secure_password

# Leave DATABASE_PATH empty when using MySQL
DATABASE_PATH=
```

For Docker:
```bash
DB_HOST=mysql  # Service name from docker-compose.yml
```

### Migration

The application automatically runs migrations on first request. To manually trigger:

```bash
# With PHP CLI
php -r "require 'includes/DB.php'; \$db = new DB(['database_url' => 'mysql:host=localhost;dbname=chatbot']); echo \$db->runMigrations('./db/migrations') . ' migrations executed';"

# Or via Admin API
curl -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  "http://localhost/admin-api.php?action=health"
```

### Backup & Restore

**Backup:**
```bash
# With Docker
docker-compose exec mysql mysqldump -u chatbot -p chatbot > backup_$(date +%Y%m%d).sql

# Native MySQL
mysqldump -u chatbot -p chatbot > backup_$(date +%Y%m%d).sql
```

**Restore:**
```bash
# With Docker
docker-compose exec -T mysql mysql -u chatbot -p chatbot < backup_20240101.sql

# Native MySQL
mysql -u chatbot -p chatbot < backup_20240101.sql
```

**Automated Backups:**

Add to crontab for daily backups at 2 AM:
```bash
0 2 * * * /usr/bin/docker-compose -f /path/to/docker-compose.yml exec -T mysql mysqldump -u chatbot -pchatbot chatbot > /backups/chatbot_$(date +\%Y\%m\%d).sql
```

### Performance Tuning

**MySQL Configuration** (`/etc/mysql/my.cnf`):

```ini
[mysqld]
# Memory optimization
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M

# Connection limits
max_connections = 200

# Query cache (MySQL < 8.0)
query_cache_type = 1
query_cache_size = 32M

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

### Monitoring

**Check database size:**
```sql
SELECT 
  table_schema AS 'Database',
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'chatbot'
GROUP BY table_schema;
```

**Monitor slow queries:**
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
```

**View active connections:**
```sql
SHOW PROCESSLIST;
```

### Troubleshooting

**Connection Issues:**
```bash
# Test connection
mysql -h localhost -u chatbot -p -e "SELECT 1;"

# Check MySQL is running
sudo systemctl status mysql  # Linux
brew services list          # macOS

# Check Docker container
docker-compose ps
docker-compose logs mysql
```

**Permission Issues:**
```sql
-- Grant privileges
GRANT ALL PRIVILEGES ON chatbot.* TO 'chatbot'@'%';
FLUSH PRIVILEGES;

-- Verify grants
SHOW GRANTS FOR 'chatbot'@'%';
```

**Reset root password:**
```bash
# Stop MySQL
sudo systemctl stop mysql

# Start in safe mode
sudo mysqld_safe --skip-grant-tables &

# Reset password
mysql -u root
mysql> ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
mysql> FLUSH PRIVILEGES;
mysql> EXIT;

# Restart normally
sudo systemctl start mysql
```

## Cloud Deployment

### AWS EC2

1. **Launch EC2 instance**:
   - AMI: Ubuntu 22.04 LTS
   - Instance type: t3.micro or larger
   - Security group: HTTP (80), HTTPS (443), SSH (22)

2. **Connect and deploy**:
   ```bash
   ssh -i your-key.pem ubuntu@your-ec2-ip
   
   # Install Docker
   curl -fsSL https://get.docker.com -o get-docker.sh
   sudo sh get-docker.sh
   sudo usermod -aG docker ubuntu
   
   # Deploy application
   git clone https://github.com/your-repo/gpt-chatbot-boilerplate.git
   cd gpt-chatbot-boilerplate
   cp .env.example .env
   # Configure .env
   
   docker-compose up -d
   ```

3. **Configure domain**:
   - Point your domain to EC2 public IP
   - Use Elastic IP for static IP address
   - Configure SSL with Let's Encrypt

### DigitalOcean App Platform

1. **Create app.yaml**:
   ```yaml
   name: gpt-chatbot
   services:
   - name: web
     source_dir: /
     github:
       repo: your-username/gpt-chatbot-boilerplate
       branch: main
     run_command: apache2-foreground
     environment_slug: php
     instance_count: 1
     instance_size_slug: basic-xxs
     envs:
     - key: OPENAI_API_KEY
       value: your_api_key_here
       type: SECRET
     - key: DEBUG
       value: false
     routes:
     - path: /
   ```

2. **Deploy**:
   - Push to GitHub
   - Create new app in DigitalOcean
   - Connect GitHub repository
   - Configure environment variables
   - Deploy

### Heroku

1. **Prepare for Heroku**:
   ```bash
   # Create Procfile
   echo "web: vendor/bin/heroku-php-apache2 public/" > Procfile
   
   # Create public/index.php (if not exists)
   mkdir -p public
   cp default.php public/
   ```

2. **Deploy**:
   ```bash
   heroku create your-app-name
   heroku config:set OPENAI_API_KEY=your_api_key_here
   git push heroku main
   ```

## Server Configuration

### Apache Configuration

```apache
# Enable required modules
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so

# Global PHP configuration
<IfModule mod_php.c>
    php_value output_buffering Off
    php_value zlib.output_compression Off
    php_value implicit_flush On
    php_value max_execution_time 300
</IfModule>

# SSE-specific configuration
<Location "/chat-unified.php">
    SetEnv no-gzip 1
    SetEnv no-buffer 1
    Header always set Cache-Control "no-cache, no-store, must-revalidate"
    Header always set Pragma "no-cache"
    Header always set Expires "0"
</Location>

# Security headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# CORS (if needed)
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
```

### Nginx Configuration

```nginx
# Main server block
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/chatbot;
    index default.php index.php;
    
    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:MozTLS:10m;
    ssl_session_tickets off;
    
    # Modern configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Deny access to sensitive files
    location ~ /\.(htaccess|htpasswd|env) {
        deny all;
    }
    
    location ~ \.(log|ini)$ {
        deny all;
    }
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }
    
    # PHP processing
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # SSE endpoint optimization
    location /chat-unified.php {
        add_header X-Accel-Buffering no;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
        add_header Expires "0";
        
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_buffering off;
        fastcgi_request_buffering off;
        include fastcgi_params;
    }
    
    # Static files with caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
        
        # Enable compression
        gzip_static on;
        gzip_vary on;
    }
    
    # WebSocket proxy (if using WebSocket server)
    location /ws {
        proxy_pass http://localhost:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### PHP-FPM Configuration

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data

listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process management
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Timeouts
request_terminate_timeout = 300
request_slowlog_timeout = 30s

; Security
security.limit_extensions = .php
```

## Security Considerations

### Environment Variables

- Never commit `.env` files to version control
- Use strong, unique API keys
- Rotate API keys regularly
- Use environment-specific configurations

### Input Validation

The boilerplate includes:
- Message length limits
- Content sanitization
- SQL injection prevention (if using database)
- XSS protection

### Rate Limiting

Implement rate limiting:

```php
// In config.php
'rate_limit' => [
    'requests_per_minute' => 30,
    'burst_limit' => 10,
    'ip_whitelist' => ['127.0.0.1'],
]
```

### CORS Configuration

Configure CORS appropriately:

```php
// In config.php
'security' => [
    'allowed_origins' => [
        'https://yourdomain.com',
        'https://www.yourdomain.com'
    ],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
]
```

### SSL/HTTPS

- Always use HTTPS in production
- Configure HSTS headers
- Use modern SSL ciphers
- Implement certificate pinning if needed

## Performance Optimization

### PHP Optimizations

```ini
; OPcache configuration
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.save_comments=1
opcache.enable_cli=1

; Memory limits
memory_limit=512M
max_execution_time=300

; File uploads (if needed)
upload_max_filesize=10M
post_max_size=10M
```

### Web Server Optimizations

#### Nginx
```nginx
# Worker processes
worker_processes auto;
worker_connections 1024;

# Compression
gzip on;
gzip_vary on;
gzip_min_length 1000;
gzip_comp_level 6;
gzip_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/json
    application/javascript
    application/xml+rss;

# Caching
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Connection limits
limit_conn_zone $binary_remote_addr zone=conn_limit_per_ip:10m;
limit_req_zone $binary_remote_addr zone=req_limit_per_ip:10m rate=5r/s;

limit_conn conn_limit_per_ip 20;
limit_req zone=req_limit_per_ip burst=10 nodelay;
```

### Database Optimization (if using)

- Use connection pooling
- Implement query caching
- Optimize indexes
- Use read replicas for scaling

### CDN Integration

Consider using a CDN for static assets:

```javascript
// Update asset URLs in production
const assetBaseUrl = 'https://cdn.yourdomain.com';
```

## Monitoring

### Health Checks

The boilerplate includes health check endpoints:

```bash
# Basic health check
curl http://localhost/health

# Detailed status
curl http://localhost/status
```

### Logging

Configure comprehensive logging:

```php
// In config.php
'logging' => [
    'level' => 'info', // debug, info, warning, error
    'file' => '/var/log/chatbot/app.log',
    'max_size' => 100 * 1024 * 1024, // 100MB
    'max_files' => 10,
    'include_context' => true,
]
```

### Monitoring Tools

Consider integrating:

- **Application Performance Monitoring**: New Relic, DataDog, Scout APM
- **Error Tracking**: Sentry, Bugsnag, Rollbar
- **Uptime Monitoring**: Pingdom, UptimeRobot, StatusCake
- **Log Analysis**: ELK Stack, Splunk, Loggly

### Metrics to Monitor

- Response times
- Error rates
- API usage
- Connection counts
- Memory usage
- Disk usage
- SSL certificate expiration

### Alerting

Set up alerts for:

- High error rates (>5%)
- Slow response times (>5s)
- High memory usage (>80%)
- SSL certificate expiration (<30 days)
- API rate limit approaching

### Backup Strategy

1. **Application Code**: Version control (Git)
2. **Configuration**: Encrypted backup of `.env` files
3. **Logs**: Regular rotation and archival
4. **SSL Certificates**: Automated renewal with monitoring

## Optional Features Configuration

### LeadSense - AI Lead Detection & CRM

LeadSense automatically detects commercial opportunities in conversations, scores leads, and provides visual CRM pipeline management.

#### Quick Setup

1. **Enable LeadSense** in `.env`:
   ```bash
   LEADSENSE_ENABLED=true
   LEADSENSE_SCORE_THRESHOLD=70          # 0-100, higher = stricter qualification
   LEADSENSE_INTENT_THRESHOLD=0.6        # 0-1, higher = require stronger signals
   ```

2. **Run Database Migrations**:
   ```bash
   php scripts/run_migrations.php
   ```
   
   This will create the required tables:
   - `leads` - Lead information and scores
   - `lead_events` - Timeline of all lead activities
   - `lead_scores` - Scoring history
   - `crm_pipelines` - Visual pipeline definitions
   - `crm_pipeline_stages` - Pipeline stages
   - `crm_lead_assignments` - Owner tracking

3. **Configure Notifications** (Optional):
   ```bash
   # Slack notifications
   LEADSENSE_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
   
   # Generic webhook for CRM integration
   LEADSENSE_WEBHOOK_URL=https://your-crm.com/webhooks/leads
   LEADSENSE_WEBHOOK_SECRET=your-secret-key
   ```

4. **Privacy Settings**:
   ```bash
   LEADSENSE_PII_REDACTION=true          # Mask emails/phones in notifications
   ```

#### Production Configuration

```bash
# Core Settings
LEADSENSE_ENABLED=true
LEADSENSE_SCORE_THRESHOLD=70
LEADSENSE_INTENT_THRESHOLD=0.6
LEADSENSE_FOLLOWUP_ENABLED=true

# Notifications
LEADSENSE_SLACK_WEBHOOK=https://hooks.slack.com/services/...
LEADSENSE_WEBHOOK_URL=https://crm.example.com/webhooks/leads
LEADSENSE_WEBHOOK_SECRET=your-secret-key
LEADSENSE_PII_REDACTION=true

# Advanced Settings
LEADSENSE_SCORING_MODE=rules             # 'rules' or 'ml' (future)
LEADSENSE_DEBOUNCE_WINDOW=300            # Seconds between processing same conversation
LEADSENSE_MAX_DAILY_NOTIFICATIONS=100    # Rate limit notifications
LEADSENSE_CONTEXT_WINDOW=10              # Messages to analyze
LEADSENSE_MAX_TOKENS=1000                # Max tokens for extraction
```

#### Accessing LeadSense CRM

1. **Admin UI**: Navigate to `http://yourdomain.com/public/admin/leadsense-crm.html`
2. **API Access**: Use Admin API endpoints:
   ```bash
   # List qualified leads
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://yourdomain.com/admin-api.php?action=list_leads&qualified=true"
   
   # Get Kanban board
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://yourdomain.com/admin-api.php?action=leadsense.crm.list_leads_board&pipeline_id=default"
   ```

#### Testing LeadSense

```bash
# Run unit tests
php tests/test_leadsense_intent.php
php tests/test_leadsense_extractor.php
php tests/test_leadsense_scorer.php
php tests/test_leadsense_crm_integration.php

# All LeadSense tests
php tests/run_tests.php | grep -i leadsense
```

#### Performance Considerations

- **Non-blocking**: LeadSense runs after stream completion, doesn't slow responses
- **Efficient**: Regex-based extraction, no external API calls
- **Scalable**: Handles thousands of conversations per day
- **Database**: Add indexes for performance:
  ```sql
  CREATE INDEX idx_leads_pipeline_stage ON leads(pipeline_id, stage_id);
  CREATE INDEX idx_leads_score ON leads(score);
  CREATE INDEX idx_leads_qualified ON leads(qualified);
  ```

#### Security & Compliance

- **PII Protection**: Enable `LEADSENSE_PII_REDACTION=true` in production
- **RBAC**: Control access to lead data via Admin API permissions
- **Webhook Security**: Use `LEADSENSE_WEBHOOK_SECRET` for HMAC signatures
- **Audit Trails**: All lead operations logged in `lead_events` table
- **GDPR/CCPA**: Export/delete APIs available for compliance

#### Troubleshooting

**Leads not being detected:**
1. Check `LEADSENSE_ENABLED=true`
2. Lower thresholds temporarily for testing:
   ```bash
   LEADSENSE_INTENT_THRESHOLD=0.5
   LEADSENSE_SCORE_THRESHOLD=50
   ```
3. Check logs: `tail -f logs/chatbot.log | grep LeadSense`

**Notifications not sending:**
1. Verify webhook URL is accessible
2. Test Slack webhook: `curl -X POST -H "Content-Type: application/json" -d '{"text":"test"}' $LEADSENSE_SLACK_WEBHOOK`
3. Check error logs for webhook failures

**CRM board not loading:**
1. Verify migrations completed: `ls -la db/migrations/`
2. Check default pipeline exists: `sqlite3 data/chatbot.db "SELECT * FROM crm_pipelines WHERE is_default=1;"`
3. Verify admin authentication

#### Documentation

- **Quick Start**: [docs/LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md)
- **Architecture**: [docs/leadsense-overview.md](leadsense-overview.md)
- **API Reference**: [docs/leadsense-api.md](leadsense-api.md)
- **CRM Details**: [docs/LEADSENSE_CRM.md](LEADSENSE_CRM.md)
- **Privacy**: [docs/leadsense-privacy.md](leadsense-privacy.md)

### WhatsApp Integration

For WhatsApp Business API integration, see [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md).

### Multi-Tenancy

For multi-tenant deployments, see [MULTI_TENANCY.md](MULTI_TENANCY.md).

## Troubleshooting

### Common Issues

1. **SSE not working**:
   - Check server configuration for buffering
   - Verify Content-Type headers
   - Check firewall settings

2. **High memory usage**:
   - Monitor PHP memory limits
   - Check for memory leaks in long-running processes
   - Optimize conversation history storage

3. **Slow response times**:
   - Monitor OpenAI API response times
   - Check server resources
   - Optimize PHP configuration

4. **CORS errors**:
   - Verify allowed origins configuration
   - Check browser developer tools
   - Test with curl

### Debug Mode

Enable debug mode for development:

```bash
# In .env
DEBUG=true
LOG_LEVEL=debug
```

This enables detailed logging and error reporting.

---

## Advanced Production Topics

For comprehensive production deployment and operations, refer to:

- **[Production Deployment Guide](ops/production-deploy.md)** - Complete step-by-step production deployment
- **[Backup & Restore](ops/backup_restore.md)** - Automated backup and disaster recovery procedures
- **[Incident Response Runbook](ops/incident_runbook.md)** - Incident response procedures and troubleshooting
- **[Secrets Management](ops/secrets_management.md)** - Token rotation and secrets management
- **[Logging Guide](ops/logs.md)** - Structured logging and log aggregation
- **[Monitoring Configuration](ops/monitoring/)** - Prometheus alerts and monitoring setup
- **[Nginx Production Config](ops/nginx-production.conf)** - Production-ready Nginx configuration

---

For additional support, please check the [main README](../README.md) or open an issue on GitHub.
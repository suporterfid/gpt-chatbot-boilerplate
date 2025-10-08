# Deployment Guide - GPT Chatbot Boilerplate

This guide covers various deployment scenarios for the GPT Chatbot Boilerplate, from development to production environments.

## Table of Contents

- [Development Setup](#development-setup)
- [Production Deployment](#production-deployment)
- [Docker Deployment](#docker-deployment)
- [Cloud Deployment](#cloud-deployment)
- [Server Configuration](#server-configuration)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)
- [Monitoring](#monitoring)

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
       <Location "/chat.php">
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
   sudo a2enmod rewrite headers
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
       index index.html index.php;
       
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
       
       location /chat.php {
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
       index index.html index.php;
       
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
       location /chat.php {
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

```bash
# Clone repository
git clone https://github.com/your-repo/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Configure environment
cp .env.example .env
# Edit .env with your settings

# Start development environment
docker-compose up -d

# View logs
docker-compose logs -f

# Access application
curl http://localhost:8080
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
   cp index.html public/
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
<Location "/chat.php">
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
    index index.html index.php;
    
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
    location /chat.php {
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

For additional support, please check the [main README](README.md) or open an issue on GitHub.
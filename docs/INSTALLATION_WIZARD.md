# Installation Wizard Guide

The GPT Chatbot Boilerplate includes a user-friendly web-based installation wizard that simplifies the setup process for new deployments.

## Quick Start

1. **Start the Application**
   ```bash
   # Option A: Docker (recommended)
   docker-compose up -d
   
   # Option B: PHP built-in server
   php -S localhost:8000
   ```

2. **Access the Wizard**
   ```
   http://localhost:8088/setup/install.php
   # or http://localhost:8000/setup/install.php
   ```

3. **Follow the Steps**
   - System requirements verification
   - Configuration settings
   - Database initialization
   - Completion

## Features

### Step 1: System Requirements Check

The wizard automatically verifies:
- ✅ PHP version (8.0+)
- ✅ Required extensions (cURL, JSON, PDO)
- ✅ Optional extensions (PDO SQLite, PDO MySQL)
- ✅ Write permissions
- ✅ Database drivers

### Step 2: Configuration Settings

#### OpenAI Configuration (Required)
- **API Key**: Your OpenAI API key
- **API Type**: Choose between Responses API (recommended) or Chat Completions API
- **Models**: Select models for both APIs

#### Database Configuration
- **SQLite**: File-based, zero configuration (default)
  - Path: `./data/chatbot.db`
  - Automatic directory creation
  
- **MySQL**: Production-ready relational database
  - Host: `mysql` (Docker) or `localhost`
  - Port: `3306`
  - Database name, user, and password
  - Automatic table creation

#### Admin & Security
- **Admin Token**: Auto-generated secure token (64 characters)
- **CORS Origins**: Configure allowed origins
- **Max Message Length**: Set character limit per message

#### Features & Options
- **File Upload**: Enable/disable file uploads
  - Max file size (bytes)
  - Allowed file types
- **Background Jobs**: Enable async processing
- **Audit Trail**: Enable conversation tracking
- **Audit Encryption**: Encrypt audit data at rest

### Step 3: Database Initialization

- Establishes database connection
- Creates required tables
- Runs all migrations
- Creates installation lock file (`.install.lock`)

### Step 4: Completion

- Displays success message
- Shows admin token (save this!)
- Provides links to:
  - Admin Panel
  - Chatbot Interface
  - Documentation

## Generated Files

### `.env` File

The wizard generates a complete `.env` file with:
- Core configuration (API type, storage)
- OpenAI settings (API key, models, temperatures)
- Database configuration (SQLite or MySQL)
- Admin credentials and security
- Feature toggles
- Performance settings

Example structure:
```env
# Core Configuration
API_TYPE=responses
STORAGE_TYPE=session

# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1

# Database Configuration
DATABASE_PATH=./data/chatbot.db
# or for MySQL:
# DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot

# Admin Configuration
ADMIN_ENABLED=true
ADMIN_TOKEN=c47de918e299...
```

### `.install.lock` File

JSON file containing installation metadata:
```json
{
  "installed_at": "2024-11-06T13:45:00+00:00",
  "migrations_run": 17,
  "php_version": "8.3.6",
  "database_type": "mysql"
}
```

## Security Features

### Installation Lock
- Prevents accidental re-installation
- Can be removed manually or via unlock link
- Protects existing configuration and data

### Token Generation
- Uses cryptographically secure random bytes
- 64-character hex tokens (32 bytes)
- Unique per installation

### Data Validation
- API key format validation (sk-...)
- Required field checking
- Database connection testing
- Permission verification

## Re-installation

To reconfigure an existing installation:

1. **Via File System**
   ```bash
   rm .install.lock
   # Then access the wizard again
   ```

2. **Via Web Interface**
   - Visit the installation page
   - Click "click here to unlock" link
   - Confirm re-installation

⚠️ **Note**: Re-installation will:
- ✅ Regenerate `.env` file
- ✅ Allow database reconfiguration
- ❌ NOT delete existing data
- ❌ NOT remove database tables

## Troubleshooting

### Cannot Access Wizard

**Problem**: 404 error when accessing `/setup/install.php`

**Solutions**:
1. Check web server is running
2. Verify `.htaccess` is enabled (Apache)
3. Try direct path: `http://localhost:8000/setup/install.php`

### Permission Errors

**Problem**: "Cannot write to application directory"

**Solutions**:
```bash
# Give write permissions
chmod -R 755 /path/to/chatbot
chown -R www-data:www-data /path/to/chatbot  # Apache/Nginx
```

### Database Connection Failed

**Problem**: MySQL connection error

**Solutions**:
1. Verify MySQL is running:
   ```bash
   docker-compose ps mysql
   # or
   sudo systemctl status mysql
   ```

2. Check credentials in `.env` match MySQL configuration

3. Ensure database exists:
   ```sql
   CREATE DATABASE chatbot;
   ```

### Already Installed Message

**Problem**: Wizard shows "Installation Already Complete"

**Solutions**:
1. Delete `.install.lock` file
2. Or use the unlock link in the wizard
3. Confirm you want to reconfigure

## Best Practices

### Production Deployment

1. **Use MySQL** instead of SQLite for better performance and scalability

2. **Secure Admin Token**
   - Save the generated token immediately
   - Store in password manager
   - Never commit to version control

3. **Configure CORS**
   - Replace `*` with specific domains
   - Example: `https://yourdomain.com,https://app.yourdomain.com`

4. **Enable HTTPS**
   - Always use SSL certificates in production
   - Update `OPENAI_BASE_URL` if using proxy

5. **Backup Configuration**
   ```bash
   cp .env .env.backup
   cp .install.lock .install.lock.backup
   ```

### Development Setup

1. **Use SQLite** for quick setup without MySQL dependency

2. **Keep Debug Mode Off** even in development for accurate testing

3. **Test Both API Types**
   - Try Responses API with prompts
   - Test Chat Completions for simple use cases

## Docker-Specific Notes

### Using Docker Compose

The wizard works seamlessly with Docker:

```yaml
services:
  chatbot:
    build: .
    ports:
      - "8088:80"
    depends_on:
      - mysql
    volumes:
      - ./data:/var/www/html/data  # Persist database
      - ./logs:/var/www/html/logs  # Persist logs
  
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
```

### Database Host Configuration

When using Docker Compose:
- **Database Host**: Use service name `mysql` (not `localhost`)
- **Connection**: Containers communicate via internal network
- **Port**: Internal port `3306` (external mapping optional)

Example `.env` for Docker:
```env
DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot;charset=utf8mb4
DB_HOST=mysql
DB_PORT=3306
```

## Advanced Configuration

### Environment Variables Not in Wizard

Some advanced settings are not in the wizard but can be added to `.env`:

```env
# Responses API Advanced
RESPONSES_PROMPT_ID=pmpt_xyz
RESPONSES_PROMPT_VERSION=1
RESPONSES_TOOLS=[{"type":"file_search"}]
RESPONSES_VECTOR_STORE_IDS=vs_123,vs_456

# WebSocket (Optional)
WEBSOCKET_ENABLED=true
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080

# Performance
CACHE_ENABLED=true
CACHE_TTL=3600
COMPRESSION_ENABLED=true

# Logging
LOG_LEVEL=debug
LOG_FILE=logs/chatbot.log
```

### Manual Migration Run

If migrations don't run automatically:

```bash
php -r "
require 'includes/DB.php';
\$config = ['database_path' => './data/chatbot.db'];
\$db = new DB(\$config);
echo \$db->runMigrations('./db/migrations') . ' migrations executed';
"
```

## Support

For issues or questions:
- 📖 [Documentation](../README.md)
- 🐛 [GitHub Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)

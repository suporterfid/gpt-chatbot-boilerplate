# Deployment Package Guide

This guide explains how to create and deploy production packages for cloud hosting services like Hostinger, cPanel, and other shared hosting environments.

## Overview

The project includes automated deployment scripts that work both **locally** and in **GitHub Actions**:

- **Local builds**: Run scripts on your development machine (Windows/Linux/macOS)
- **CI/CD builds**: Trigger builds via GitHub Actions with a single click
- **Automated packaging**: Creates optimized ZIP files ready for deployment

---

## Quick Start

### Option 1: Build Locally

#### On Linux/macOS:
```bash
# Make script executable (first time only)
chmod +x scripts/build-deployment.sh

# Build deployment package
./scripts/build-deployment.sh

# Or specify custom output filename
./scripts/build-deployment.sh my-deployment.zip
```

#### On Windows:
```cmd
# Build deployment package
scripts\build-deployment.bat

# Or specify custom output filename
scripts\build-deployment.bat my-deployment.zip
```

**Output**: Creates `chatbot-deploy.zip` (or your custom filename) in the project root.

---

### Option 2: Build via GitHub Actions (Recommended)

1. **Navigate to GitHub Actions**:
   - Go to your repository on GitHub
   - Click **Actions** tab
   - Select **Build Deployment Package** workflow

2. **Trigger the workflow**:
   - Click **Run workflow** button
   - Select options:
     - **Environment**: `production` or `staging`
     - **Include vendor**: Whether to bundle dependencies (optional)
   - Click **Run workflow**

3. **Download the package**:
   - Wait for the workflow to complete (usually 2-3 minutes)
   - Click on the completed workflow run
   - Scroll to **Artifacts** section
   - Download `deployment-package-[commit-hash]`

**Benefits**:
- ✅ Consistent build environment
- ✅ Automatic checksums
- ✅ Build metadata tracking
- ✅ No local setup required

---

## What's Included in the Package

### ✅ Included Files:
- All PHP application files (`*.php`)
- Frontend assets (`*.css`, `*.js`)
- Configuration files (`.htaccess`, `composer.json`)
- Application directories:
  - `api/` - API endpoints
  - `assets/` - Static resources
  - `channels/` - Channel integrations
  - `db/migrations/` - Database schemas
  - `includes/` - Core business logic
  - `public/` - Public-facing files
  - `webhooks/` - Webhook handlers
- `vendor/` - PHP dependencies (if Composer is available)
- `favicon.ico`
- `.env.example` - Environment template
- `DEPLOYMENT_INFO.txt` - Build metadata

### ❌ Excluded Files:
- `.git/` - Git repository data
- `.github/` - CI/CD workflows
- Development files:
  - `docker*` - Docker configurations
  - `tests/` - Test suites
  - `docs/` - Documentation
  - `*.md` - Markdown files
  - `phpstan.neon` - Static analysis config
- Build artifacts:
  - `build/`
  - `logs/`
  - `data/`
- Sensitive files:
  - `.env` (with actual credentials)
  - `*.backup`

---

## Deployment Steps

### 1. Upload to Hosting Service

#### Hostinger (File Manager):
1. Log in to Hostinger control panel
2. Go to **File Manager**
3. Navigate to `public_html` (or your domain directory)
4. Click **Upload** and select your ZIP file
5. Right-click the ZIP → **Extract**
6. Delete the ZIP file after extraction

#### Hostinger (SFTP):
```bash
# Connect via SFTP
sftp username@your-server.com

# Navigate to web root
cd public_html

# Upload package
put chatbot-deploy.zip

# Extract (requires shell access)
unzip chatbot-deploy.zip
rm chatbot-deploy.zip
```

#### Other Providers (cPanel):
1. Log in to cPanel
2. Open **File Manager**
3. Navigate to `public_html`
4. Upload ZIP file
5. Extract using **Extract** option

---

### 2. Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit configuration
nano .env  # or use File Manager editor
```

**Required settings**:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_secure_password

# OpenAI API
OPENAI_API_KEY=sk-your-openai-api-key-here

# Admin Access
ADMIN_TOKEN=generate-a-strong-random-token-here

# Application
BASE_URL=https://your-domain.com
ENVIRONMENT=production
DEBUG_MODE=false
```

**Security Tips**:
- Use strong, random values for `ADMIN_TOKEN`
- Never commit `.env` to version control
- Ensure `.env` is not web-accessible (protected by `.htaccess`)

---

### 3. Set File Permissions

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Create writable directories
mkdir -p logs data
chmod 777 logs data  # Or 755 with proper ownership
```

**Alternative** (if you don't have SSH access):
- Use File Manager → Right-click → Permissions
- Directories: `755` (rwxr-xr-x)
- Files: `644` (rw-r--r--)
- Logs/Data: `777` (rwxrwxrwx) - temporarily, adjust after setup

---

### 4. Install Dependencies

If `vendor/` directory is not included in the package:

```bash
# Via SSH
composer install --no-dev --optimize-autoloader

# Via Hostinger Terminal (if available)
cd public_html/your-domain
composer install --no-dev --optimize-autoloader
```

**Note**: Most shared hosting environments support Composer. Check your provider's documentation.

---

### 5. Create Database

#### Via phpMyAdmin:
1. Log in to phpMyAdmin
2. Click **New** to create a database
3. Enter database name (matching `DB_NAME` in `.env`)
4. Select collation: `utf8mb4_unicode_ci`
5. Click **Create**

#### Via MySQL Command Line:
```sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

---

### 6. Run Database Migrations

```bash
# Via SSH
php scripts/run_migrations.php

# Or via browser (if web-accessible)
https://your-domain.com/scripts/run_migrations.php
```

**Expected output**:
```
Running database migrations...
✓ Migration 001_initial_schema.sql applied
✓ Migration 002_add_agents.sql applied
✓ All migrations completed successfully
```

---

### 7. Verify Deployment

**Checklist**:
- [ ] **Home page**: `https://your-domain.com/` loads correctly
- [ ] **Admin panel**: `https://your-domain.com/public/admin/` is accessible
- [ ] **Chat widget**: Appears and responds to messages
- [ ] **Database**: Connections work without errors
- [ ] **HTTPS**: SSL certificate is active and valid
- [ ] **Logs**: Check `logs/error.log` for issues
- [ ] **File permissions**: All files have correct permissions
- [ ] **Environment**: `.env` is configured and not publicly accessible

**Test Commands**:
```bash
# Check PHP version
php -v

# Check installed extensions
php -m | grep -E 'curl|json|mbstring|pdo'

# Test database connection
php -r "new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');" && echo "OK"

# Check file permissions
ls -la | head -n 10
```

---

## Security Checklist

Before going live, verify:

- [ ] **Environment file**: `.env` is protected and not in web root
- [ ] **Admin token**: Strong, random token is set (`ADMIN_TOKEN`)
- [ ] **HTTPS enabled**: SSL certificate installed and enforced
- [ ] **Database credentials**: Strong passwords, limited privileges
- [ ] **Error display**: `display_errors = Off` in `php.ini`
- [ ] **File permissions**: Correct permissions (755/644)
- [ ] **Log protection**: `logs/` directory has `.htaccess` deny rule
- [ ] **Debug mode**: `DEBUG_MODE=false` in production
- [ ] **API keys**: OpenAI key is valid and has usage limits
- [ ] **Backups**: Regular backup schedule configured

---

## Troubleshooting

### Issue: "500 Internal Server Error"

**Solutions**:
1. Check error logs: `tail -f logs/error.log`
2. Verify file permissions: `755` for dirs, `644` for files
3. Check `.htaccess` syntax
4. Enable error display temporarily:
   ```php
   // In index.php (temporarily)
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

### Issue: "Database connection failed"

**Solutions**:
1. Verify `.env` database credentials
2. Check database exists: `SHOW DATABASES;`
3. Test connection:
   ```bash
   mysql -u username -p -h localhost database_name
   ```
4. Check MySQL service: `systemctl status mysql`

### Issue: "Composer dependencies missing"

**Solutions**:
1. Run: `composer install --no-dev --optimize-autoloader`
2. Verify Composer is available: `composer --version`
3. Check PHP version compatibility: `php -v` (must be ≥ 8.0)

### Issue: "File permissions error"

**Solutions**:
1. Reset permissions:
   ```bash
   find . -type d -exec chmod 755 {} \;
   find . -type f -exec chmod 644 {} \;
   chmod 777 logs data
   ```
2. Check ownership: `chown -R www-data:www-data .`
3. Verify SELinux context (if applicable)

### Issue: "OpenAI API calls failing"

**Solutions**:
1. Verify API key in `.env`: `OPENAI_API_KEY`
2. Check API quota: https://platform.openai.com/usage
3. Test connectivity:
   ```bash
   curl https://api.openai.com/v1/models \
     -H "Authorization: Bearer YOUR_API_KEY"
   ```
4. Check firewall/outbound rules

---

## GitHub Actions Configuration

### Setting Up Secrets

To enable automatic deployment via GitHub Actions:

1. Go to **Settings** → **Secrets and variables** → **Actions**
2. Add the following secrets:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `DEPLOY_HOST` | Server hostname or IP | `ftp.your-domain.com` |
| `DEPLOY_USER` | SFTP/SSH username | `your-username` |
| `DEPLOY_PASSWORD` | SFTP/SSH password | `your-password` |
| `DEPLOY_PATH` | Server directory path | `/public_html/` |
| `DEPLOY_PORT` | SSH/SFTP port (optional) | `22` |
| `DEPLOY_KEY` | SSH private key (optional) | `-----BEGIN RSA PRIVATE KEY-----...` |

### Workflow Configuration

The workflow file is located at `.github/workflows/deploy-package.yml`

**Features**:
- ✅ Manual trigger (workflow_dispatch)
- ✅ Automatic on releases
- ✅ Environment selection (production/staging)
- ✅ Artifact upload with retention
- ✅ SHA256 checksum generation
- ✅ Optional auto-deployment via SFTP

**Triggering the workflow**:
```bash
# Via GitHub UI: Actions → Build Deployment Package → Run workflow

# Via GitHub CLI:
gh workflow run deploy-package.yml \
  -f environment=production \
  -f include_vendor=false
```

---

## Best Practices

### 1. **Version Control**
- Tag releases: `git tag -a v1.0.0 -m "Release 1.0.0"`
- Use semantic versioning
- Never commit `.env` files

### 2. **Testing**
- Test locally before deploying
- Use staging environment first
- Run automated tests in CI/CD

### 3. **Backups**
- Backup database before migrations
- Keep previous deployment packages
- Test restore procedures

### 4. **Monitoring**
- Set up error log monitoring
- Monitor API usage and costs
- Track application performance

### 5. **Updates**
- Keep dependencies updated
- Subscribe to security advisories
- Test updates in staging first

---

## Advanced: Automated Deployment

### Using GitHub Actions for Full Automation

The workflow can automatically deploy to your server after building:

1. **Configure secrets** (see above)
2. **Set up environment**:
   - Go to **Settings** → **Environments**
   - Create `production` environment
   - Add protection rules (optional)
3. **Trigger deployment**:
   - Workflow will build and deploy automatically
   - Monitor progress in Actions tab

### Using Deployment Hooks

Add to `.github/workflows/deploy-package.yml`:

```yaml
- name: Run post-deployment commands
  uses: appleboy/ssh-action@master
  with:
    host: ${{ secrets.DEPLOY_HOST }}
    username: ${{ secrets.DEPLOY_USER }}
    password: ${{ secrets.DEPLOY_PASSWORD }}
    script: |
      cd ${{ secrets.DEPLOY_PATH }}
      php scripts/run_migrations.php
      echo "Deployment complete!"
```

---

## Support

### Documentation
- [README.md](../README.md) - Project overview
- [CHANGELOG.md](../CHANGELOG.md) - Version history
- [PRODUCTION_SECURITY_CHECKLIST.md](../PRODUCTION_SECURITY_CHECKLIST.md) - Security guide
- [deployment.md](deployment.md) - General deployment guide (Docker, cloud providers)

### Getting Help
- **Issues**: [GitHub Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- **Discussions**: [GitHub Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)

### Resources
- [Hostinger Documentation](https://support.hostinger.com/)
- [PHP Official Docs](https://www.php.net/docs.php)
- [Composer Documentation](https://getcomposer.org/doc/)
- [OpenAI API Docs](https://platform.openai.com/docs)

---

**Happy deploying! 🚀**

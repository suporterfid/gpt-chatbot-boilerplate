# Setup Directory

This directory contains the web-based installation wizard for the GPT Chatbot Boilerplate.

## Files

- **install.php** - Main installation wizard interface
- **.htaccess** - Apache configuration for the setup directory

## Usage

Access the installation wizard by navigating to:

```
http://your-domain/setup/install.php
```

Or if using a development server:

```bash
php -S localhost:8000
# Then visit: http://localhost:8000/setup/install.php
```

## Features

The installation wizard provides:

1. **System Requirements Check**
   - PHP version validation
   - Extension availability check
   - Permission verification

2. **Configuration Setup**
   - OpenAI API configuration
   - Database selection (SQLite or MySQL)
   - Admin credentials
   - Security settings
   - Feature toggles

3. **Database Initialization**
   - Automatic table creation
   - Migration execution
   - Connection testing

4. **Installation Lock**
   - Prevents accidental re-installation
   - Can be removed for reconfiguration

## Security

- Installation is locked after completion via `.install.lock` file
- Admin token is auto-generated with 64 characters
- All inputs are validated and sanitized
- HTTPS recommended for production use

## Documentation

For complete documentation, see:
- [Installation Wizard Guide](../docs/INSTALLATION_WIZARD.md)
- [Deployment Guide](../docs/deployment.md)
- [README](../README.md)

## Support

For issues or questions:
- [GitHub Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- [Documentation](../docs/)

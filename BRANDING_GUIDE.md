# Branding Configuration Guide

This guide explains how to customize your application's branding using environment variables.

## Overview

The branding configuration system allows you to customize your application's name, logo, and attribution text without modifying any code. All settings are controlled through environment variables in your `.env` file.

## Configuration Variables

### 1. BRAND_NAME
**Default:** `Assistant Chat Boilerplate`

The main application/product name displayed throughout the UI.

**Example:**
```env
BRAND_NAME=My Custom Assistant
```

**Where it appears:**
- Browser tab title
- Admin panel header
- Login modal
- Public website header and hero section
- Footer copyright text

---

### 2. LOGO_URL
**Default:** *(empty)*

URL to your brand logo image. Can be an absolute URL or a relative path from the web root.

**Examples:**
```env
# Relative path
LOGO_URL=/assets/logo.png

# Absolute URL
LOGO_URL=https://cdn.example.com/images/brand-logo.png
```

**Where it appears:**
- Browser favicon
- Admin panel sidebar header
- Login modal
- Public website header (replaces default icon)

**Image recommendations:**
- Format: PNG with transparent background
- Admin sidebar: 32px height
- Login modal: 48px height
- Website header: 48px height
- Favicon: 32x32px or 64x64px

---

### 3. POWERED_BY_LABEL
**Default:** `Powered by`

Text label shown in footers or attribution sections.

**Example:**
```env
POWERED_BY_LABEL=Built with
```

**Where it appears:**
- Public website footer
- Can be used in custom views/templates

---

## How to Use Branding Configuration

### Step 1: Update Your .env File

Edit your `.env` file and add/modify the branding variables:

```env
# Branding configuration
BRAND_NAME=Acme AI Assistant
LOGO_URL=/assets/acme-logo.png
POWERED_BY_LABEL=Powered by
```

### Step 2: Access Values in PHP

The branding configuration is automatically loaded into the `$config` array. You can access these values anywhere you have access to the config:

```php
<?php
// Load configuration
$config = require __DIR__ . '/config.php';

// Access branding values
$brandName = $config['branding']['brand_name'];
$logoUrl = $config['branding']['logo_url'];
$poweredByLabel = $config['branding']['powered_by_label'];

// Use in your HTML
echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
?>
```

### Step 3: Using the Standalone Config File (Optional)

You can also load just the branding configuration:

```php
<?php
// Load branding config separately
$brandingConfig = require __DIR__ . '/config/branding.php';

echo $brandingConfig['brand_name'];
echo $brandingConfig['logo_url'];
echo $brandingConfig['powered_by_label'];
?>
```

## Complete PHP Example

Here's a complete example of creating a branded page:

```php
<?php
// Load configuration
$config = require __DIR__ . '/config.php';

// Get branding configuration with fallback defaults
$brandName = $config['branding']['brand_name'] ?? 'Assistant Chat Boilerplate';
$logoUrl = $config['branding']['logo_url'] ?? '';
$poweredByLabel = $config['branding']['powered_by_label'] ?? 'Powered by';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></title>

    <?php if (!empty($logoUrl)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body>
    <header>
        <?php if (!empty($logoUrl)): ?>
        <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
             alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
             style="height: 48px;">
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></h1>
    </header>

    <main>
        <h2>Welcome to <?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p>Your AI-powered assistant is here to help.</p>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if (!empty($poweredByLabel)): ?>
        <p><?php echo htmlspecialchars($poweredByLabel, ENT_QUOTES, 'UTF-8'); ?> GPT Assistant Boilerplate</p>
        <?php endif; ?>
    </footer>
</body>
</html>
```

## Using in Controllers/Services

When you need branding information in your PHP classes:

```php
<?php

class MyController {
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function getBrandName(): string {
        return $this->config['branding']['brand_name'] ?? 'Assistant Chat Boilerplate';
    }

    public function renderHeader(): string {
        $brandName = $this->getBrandName();
        $logoUrl = $this->config['branding']['logo_url'] ?? '';

        $html = '<header>';

        if (!empty($logoUrl)) {
            $html .= sprintf(
                '<img src="%s" alt="%s">',
                htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8')
            );
        }

        $html .= sprintf('<h1>%s</h1>', htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'));
        $html .= '</header>';

        return $html;
    }
}
```

## Using in JavaScript

To pass branding configuration to JavaScript:

```php
<script>
    window.brandingConfig = {
        brandName: <?php echo json_encode($brandName); ?>,
        logoUrl: <?php echo json_encode($logoUrl); ?>,
        poweredByLabel: <?php echo json_encode($poweredByLabel); ?>
    };
</script>

<script>
    // Now use it in your JavaScript
    console.log('Brand:', window.brandingConfig.brandName);

    // Update page title dynamically
    document.title = window.brandingConfig.brandName + ' - Dashboard';

    // Update logo if present
    if (window.brandingConfig.logoUrl) {
        const logoElement = document.getElementById('brand-logo');
        if (logoElement) {
            logoElement.src = window.brandingConfig.logoUrl;
        }
    }
</script>
```

## Best Practices

### 1. Always Escape Output
Always use `htmlspecialchars()` when outputting user-configurable values to prevent XSS:

```php
// Good
echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');

// Bad - vulnerable to XSS
echo $brandName;
```

### 2. Provide Fallback Defaults
Always provide sensible defaults using the null coalescing operator:

```php
$brandName = $config['branding']['brand_name'] ?? 'Assistant Chat Boilerplate';
```

### 3. Check for Empty Values
Before outputting optional elements like logos, check if they're set:

```php
<?php if (!empty($logoUrl)): ?>
    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
```

### 4. Use Consistent Image Sizing
When displaying logos, use consistent heights across your application:
- Icons: 24-32px
- Headers: 40-48px
- Hero sections: 60-80px

### 5. Test with Different Configurations
Test your application with:
- All variables set
- All variables empty
- Only some variables set
- Very long brand names
- Special characters in brand names

## Files Updated

The branding system touches the following files:

1. **[.env.example](.env.example)** - Environment variable documentation
2. **[config.php](config.php)** - Main configuration (includes branding config)
3. **[config/branding.php](config/branding.php)** - Standalone branding config
4. **[public/admin/index.php](public/admin/index.php)** - Admin panel
5. **[default.php](default.php)** - Public website
6. **[public/whitelabel.php](public/whitelabel.php)** - Already uses similar pattern

## Troubleshooting

### Brand name not updating
1. Check your `.env` file has the correct variable names
2. Clear any PHP opcache: `php -r "opcache_reset();"`
3. Restart your web server

### Logo not displaying
1. Verify the path is correct (relative to web root)
2. Check file permissions
3. Verify the image file exists
4. Check browser console for 404 errors

### Variables showing as empty
1. Ensure `.env` file is in the root directory
2. Check `.env` file syntax (no spaces around `=`)
3. Verify config.php is loading the .env file correctly

## Example Configurations

### Minimal (No Logo)
```env
BRAND_NAME=My Assistant
LOGO_URL=
POWERED_BY_LABEL=Powered by
```

### Full Branding
```env
BRAND_NAME=Acme AI Solutions
LOGO_URL=/assets/branding/acme-logo.png
POWERED_BY_LABEL=Built with
```

### White Label (Hide Attribution)
```env
BRAND_NAME=Enterprise Assistant
LOGO_URL=https://cdn.enterprise.com/logo.png
POWERED_BY_LABEL=
```

## Support

For questions or issues with branding configuration:
1. Check this guide first
2. Review the example files
3. Open an issue on GitHub

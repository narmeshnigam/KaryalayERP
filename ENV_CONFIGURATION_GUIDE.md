# Environment-Based Configuration Guide

## Overview

KaryalayERP now uses environment-based configuration to manage database credentials and application settings. This approach:

- **Enhances Security**: Keeps sensitive credentials out of version control
- **Improves Flexibility**: Easy to configure for different environments (development, staging, production)
- **Simplifies Deployment**: Change settings without modifying code

## Quick Start

### First-Time Setup

1. **Copy the example environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` with your database credentials:**
   ```bash
   # Database Configuration
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=your_password
   DB_NAME=karyalay_db
   DB_CHARSET=utf8mb4
   ```

3. **Run the setup wizard:**
   Navigate to: `http://localhost/KaryalayERP/setup/`
   
   The setup wizard will automatically update your `.env` file with the credentials you provide.

### Manual Configuration

If you prefer to configure manually without the setup wizard:

1. Open `.env` in a text editor
2. Fill in all required values:
   - Database credentials (DB_HOST, DB_USER, DB_PASS, DB_NAME)
   - Application URL (APP_URL)
   - Other settings as needed
3. Save the file
4. The application will automatically load these settings

## Configuration Files

### `.env` (Your Environment File)
- **Location**: Project root directory
- **Purpose**: Stores your actual configuration values
- **Security**: NEVER commit this file to version control
- **Git Status**: Automatically ignored by `.gitignore`

### `.env.example` (Template File)
- **Location**: Project root directory
- **Purpose**: Template showing all available configuration options
- **Security**: Safe to commit to version control
- **Usage**: Copy this to create your `.env` file

### `config/config.php` (Configuration Loader)
- **Purpose**: Loads environment variables and defines PHP constants
- **Note**: No longer contains hardcoded credentials
- **Modification**: Generally, you won't need to edit this file

### `config/env_loader.php` (Environment Parser)
- **Purpose**: Parses `.env` file and loads variables
- **Note**: Core utility file - avoid modifications unless necessary

## Available Configuration Options

### Database Settings
```bash
DB_HOST=localhost          # Database server address
DB_USER=root              # Database username
DB_PASS=                  # Database password (can be empty)
DB_NAME=karyalay_db       # Database name
DB_CHARSET=utf8mb4        # Character encoding
```

### Application Settings
```bash
APP_NAME=Karyalay ERP     # Application display name
APP_URL=http://localhost/KaryalayERP  # Base URL of your application
```

### Session Settings
```bash
SESSION_NAME=karyalay_session  # Session cookie name
SESSION_LIFETIME=3600          # Session lifetime in seconds (1 hour)
SESSION_SECRET=                # Optional: session encryption key
```

### Environment Settings
```bash
TIMEZONE=Asia/Kolkata     # Application timezone
ENVIRONMENT=development   # Environment mode (development/production)
DEBUG_MODE=true          # Enable/disable debug output
```

## Environment Modes

### Development Mode
```bash
ENVIRONMENT=development
DEBUG_MODE=true
```
- Full error reporting
- Detailed error messages
- Suitable for local development

### Production Mode
```bash
ENVIRONMENT=production
DEBUG_MODE=false
```
- Suppressed error reporting
- User-friendly error pages
- Enhanced security

## Migration from Old Configuration

If you're upgrading from the previous version with hardcoded credentials:

1. **Your old `config/config.php` has been updated** to use environment variables
2. **A new `.env` file has been created** with empty values
3. **Fill in your `.env` file** with your existing credentials:
   - Old DB_HOST value → New .env DB_HOST
   - Old DB_USER value → New .env DB_USER
   - Old DB_PASS value → New .env DB_PASS
   - Old DB_NAME value → New .env DB_NAME

## Troubleshooting

### "Cannot connect to database"
1. Check that `.env` file exists in the project root
2. Verify database credentials in `.env` are correct
3. Ensure database server is running
4. Test connection manually with credentials

### "Configuration file not found"
1. Verify `.env` file is in the project root directory (not in subdirectories)
2. Check file permissions - PHP must be able to read the file
3. Ensure the file is named exactly `.env` (not `.env.txt` or similar)

### ".env changes not taking effect"
1. Restart your web server (Apache/Nginx)
2. Clear PHP OpCache if enabled
3. Verify you're editing the correct `.env` file
4. Check for syntax errors in `.env` (proper key=value format)

### "Setup wizard can't update configuration"
1. Check file permissions on `.env` file
2. Ensure PHP has write access to the file
3. Verify `.env` file exists (should be created automatically)

## Security Best Practices

### DO:
✅ Keep `.env` file in project root (already done)  
✅ Use `.gitignore` to exclude `.env` (already configured)  
✅ Use strong database passwords in production  
✅ Set `ENVIRONMENT=production` and `DEBUG_MODE=false` in production  
✅ Restrict file permissions on `.env` (e.g., `chmod 600 .env` on Linux)  

### DON'T:
❌ Commit `.env` file to version control  
❌ Share `.env` file contents publicly  
❌ Use development settings in production  
❌ Store `.env` file in web-accessible directories (it's in root, which is correct)  
❌ Leave database password empty in production  

## Using Setup Wizard

The setup wizard (`setup/database.php`) now automatically:

1. Accepts your database credentials through a web form
2. Tests the database connection
3. Creates the database if it doesn't exist
4. **Writes credentials to `.env` file** (not to `config.php`)
5. Reloads configuration automatically

No manual editing of configuration files is required when using the setup wizard.

## Advanced Usage

### Accessing Environment Variables in Code

```php
// Using EnvLoader utility
require_once 'config/env_loader.php';

$dbHost = EnvLoader::get('DB_HOST');
$appName = EnvLoader::get('APP_NAME', 'Default App Name');

// Check if variable exists
if (EnvLoader::has('SESSION_SECRET')) {
    // Variable is set
}

// Set environment variable programmatically
EnvLoader::set('CUSTOM_VAR', 'value');
```

### Using PHP Constants (Recommended)

```php
// After config.php is loaded, use defined constants
require_once 'config/config.php';

echo DB_HOST;      // Database host
echo DB_NAME;      // Database name
echo APP_NAME;     // Application name
echo APP_URL;      // Application URL
```

## Support

If you encounter any issues with the environment-based configuration:

1. Check this documentation first
2. Verify your `.env` file syntax
3. Review file permissions
4. Check web server error logs
5. Ensure PHP version compatibility (PHP 7.0+)

## Changelog

### Version 2.0 (Environment-Based Configuration)
- **Added**: `.env` file support for configuration
- **Added**: `config/env_loader.php` utility for parsing environment files
- **Changed**: `config/config.php` now loads from environment variables
- **Changed**: Setup wizard updates `.env` instead of `config.php`
- **Added**: `.gitignore` to protect sensitive configuration
- **Removed**: Hardcoded database credentials from all files

### Version 1.0 (Legacy)
- Hardcoded credentials in `config/config.php`
- Setup wizard modified `config.php` directly

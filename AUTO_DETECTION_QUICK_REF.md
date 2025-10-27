# Auto-Detection Quick Reference

## 🎯 How It Works

```
.env file has APP_URL?
├─ YES → Use .env value ✓
└─ NO  → Auto-detect from server ✓

Auto-Detection Process:
1. Check server name (localhost vs domain)
2. Detect protocol (http vs https)
3. Check port (80, 443, or custom)
4. Find base path (subdirectory)
5. Build complete APP_URL
6. Configure all URLs automatically
```

## 🚀 Supported Configurations

| Scenario | Auto-Detected URL | Notes |
|----------|-------------------|-------|
| Localhost root | `http://localhost` | Default XAMPP |
| Localhost subdirectory | `http://localhost/KaryalayERP` | Standard setup |
| Custom port | `http://localhost:8080/app` | Any port |
| Production HTTPS | `https://domain.com/erp` | Secure |
| Subdomain | `https://erp.domain.com` | Root install |
| Staging | `https://staging.domain.com` | Auto-detects |

## 📝 Configuration Options

### Option 1: Auto-Detection (Recommended)
```bash
# Leave .env empty or omit APP_URL
# System auto-detects everything
```

### Option 2: Manual Override
```bash
# Set explicit value in .env
APP_URL=https://yourdomain.com/erp
```

### Option 3: Hybrid
```bash
# Set for production, auto-detect in dev
# Just don't set APP_URL in dev .env
```

## 🔧 Key Files

| File | Purpose |
|------|---------|
| `config/server_detector.php` | Detection engine |
| `config/config.php` | If/else logic |
| `includes/url_helper.php` | URL functions |
| `check_environment.php` | Test page |

## 🧪 Testing

```bash
# Check detection:
http://localhost/KaryalayERP/check_environment.php

# Validate config:
http://localhost/KaryalayERP/validate_config.php
```

## 💡 New Functions

```php
// Environment checks
is_localhost_environment()  // true on localhost
is_production()            // true on live server
is_development()           // true in dev mode
get_environment()          // 'development'|'staging'|'production'
get_protocol()             // 'http' or 'https'

// URL generators
base_url()                 // Base URL without /public
public_url()               // Public directory URL
setup_url()                // Setup wizard URL
login_url()                // Login page URL
home_url()                 // Dashboard URL

// Smart redirect
smart_redirect('page.php') // Adapts to environment
```

## ✅ Benefits

- ✅ Zero configuration deployment
- ✅ Works in any environment
- ✅ Automatic subdirectory support
- ✅ HTTPS auto-detection
- ✅ Custom port support
- ✅ No hardcoded values

## 🎓 Examples

### Development
```php
// Automatically generates:
http://localhost/KaryalayERP/public/login.php
```

### Production with HTTPS
```php
// Automatically generates:
https://yourdomain.com/erp/public/login.php
```

### Custom Port
```php
// Automatically generates:
http://localhost:8080/myapp/public/login.php
```

## 🔍 Detection Priority

```
1. .env file APP_URL (if set)      → Highest priority
2. Auto-detected from server       → Automatic fallback
3. Works out of the box!           → Zero config needed
```

## 🚨 Quick Fixes

### Wrong URL detected?
→ Set explicit APP_URL in .env

### Not working after deploy?
→ Check `check_environment.php` for detection results

### Behind proxy?
→ Set APP_URL in .env or configure proxy headers

## 📚 Documentation

- Full Guide: `AUTO_DETECTION_GUIDE.md`
- URL Helpers: `URL_PATH_GUIDE.md`
- Quick Ref: This file

---

**Deploy anywhere, configure nowhere!** 🚀

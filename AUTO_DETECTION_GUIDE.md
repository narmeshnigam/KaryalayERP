# Auto-Detection Configuration Guide

## 🎯 What Is Auto-Detection?

KaryalayERP now **automatically detects** your server environment and configures URLs accordingly. No more hardcoded localhost URLs or manual configuration!

### Key Features

✅ **Automatic Protocol Detection** - Detects http vs https  
✅ **Subdirectory Support** - Works in any folder structure  
✅ **Environment Detection** - Identifies localhost, staging, production  
✅ **Port Detection** - Handles custom ports automatically  
✅ **Zero Configuration** - Works out of the box without .env file  
✅ **Smart Fallback** - Uses .env values when present, auto-detects when missing  

## 🚀 How It Works

### Detection Priority (If/Else Logic)

```
1. CHECK: Is APP_URL set in .env file?
   ├─ YES → Use the value from .env
   └─ NO  → Auto-detect from server environment
   
2. DETECT SERVER ENVIRONMENT:
   ├─ Server Name (localhost vs domain)
   ├─ Protocol (http vs https)
   ├─ Port (80, 443, or custom)
   ├─ Base Path (subdirectory detection)
   └─ Environment Type (development/staging/production)
   
3. BUILD URLs:
   ├─ Combine detected values
   ├─ Generate APP_URL automatically
   └─ Configure all paths accordingly
```

### Automatic Detection Examples

#### Example 1: Localhost with Subdirectory
```
Server: localhost
Path: /KaryalayERP/public/index.php
Port: 80
Protocol: http

→ Auto-detected APP_URL: http://localhost/KaryalayERP
```

#### Example 2: Production with HTTPS
```
Server: yourdomain.com
Path: /erp/public/index.php
Port: 443
Protocol: https

→ Auto-detected APP_URL: https://yourdomain.com/erp
```

#### Example 3: Subdomain at Root
```
Server: erp.yourdomain.com
Path: /public/index.php
Port: 443
Protocol: https

→ Auto-detected APP_URL: https://erp.yourdomain.com
```

#### Example 4: Custom Port
```
Server: localhost
Path: /myapp/public/index.php
Port: 8080
Protocol: http

→ Auto-detected APP_URL: http://localhost:8080/myapp
```

## 📋 Usage Scenarios

### Scenario A: Fresh Installation (No .env file)

**What happens:**
1. System detects no .env file
2. Auto-detection kicks in
3. URLs generated based on current server
4. Application works immediately!

**Result:** ✅ **Zero configuration needed!**

### Scenario B: Existing Installation (With .env)

**What happens:**
1. System finds .env file
2. Reads APP_URL from .env
3. Uses explicit configuration
4. Auto-detection available as fallback

**Result:** ✅ **Manual control with auto-fallback!**

### Scenario C: Empty APP_URL in .env

**What happens:**
```bash
# .env file has:
APP_URL=
```
1. System sees empty value
2. Treats as not set
3. Auto-detection activates
4. URLs configured automatically

**Result:** ✅ **Smart fallback activated!**

### Scenario D: Moving to Production

**What happens:**
1. Deploy code to production server
2. No .env changes needed
3. System auto-detects new environment
4. URLs automatically update

**Result:** ✅ **Seamless deployment!**

## 🔧 Configuration Files Modified

### 1. `config/server_detector.php` (NEW)
**Purpose:** Server environment detection engine

**Key Functions:**
- `ServerDetector::detect()` - Full environment detection
- `ServerDetector::getAppUrl()` - Get auto-detected URL
- `ServerDetector::isLocalhost()` - Check if localhost
- `ServerDetector::getEnvironment()` - Get environment type
- `ServerDetector::suggestEnvConfig()` - Generate .env template

**Detection Logic:**
```php
// Detects:
✓ Server name (domain/localhost)
✓ Protocol (http/https via HTTPS, X-Forwarded-Proto headers)
✓ Port (standard or custom)
✓ Base path (subdirectory from SCRIPT_NAME)
✓ Environment type (localhost → development, else → production)
```

### 2. `config/config.php` (UPDATED)
**Changes:** Added intelligent if/else logic

**Before:**
```php
define('APP_URL', EnvLoader::get('APP_URL', 'http://localhost/KaryalayERP'));
// Always used hardcoded fallback
```

**After:**
```php
$app_url_from_env = EnvLoader::get('APP_URL', null);
if (!empty($app_url_from_env)) {
    // Use .env value if set
    define('APP_URL', $app_url_from_env);
} else {
    // Auto-detect from server
    define('APP_URL', ServerDetector::getAppUrl());
}
```

**Result:** Smart fallback with priority to manual config!

### 3. `config/env_loader.php` (ENHANCED)
**Changes:** Added detection helpers

**New Methods:**
- `EnvLoader::envFileExists()` - Check if .env exists
- `EnvLoader::isMissing($key)` - Check if key is empty
- `ENV_FILE_LOADED` constant - Track load status

### 4. `includes/url_helper.php` (ENHANCED)
**Changes:** Added environment-aware functions

**New Functions:**
```php
base_url()                  // Get base URL
public_url()                // Get public directory URL
get_environment()           // Current environment type
is_localhost_environment()  // Check if localhost
is_production()             // Check if production
is_development()            // Check if development
get_protocol()              // Get http/https
smart_redirect()            // Environment-aware redirect
setup_url()                 // Setup wizard URL
login_url()                 // Login page URL
logout_url()                // Logout page URL
home_url()                  // Dashboard URL
```

## 🧪 Testing & Verification

### Check Your Environment

**Option 1: Environment Check Page**
```
http://localhost/KaryalayERP/check_environment.php
```

Shows:
- Auto-detected values
- Active configuration
- Environment type
- URL generation tests
- Suggested .env content

**Option 2: Configuration Validator**
```
http://localhost/KaryalayERP/validate_config.php
```

## 📝 .env Configuration (Optional)

You can still use .env for explicit control:

```bash
# Explicit Configuration (takes priority over auto-detection)
APP_URL=https://yourdomain.com/erp

# Or leave empty for auto-detection
APP_URL=

# Environment type (auto-detected if not set)
ENVIRONMENT=production

# Debug mode (auto-detected based on environment if not set)
DEBUG_MODE=false
```

## 🎯 Benefits of Auto-Detection

### 1. **Zero Configuration Deployment**
```bash
# Old way:
1. Deploy code
2. Edit .env file
3. Update APP_URL
4. Test links
5. Fix broken URLs

# New way:
1. Deploy code
2. Done! ✓
```

### 2. **Multi-Environment Support**
```bash
# Same code works everywhere:
✓ http://localhost/KaryalayERP           # Development
✓ http://localhost:8080/myapp            # Custom port
✓ https://staging.domain.com/erp         # Staging
✓ https://yourdomain.com/erp             # Production subdirectory
✓ https://erp.yourdomain.com             # Production subdomain
```

### 3. **No Hardcoded Values**
```php
// Everything adapts automatically:
url('login.php')           // Works in any environment
asset('assets/icon.png')   // Correct path always
redirect('index.php')      // Smart redirect
```

### 4. **Developer Friendly**
```bash
# Each developer can have different setup:
Developer A: http://localhost/KaryalayERP
Developer B: http://localhost:8000/erp
Developer C: http://192.168.1.100/app

# All work without configuration!
```

## 🔍 Detection Algorithm Details

### Step 1: Protocol Detection
```php
IF (HTTPS is set AND not 'off')
    → Use https
ELSE IF (X-Forwarded-Proto header is 'https')
    → Use https (proxy/load balancer)
ELSE IF (X-Forwarded-SSL is 'on')
    → Use https
ELSE IF (Port is 443)
    → Use https
ELSE
    → Use http
```

### Step 2: Base Path Detection
```php
1. Get SCRIPT_NAME (e.g., /KaryalayERP/public/index.php)
2. Extract directory (e.g., /KaryalayERP/public)
3. Remove /public suffix (e.g., /KaryalayERP)
4. This is the base path
```

### Step 3: Environment Type Detection
```php
IF (Server is localhost OR 127.0.0.1 OR ::1)
    → Environment = 'development'
ELSE IF (Domain contains 'staging' OR 'test' OR 'dev' OR 'demo')
    → Environment = 'staging'
ELSE
    → Environment = 'production'
```

### Step 4: URL Construction
```php
URL = protocol + '://' + server_name
IF (port is not standard 80/443)
    URL += ':' + port
URL += base_path

Example:
https + :// + yourdomain.com + /erp
= https://yourdomain.com/erp
```

## 🎓 Advanced Usage

### Override Auto-Detection

Set explicit value in .env:
```bash
APP_URL=https://myspecific.url.com/path
```

### Check Detection Status
```php
// In your PHP code:
if (defined('SERVER_AUTO_DETECTED') && SERVER_AUTO_DETECTED) {
    echo "Using auto-detection";
} else {
    echo "Using .env configuration";
}
```

### Get Detection Info
```php
$info = ServerDetector::detect();
echo $info['base_url'];        // Detected URL
echo $info['is_localhost'];    // true/false
echo $info['environment'];     // development/staging/production
echo $info['has_subdirectory']; // true/false
```

### Generate .env Suggestion
```php
$suggested_config = ServerDetector::suggestEnvConfig();
// Returns complete .env file content based on detection
```

## 🚨 Troubleshooting

### Issue: Wrong URL Generated

**Check:**
1. Visit `check_environment.php`
2. Review "Server Detection Results"
3. Verify detected values are correct

**Fix Options:**
- Set explicit APP_URL in .env if detection is wrong
- Check web server configuration
- Verify reverse proxy headers if behind proxy

### Issue: URLs Don't Work After Deployment

**Likely Cause:** Reverse proxy or load balancer

**Solution:**
```bash
# In .env, set explicit URL:
APP_URL=https://yourdomain.com/erp
```

Or configure proxy to send correct headers:
```
X-Forwarded-Proto: https
X-Forwarded-Host: yourdomain.com
```

### Issue: Localhost Detection on Production

**Check:**
- Is `SERVER_NAME` set correctly?
- Is server configured with proper domain?

**Quick Fix:**
```bash
# Force production mode in .env:
ENVIRONMENT=production
APP_URL=https://yourdomain.com
```

## 📊 Comparison: Before vs After

### Before Auto-Detection
```
❌ Manual .env configuration required
❌ Different .env for each environment
❌ Hardcoded fallback values
❌ Deployment requires configuration
❌ Developer-specific setup needed
```

### After Auto-Detection
```
✅ Zero configuration out of the box
✅ Same code, any environment
✅ Smart detection with fallback
✅ Deploy and run immediately
✅ Works for every developer
```

## 🎉 Conclusion

Auto-detection makes KaryalayERP truly environment-agnostic:

1. **Deploy anywhere** without configuration
2. **Works immediately** on any server
3. **Smart fallback** when .env is missing
4. **Manual override** still available
5. **Developer friendly** - no setup conflicts

The system uses intelligent if/else logic to:
- Check .env first (manual control)
- Auto-detect if missing (convenience)
- Adapt to any environment (flexibility)
- Generate correct URLs (reliability)

**Result:** Deploy once, run anywhere! 🚀

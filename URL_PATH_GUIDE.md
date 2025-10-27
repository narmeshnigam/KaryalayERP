# URL and Path Management Guide

## ⚠️ Problem: Hardcoded Paths

**DO NOT DO THIS:**
```php
// ❌ Hardcoded localhost URL
header('Location: http://localhost/KaryalayERP/public/login.php');

// ❌ Hardcoded absolute path from root
<a href="/public/employee/index.php">Employees</a>

// ❌ Assumes specific directory structure
window.location.href = 'http://localhost/KaryalayERP/public/index.php';
```

**Why it's a problem:**
- Breaks when deployed to production (different domain/path)
- Breaks when app is in a different directory
- Hardcoded "localhost" doesn't work on live servers
- Difficult to maintain and change

## ✅ Solution: Dynamic URL Generation

### 1. Use Relative Paths (Simple & Recommended)

For links and redirects within the same folder or nearby:

```php
// ✅ Relative paths (recommended for most cases)
header('Location: login.php');           // Same folder
header('Location: ../login.php');        // Parent folder
header('Location: employee/index.php');  // Subfolder
header('Location: ../../login.php');     // Two levels up

// ✅ In HTML
<a href="index.php">Dashboard</a>
<a href="../login.php">Login</a>
<a href="employee/view.php?id=5">View Employee</a>
```

**When to use:** Always prefer this for navigation within related pages.

### 2. Use APP_URL Constant (For Absolute URLs)

When you need full URLs (emails, redirects from different modules, etc.):

```php
// ✅ Using APP_URL constant (automatically loaded from .env)
header('Location: ' . APP_URL . '/public/login.php');
header('Location: ' . APP_URL . '/setup/index.php');

// ✅ In HTML
<a href="<?php echo APP_URL; ?>/public/index.php">Dashboard</a>
```

**When to use:**
- Cross-module navigation
- Setup wizard redirects
- Email notifications with links
- API responses with URLs

### 3. Use URL Helper Functions (Best Practice)

We've created helper functions in `includes/url_helper.php`:

```php
// Include the helper (or include it in bootstrap.php)
require_once __DIR__ . '/includes/url_helper.php';

// ✅ Generate URLs
$login_url = url('login.php');
// Result: http://yoursite.com/KaryalayERP/public/login.php

$employee_url = url('employee/view.php', ['id' => 5]);
// Result: http://yoursite.com/KaryalayERP/public/employee/view.php?id=5

// ✅ Generate asset URLs
$icon_url = asset('assets/icons/dashboard.png');
// Result: http://yoursite.com/KaryalayERP/assets/icons/dashboard.png

// ✅ Redirect helpers
redirect('login.php');                        // Simple redirect
redirect('employee/view.php', ['id' => 5]);   // With parameters
redirect_back('index.php');                   // Go back or fallback

// ✅ Current page helpers
if (is_page('index.php')) {
    // We're on index.php
}

if (is_path('/employee/')) {
    // We're in employee section
}
```

## 📋 Common Patterns & Solutions

### Pattern 1: Navigation Links (Sidebar, Menu)

**❌ Wrong:**
```php
<a href="/public/employee/index.php">Employees</a>
<a href="http://localhost/KaryalayERP/public/employee/index.php">Employees</a>
```

**✅ Correct:**
```php
// Option A: Using APP_URL (used in sidebar.php)
<a href="<?php echo APP_URL; ?>/public/employee/index.php">Employees</a>

// Option B: Using helper
<a href="<?php echo url('employee/index.php'); ?>">Employees</a>

// Option C: Relative (if same level)
<a href="employee/index.php">Employees</a>
```

### Pattern 2: Form Actions

**❌ Wrong:**
```php
<form action="/public/api/save.php" method="post">
```

**✅ Correct:**
```php
<!-- Relative path (preferred) -->
<form action="api/save.php" method="post">

<!-- Or same page submission -->
<form action="" method="post">

<!-- Or using helper -->
<form action="<?php echo url('api/save.php'); ?>" method="post">
```

### Pattern 3: Header Redirects

**❌ Wrong:**
```php
header('Location: http://localhost/KaryalayERP/public/login.php');
header('Location: /public/login.php');
```

**✅ Correct:**
```php
// Option A: Relative path (best for most cases)
header('Location: login.php');
header('Location: ../login.php');

// Option B: Using APP_URL (for cross-module)
header('Location: ' . APP_URL . '/public/login.php');

// Option C: Using helper
redirect('login.php');
redirect('employee/view.php', ['id' => 5]);
```

### Pattern 4: JavaScript Redirects

**❌ Wrong:**
```javascript
window.location.href = 'http://localhost/KaryalayERP/public/index.php';
window.location.href = '/public/index.php';
```

**✅ Correct:**
```javascript
// Relative path
window.location.href = 'index.php';
window.location.href = '../public/index.php';

// Or pass from PHP
window.location.href = '<?php echo url("index.php"); ?>';
```

### Pattern 5: Image/Asset URLs

**❌ Wrong:**
```php
<img src="/assets/icons/dashboard.png">
<img src="http://localhost/KaryalayERP/assets/icons/dashboard.png">
```

**✅ Correct:**
```php
<!-- Relative path (if nearby) -->
<img src="../assets/icons/dashboard.png">

<!-- Using APP_URL -->
<img src="<?php echo APP_URL; ?>/assets/icons/dashboard.png">

<!-- Using helper -->
<img src="<?php echo asset('assets/icons/dashboard.png'); ?>">
```

### Pattern 6: Setup Wizard Redirects

**❌ Wrong:**
```php
header('Location: http://localhost/KaryalayERP/setup/create_tables.php');
```

**✅ Correct:**
```php
// Always use APP_URL for setup wizard (cross-module)
header('Location: ' . APP_URL . '/setup/create_tables.php');

// Or relative if within setup folder
header('Location: create_tables.php');
```

## 🎯 Decision Tree: Which Method to Use?

```
Need a URL?
│
├─ Is it in the same folder?
│  └─ Use relative: "page.php"
│
├─ Is it in a nearby folder?
│  └─ Use relative: "../folder/page.php"
│
├─ Is it cross-module or for email?
│  └─ Use APP_URL: APP_URL . '/public/page.php'
│
└─ Want best practice with params?
   └─ Use helper: url('page.php', ['id' => 5])
```

## 🔧 Migration Guide

### Step 1: Find Hardcoded URLs

Search your codebase for:
- `http://localhost/KaryalayERP`
- `href="/public/`
- `action="/public/`
- `Location: /`

### Step 2: Replace Based on Context

**In PHP navigation/redirects:**
```php
// Before
header('Location: http://localhost/KaryalayERP/public/login.php');

// After (relative)
header('Location: login.php');

// Or (APP_URL)
header('Location: ' . APP_URL . '/public/login.php');
```

**In HTML attributes:**
```html
<!-- Before -->
<a href="/public/employee/index.php">Employees</a>

<!-- After (relative) -->
<a href="employee/index.php">Employees</a>

<!-- Or (APP_URL) -->
<a href="<?php echo APP_URL; ?>/public/employee/index.php">Employees</a>
```

**In JavaScript:**
```javascript
// Before
window.location.href = 'http://localhost/KaryalayERP/public/index.php';

// After
window.location.href = 'index.php'; // or '../public/index.php' based on location
```

### Step 3: Test Different Environments

After changes, test with different APP_URL values in `.env`:

```bash
# Local development
APP_URL=http://localhost/KaryalayERP

# Local with different port
APP_URL=http://localhost:8080/myerp

# Production
APP_URL=https://yourdomain.com/erp

# Production without subfolder
APP_URL=https://erp.yourdomain.com
```

## 📝 Checklist for New Code

When writing new code, ensure:

- [ ] No hardcoded `localhost` URLs
- [ ] No hardcoded `/public/` absolute paths
- [ ] Use relative paths for same-level navigation
- [ ] Use APP_URL for cross-module or external needs
- [ ] JavaScript redirects use relative or PHP-generated URLs
- [ ] Form actions use relative paths
- [ ] Asset URLs are relative or use APP_URL/asset()
- [ ] Tested with different APP_URL configurations

## 🚀 Using URL Helpers in Your Code

### Include the Helper

**Option A: Include in specific file**
```php
require_once __DIR__ . '/includes/url_helper.php';
```

**Option B: Include in bootstrap.php (recommended)**
```php
// In includes/bootstrap.php
require_once __DIR__ . '/url_helper.php';
```

### Available Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `url($path, $params)` | Generate full URL | `url('login.php')` |
| `asset($path)` | Generate asset URL | `asset('assets/icon.png')` |
| `redirect($path, $params)` | Redirect to page | `redirect('login.php')` |
| `redirect_back($fallback)` | Go back or fallback | `redirect_back('index.php')` |
| `current_page()` | Get current filename | `if (current_page() == 'index.php')` |
| `is_page($page)` | Check current page | `is_page('index.php')` |
| `is_path($part)` | Check path contains | `is_path('/employee/')` |
| `back_url($fallback)` | Get back URL | `$back = back_url('index.php')` |

## 🎓 Examples

### Example 1: Redirect After Form Submit
```php
// After saving employee
if ($saved) {
    $_SESSION['success'] = 'Employee saved!';
    redirect('employee/index.php');
}
```

### Example 2: Navigation Menu
```php
<nav>
    <a href="<?php echo url('index.php'); ?>" 
       class="<?php echo is_page('index.php') ? 'active' : ''; ?>">
        Dashboard
    </a>
    <a href="<?php echo url('employee/index.php'); ?>"
       class="<?php echo is_path('/employee/') ? 'active' : ''; ?>">
        Employees
    </a>
</nav>
```

### Example 3: Back Button
```php
<a href="<?php echo back_url('index.php'); ?>" class="btn">
    ← Back
</a>
```

### Example 4: Pagination
```php
<a href="<?php echo pagination_url($page + 1); ?>">Next Page</a>
```

## 🔒 Security Note

When generating URLs with user input, always sanitize:

```php
// ✅ Good
$id = (int)$_GET['id'];
$url = url('view.php', ['id' => $id]);

// ✅ Good
$search = htmlspecialchars($_GET['search']);
$url = url('search.php', ['q' => $search]);
```

## 📚 Additional Resources

- See `includes/url_helper.php` for full function documentation
- Check `includes/sidebar.php` for APP_URL usage examples
- Review `config/config.php` for APP_URL configuration

---

**Remember:** The goal is to make your application work seamlessly regardless of where it's deployed - localhost, subdirectory, subdomain, or root domain!

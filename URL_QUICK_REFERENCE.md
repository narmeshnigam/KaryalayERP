# URL Management Quick Reference Card

## 🚫 Never Do This
```php
❌ header('Location: http://localhost/KaryalayERP/public/login.php');
❌ <a href="/public/employee/index.php">Employees</a>
❌ window.location.href = 'http://localhost/KaryalayERP/...';
```

## ✅ Always Do This

### Same Folder Navigation
```php
✅ header('Location: login.php');
✅ <a href="index.php">Dashboard</a>
✅ window.location.href = 'index.php';
```

### Parent/Child Folders
```php
✅ header('Location: ../login.php');              // Parent folder
✅ header('Location: ../../login.php');           // Two levels up
✅ header('Location: employee/index.php');        // Subfolder
✅ <a href="../public/index.php">Home</a>
```

### Cross-Module (Use APP_URL)
```php
✅ header('Location: ' . APP_URL . '/public/login.php');
✅ header('Location: ' . APP_URL . '/setup/index.php');
✅ <a href="<?php echo APP_URL; ?>/public/index.php">Dashboard</a>
```

### With URL Helper (Best Practice)
```php
✅ redirect('login.php');                          // Simple redirect
✅ redirect('employee/view.php', ['id' => 5]);    // With params
✅ $url = url('employee/index.php');               // Generate URL
✅ $asset = asset('assets/icons/dashboard.png');   // Asset URL
✅ redirect_back('index.php');                     // Go back or fallback
```

## 🎯 Decision Tree

```
Need a URL?
├─ Same folder? → Use "page.php"
├─ Nearby folder? → Use "../folder/page.php"
├─ Cross-module? → Use APP_URL . '/public/page.php'
└─ Want helpers? → Use url('page.php') or redirect('page.php')
```

## 📚 Helper Functions Reference

| Function | Use Case | Example |
|----------|----------|---------|
| `url($path, $params)` | Generate URL | `url('login.php')` |
| `asset($path)` | Asset URL | `asset('assets/icon.png')` |
| `redirect($path, $params)` | Redirect | `redirect('login.php')` |
| `redirect_back($fallback)` | Go back | `redirect_back('index.php')` |
| `current_page()` | Current file | `if (current_page() == 'index.php')` |
| `is_page($page)` | Check page | `is_page('index.php')` |
| `is_path($part)` | Check path | `is_path('/employee/')` |

## 🔍 Find Issues

Run detection script:
```bash
php detect_hardcoded_paths.php
```

## 📖 Full Documentation

- **Complete Guide**: `URL_PATH_GUIDE.md`
- **Implementation Details**: `PATH_RESOLUTION_SUMMARY.md`
- **Helper Functions**: `includes/url_helper.php`

## ⚙️ Configuration

URLs are controlled by `.env`:
```bash
APP_URL=http://localhost/KaryalayERP   # Development
APP_URL=https://yourdomain.com/erp     # Production
```

Change `.env`, no code changes needed!

## 💡 Remember

**Goal**: Make it work everywhere without code changes - just update `.env`!

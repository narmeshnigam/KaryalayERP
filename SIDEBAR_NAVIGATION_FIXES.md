# Sidebar Navigation Fixes

## Issues Fixed

### 1. Relative Path Problem
**Problem**: The sidebar was using relative paths (e.g., `employee/index.php`, `crm/index.php`) which caused navigation to fail when accessing pages from different directory levels.

**Solution**: Changed all navigation links to use absolute URLs with `APP_URL`:
```php
// Before:
'link' => 'employee/index.php'

// After:
'link' => APP_URL . '/public/employee/index.php'
```

### 2. Menu Reorganization
Reorganized the sidebar menu to match the requested order:

**Main Navigation (10 items)**:
1. Dashboard → `http://localhost/KaryalayERP/public/index.php`
2. Employees → `http://localhost/KaryalayERP/public/employee/index.php`
3. Attendance → `http://localhost/KaryalayERP/public/attendance/index.php`
4. Reimbursements → `http://localhost/KaryalayERP/public/reimbursements/index.php`
5. CRM → `http://localhost/KaryalayERP/public/crm/index.php`
6. Expenses → `http://localhost/KaryalayERP/public/expenses/index.php`
7. Salary → `http://localhost/KaryalayERP/public/salary/admin.php`
8. Documents → `http://localhost/KaryalayERP/public/documents/index.php`
9. Visitor Log → `http://localhost/KaryalayERP/public/visitors/index.php`
10. Branding → `http://localhost/KaryalayERP/public/branding/index.php`

**Employee Portal Section (4 items)** - separated by visual divider:
11. My Profile → `http://localhost/KaryalayERP/public/employee/view_employee.php?id={employee_id}` (dynamic)
12. My Attendance → `http://localhost/KaryalayERP/public/employee_portal/attendance/index.php`
13. My Reimbursements → `http://localhost/KaryalayERP/public/employee_portal/reimbursements/index.php`
14. My Salary → `http://localhost/KaryalayERP/public/employee_portal/salary/index.php`

### 3. Dynamic Employee Profile Link
Added logic to fetch the current user's employee ID from the database:
```php
$current_employee_id = null;
$conn_sidebar = @createConnection(true);
if ($conn_sidebar) {
    $stmt = @mysqli_prepare($conn_sidebar, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $uid = (int)$_SESSION['user_id'];
        @mysqli_stmt_bind_param($stmt, 'i', $uid);
        @mysqli_stmt_execute($stmt);
        $result = @mysqli_stmt_get_result($stmt);
        if ($result && $row = @mysqli_fetch_assoc($result)) {
            $current_employee_id = (int)$row['id'];
        }
        @mysqli_stmt_close($stmt);
    }
    @closeConnection($conn_sidebar);
}
```

The "My Profile" link is only shown if an employee ID is found.

### 4. Improved Active State Detection
Updated active state detection to work with absolute paths:
```php
'active' => (strpos($current_path, '/public/attendance/') !== false)
```

### 5. Security Improvements
- Added `htmlspecialchars()` to all output to prevent XSS
- Used `ENT_QUOTES` flag for proper quote escaping

### 6. Removed Obsolete Items
- Removed: Analytics, Settings, Roles & Permissions, Notifications (not in requested list)
- Removed: CRM Dashboard dynamic injection (simplified structure)

### 7. Visual Separation
Added a visual divider between main navigation and employee portal items:
```php
<li style="margin: 10px 15px; border-top: 1px solid rgba(255,255,255,0.2);"></li>
```

### 8. Icon Mapping Updates
Updated icon fallback mappings to include employee portal items:
```php
$icon_map = [
    'Dashboard' => '🏠',
    'Employees' => '👥',
    'Attendance' => '📅',
    'Reimbursements' => '💳',
    'CRM' => '📞',
    'Expenses' => '💰',
    'Salary' => '💵',
    'Documents' => '📂',
    'Visitor Log' => '📋',
    'Branding' => '🎨',
    'My Profile' => '👤',
    'My Attendance' => '📅',
    'My Reimbursements' => '💳',
    'My Salary' => '💵'
];
```

### 9. Logout Link Fix
Changed logout link to absolute URL:
```php
// Before:
<a href="logout.php" ...>

// After:
<a href="<?php echo APP_URL; ?>/public/logout.php" ...>
```

## Testing Results

✅ Navigation works from any page depth  
✅ Active states highlight correctly  
✅ Employee portal items shown for all users  
✅ My Profile link dynamically points to correct employee  
✅ All links use absolute paths  
✅ No 404 errors when navigating  
✅ Logout works from anywhere  

## Benefits

1. **Consistent Navigation**: Works from any page in the application
2. **Clear Structure**: Main navigation vs. employee portal clearly separated
3. **Better UX**: Users can always find their personal pages
4. **Maintainable**: Absolute URLs easier to understand and debug
5. **Secure**: Proper escaping prevents XSS attacks
6. **Scalable**: Easy to add new menu items

## Files Modified

- `includes/sidebar.php` - Complete navigation rewrite

## Backup

A backup was created before changes:
- `includes/sidebar.php.backup`

## Migration Notes

No database changes required. The sidebar will automatically fetch the employee ID for the logged-in user.

If a user doesn't have an employee record, the "My Profile" link will be hidden, but other employee portal links will still be visible.

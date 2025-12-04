# Salary Module Restructure

## Overview
The salary module has been restructured to separate employee-facing pages from admin pages, following the same pattern as the reimbursements module.

## Changes Made

### 1. New Directory Structure

#### Employee Portal (`public/employee_portal/salary/`)
- `index.php` - Employee salary listing page (view their own salary records)
- `view.php` - Detailed salary record view for employees

#### Admin Section (`public/salary/`)
- `admin.php` - Admin/Manager salary management console
- `view.php` - Admin view of salary records (can view any employee's records)
- `edit.php` - Edit salary records
- `upload.php` - Upload salary slips
- `helpers.php` - Shared helper functions (used by both employee and admin pages)
- `onboarding.php` - Module setup/onboarding page

### 2. Path Updates

#### Employee Portal Files
Both `public/employee_portal/salary/index.php` and `view.php` now use:
- `require_once __DIR__ . '/../../../includes/bootstrap.php'` - Bootstrap
- `require_once __DIR__ . '/../../../config/config.php'` - Config
- `require_once __DIR__ . '/../../../config/db_connect.php'` - Database
- `require_once __DIR__ . '/../../salary/helpers.php'` - Shared helpers
- `require_once __DIR__ . '/../../salary/onboarding.php'` - Onboarding (when needed)

#### Admin Files
Admin files (`public/salary/admin.php`, `view.php`, `edit.php`, `upload.php`) continue using:
- `require_once __DIR__ . '/../../includes/bootstrap.php'`
- `require_once __DIR__ . '/../../config/config.php'`
- `require_once __DIR__ . '/../../config/db_connect.php'`
- `require_once __DIR__ . '/helpers.php'` - Shared helpers

### 3. Navigation Updates

#### Sidebar (`includes/sidebar.php`)
The salary link now routes based on user role:
```php
// Salary link based on role
$salary_link = (in_array(strtolower($user_role), ['admin', 'manager'], true))
    ? 'salary/admin.php'
    : 'employee_portal/salary/index.php';
```

Active state detection updated to recognize both paths:
```php
'active' => (strpos($current_path, '/salary/') !== false) || (strpos($current_path, '/employee_portal/salary/') !== false) || in_array($current_page, ['salary.php'], true)
```

#### Redirect File (`public/salary.php`)
Updated to route users based on their role:
```php
$user_role = $_SESSION['role'] ?? 'employee';
if (in_array(strtolower($user_role), ['admin', 'manager'], true)) {
    header('Location: ' . APP_URL . '/public/salary/admin.php');
} else {
    header('Location: ' . APP_URL . '/public/employee_portal/salary/index.php');
}
```

### 4. Back Links and Redirects

#### Employee Portal View (`public/employee_portal/salary/view.php`)
- Back button routes to `index.php` for employees, `../../salary/admin.php` for managers
- Edit button links to `../../salary/edit.php` for managers
- All error redirects go to appropriate page based on role

#### Admin View (`public/salary/view.php`)
- Back button routes to `admin.php` for managers, `../employee_portal/salary/index.php` for employees
- Edit button links to `edit.php`
- Delete action redirects to `admin.php`
- All error redirects use role-based routing

#### Admin Pages (`public/salary/admin.php`)
- Unauthorized access redirects to `../employee_portal/salary/index.php`

### 5. Role-Based Access Control

Both employee and admin versions properly check:
- **Employee access**: Can only view their own salary records
- **Manager/Admin access**: Can view and manage all salary records
- Redirects to appropriate page if user doesn't have permission

## File Removals

The following file was removed as it's no longer needed:
- `public/salary/index.php` (replaced by employee_portal version and admin.php)

## Benefits

1. **Clear Separation**: Employee and admin functionalities are now clearly separated
2. **Consistent Pattern**: Follows the same structure as reimbursements module
3. **Better Security**: Role-based routing ensures users only access appropriate pages
4. **Maintainability**: Easier to maintain separate employee and admin interfaces
5. **Shared Logic**: Helper functions remain centralized in `salary/helpers.php`

## User Experience

### For Employees
- Click "Salary Viewer" in sidebar → Routes to `employee_portal/salary/index.php`
- See only their own salary records
- Can view details and download salary slips
- Cannot edit or delete records

### For Admins/Managers
- Click "Salary Viewer" in sidebar → Routes to `salary/admin.php`
- See all employees' salary records
- Can filter by employee, month, lock status, uploader
- Can view, edit, delete, lock/unlock records
- Can upload new salary slips

## Testing Checklist

- [ ] Employee can view their salary records at `/employee_portal/salary/`
- [ ] Employee cannot access `/salary/admin.php`
- [ ] Admin/Manager can access salary management at `/salary/admin.php`
- [ ] Admin/Manager can view any employee's salary details
- [ ] Sidebar routes correctly based on user role
- [ ] `/public/salary.php` redirects correctly based on user role
- [ ] All back links work correctly
- [ ] Edit and delete functions work for admins
- [ ] Salary slip downloads work for both employees and admins
- [ ] Lock/unlock functionality works for admins
- [ ] Error messages and redirects work correctly

## Migration Notes

No database changes required. This is purely a file structure reorganization.

Existing salary records, uploads, and functionality remain unchanged.

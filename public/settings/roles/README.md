# Roles & Permissions Module

## Overview
Complete role-based access control (RBAC) system for KaryalayERP that manages user permissions across all modules.

## Module Structure

```
public/settings/roles/
├── index.php           # Main roles listing page
├── add.php             # Create new role
├── edit.php            # Edit existing role
├── delete.php          # Delete role (system roles protected)
├── helpers.php         # Authorization helper functions
├── onboarding.php      # Setup wizard for first-time users
└── setup_handler.php   # API endpoint for running setup
```

## Database Schema

### Tables Created:
1. **roles** - Role definitions (Admin, Manager, Employee, etc.)
2. **permissions** - Page-level permission definitions
3. **role_permissions** - Maps permissions to roles
4. **user_roles** - Assigns roles to users
5. **permission_audit_log** - Tracks permission changes

### Default Roles:
- Super Admin (full access)
- Admin
- Manager
- Employee
- HR Manager
- Accountant
- Sales Executive
- Guest (read-only)

### Default Permissions:
40+ permissions covering:
- Dashboard
- Employees (view, add, edit, delete)
- CRM (leads, calls, meetings, tasks, visits)
- Attendance (view, mark, approve)
- Salary (view, generate, approve)
- Documents (view, upload, delete)
- Visitors (view, register)
- Expenses & Reimbursements
- Settings & Branding

## Features

### Onboarding Process
- Automatic detection of missing tables
- Visual setup wizard with status indicators
- One-click setup via AJAX
- Redirect to roles management after completion

### Permission System
- 6 permission types: view, create, edit, delete, export, approve
- Page-level granular control
- Role-based inheritance
- System role protection (cannot be deleted)

### Helper Functions
```php
// Check if tables exist
roles_tables_exist($conn)

// Get user's roles
get_user_roles($conn, $user_id)

// Check specific permission
has_permission($conn, $user_id, 'page_name', 'view')

// Check multiple permissions
has_any_permission($conn, $user_id, 'page_name', ['view', 'edit'])

// Require permission (redirects if denied)
require_permission($conn, $user_id, 'page_name', 'view')

// Assign role to user
assign_role_to_user($conn, $user_id, $role_id, $assigned_by)

// Log audit trail
log_permission_audit($conn, $user_id, 'ACTION', 'entity_type', $entity_id, $changes)
```

## Navigation

### Sidebar Menu
Location: After "Branding" menu item
- **Label**: Roles & Permissions
- **Icon**: settings.png (or 🔐 emoji fallback)
- **URL**: `/public/settings/roles/index.php`
- **Active**: Highlights when in `/settings/roles/` or `/settings/permissions/`

## Setup Instructions

### First Time Access:
1. Click "Roles & Permissions" in sidebar
2. Onboarding page appears automatically
3. Review what will be created
4. Click "Run Setup Script"
5. Wait for completion (5-10 seconds)
6. Redirected to roles management

### Manual Setup (via terminal):
```bash
php scripts/setup_roles_permissions_tables.php
```

## Usage Examples

### Protecting a Page:
```php
require_once __DIR__ . '/path/to/helpers.php';

// Basic protection
require_permission($conn, $user_id, 'crm/leads', 'view');

// Check without redirect
if (has_permission($conn, $user_id, 'crm/leads', 'edit')) {
    // Show edit button
}

// Check multiple permissions
if (has_any_permission($conn, $user_id, 'crm/leads', ['edit', 'delete'])) {
    // Show actions
}
```

### Assigning Roles:
```php
// Assign role to user
assign_role_to_user($conn, $new_user_id, $employee_role_id, $admin_user_id);
```

## Security Features

- System roles cannot be deleted
- Permission checks on every page
- Audit logging for all changes
- Graceful fallback when tables don't exist
- Unauthorized page with 403 status
- Session-based authentication required

## Future Enhancements

- [ ] Permissions matrix UI (visual grid)
- [ ] User-role assignment interface
- [ ] Role cloning functionality
- [ ] Bulk permission updates
- [ ] Permission groups/categories
- [ ] API endpoints for CRUD operations
- [ ] Export/import role configurations

## Troubleshooting

### Tables Not Created:
- Check MySQL user has CREATE TABLE privileges
- Verify database exists in config.php
- Review setup script output for errors

### Permission Denied Errors:
- Ensure user has role assigned in user_roles table
- Check role status is 'Active'
- Verify permission exists in permissions table
- Check role_permissions mapping

### Onboarding Loop:
- Verify all 5 tables created successfully
- Run: `SHOW TABLES LIKE '%role%'` in MySQL
- Check for partial table creation

## Files Modified

- `includes/sidebar.php` - Added navigation menu item
- `public/settings/roles/helpers.php` - Added table existence checks
- `public/settings/roles/index.php` - Added onboarding redirect
- `scripts/setup_roles_permissions_tables.php` - Fixed BOOLEAN to TINYINT(1)

## Setup Script Location
`scripts/setup_roles_permissions_tables.php`

Creates all tables, inserts default roles, permissions, and assigns Super Admin full access.

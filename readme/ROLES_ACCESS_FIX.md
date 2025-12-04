# Roles & Permissions Access Fix

## Problem
Users were getting redirected to `/public/unauthorized.php` when accessing `/public/settings/roles/index.php` because they had no roles assigned yet after initial setup.

## Solution Implemented

### 1. Updated `helpers.php` - `require_permission()` Function
Added logic to allow access to roles management pages during initial setup:

```php
function require_permission($conn, $user_id, $page_name, $permission_type = 'view', $fallback_url = null) {
    // If tables don't exist, allow access (setup mode)
    if (!roles_tables_exist($conn)) {
        return;
    }
    
    // Check if user has any roles assigned
    $user_roles = get_user_roles($conn, $user_id);
    
    // If no roles assigned yet, allow access to roles management pages for setup
    if (empty($user_roles) && strpos($page_name, 'settings/roles') !== false) {
        return;
    }
    
    // ... rest of permission checking
}
```

**Key Changes:**
- Tables don't exist → Allow access (setup mode)
- User has no roles + accessing roles pages → Allow access (initial setup)
- Otherwise → Check permissions normally

### 2. Enhanced `index.php` - Added Setup Notice
Added prominent warning banner when user has no roles assigned:

```php
// Check if current user has any roles assigned
$current_user_roles = get_user_roles($conn, $user_id);
$show_setup_notice = empty($current_user_roles);
```

**Warning Banner:**
- Shows at top of page when `$show_setup_notice` is true
- Yellow background with warning icon
- Explains that no roles are assigned
- Links to quick assignment section below

### 3. Added Quick Role Assignment Section
Created inline form at bottom of roles index page:

**Features:**
- Only shows when user has no roles
- Dropdown with all active roles
- Pre-selects "Super Admin" by default
- One-click assignment button
- Scrolls smoothly from warning banner

### 4. Created `quick_assign.php` Handler
New file to process role self-assignment:

**Functionality:**
- Validates role exists and is active
- Checks for duplicate assignments
- Inserts into `user_roles` table
- Logs audit trail with `log_permission_audit()`
- Sets success/error messages in session
- Redirects back to index

**Security:**
- POST only
- Session validation
- SQL injection prevention with prepared statements
- Role validation before assignment

### 5. Enhanced Message Display
Updated index.php to show session-based messages:

```php
// Session messages from quick_assign.php
$_SESSION['success_message'] // Green alert
$_SESSION['error_message']   // Red alert
$_SESSION['info_message']    // Blue alert
```

Messages auto-clear after display using `unset()`.

## User Flow

### First Access (No Roles Assigned):
1. User clicks "Roles & Permissions" in sidebar
2. Loads index.php successfully (no redirect)
3. Yellow warning banner appears at top
4. Roles list displays normally
5. Scroll to bottom → Assignment form visible
6. Select "Super Admin" (pre-selected)
7. Click "Assign Role to My Account"
8. Redirects back with success message
9. Warning banner disappears
10. Full permissions now active

### Subsequent Access (Roles Assigned):
1. User clicks "Roles & Permissions"
2. Loads index.php with normal permissions
3. No warning banner
4. No assignment section
5. Full CRUD operations available

## Files Modified

1. **public/settings/roles/helpers.php**
   - Updated `require_permission()` function
   - Added setup mode detection

2. **public/settings/roles/index.php**
   - Added `$show_setup_notice` check
   - Added warning banner HTML
   - Added quick assignment form
   - Enhanced message display

3. **public/settings/roles/quick_assign.php** (NEW)
   - Handles self role assignment
   - Validates and inserts user_roles
   - Logs audit trail
   - Sets session messages

## Testing

```bash
# Test the flow
1. Drop roles tables: php scripts/drop_roles_tables.php
2. Access: http://localhost/KaryalayERP/public/settings/roles/
3. Should redirect to onboarding
4. Run setup
5. Access index.php again
6. Should see warning banner (no roles)
7. Select Super Admin and assign
8. Warning disappears, full access granted
```

## Security Considerations

✅ Only allows self-assignment to roles management pages
✅ Other modules still require proper role assignment
✅ Setup mode only active when tables missing
✅ All assignments logged in audit trail
✅ SQL injection prevented with prepared statements
✅ Role validation before assignment

## Benefits

1. **No More Lockouts** - Users can always access roles pages to fix permissions
2. **Self-Service Setup** - Admin can assign their own role without database access
3. **Clear Guidance** - Warning banners explain what needs to be done
4. **Audit Trail** - All self-assignments logged for security review
5. **Smooth UX** - One-click solution with helpful defaults

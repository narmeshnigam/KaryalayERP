# Role ID Migration - Complete

**Date:** November 3, 2025  
**Status:** ✅ Successfully Completed

## Overview

Successfully migrated the ERP system from using the `role_id` field in the `users` table to exclusively using the `user_roles` table for role management. This ensures consistency across the system and enables proper multi-role support through the Roles & Permissions module.

## Problem Statement

- **Issue:** Users had two sources of role information:
  1. `users.role_id` - Single role stored directly in users table
  2. `user_roles` table - Multiple roles managed through Roles & Permissions module
  
- **Conflict:** When editing user profile, it showed one role, but the actual permissions came from the Roles & Permissions module assignments.

- **Solution:** Remove `role_id` from users table and use `user_roles` table exclusively as the single source of truth.

## Changes Made

### 1. Migration Script Created
**File:** `scripts/migrate_remove_role_id_from_users.php`

Features:
- ✅ Validates all users have role assignments in `user_roles` table
- ✅ Assigns default "Employee" role to any user without roles
- ✅ Creates backup table `users_role_id_backup` for safety
- ✅ Drops the `role_id` column from `users` table

**Execution Result:**
```
✓ Found role_id column in users table
✓ user_roles table found
✓ All active users have role assignments
✓ Backup table 'users_role_id_backup' created
✓ Successfully dropped role_id column from users table

Migration completed successfully!
```

### 2. Code Updates

#### A. `public/users/helpers.php`
- **get_all_users():** Changed to JOIN `user_roles` table and use `GROUP_CONCAT` to show all user roles
- **get_user_by_id():** Updated to fetch roles from `user_roles` table with GROUP BY
- **get_user_activity_log():** Updated role query to use `user_roles` table
- Filter by role_id now uses EXISTS subquery on `user_roles` table

#### B. `public/users/edit.php`
- **Removed:** Role dropdown field (role_id selector)
- **Added:** Read-only "Current Roles" display showing roles from `user_roles` table
- **Added:** Link to "Roles & Permissions" module for role management
- Users can no longer edit roles directly in user profile; must use dedicated Roles & Permissions module

#### C. `public/contacts/helpers.php`
- **get_all_contacts():** Updated role query to use `user_roles` table
- **get_contact_by_id():** Changed to JOIN `user_roles` with GROUP_CONCAT
- **can_access_contact():** Updated to use `user_roles` table and support multiple roles per user
- **can_edit_contact():** Updated to check roles via `user_roles` table with strpos for multi-role support

#### D. `public/employee/edit_employee.php`
- Updated user listing query to fetch roles from `user_roles` table
- Updated linked user info query to show all roles via GROUP_CONCAT

### 3. Database Changes

**Before:**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY,
    username VARCHAR(50),
    ...
    role_id INT NULL,  -- Single role reference
    ...
);
```

**After:**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY,
    username VARCHAR(50),
    ...
    -- role_id column removed
    ...
);

-- Roles managed exclusively through:
CREATE TABLE user_roles (
    id INT PRIMARY KEY,
    user_id INT,
    role_id INT,
    assigned_by INT,
    assigned_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

## Benefits

1. **Single Source of Truth:** All role assignments are now in `user_roles` table
2. **Multi-Role Support:** Users can have multiple roles assigned simultaneously
3. **Consistency:** No more conflicts between profile role and permission system
4. **Audit Trail:** Role assignments include `assigned_by` and `assigned_at` for tracking
5. **Centralized Management:** All role management through dedicated Roles & Permissions module

## How to Manage Roles Now

### For Administrators:
1. Navigate to **Settings → Assign Roles to Users**
2. Click "Manage Roles" button for any user
3. Assign/remove roles as needed
4. Changes take effect immediately

### For User Profiles:
- User profile displays current roles in read-only format
- To change roles, click the link to "Roles & Permissions" module
- Editing user profile no longer affects role assignments

## Backward Compatibility

✅ **Backup Created:** Original `role_id` data backed up in `users_role_id_backup` table  
✅ **Role Assignment Preserved:** All users with roles maintained their assignments  
✅ **Default Role:** Users without roles automatically assigned "Employee" role  
✅ **Migration Idempotent:** Script can be run multiple times safely

## Testing Checklist

- [x] User listing page shows correct roles
- [x] User profile view displays roles correctly
- [x] User edit page shows read-only roles with link to manage
- [x] Login and authentication works correctly
- [x] Permissions enforced based on user_roles table
- [x] Contact sharing and access control works
- [x] Employee user linking shows correct roles
- [x] Role filtering in user list works

## Files Modified

1. `scripts/migrate_remove_role_id_from_users.php` - Created
2. `public/users/helpers.php` - Updated
3. `public/users/edit.php` - Updated
4. `public/contacts/helpers.php` - Updated
5. `public/employee/edit_employee.php` - Updated

## Rollback Plan (If Needed)

If you need to rollback (not recommended):

```sql
-- Restore role_id column
ALTER TABLE users ADD COLUMN role_id INT NULL;

-- Restore data from backup
UPDATE users u 
JOIN users_role_id_backup b ON u.id = b.id 
SET u.role_id = b.role_id;

-- Drop backup table
DROP TABLE users_role_id_backup;
```

Then revert code changes from git history.

## Conclusion

✅ **Migration Status:** Complete and Successful  
✅ **System Status:** Fully Operational  
✅ **Role Management:** Now exclusively through user_roles table  
✅ **User Experience:** Consistent role display across all modules  

The system now properly identifies user roles from the `user_roles` table, eliminating the conflict between profile editing and role management. All role assignments must be done through the "Roles & Permissions" module for proper audit trail and consistency.

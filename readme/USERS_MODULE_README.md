# 👥 Users Management Module - Karyalay ERP

## Overview
The Users Management Module enables administrators to manage all user accounts, assign system roles, and maintain access control integrity across the Karyalay ERP system. It serves as the bridge between the Roles & Permissions Module and various user types (Employees, Clients, or External Users).

---

## ✨ Features

### Admin Features
- ✅ Create new user accounts manually or from existing employee/client records
- ✅ Assign or modify user roles defined in the Roles & Permissions module
- ✅ Activate, deactivate, or suspend user access
- ✅ Reset passwords or send password reset links
- ✅ Monitor login activity and last login timestamps
- ✅ Filter users by role, user type, or status
- ✅ View comprehensive user activity logs

### Manager Features
- 👁️ View user list within their department (read-only)
- 📝 Recommend role changes (request sent to Admin)

### Employee/Client Features
- 👤 View their own profile and assigned role
- 🔑 Change password (with old-password verification)
- 📊 View personal login activity

---

## 📂 Module Structure

```
public/
├── users/
│   ├── index.php              # Main users listing with filters
│   ├── add.php                # Create new user
│   ├── edit.php               # Edit existing user
│   ├── view.php               # View user profile & activity
│   ├── delete.php             # Delete user (POST only)
│   ├── my-account.php         # Personal profile & password change
│   ├── activity-log.php       # System-wide activity log
│   └── helpers.php            # Core helper functions
└── api/
    └── users/
        └── index.php          # RESTful API endpoints

scripts/
├── migrate_users_table.php           # Update existing users table schema
├── setup_user_activity_log.php      # Create activity log table
└── check_users_table.php            # Verify table structure
```

---

## 🗄️ Database Schema

### Table: `users`
| Field | Type | Description |
|-------|------|-------------|
| id | INT, PK, AI | Unique user ID |
| entity_id | INT, FK NULL | ID of linked entity (employee/client) |
| entity_type | ENUM('Employee','Client','Other') | User entity type |
| username | VARCHAR(100) | Unique login username |
| email | VARCHAR(150) | Email for notifications |
| phone | VARCHAR(20) NULL | Contact number |
| password_hash | VARCHAR(255) | Encrypted password |
| role_id | INT, FK | Linked to `roles.id` |
| status | ENUM('Active','Inactive','Suspended') | Account status |
| last_login | DATETIME NULL | Last successful login |
| created_by | INT, FK | Admin who created account |
| created_at | TIMESTAMP | Record creation timestamp |
| updated_at | TIMESTAMP NULL | Record update timestamp |

### Table: `user_activity_log`
| Field | Type | Description |
|-------|------|-------------|
| id | INT, PK, AI | Unique log ID |
| user_id | INT, FK | Linked user ID |
| ip_address | VARCHAR(45) | IP address at login |
| device | VARCHAR(150) NULL | Device/browser info |
| login_time | DATETIME | Login timestamp |
| logout_time | DATETIME NULL | Logout timestamp |
| status | ENUM('Success','Failed') | Login status |
| failure_reason | VARCHAR(255) NULL | Reason for failure |
| created_at | TIMESTAMP | Log creation timestamp |

---

## 🚀 Installation & Setup

### Step 1: Run Migration Script
Update the existing `users` table to the new schema:

```bash
php scripts/migrate_users_table.php
```

**What it does:**
- Adds new columns: `entity_id`, `entity_type`, `phone`, `role_id`, `status`, `last_login`, `created_by`
- Renames `password` to `password_hash`
- Migrates existing data (role enum → role_id)
- Adds necessary indexes
- Preserves legacy columns for safety

### Step 2: Create Activity Log Table
Set up the user activity tracking table:

```bash
php scripts/setup_user_activity_log.php
```

**What it does:**
- Creates `user_activity_log` table
- Sets up foreign key relationships
- Adds indexes for performance

### Step 3: Verify Setup
Check that tables are properly configured:

```bash
php scripts/check_users_table.php
```

---

## 📋 Page Routes

| Page | URL | Description | Access |
|------|-----|-------------|--------|
| Users List | `/public/users/index.php` | List and filter all users | Admin |
| Add User | `/public/users/add.php` | Create new user | Admin |
| Edit User | `/public/users/edit.php?id={id}` | Modify user details | Admin |
| View Profile | `/public/users/view.php?id={id}` | View user info & activity | Admin, Manager |
| My Account | `/public/users/my-account.php` | Personal profile | All Users |
| Activity Log | `/public/users/activity-log.php` | System-wide activity | Admin |

---

## 🔌 API Endpoints

Base URL: `/public/api/users/index.php`

### List Users
```http
GET /public/api/users/index.php?action=list
GET /public/api/users/index.php?action=list&status=Active&role_id=1
```

### Get User Details
```http
GET /public/api/users/index.php?action=get&id=123
```

### Create User
```http
POST /public/api/users/index.php?action=create
Content-Type: application/json

{
  "username": "john.doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "secure123",
  "role_id": 2,
  "status": "Active",
  "entity_type": "Employee",
  "entity_id": 45
}
```

### Update User
```http
POST /public/api/users/index.php?action=update
Content-Type: application/json

{
  "id": 123,
  "username": "john.doe",
  "email": "john.new@example.com",
  "role_id": 3,
  "status": "Active"
}
```

### Reset Password
```http
POST /public/api/users/index.php?action=reset-password
Content-Type: application/json

{
  "id": 123,
  "password": "newpassword123"
}
```

### Delete User
```http
POST /public/api/users/index.php?action=delete
Content-Type: application/json

{
  "id": 123
}
```

### Get Activity Log
```http
GET /public/api/users/index.php?action=activity-log
GET /public/api/users/index.php?action=activity-log&user_id=123&limit=50
```

### Get Statistics
```http
GET /public/api/users/index.php?action=statistics
```

### Get Available Employees
```http
GET /public/api/users/index.php?action=available-employees
```

### Get Active Roles
```http
GET /public/api/users/index.php?action=roles
```

---

## 🔐 Helper Functions

### User Management
```php
get_all_users($conn, $filters)           // Fetch all users with filters
get_user_by_id($conn, $user_id)          // Get specific user
get_user_by_username($conn, $username)   // Find user by username
create_user($conn, $data)                // Create new user
update_user($conn, $user_id, $data)      // Update user info
delete_user($conn, $user_id)             // Delete user
```

### Validation
```php
username_exists($conn, $username, $exclude_id)
email_exists($conn, $email, $exclude_id)
validate_user_data($data, $is_update)
```

### Security
```php
hash_password($password)                 // Hash password
verify_password($password, $hash)        // Verify password
update_user_password($conn, $user_id, $hash)
```

### Activity Tracking
```php
log_user_activity($conn, $data)          // Log login/logout
get_user_activity_log($conn, $user_id, $limit)
update_last_login($conn, $user_id)
```

### Entity Linking
```php
get_available_employees($conn)           // Get unlinked employees
get_active_roles($conn)                  // Get all active roles
get_user_statistics($conn)               // Get system statistics
```

---

## ⚙️ Functional Rules

1. **Username & Email Uniqueness**: Both must be unique across all entity types
2. **Password Security**: Stored only as hash (bcrypt via `PASSWORD_DEFAULT`)
3. **Entity Linking**: Uses (`entity_id`, `entity_type`) for universal compatibility
4. **Self-Protection**: Cannot deactivate/delete your own logged-in session
5. **Admin Preservation**: At least one Admin user must always remain active
6. **Role Validation**: Role selection dynamically fetched from active roles
7. **Email & Phone Validation**: Format validation applied on input

---

## 🎨 UI Components

### Users List
- Data table with role badges and status icons
- Search bar + filters (Status, Role, Type, Search)
- Statistics cards (Total, Active, Employees, Recent Logins)
- Action buttons (View, Edit, Delete)

### Add/Edit Forms
- Entity type selector with employee auto-linking
- Password strength indicators
- Role dropdown from Roles Module
- Status selector with warnings
- Entity information display (read-only on edit)

### Profile View
- User information card
- Linked entity details (if applicable)
- Activity statistics (logins, failures, last login)
- Recent activity table (last 20 records)

### My Account
- Personal profile information (read-only)
- Password change form with verification
- Recent login activity table
- Role and permissions display

---

## 🔗 Integration Points

| Module | Integration Purpose |
|--------|-------------------|
| **Employees Module** | Link users with employee data |
| **Clients Module (Future)** | Support client logins and access segregation |
| **Roles Module** | Role assignment and permission mapping |
| **Activity Log Module** | Track all login/logout sessions |
| **Sidebar Navigation** | "Users Management" and "My Account" menu items |

---

## 🧪 Testing Checklist

- [ ] Run migration script without errors
- [ ] Create activity log table successfully
- [ ] Create new standalone user
- [ ] Create user linked to employee
- [ ] Edit user details (username, email, phone)
- [ ] Change user role
- [ ] Change user status (Active, Inactive, Suspended)
- [ ] Reset user password
- [ ] Delete user (not self)
- [ ] Access "My Account" page
- [ ] Change own password
- [ ] View user activity log
- [ ] Filter users by status, role, type
- [ ] Search users by username/email/phone
- [ ] Verify sidebar navigation items appear
- [ ] Test API endpoints with Postman/cURL

---

## 🐛 Troubleshooting

### Issue: "Users module tables are not set up properly"
**Solution**: Run the migration and setup scripts:
```bash
php scripts/migrate_users_table.php
php scripts/setup_user_activity_log.php
```

### Issue: "Username already exists"
**Solution**: Choose a unique username. Usernames must be unique across all users.

### Issue: "You cannot deactivate your own account"
**Solution**: This is a safety feature. Ask another admin to change your status.

### Issue: Sidebar menu item not appearing
**Solution**: Clear browser cache and reload. Ensure `includes/sidebar.php` has been updated.

### Issue: Password reset not working
**Solution**: Verify password meets minimum length (6 characters). Check error messages.

---

## 📈 Future Enhancements (Phase 2)

- [ ] Multi-entity authentication (Clients, Vendors, Partners)
- [ ] Two-factor authentication (2FA)
- [ ] User avatar upload
- [ ] Bulk import/export of user accounts
- [ ] Role-based home page redirection
- [ ] Email notifications for account changes
- [ ] Password complexity requirements
- [ ] Session management and force logout
- [ ] IP whitelisting/blacklisting
- [ ] Login attempt rate limiting

---

## 📝 Notes

- **Security**: All passwords are hashed using PHP's `password_hash()` with `PASSWORD_DEFAULT` algorithm
- **Entity Flexibility**: The `entity_type` field allows future expansion to other user types (Clients, Vendors, etc.)
- **Activity Logging**: Every login attempt (success or failure) is recorded for security auditing
- **UI Consistency**: Follows the existing ERP design system with cards, badges, and color scheme
- **Permissions**: Integrates with the Roles & Permissions module for granular access control

---

## 📧 Support

For issues or questions, contact the development team or refer to the main ERP documentation.

**Module Version**: 1.0.0  
**Last Updated**: October 29, 2025  
**Developed By**: Karyalay ERP Development Team

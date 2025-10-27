# Roles & Permissions - Visual Guide

## Sidebar Navigation

```
┌─────────────────────────────────┐
│  KaryalayERP                    │
├─────────────────────────────────┤
│  🏠 Dashboard                   │
│  🔍 Search                      │
│  👥 Employees                   │
│  📅 Attendance                  │
│  💰 Reimbursements              │
│  📞 CRM                         │
│  💵 Expenses                    │
│  💳 Salary                      │
│  📁 Documents                   │
│  📋 Visitor Log                 │
│  🎨 Branding                    │
│  🔐 Roles & Permissions  ← NEW  │
├─────────────────────────────────┤
│  👤 My Profile                  │
│  📊 My Attendance               │
│  💰 My Reimbursements           │
│  📄 My Documents                │
│  💳 My Salary                   │
│  🚪 Logout                      │
└─────────────────────────────────┘
```

## User Flow Diagram

### First Time Access (No Tables)

```
User clicks "Roles & Permissions"
            ↓
    index.php loads
            ↓
  Check: roles_tables_exist()?
            ↓
         NO
            ↓
  Redirect to onboarding.php
            ↓
  ┌─────────────────────────────┐
  │  Welcome to R&P Setup       │
  │                             │
  │  Status:                    │
  │  ⚠ roles           Missing  │
  │  ⚠ permissions     Missing  │
  │  ⚠ role_perms      Missing  │
  │  ⚠ user_roles      Missing  │
  │  ⚠ audit_log       Missing  │
  │                             │
  │  [Run Setup Script] ←Click  │
  └─────────────────────────────┘
            ↓
    AJAX to setup_handler.php
            ↓
  Runs setup_roles_permissions_tables.php
            ↓
  Creates 5 tables
  Inserts 8 roles
  Inserts 40+ permissions
  Maps Super Admin permissions
            ↓
  ┌─────────────────────────────┐
  │  ✓ Setup Complete!          │
  │                             │
  │  [Go to Roles Management]   │
  └─────────────────────────────┘
            ↓
    Redirect to index.php
            ↓
  Check: roles_tables_exist()?
            ↓
         YES
            ↓
    Show roles list (8 roles)
```

### Subsequent Access (Tables Exist)

```
User clicks "Roles & Permissions"
            ↓
    index.php loads
            ↓
  Check: roles_tables_exist()?
            ↓
         YES
            ↓
  Check: has_permission('settings/roles', 'view')?
            ↓
         YES
            ↓
  ┌─────────────────────────────────────────┐
  │  Roles & Permissions Management         │
  │                                         │
  │  [+ Add New Role]                       │
  │                                         │
  │  ┌─────────────────────────────────┐   │
  │  │ Super Admin        👥 0  🔐 40  │   │
  │  │ System role - Full access       │   │
  │  │              [Edit] [🚫Delete]  │   │
  │  └─────────────────────────────────┘   │
  │                                         │
  │  ┌─────────────────────────────────┐   │
  │  │ Admin             👥 0  🔐 35   │   │
  │  │ System role - Admin access      │   │
  │  │              [Edit] [🚫Delete]  │   │
  │  └─────────────────────────────────┘   │
  │                                         │
  │  ┌─────────────────────────────────┐   │
  │  │ Employee          👥 0  🔐 15   │   │
  │  │ Basic access                    │   │
  │  │              [Edit] [Delete]    │   │
  │  └─────────────────────────────────┘   │
  └─────────────────────────────────────────┘
```

## Helper Functions Flow

### has_permission() Logic

```
has_permission($conn, $user_id, 'crm/leads', 'view')
            ↓
  Check: roles_tables_exist($conn)?
            ↓
      ┌─────NO─────┐        YES
      ↓            ↓         ↓
  Return TRUE   Query:   SELECT COUNT(*)
  (Allow in      ↓      FROM role_permissions rp
   setup mode)   ↓      JOIN user_roles ur
                 ↓      JOIN permissions p
                 ↓      WHERE user_id = ?
                 ↓      AND page_name = ?
                 ↓      AND can_view = 1
                 ↓            ↓
                 ↓      Count > 0?
                 ↓            ↓
                 ↓    YES ────┴──── NO
                 ↓     ↓             ↓
                 ↓  Return TRUE   Return FALSE
                 ↓     ↓             ↓
                 └─────┴─────────────┘
```

## Database Schema

```
┌──────────────┐
│   roles      │
├──────────────┤
│ id (PK)      │──┐
│ name         │  │
│ description  │  │
│ is_system    │  │         ┌──────────────────┐
│ status       │  │         │ role_permissions │
│ created_at   │  │         ├──────────────────┤
└──────────────┘  │         │ id (PK)          │
                  └────────→│ role_id (FK)     │
                            │ permission_id(FK)│──┐
                            │ can_view         │  │
┌──────────────┐            │ can_create       │  │
│ permissions  │            │ can_edit         │  │
├──────────────┤            │ can_delete       │  │
│ id (PK)      │←───────────│ can_export       │  │
│ page_name    │            │ can_approve      │  │
│ module       │            └──────────────────┘  │
│ display_name │                                  │
│ can_view     │                                  │
│ can_create   │            ┌──────────────────┐  │
│ can_edit     │            │   user_roles     │  │
│ can_delete   │            ├──────────────────┤  │
│ can_export   │            │ id (PK)          │  │
│ can_approve  │       ┌───→│ user_id          │  │
└──────────────┘       │    │ role_id (FK)     │──┘
                       │    │ assigned_at      │
                       │    │ assigned_by      │
                       │    └──────────────────┘
┌──────────────┐       │
│    users     │       │    ┌──────────────────────┐
├──────────────┤       │    │ permission_audit_log │
│ id (PK)      │───────┘    ├──────────────────────┤
│ username     │            │ id (PK)              │
│ email        │            │ user_id              │
│ password     │            │ action               │
│ role         │            │ entity_type          │
│ created_at   │            │ entity_id            │
└──────────────┘            │ changes (JSON)       │
                            │ ip_address           │
                            │ created_at           │
                            └──────────────────────┘
```

## Permission Types

```
┌─────────────┬──────────────────────────────────┐
│  Type       │  Description                     │
├─────────────┼──────────────────────────────────┤
│ can_view    │ View/access the page             │
│ can_create  │ Create new records               │
│ can_edit    │ Modify existing records          │
│ can_delete  │ Delete records                   │
│ can_export  │ Export data (CSV, PDF, Excel)    │
│ can_approve │ Approve actions (leave, salary)  │
└─────────────┴──────────────────────────────────┘
```

## Default Roles & Permissions

```
Super Admin ─── ALL 40+ PERMISSIONS
     │
Admin ────────── 35 permissions (no delete sensitive data)
     │
Manager ──────── 25 permissions (dept. specific)
     │
HR Manager ───── 20 permissions (HR focused)
     │
Accountant ───── 18 permissions (finance focused)
     │
Sales Executive ─ 15 permissions (CRM focused)
     │
Employee ──────── 10 permissions (view own data)
     │
Guest ─────────── 5 permissions (read-only basic)
```

## Onboarding Page Layout

```
╔═══════════════════════════════════════════════════╗
║                                                   ║
║              🔐                                   ║
║    Welcome to Roles & Permissions                 ║
║                                                   ║
║    Set up role-based access control for           ║
║    your KaryalayERP system                        ║
║                                                   ║
╠═══════════════════════════════════════════════════╣
║  Setup Status                                     ║
╠═══════════════════════════════════════════════════╣
║  📊 Roles table              ⚠ Missing            ║
║  📊 Permissions table        ⚠ Missing            ║
║  📊 Role permissions table   ⚠ Missing            ║
║  📊 User roles table         ⚠ Missing            ║
║  📊 Audit log table          ⚠ Missing            ║
╠═══════════════════════════════════════════════════╣
║  What Gets Created                                ║
╠═══════════════════════════════════════════════════╣
║  Database Tables:                                 ║
║  • roles - Store role definitions                 ║
║  • permissions - Define page-level access         ║
║  • role_permissions - Map permissions to roles    ║
║  • user_roles - Assign roles to users             ║
║  • permission_audit_log - Track changes           ║
║                                                   ║
║  Default Roles: 8 roles                           ║
║  Default Permissions: 40+ permissions             ║
╠═══════════════════════════════════════════════════╣
║                                                   ║
║         [ ▶ Run Setup Script ]                    ║
║                                                   ║
╠═══════════════════════════════════════════════════╣
║          [ ← Back to Dashboard ]                  ║
╚═══════════════════════════════════════════════════╝
```

## Success Screen

```
╔═══════════════════════════════════════════════════╗
║  ✓ Setup Complete!                                ║
╠═══════════════════════════════════════════════════╣
║                                                   ║
║  Roles & Permissions module has been set up       ║
║  successfully! All tables created and default     ║
║  data inserted.                                   ║
║                                                   ║
║  Created:                                         ║
║  ✓ 5 database tables                              ║
║  ✓ 8 default roles                                ║
║  ✓ 40+ default permissions                        ║
║  ✓ Super Admin configuration                      ║
║                                                   ║
║    [ → Go to Roles Management ]                   ║
║                                                   ║
╚═══════════════════════════════════════════════════╝
```

## File Structure

```
KaryalayERP/
│
├── includes/
│   └── sidebar.php ────────────── Updated with R&P menu
│
├── public/
│   └── settings/
│       └── roles/
│           ├── index.php ──────── Main roles list
│           ├── add.php ────────── Create new role
│           ├── edit.php ───────── Edit existing role
│           ├── delete.php ─────── Delete role
│           ├── helpers.php ────── Helper functions ✓
│           ├── onboarding.php ─── Setup wizard ✓
│           ├── setup_handler.php ─ Setup API ✓
│           └── README.md ──────── Documentation ✓
│
└── scripts/
    ├── setup_roles_permissions_tables.php ✓
    ├── drop_roles_tables.php ──────────── ✓
    ├── test_roles_flow.php ────────────── ✓
    ├── list_tables.php ────────────────── ✓
    └── check_table_structure.php ──────── ✓
```

## Testing Checklist

- [x] Tables don't exist initially
- [x] Helper functions return safe defaults
- [x] Index redirects to onboarding
- [x] Onboarding shows missing tables
- [x] Setup button works via AJAX
- [x] Tables created successfully
- [x] Default data inserted
- [x] Success message displayed
- [x] Redirect to index works
- [x] Roles list displays correctly
- [x] No PHP errors
- [x] No SQL errors
- [x] Sidebar navigation works

## Quick Commands

```bash
# Reset and test onboarding
php scripts/drop_roles_tables.php
# Then visit: http://localhost/KaryalayERP/public/settings/roles/

# Run setup manually
php scripts/setup_roles_permissions_tables.php

# Verify setup
php scripts/list_tables.php

# Test helper functions
php scripts/test_roles_flow.php
```

<?php
/**
 * Roles & Permissions Module - Database Setup Script
 * Creates tables for role-based access control system
 */


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "🔐 Setting up Roles & Permissions Module Tables...\n\n";

// Drop tables if they exist (in correct order for FKs)
mysqli_query($conn, "DROP TABLE IF EXISTS role_permissions");
mysqli_query($conn, "DROP TABLE IF EXISTS user_roles");
mysqli_query($conn, "DROP TABLE IF EXISTS permission_audit_log");
mysqli_query($conn, "DROP TABLE IF EXISTS permissions");
mysqli_query($conn, "DROP TABLE IF EXISTS roles");

// 1. ROLES TABLE
$roles_table = "
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_system_role TINYINT(1) DEFAULT 0 COMMENT 'System roles cannot be deleted',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_system_role (is_system_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if (mysqli_query($conn, $roles_table)) {
    echo "✅ Table 'roles' created successfully.\n";
} else {
    echo "❌ Error creating 'roles' table: " . mysqli_error($conn) . "\n";
}

// 2. PERMISSIONS TABLE (table-based granular)
$permissions_table = "
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Database table name (e.g., employees, crm_leads, attendance)',
    module VARCHAR(100) NOT NULL COMMENT 'Module grouping (HR, CRM, Finance, etc.)',
    display_name VARCHAR(150) NOT NULL COMMENT 'Human-readable name (e.g., Employees, CRM Leads)',
    description TEXT NULL COMMENT 'Description of what this table contains',
    can_create TINYINT(1) DEFAULT 0 COMMENT 'Can create new records',
    can_view_all TINYINT(1) DEFAULT 0 COMMENT 'Can view all records',
    can_view_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can view records assigned to user',
    can_view_own TINYINT(1) DEFAULT 0 COMMENT 'Can view records created by user',
    can_edit_all TINYINT(1) DEFAULT 0 COMMENT 'Can edit all records',
    can_edit_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can edit records assigned to user',
    can_edit_own TINYINT(1) DEFAULT 0 COMMENT 'Can edit records created by user',
    can_delete_all TINYINT(1) DEFAULT 0 COMMENT 'Can delete all records',
    can_delete_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can delete records assigned to user',
    can_delete_own TINYINT(1) DEFAULT 0 COMMENT 'Can delete records created by user',
    can_export TINYINT(1) DEFAULT 0 COMMENT 'Can export data',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether table exists in database',
    last_scanned TIMESTAMP NULL COMMENT 'Last time table was detected in scan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_table_name (table_name),
    INDEX idx_module (module),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if (mysqli_query($conn, $permissions_table)) {
    echo "✅ Table 'permissions' created successfully.\n";
} else {
    echo "❌ Error creating 'permissions' table: " . mysqli_error($conn) . "\n";
}

// 3. ROLE_PERMISSIONS TABLE (granular)
$role_permissions_table = "
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    can_create TINYINT(1) DEFAULT 0,
    can_view_all TINYINT(1) DEFAULT 0,
    can_view_assigned TINYINT(1) DEFAULT 0,
    can_view_own TINYINT(1) DEFAULT 0,
    can_edit_all TINYINT(1) DEFAULT 0,
    can_edit_assigned TINYINT(1) DEFAULT 0,
    can_edit_own TINYINT(1) DEFAULT 0,
    can_delete_all TINYINT(1) DEFAULT 0,
    can_delete_assigned TINYINT(1) DEFAULT 0,
    can_delete_own TINYINT(1) DEFAULT 0,
    can_export TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if (mysqli_query($conn, $role_permissions_table)) {
    echo "✅ Table 'role_permissions' created successfully.\n";
} else {
    echo "❌ Error creating 'role_permissions' table: " . mysqli_error($conn) . "\n";
}

// 4. USER_ROLES TABLE
$user_roles_table = "
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Links to users or employees table',
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT COMMENT 'Admin who assigned the role',
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if (mysqli_query($conn, $user_roles_table)) {
    echo "✅ Table 'user_roles' created successfully.\n";
} else {
    echo "❌ Error creating 'user_roles' table: " . mysqli_error($conn) . "\n";
}

// 5. PERMISSION_AUDIT_LOG TABLE
$audit_log_table = "
CREATE TABLE IF NOT EXISTS permission_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Admin who made the change',
    action VARCHAR(50) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, ASSIGN',
    entity_type VARCHAR(50) COMMENT 'role, permission, user_role',
    entity_id INT COMMENT 'ID of affected entity',
    changes TEXT COMMENT 'JSON of what changed',
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if (mysqli_query($conn, $audit_log_table)) {
    echo "✅ Table 'permission_audit_log' created successfully.\n";
} else {
    echo "❌ Error creating 'permission_audit_log' table: " . mysqli_error($conn) . "\n";
}

echo "\n";

// ===========================
// INSERT DEFAULT ROLES
// ===========================
echo "📝 Inserting default roles...\n";

$default_roles = [
    ['Super Admin', 'Full system access with all permissions', 1],
    ['Admin', 'Administrative access to most modules', 1],
    ['Manager', 'Managerial access with approval rights', 0],
    ['Employee', 'Basic employee access', 0],
    ['HR Manager', 'Human Resources management access', 0],
    ['Accountant', 'Financial and accounting access', 0],
    ['Sales Executive', 'Sales and CRM access', 0],
    ['Guest', 'Read-only limited access', 0]
];

$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO roles (name, description, is_system_role, created_at) VALUES (?, ?, ?, NOW())");

foreach ($default_roles as $role) {
    mysqli_stmt_bind_param($stmt, 'ssi', $role[0], $role[1], $role[2]);
    if (mysqli_stmt_execute($stmt)) {
        echo "  ✓ Role: {$role[0]}\n";
    }
}
mysqli_stmt_close($stmt);

echo "\n";

// ===========================
// INSERT DEFAULT PERMISSIONS (Table-Based)
// ===========================
echo "📝 Inserting default table-based permissions...\n";

$default_permissions = [
    // HR Module Tables
    ['employees', 'HR', 'Employees', 'Employee records and profiles'],
    ['departments', 'HR', 'Departments', 'Department structure and hierarchy'],
    ['designations', 'HR', 'Designations', 'Job titles and positions'],
    ['attendance', 'HR', 'Attendance', 'Daily attendance records'],
    ['leave_types', 'HR', 'Leave Types', 'Leave categories and policies'],
    ['holidays', 'HR', 'Holidays', 'Public holidays calendar'],
    
    // CRM Module Tables
    ['crm_leads', 'CRM', 'CRM Leads', 'Sales leads and prospects'],
    ['crm_calls', 'CRM', 'CRM Calls', 'Customer call logs'],
    ['crm_meetings', 'CRM', 'CRM Meetings', 'Meeting schedules and notes'],
    ['crm_tasks', 'CRM', 'CRM Tasks', 'Task assignments and tracking'],
    ['crm_visits', 'CRM', 'CRM Visits', 'Site visit records'],
    
    // Finance Module Tables
    ['salary_records', 'Finance', 'Salary Records', 'Employee salary processing'],
    ['reimbursements', 'Finance', 'Reimbursements', 'Employee expense reimbursements'],
    ['office_expenses', 'Finance', 'Office Expenses', 'General office expenditures'],
    
    // Documents Module
    ['documents', 'Documents', 'Documents', 'Document management system'],
    
    // Reception Module
    ['visitor_logs', 'Reception', 'Visitor Logs', 'Visitor check-in records'],
    
    // Settings Module
    ['roles', 'Settings', 'Roles', 'User role definitions'],
    ['permissions', 'Settings', 'Permissions', 'Permission structure'],
    ['user_roles', 'Settings', 'User Roles', 'Role assignments to users'],
    ['branding_settings', 'Settings', 'Branding', 'System branding configuration'],
    
    // System Tables
    ['users', 'System', 'Users', 'System user accounts'],
];

$stmt = mysqli_prepare($conn, "
    INSERT IGNORE INTO permissions 
    (table_name, module, display_name, description) 
    VALUES (?, ?, ?, ?)
");

foreach ($default_permissions as $perm) {
    mysqli_stmt_bind_param($stmt, 'ssss', 
        $perm[0], $perm[1], $perm[2], $perm[3]
    );
    if (mysqli_stmt_execute($stmt)) {
        echo "  ✓ Table: {$perm[2]}\n";
    }
}
mysqli_stmt_close($stmt);

echo "\n";

// ===========================
// ASSIGN DEFAULT PERMISSIONS TO SUPER ADMIN
// ===========================
echo "🔑 Assigning all permissions to Super Admin role...\n";

$assign_query = "
    INSERT IGNORE INTO role_permissions (role_id, permission_id, can_create, can_view_all, can_view_assigned, can_view_own, can_edit_all, can_edit_assigned, can_edit_own, can_delete_all, can_delete_assigned, can_delete_own, can_export)
    SELECT r.id, p.id, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1
    FROM roles r
    CROSS JOIN permissions p
    WHERE r.name = 'Super Admin'
";

if (mysqli_query($conn, $assign_query)) {
    echo "✅ All permissions assigned to Super Admin.\n";
} else {
    echo "❌ Error assigning permissions: " . mysqli_error($conn) . "\n";
}

echo "\n";

// ===========================
// ASSIGN BASIC PERMISSIONS TO EMPLOYEE ROLE
// ===========================
echo "🔑 Assigning basic view permissions to Employee role...\n";

$employee_query = "
    INSERT IGNORE INTO role_permissions (role_id, permission_id, can_create, can_view_all, can_view_assigned, can_view_own, can_edit_all, can_edit_assigned, can_edit_own, can_delete_all, can_delete_assigned, can_delete_own, can_export)
    SELECT r.id, p.id, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0, 0
    FROM roles r
    CROSS JOIN permissions p
    WHERE r.name = 'Employee'
";

if (mysqli_query($conn, $employee_query)) {
    echo "✅ Basic view-own/edit-own permissions assigned to Employee role.\n";
} else {
    echo "❌ Error assigning employee permissions: " . mysqli_error($conn) . "\n";
}

echo "\n🎉 Roles & Permissions Module setup complete!\n";

closeConnection($conn);
?>
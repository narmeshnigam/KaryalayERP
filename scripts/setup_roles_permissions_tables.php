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

// ===========================
// 1. ROLES TABLE
// ===========================
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

// ===========================
// 2. PERMISSIONS TABLE
// ===========================
$permissions_table = "
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(150) NOT NULL UNIQUE COMMENT 'Page identifier (e.g., crm/leads, employees/add)',
    module VARCHAR(50) COMMENT 'Module grouping (CRM, HR, Settings, etc.)',
    display_name VARCHAR(150) COMMENT 'Human-readable page name',
    can_view TINYINT(1) DEFAULT 0 COMMENT 'Permission to view the page',
    can_create TINYINT(1) DEFAULT 0 COMMENT 'Permission to create entries',
    can_edit TINYINT(1) DEFAULT 0 COMMENT 'Permission to edit entries',
    can_delete TINYINT(1) DEFAULT 0 COMMENT 'Permission to delete entries',
    can_export TINYINT(1) DEFAULT 0 COMMENT 'Permission to export data',
    can_approve TINYINT(1) DEFAULT 0 COMMENT 'Permission to approve actions',
    fallback_page VARCHAR(150) DEFAULT '/public/unauthorized.php' COMMENT 'Redirect route if unauthorized',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_page_name (page_name),
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (mysqli_query($conn, $permissions_table)) {
    echo "✅ Table 'permissions' created successfully.\n";
} else {
    echo "❌ Error creating 'permissions' table: " . mysqli_error($conn) . "\n";
}

// ===========================
// 3. ROLE_PERMISSIONS TABLE
// ===========================
$role_permissions_table = "
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_create TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    can_export TINYINT(1) DEFAULT 0,
    can_approve TINYINT(1) DEFAULT 0,
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

// ===========================
// 4. USER_ROLES TABLE
// ===========================
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

// ===========================
// 5. PERMISSION_AUDIT_LOG TABLE
// ===========================
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
// INSERT DEFAULT PERMISSIONS
// ===========================
echo "📝 Inserting default permissions...\n";

$default_permissions = [
    // Dashboard
    ['dashboard/index', 'Dashboard', 'Dashboard Home', 1, 0, 0, 0, 0, 0],
    
    // Employees Module
    ['employees/index', 'HR', 'Employee List', 1, 0, 0, 0, 1, 0],
    ['employees/add', 'HR', 'Add Employee', 1, 1, 0, 0, 0, 0],
    ['employees/edit', 'HR', 'Edit Employee', 1, 0, 1, 0, 0, 0],
    ['employees/delete', 'HR', 'Delete Employee', 1, 0, 0, 1, 0, 0],
    ['employees/view', 'HR', 'View Employee Details', 1, 0, 0, 0, 0, 0],
    
    // CRM Module
    ['crm/leads/index', 'CRM', 'Leads List', 1, 0, 0, 0, 1, 0],
    ['crm/leads/add', 'CRM', 'Add Lead', 1, 1, 0, 0, 0, 0],
    ['crm/leads/edit', 'CRM', 'Edit Lead', 1, 0, 1, 0, 0, 0],
    ['crm/leads/delete', 'CRM', 'Delete Lead', 1, 0, 0, 1, 0, 0],
    ['crm/calls/index', 'CRM', 'Calls Log', 1, 0, 0, 0, 1, 0],
    ['crm/calls/add', 'CRM', 'Log Call', 1, 1, 0, 0, 0, 0],
    ['crm/meetings/index', 'CRM', 'Meetings Log', 1, 0, 0, 0, 1, 0],
    ['crm/meetings/add', 'CRM', 'Schedule Meeting', 1, 1, 0, 0, 0, 0],
    ['crm/tasks/index', 'CRM', 'Tasks List', 1, 0, 0, 0, 1, 0],
    ['crm/tasks/add', 'CRM', 'Add Task', 1, 1, 0, 0, 0, 0],
    ['crm/visits/index', 'CRM', 'Visits Log', 1, 0, 0, 0, 1, 0],
    ['crm/visits/add', 'CRM', 'Log Visit', 1, 1, 0, 0, 0, 0],
    
    // Attendance Module
    ['attendance/index', 'HR', 'Attendance Records', 1, 0, 0, 0, 1, 0],
    ['attendance/mark', 'HR', 'Mark Attendance', 1, 1, 0, 0, 0, 0],
    ['attendance/approve', 'HR', 'Approve Leave', 1, 0, 0, 0, 0, 1],
    
    // Salary Module
    ['salary/index', 'Finance', 'Salary Records', 1, 0, 0, 0, 1, 0],
    ['salary/generate', 'Finance', 'Generate Salary', 1, 1, 0, 0, 0, 0],
    ['salary/approve', 'Finance', 'Approve Salary', 1, 0, 0, 0, 0, 1],
    
    // Documents Module
    ['documents/index', 'Documents', 'Documents Vault', 1, 0, 0, 0, 1, 0],
    ['documents/upload', 'Documents', 'Upload Document', 1, 1, 0, 0, 0, 0],
    ['documents/delete', 'Documents', 'Delete Document', 1, 0, 0, 1, 0, 0],
    
    // Visitors Module
    ['visitors/index', 'Reception', 'Visitor Log', 1, 0, 0, 0, 1, 0],
    ['visitors/add', 'Reception', 'Register Visitor', 1, 1, 0, 0, 0, 0],
    
    // Expenses Module
    ['expenses/index', 'Finance', 'Expenses List', 1, 0, 0, 0, 1, 0],
    ['expenses/add', 'Finance', 'Add Expense', 1, 1, 0, 0, 0, 0],
    ['expenses/approve', 'Finance', 'Approve Expense', 1, 0, 0, 0, 0, 1],
    
    // Reimbursements Module
    ['reimbursements/index', 'Finance', 'Reimbursements List', 1, 0, 0, 0, 1, 0],
    ['reimbursements/add', 'Finance', 'Submit Reimbursement', 1, 1, 0, 0, 0, 0],
    ['reimbursements/approve', 'Finance', 'Approve Reimbursement', 1, 0, 0, 0, 0, 1],
    
    // Settings & Admin
    ['settings/roles', 'Settings', 'Manage Roles', 1, 1, 1, 1, 0, 0],
    ['settings/permissions', 'Settings', 'Manage Permissions', 1, 0, 1, 0, 0, 0],
    ['settings/assign-roles', 'Settings', 'Assign User Roles', 1, 0, 1, 0, 0, 0],
    ['settings/branding', 'Settings', 'Branding Settings', 1, 0, 1, 0, 0, 0],
];

$stmt = mysqli_prepare($conn, "
    INSERT IGNORE INTO permissions 
    (page_name, module, display_name, can_view, can_create, can_edit, can_delete, can_export, can_approve) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($default_permissions as $perm) {
    mysqli_stmt_bind_param($stmt, 'sssiiiiii', 
        $perm[0], $perm[1], $perm[2], $perm[3], $perm[4], $perm[5], $perm[6], $perm[7], $perm[8]
    );
    if (mysqli_stmt_execute($stmt)) {
        echo "  ✓ Permission: {$perm[2]}\n";
    }
}
mysqli_stmt_close($stmt);

echo "\n";

// ===========================
// ASSIGN DEFAULT PERMISSIONS TO SUPER ADMIN
// ===========================
echo "🔑 Assigning all permissions to Super Admin role...\n";

$assign_query = "
    INSERT IGNORE INTO role_permissions (role_id, permission_id, can_view, can_create, can_edit, can_delete, can_export, can_approve)
    SELECT r.id, p.id, 1, 1, 1, 1, 1, 1
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
echo "🔑 Assigning basic permissions to Employee role...\n";

$employee_query = "
    INSERT IGNORE INTO role_permissions (role_id, permission_id, can_view, can_create, can_edit, can_delete, can_export, can_approve)
    SELECT r.id, p.id, 1, 0, 0, 0, 0, 0
    FROM roles r
    CROSS JOIN permissions p
    WHERE r.name = 'Employee' AND p.page_name IN ('dashboard/index', 'employees/view')
";

if (mysqli_query($conn, $employee_query)) {
    echo "✅ Basic permissions assigned to Employee role.\n";
} else {
    echo "❌ Error assigning employee permissions: " . mysqli_error($conn) . "\n";
}

echo "\n🎉 Roles & Permissions Module setup complete!\n";

closeConnection($conn);
?>
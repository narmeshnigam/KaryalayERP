<?php
/**
 * RBAC bootstrap utilities for setup workflow.
 */


if (!function_exists('setup_rbac_tables')) {
    /**
     * Ensure RBAC tables exist with the expected structure.
     *
     * @throws RuntimeException when a table cannot be created.
     */
    function setup_rbac_tables(mysqli $conn): void
    {
        $tableStatements = [
            "CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NULL,
                is_system_role TINYINT(1) DEFAULT 0,
                status ENUM('Active','Inactive') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NULL,
                INDEX idx_roles_name (name),
                INDEX idx_roles_status (status),
                INDEX idx_roles_system (is_system_role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL UNIQUE,
                module VARCHAR(100) NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                description TEXT NULL,
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
                is_active TINYINT(1) DEFAULT 1,
                last_scanned TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_permissions_table (table_name),
                INDEX idx_permissions_module (module),
                INDEX idx_permissions_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS role_permissions (
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
                UNIQUE KEY uniq_role_permission (role_id, permission_id),
                INDEX idx_role_permissions_role (role_id),
                INDEX idx_role_permissions_perm (permission_id),
                CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS user_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                assigned_by INT NULL,
                UNIQUE KEY uniq_user_role (user_id, role_id),
                INDEX idx_user_roles_user (user_id),
                INDEX idx_user_roles_role (role_id),
                CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS permission_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id INT NULL,
                changes TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_permission_audit_user (user_id),
                INDEX idx_permission_audit_action (action),
                INDEX idx_permission_audit_entity (entity_type, entity_id),
                INDEX idx_permission_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($tableStatements as $statement) {
            if (!$conn->query($statement)) {
                throw new RuntimeException('RBAC table creation failed: ' . $conn->error);
            }
        }
    }
}

if (!function_exists('setup_rbac_seed_defaults')) {
    /**
     * Insert default RBAC seed data.
     *
     * @throws RuntimeException when seed inserts fail.
     */
    function setup_rbac_seed_defaults(mysqli $conn): void
    {
        $defaultRoles = [
            ['Super Admin', 'Full system access with all permissions', 1],
            ['Admin', 'Administrative access to most modules', 1],
            ['Manager', 'Managerial access with approval rights', 0],
            ['Employee', 'Basic employee access', 0],
            ['HR Manager', 'Human Resources management access', 0],
            ['Accountant', 'Financial and accounting access', 0],
            ['Sales Executive', 'Sales and CRM access', 0],
            ['Guest', 'Read-only limited access', 0],
        ];

        $roleStmt = $conn->prepare('INSERT IGNORE INTO roles (name, description, is_system_role, status, created_at) VALUES (?, ?, ?, \'Active\', NOW())');
        if (!$roleStmt) {
            throw new RuntimeException('Failed to prepare role seed statement: ' . $conn->error);
        }

        foreach ($defaultRoles as $role) {
            [$name, $description, $isSystem] = $role;
            $roleStmt->bind_param('ssi', $name, $description, $isSystem);
            if (!$roleStmt->execute()) {
                $roleStmt->close();
                throw new RuntimeException('Failed to seed role ' . $name . ': ' . $conn->error);
            }
        }
        $roleStmt->close();

        $defaultPermissions = [
            // HR Module
            ['employees', 'HR', 'Employees', 'Employee records and profiles'],
            ['departments', 'HR', 'Departments', 'Department structure and hierarchy'],
            ['designations', 'HR', 'Designations', 'Job titles and positions'],
            ['attendance', 'HR', 'Attendance', 'Daily attendance records'],
            ['leave_types', 'HR', 'Leave Types', 'Leave categories and policies'],
            ['holidays', 'HR', 'Holidays', 'Public holidays calendar'],
            // CRM Module
            ['crm_leads', 'CRM', 'CRM Leads', 'Sales leads and prospects'],
            ['crm_calls', 'CRM', 'CRM Calls', 'Customer call logs'],
            ['crm_meetings', 'CRM', 'CRM Meetings', 'Meeting schedules and notes'],
            ['crm_tasks', 'CRM', 'CRM Tasks', 'Task assignments and tracking'],
            ['crm_visits', 'CRM', 'CRM Visits', 'Site visit records'],
            // Finance Module
            ['salary_records', 'Finance', 'Salary Records', 'Employee salary processing'],
            ['reimbursements', 'Finance', 'Reimbursements', 'Employee expense reimbursements'],
            ['office_expenses', 'Finance', 'Office Expenses', 'General office expenditures'],
            // Documents Module
            ['documents', 'Documents', 'Documents', 'Document management system'],
            // Reception Module
            ['visitor_logs', 'Reception', 'Visitor Logs', 'Visitor check-in records'],
            // Catalog Module
            ['items_master', 'Catalog', 'Catalog Items', 'Products and services inventory'],
            ['item_inventory_log', 'Catalog', 'Inventory Log', 'Stock movement history'],
            // Settings Module
            ['roles', 'Settings', 'Roles', 'User role definitions'],
            ['permissions', 'Settings', 'Permissions', 'Permission structure'],
            ['user_roles', 'Settings', 'User Roles', 'Role assignments to users'],
            ['branding_settings', 'Settings', 'Branding', 'System branding configuration'],
            // System Module
            ['users', 'System', 'Users', 'System user accounts'],
        ];

        $permStmt = $conn->prepare('INSERT IGNORE INTO permissions (table_name, module, display_name, description, created_at) VALUES (?, ?, ?, ?, NOW())');
        if (!$permStmt) {
            throw new RuntimeException('Failed to prepare permission seed statement: ' . $conn->error);
        }

        foreach ($defaultPermissions as $perm) {
            [$tableName, $module, $displayName, $description] = $perm;
            $permStmt->bind_param('ssss', $tableName, $module, $displayName, $description);
            if (!$permStmt->execute()) {
                $permStmt->close();
                throw new RuntimeException('Failed to seed permission ' . $tableName . ': ' . $conn->error);
            }
        }
        $permStmt->close();

        // Assign every permission to Super Admin role.
        $assignSuperAdmin = "INSERT IGNORE INTO role_permissions (
                role_id, permission_id,
                can_create, can_view_all, can_view_assigned, can_view_own,
                can_edit_all, can_edit_assigned, can_edit_own,
                can_delete_all, can_delete_assigned, can_delete_own,
                can_export
            )
            SELECT r.id, p.id, 1,1,1,1,1,1,1,1,1,1,1
            FROM roles r
            CROSS JOIN permissions p
            WHERE r.name = 'Super Admin'";

        if (!$conn->query($assignSuperAdmin)) {
            throw new RuntimeException('Failed to grant Super Admin permissions: ' . $conn->error);
        }

        // Assign basic self-service permissions to Employee role.
        $assignEmployee = "INSERT IGNORE INTO role_permissions (
                role_id, permission_id,
                can_create, can_view_all, can_view_assigned, can_view_own,
                can_edit_all, can_edit_assigned, can_edit_own,
                can_delete_all, can_delete_assigned, can_delete_own,
                can_export
            )
            SELECT r.id, p.id,
                   0, 0, 0, 1,
                   0, 0, 1,
                   0, 0, 0,
                   0
            FROM roles r
            CROSS JOIN permissions p
            WHERE r.name = 'Employee'";

        if (!$conn->query($assignEmployee)) {
            throw new RuntimeException('Failed to grant Employee permissions: ' . $conn->error);
        }
    }
}

if (!function_exists('setup_rbac_bootstrap')) {
    /**
     * Ensure RBAC structures and seed data exist.
     */
    function setup_rbac_bootstrap(mysqli $conn): void
    {
        setup_rbac_tables($conn);
        setup_rbac_seed_defaults($conn);
    }
}

if (!function_exists('setup_rbac_get_role_id')) {
    /**
     * Fetch role ID by name.
     */
    function setup_rbac_get_role_id(mysqli $conn, string $roleName): ?int
    {
        $stmt = $conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ? (int) $row['id'] : null;
    }
}

if (!function_exists('setup_rbac_assign_role')) {
    /**
     * Assign a role to a user if not already assigned and sync users.role_id.
     */
    function setup_rbac_assign_role(mysqli $conn, int $userId, string $roleName, ?int $assignedBy = null): void
    {
        $roleId = setup_rbac_get_role_id($conn, $roleName);
        if ($roleId === null) {
            return;
        }

        $assignStmt = $conn->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)');
        if ($assignStmt) {
            $assigner = $assignedBy ?? $userId;
            $assignStmt->bind_param('iii', $userId, $roleId, $assigner);
            $assignStmt->execute();
            $assignStmt->close();
        }

        $updateStmt = $conn->prepare('UPDATE users SET role_id = ?, status = \'Active\', is_active = 1 WHERE id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('ii', $roleId, $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

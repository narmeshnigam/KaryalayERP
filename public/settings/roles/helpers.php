<?php
/**
 * Roles & Permissions Helper Functions
 * Centralized authorization logic for the Karyalay ERP system
 */

/**
 * Check if roles & permissions tables exist
 * @param mysqli $conn Database connection
 * @return bool True if all required tables exist
 */
function roles_tables_exist($conn) {
    $required_tables = ['roles', 'permissions', 'role_permissions', 'user_roles'];
    
    foreach ($required_tables as $table) {
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$result || mysqli_num_rows($result) === 0) {
            if ($result) @mysqli_free_result($result);
            return false;
        }
        @mysqli_free_result($result);
    }
    
    return true;
}

/**
 * Get user's assigned roles
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array Array of role objects
 */
function get_user_roles($conn, $user_id) {
    // Check if tables exist first
    if (!roles_tables_exist($conn)) {
        return [];
    }
    
    $roles = [];
    $stmt = mysqli_prepare($conn, "
        SELECT r.* 
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ? AND r.status = 'Active'
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $roles[] = $row;
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $roles;
}

/**
 * Check if user has specific permission for a page
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $page_path Page path (e.g., 'crm/leads.php', 'employees.php')
 * @param string $permission_type Permission type: 'create', 'view_all', 'view_assigned', 'view_own', 'edit_all', 'edit_assigned', 'edit_own', 'delete_all', 'delete_assigned', 'delete_own', 'export'
 * @return bool True if user has permission
 */
function has_permission($conn, $user_id, $page_path, $permission_type = 'view_all') {
    // If tables don't exist, allow access (setup mode)
    if (!roles_tables_exist($conn)) {
        return true;
    }
    
    // Map old permission types to new granular types for backward compatibility
    $permission_map = [
        'view' => 'can_view_all',
        'create' => 'can_create',
        'edit' => 'can_edit_all',
        'delete' => 'can_delete_all',
        'export' => 'can_export'
    ];
    
    // Check if it's an old permission type and map it
    if (isset($permission_map[$permission_type])) {
        $permission_field = $permission_map[$permission_type];
    } else {
        // New granular permission type
        $permission_field = 'can_' . strtolower($permission_type);
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as has_access
        FROM role_permissions rp
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        INNER JOIN permissions p ON rp.permission_id = p.id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? 
        AND p.page_path = ? 
        AND rp.$permission_field = 1
        AND r.status = 'Active'
        AND p.is_active = 1
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $page_path);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['has_access'] > 0;
    }
    
    return false;
}

/**
 * Check if user has any of the specified permissions
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $page_name Page identifier
 * @param array $permission_types Array of permission types to check
 * @return bool True if user has at least one permission
 */
function has_any_permission($conn, $user_id, $page_name, $permission_types = []) {
    foreach ($permission_types as $type) {
        if (has_permission($conn, $user_id, $page_name, $type)) {
            return true;
        }
    }
    return false;
}

/**
 * Get user's permissions for a specific page
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $page_name Page identifier
 * @return array Associative array of permissions
 */
function get_user_page_permissions($conn, $user_id, $page_name) {
    $permissions = [
        'can_create' => false,
        'can_view_all' => false,
        'can_view_assigned' => false,
        'can_view_own' => false,
        'can_edit_all' => false,
        'can_edit_assigned' => false,
        'can_edit_own' => false,
        'can_delete_all' => false,
        'can_delete_assigned' => false,
        'can_delete_own' => false,
        'can_export' => false
    ];
    
    $stmt = mysqli_prepare($conn, "
        SELECT 
            MAX(rp.can_create) as can_create,
            MAX(rp.can_view_all) as can_view_all,
            MAX(rp.can_view_assigned) as can_view_assigned,
            MAX(rp.can_view_own) as can_view_own,
            MAX(rp.can_edit_all) as can_edit_all,
            MAX(rp.can_edit_assigned) as can_edit_assigned,
            MAX(rp.can_edit_own) as can_edit_own,
            MAX(rp.can_delete_all) as can_delete_all,
            MAX(rp.can_delete_assigned) as can_delete_assigned,
            MAX(rp.can_delete_own) as can_delete_own,
            MAX(rp.can_export) as can_export
        FROM role_permissions rp
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        INNER JOIN permissions p ON rp.permission_id = p.id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? 
        AND p.page_path = ?
        AND r.status = 'Active'
        AND p.is_active = 1
        GROUP BY p.id
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $page_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $permissions = [
                'can_create' => (bool)$row['can_create'],
                'can_view_all' => (bool)$row['can_view_all'],
                'can_view_assigned' => (bool)$row['can_view_assigned'],
                'can_view_own' => (bool)$row['can_view_own'],
                'can_edit_all' => (bool)$row['can_edit_all'],
                'can_edit_assigned' => (bool)$row['can_edit_assigned'],
                'can_edit_own' => (bool)$row['can_edit_own'],
                'can_delete_all' => (bool)$row['can_delete_all'],
                'can_delete_assigned' => (bool)$row['can_delete_assigned'],
                'can_delete_own' => (bool)$row['can_delete_own'],
                'can_export' => (bool)$row['can_export']
            ];
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $permissions;
}

/**
 * Enforce permission check - redirect if unauthorized
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $page_name Page identifier
 * @param string $permission_type Permission type required
 * @param string $fallback_url Optional custom fallback URL
 */
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
    
    if (!has_permission($conn, $user_id, $page_name, $permission_type)) {
        // Default to unauthorized page (fallback_page column removed in new structure)
        if (!$fallback_url) {
            $fallback_url = '/public/unauthorized.php';
        }
        
        // Store attempted page for redirect back
        $_SESSION['attempted_page'] = $_SERVER['REQUEST_URI'];
        $_SESSION['permission_error'] = "You don't have permission to $permission_type this page.";
        
        header('Location: ' . $fallback_url);
        exit;
    }
}

/**
 * Check if user has a specific role
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $role_name Role name to check
 * @return bool True if user has the role
 */
function has_role($conn, $user_id, $role_name) {
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as has_role
        FROM user_roles ur
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.name = ? AND r.status = 'Active'
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $role_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['has_role'] > 0;
    }
    
    return false;
}

/**
 * Check if user is Super Admin
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True if user is Super Admin
 */
function is_super_admin($conn, $user_id) {
    return has_role($conn, $user_id, 'Super Admin');
}

/**
 * Log permission audit trail
 * @param mysqli $conn Database connection
 * @param int $user_id User who performed the action
 * @param string $action Action performed (CREATE, UPDATE, DELETE, ASSIGN)
 * @param string $entity_type Entity type (role, permission, user_role)
 * @param int $entity_id Entity ID
 * @param array $changes Array of changes made
 */
function log_permission_audit($conn, $user_id, $action, $entity_type, $entity_id, $changes = []) {
    $changes_json = json_encode($changes);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = mysqli_prepare($conn, "
        INSERT INTO permission_audit_log 
        (user_id, action, entity_type, entity_id, changes, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ississ', $user_id, $action, $entity_type, $entity_id, $changes_json, $ip_address);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get all permissions grouped by module
 * @param mysqli $conn Database connection
 * @return array Grouped permissions array
 */
function get_permissions_by_module($conn) {
    $grouped = [];
    
    $result = mysqli_query($conn, "
        SELECT * FROM permissions 
        ORDER BY module, display_name
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $module = $row['module'] ?? 'Other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $row;
        }
        mysqli_free_result($result);
    }
    
    return $grouped;
}

/**
 * Assign role to user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @param int $assigned_by Admin user ID
 * @return bool Success status
 */
function assign_role_to_user($conn, $user_id, $role_id, $assigned_by) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO user_roles (user_id, role_id, assigned_by) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE assigned_by = ?, assigned_at = NOW()
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $role_id, $assigned_by, $assigned_by);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            log_permission_audit($conn, $assigned_by, 'ASSIGN', 'user_role', $user_id, [
                'role_id' => $role_id,
                'user_id' => $user_id
            ]);
        }
        
        return $success;
    }
    
    return false;
}

/**
 * Remove role from user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @param int $removed_by Admin user ID
 * @return bool Success status
 */
function remove_role_from_user($conn, $user_id, $role_id, $removed_by) {
    $stmt = mysqli_prepare($conn, "
        DELETE FROM user_roles 
        WHERE user_id = ? AND role_id = ?
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $role_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            log_permission_audit($conn, $removed_by, 'REMOVE', 'user_role', $user_id, [
                'role_id' => $role_id,
                'user_id' => $user_id
            ]);
        }
        
        return $success;
    }
    
    return false;
}

/**
 * Get role with all assigned permissions
 * @param mysqli $conn Database connection
 * @param int $role_id Role ID
 * @return array|null Role data with permissions
 */
function get_role_with_permissions($conn, $role_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM roles WHERE id = ? LIMIT 1");
    
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $role = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$role) return null;
    
    // Get permissions
    $stmt = mysqli_prepare($conn, "
        SELECT p.*, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export, rp.can_approve
        FROM permissions p
        LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
        ORDER BY p.module, p.display_name
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $role_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $permissions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $permissions[] = $row;
        }
        
        $role['permissions'] = $permissions;
        mysqli_stmt_close($stmt);
    }
    
    return $role;
}
?>
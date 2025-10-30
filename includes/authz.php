<?php
/**
 * Centralized authorization helpers for table-based permissions.
 *
 * All checks rely on the roles, permissions, role_permissions, and user_roles tables
 * managed by the Permissions Manager UI.
 */

if (!defined('AUTHZ_PERMISSION_FIELDS')) {
    define('AUTHZ_PERMISSION_FIELDS', [
        'can_create',
        'can_view_all',
        'can_view_assigned',
        'can_view_own',
        'can_edit_all',
        'can_edit_assigned',
        'can_edit_own',
        'can_delete_all',
        'can_delete_assigned',
        'can_delete_own',
        'can_export',
    ]);
}

/**
 * Check if RBAC tables exist in the current schema. Result is cached per-request.
 */
function authz_roles_tables_exist(mysqli $conn): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $required_tables = ['roles', 'permissions', 'role_permissions', 'user_roles'];

    foreach ($required_tables as $table) {
        $table_esc = mysqli_real_escape_string($conn, $table);
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table_esc'");
        if (!$result || mysqli_num_rows($result) === 0) {
            if ($result) {
                @mysqli_free_result($result);
            }
            $cache = false;
            return $cache;
        }
        @mysqli_free_result($result);
    }

    $cache = true;
    return $cache;
}

/**
 * Fetch active roles assigned to the user.
 */
function authz_fetch_user_roles(mysqli $conn, int $user_id): array {
    $roles = [];

    if (!authz_roles_tables_exist($conn)) {
        return $roles;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT r.id, r.name, r.is_system_role, r.status
        FROM user_roles ur
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.status = 'Active'
        ORDER BY r.name
    ");

    if (!$stmt) {
        return $roles;
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $roles[] = $row;
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    return $roles;
}

/**
 * Aggregate table permissions for the user across all active roles.
 */
function authz_fetch_user_permissions(mysqli $conn, int $user_id): array {
    $permissions = [];

    if (!authz_roles_tables_exist($conn)) {
        return $permissions;
    }

    $query = "
        SELECT 
            p.table_name,
            MAX(rp.can_create) AS can_create,
            MAX(rp.can_view_all) AS can_view_all,
            MAX(rp.can_view_assigned) AS can_view_assigned,
            MAX(rp.can_view_own) AS can_view_own,
            MAX(rp.can_edit_all) AS can_edit_all,
            MAX(rp.can_edit_assigned) AS can_edit_assigned,
            MAX(rp.can_edit_own) AS can_edit_own,
            MAX(rp.can_delete_all) AS can_delete_all,
            MAX(rp.can_delete_assigned) AS can_delete_assigned,
            MAX(rp.can_delete_own) AS can_delete_own,
            MAX(rp.can_export) AS can_export
        FROM user_roles ur
        INNER JOIN roles r ON ur.role_id = r.id AND r.status = 'Active'
        INNER JOIN role_permissions rp ON rp.role_id = r.id
        INNER JOIN permissions p ON rp.permission_id = p.id AND p.is_active = 1
        WHERE ur.user_id = ?
        GROUP BY p.table_name
    ";

    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        return $permissions;
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $table = $row['table_name'];
            unset($row['table_name']);
            $normalized = [];
            foreach (AUTHZ_PERMISSION_FIELDS as $field) {
                $normalized[$field] = isset($row[$field]) && (int)$row[$field] === 1;
            }
            $permissions[$table] = $normalized;
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    return $permissions;
}

/**
 * Normalize permission keyword (view_all, can_view_all, view, edit, etc.).
 */
function authz_normalize_permission_keyword(string $permission): ?string {
    $permission = strtolower(trim($permission));

    if ($permission === '') {
        $permission = 'view_all';
    }

    $map = [
        'view' => 'can_view_all',
        'view_all' => 'can_view_all',
        'view_assigned' => 'can_view_assigned',
        'view_own' => 'can_view_own',
        'create' => 'can_create',
        // Edit/Update synonyms
        'edit' => 'can_edit_all',
        'edit_all' => 'can_edit_all',
        'edit_assigned' => 'can_edit_assigned',
        'edit_own' => 'can_edit_own',
        // Accept legacy/alternate keyword 'update'
        'update' => 'can_edit_all',
        'update_all' => 'can_edit_all',
        'update_assigned' => 'can_edit_assigned',
        'update_own' => 'can_edit_own',
        'delete' => 'can_delete_all',
        'delete_all' => 'can_delete_all',
        'delete_assigned' => 'can_delete_assigned',
        'delete_own' => 'can_delete_own',
        'export' => 'can_export',
    ];

    if (isset($map[$permission])) {
        return $map[$permission];
    }

    if (strpos($permission, 'can_') === 0 && in_array($permission, AUTHZ_PERMISSION_FIELDS, true)) {
        return $permission;
    }

    return null;
}

/**
 * Build and cache authorization context for the current request.
 */
function authz_context(mysqli $conn, bool $force_refresh = false): array {
    static $context = null;

    if ($force_refresh) {
        $context = null;
    }

    if ($context !== null) {
        return $context;
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($user_id <= 0) {
        $context = [
            'user_id' => 0,
            'roles' => [],
            'permissions' => [],
            'is_super_admin' => false,
            'tables_exist' => false,
        ];
        return $context;
    }

    $tables_exist = authz_roles_tables_exist($conn);
    $roles = $tables_exist ? authz_fetch_user_roles($conn, $user_id) : [];
    $permissions = $tables_exist ? authz_fetch_user_permissions($conn, $user_id) : [];

    $is_super_admin = false;
    foreach ($roles as $role) {
        if ((int)($role['is_system_role'] ?? 0) === 1 && strtolower($role['name'] ?? '') === 'super admin') {
            $is_super_admin = true;
            break;
        }
    }

    // Persist role names in session for UI display if available.
    if (!empty($roles)) {
        $_SESSION['role_names'] = array_map(static function ($role) {
            return $role['name'];
        }, $roles);
    } else {
        $_SESSION['role_names'] = [];
    }

    $context = [
        'user_id' => $user_id,
        'roles' => $roles,
        'permissions' => $permissions,
        'is_super_admin' => $is_super_admin,
        'tables_exist' => $tables_exist,
    ];

    return $context;
}

/**
 * Force-refresh the cached authorization context (e.g., after role updates).
 */
function authz_refresh_context(mysqli $conn): array {
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
        return [];
    }

    return authz_context($conn, true);
}

/**
 * Check whether the current user can perform the requested action on a table.
 */
function authz_user_can(mysqli $conn, string $table_name, string $permission_type = 'view_all'): bool {
    $table_name = trim($table_name);
    if ($table_name === '') {
        return false;
    }

    $normalized_permission = authz_normalize_permission_keyword($permission_type);
    if ($normalized_permission === null) {
        return false;
    }

    $context = authz_context($conn);

    // If tables missing, default to allow (bootstrap mode).
    if (!$context['tables_exist']) {
        return true;
    }

    // Super Admin shortcut.
    if ($context['is_super_admin']) {
        return true;
    }

    $permissions = $context['permissions'][$table_name] ?? null;
    if (!$permissions) {
        return false;
    }

    return !empty($permissions[$normalized_permission]);
}

/**
 * Check multiple table-permission requirements; returns true if any requirement passes.
 */
function authz_user_can_any(mysqli $conn, array $requirements): bool {
    foreach ($requirements as $req) {
        if (!isset($req['table'])) {
            continue;
        }
        $perm = $req['permission'] ?? 'view_all';
        if (authz_user_can($conn, (string)$req['table'], (string)$perm)) {
            return true;
        }
    }
    return false;
}

/**
 * Ensure the current user has the specified permission; redirect to unauthorized otherwise.
 */
function authz_require_permission(mysqli $conn, string $table_name, string $permission_type = 'view_all', ?string $fallback_url = null): void {
    if (authz_user_can($conn, $table_name, $permission_type)) {
        return;
    }

    if ($fallback_url === null) {
        $fallback_url = APP_URL . '/public/unauthorized.php';
    }

    $_SESSION['permission_error'] = "You do not have permission to access this resource.";
    $_SESSION['attempted_page'] = $_SERVER['REQUEST_URI'] ?? '';

    header('Location: ' . $fallback_url);
    exit;
}

/**
 * Get the full permission set for a table (defaults to all false).
 */
function authz_get_permission_set(mysqli $conn, string $table_name): array {
    $context = authz_context($conn);
    if (!$context['tables_exist']) {
        $defaults = [];
        foreach (AUTHZ_PERMISSION_FIELDS as $field) {
            $defaults[$field] = true;
        }
        return $defaults;
    }

    $defaults = [];
    foreach (AUTHZ_PERMISSION_FIELDS as $field) {
        $defaults[$field] = false;
    }

    $actual = $context['permissions'][$table_name] ?? [];
    return array_merge($defaults, $actual);
}

/**
 * Ensure a user has at least one role assignment. If none, map legacy role field to new roles.
 */
function authz_ensure_user_role_assignment(mysqli $conn, int $user_id, ?string $legacy_role = null): void {
    if (!authz_roles_tables_exist($conn)) {
        return;
    }

    $stmt = mysqli_prepare($conn, "SELECT 1 FROM user_roles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $has_assignment = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($has_assignment) {
        return;
    }

    $role_map = [
        'super admin' => 'Super Admin',
        'admin' => 'Super Admin',
        'manager' => 'Manager',
        'employee' => 'Employee',
    ];

    $target_role = null;
    if ($legacy_role) {
        $legacy_key = strtolower(trim($legacy_role));
        if (isset($role_map[$legacy_key])) {
            $target_role = $role_map[$legacy_key];
        }
    }

    if ($target_role === null) {
        $target_role = 'Employee';
    }

    $role_stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE name = ? LIMIT 1");
    if (!$role_stmt) {
        return;
    }

    mysqli_stmt_bind_param($role_stmt, 's', $target_role);
    mysqli_stmt_execute($role_stmt);
    $result = mysqli_stmt_get_result($role_stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($role_stmt);

    if (!$row) {
        // Fallback: grab any active role.
        $fallback_stmt = mysqli_prepare($conn, "SELECT id, name FROM roles WHERE status = 'Active' ORDER BY is_system_role DESC, id ASC LIMIT 1");
        if ($fallback_stmt) {
            mysqli_stmt_execute($fallback_stmt);
            $fallback_res = mysqli_stmt_get_result($fallback_stmt);
            $row = $fallback_res ? mysqli_fetch_assoc($fallback_res) : null;
            if ($fallback_res) {
                mysqli_free_result($fallback_res);
            }
            mysqli_stmt_close($fallback_stmt);
        }
    }

    if (!$row || empty($row['id'])) {
        return;
    }

    $role_id = (int)$row['id'];
    $insert_stmt = mysqli_prepare($conn, "INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
    if ($insert_stmt) {
        $assigned_by = $user_id; // self-assigned during bootstrap.
        mysqli_stmt_bind_param($insert_stmt, 'iii', $user_id, $role_id, $assigned_by);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
    }

    // Refresh context cache so the new assignment is considered immediately.
    authz_refresh_context($conn);
}

/**
 * Convenience helper: check whether the current user is considered a Super Admin.
 */
function authz_is_super_admin(mysqli $conn): bool {
    $context = authz_context($conn);
    if (!$context['tables_exist']) {
        return true;
    }
    return $context['is_super_admin'];
}
?>

<?php
/**
 * Permissions System Helper Functions
 * Auto-discover pages, sync database, and manage permissions
 */

/**
 * Recursively scan /public folder for PHP pages
 * Returns array of page information
 */
function scan_public_pages($base_path, $relative_path = '') {
    $pages = [];
    $full_path = $base_path . $relative_path;
    
    // Skip these directories
    $skip_dirs = ['api', 'uploads'];
    
    if (!is_dir($full_path)) {
        return $pages;
    }
    
    $items = scandir($full_path);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $item_path = $full_path . '/' . $item;
        $relative_item_path = $relative_path ? $relative_path . '/' . $item : $item;
        
        if (is_dir($item_path)) {
            // Skip certain directories
            if (in_array($item, $skip_dirs)) {
                continue;
            }
            
            // Recursively scan subdirectory
            $sub_pages = scan_public_pages($base_path, $relative_item_path);
            $pages = array_merge($pages, $sub_pages);
            
        } elseif (is_file($item_path) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            // Parse the path to determine module/submodule
            $path_parts = explode('/', $relative_item_path);
            $filename = array_pop($path_parts);
            
            // Determine module and submodule
            $module = null;
            $submodule = null;
            
            if (count($path_parts) === 0) {
                // Root level file
                $module = 'Core';
            } elseif (count($path_parts) === 1) {
                // First level folder
                $module = ucfirst($path_parts[0]);
            } else {
                // Nested folder
                $module = ucfirst($path_parts[0]);
                $submodule = ucfirst($path_parts[1]);
            }
            
            // Generate display name from filename
            $display_name = generate_display_name($filename);
            
            $pages[] = [
                'page_path' => $relative_item_path,
                'module' => $module,
                'submodule' => $submodule,
                'page_name' => $display_name,
                'filename' => $filename
            ];
        }
    }
    
    return $pages;
}

/**
 * Generate a human-readable display name from filename
 */
function generate_display_name($filename) {
    // Remove .php extension
    $name = str_replace('.php', '', $filename);
    
    // Replace underscores and hyphens with spaces
    $name = str_replace(['_', '-'], ' ', $name);
    
    // Capitalize words
    $name = ucwords($name);
    
    return $name;
}

/**
 * Sync discovered pages with permissions table
 * Adds new pages, marks missing pages as inactive
 */
function sync_permissions_table($conn, $discovered_pages) {
    $stats = [
        'new' => 0,
        'updated' => 0,
        'deactivated' => 0,
        'reactivated' => 0
    ];
    
    $current_time = date('Y-m-d H:i:s');
    
    // Get all existing page paths
    $existing_result = mysqli_query($conn, "SELECT page_path, is_active FROM permissions");
    $existing_pages = [];
    
    while ($row = mysqli_fetch_assoc($existing_result)) {
        $existing_pages[$row['page_path']] = $row['is_active'];
    }
    
    // Track which pages were found in this scan
    $found_paths = [];
    
    // Process discovered pages
    foreach ($discovered_pages as $page) {
        $found_paths[] = $page['page_path'];
        
        if (isset($existing_pages[$page['page_path']])) {
            // Page exists - update it
            $stmt = mysqli_prepare($conn, "
                UPDATE permissions 
                SET module = ?, 
                    submodule = ?, 
                    page_name = ?,
                    is_active = 1,
                    last_scanned = ?
                WHERE page_path = ?
            ");
            
            mysqli_stmt_bind_param($stmt, 'sssss', 
                $page['module'], 
                $page['submodule'], 
                $page['page_name'],
                $current_time,
                $page['page_path']
            );
            
            if (mysqli_stmt_execute($stmt)) {
                if ($existing_pages[$page['page_path']] == 0) {
                    $stats['reactivated']++;
                } else {
                    $stats['updated']++;
                }
            }
            
            mysqli_stmt_close($stmt);
            
        } else {
            // New page - insert it
            $stmt = mysqli_prepare($conn, "
                INSERT INTO permissions 
                (page_path, module, submodule, page_name, last_scanned, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            mysqli_stmt_bind_param($stmt, 'sssss',
                $page['page_path'],
                $page['module'],
                $page['submodule'],
                $page['page_name'],
                $current_time
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $stats['new']++;
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Deactivate pages that no longer exist
    foreach ($existing_pages as $path => $is_active) {
        if (!in_array($path, $found_paths) && $is_active == 1) {
            $stmt = mysqli_prepare($conn, "UPDATE permissions SET is_active = 0 WHERE page_path = ?");
            mysqli_stmt_bind_param($stmt, 's', $path);
            
            if (mysqli_stmt_execute($stmt)) {
                $stats['deactivated']++;
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    return $stats;
}

/**
 * Get all permissions grouped by module and submodule
 */
function get_permissions_grouped($conn, $include_inactive = false) {
    $where_clause = $include_inactive ? '' : 'WHERE is_active = 1';
    
    $query = "
        SELECT id, page_path, module, submodule, page_name,
               can_create,
               can_view_all, can_view_assigned, can_view_own,
               can_edit_all, can_edit_assigned, can_edit_own,
               can_delete_all, can_delete_assigned, can_delete_own,
               can_export, is_active
        FROM permissions
        $where_clause
        ORDER BY module, submodule, page_name
    ";
    
    $result = mysqli_query($conn, $query);
    $grouped = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $module = $row['module'];
        $submodule = $row['submodule'] ?? '';
        
        if (!isset($grouped[$module])) {
            $grouped[$module] = [
                'submodules' => [],
                'pages' => []
            ];
        }
        
        if ($submodule) {
            if (!isset($grouped[$module]['submodules'][$submodule])) {
                $grouped[$module]['submodules'][$submodule] = [];
            }
            $grouped[$module]['submodules'][$submodule][] = $row;
        } else {
            $grouped[$module]['pages'][] = $row;
        }
    }
    
    return $grouped;
}

/**
 * Get role permissions for a specific permission ID
 */
function get_role_permissions_for_page($conn, $permission_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT role_id, 
               can_create,
               can_view_all, can_view_assigned, can_view_own,
               can_edit_all, can_edit_assigned, can_edit_own,
               can_delete_all, can_delete_assigned, can_delete_own,
               can_export
        FROM role_permissions
        WHERE permission_id = ?
    ");
    
    mysqli_stmt_bind_param($stmt, 'i', $permission_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[$row['role_id']] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    return $permissions;
}

/**
 * Update a specific permission for a role
 */
function update_role_permission($conn, $role_id, $permission_id, $permission_type, $value) {
    // Valid permission types
    $valid_types = [
        'can_create',
        'can_view_all', 'can_view_assigned', 'can_view_own',
        'can_edit_all', 'can_edit_assigned', 'can_edit_own',
        'can_delete_all', 'can_delete_assigned', 'can_delete_own',
        'can_export'
    ];
    
    if (!in_array($permission_type, $valid_types)) {
        return false;
    }
    
    // Check if role_permission record exists
    $check_stmt = mysqli_prepare($conn, "
        SELECT id FROM role_permissions 
        WHERE role_id = ? AND permission_id = ?
    ");
    
    mysqli_stmt_bind_param($check_stmt, 'ii', $role_id, $permission_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $exists = mysqli_num_rows($check_result) > 0;
    mysqli_stmt_close($check_stmt);
    
    if ($exists) {
        // Update existing record
        $update_sql = "UPDATE role_permissions SET $permission_type = ? WHERE role_id = ? AND permission_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'iii', $value, $role_id, $permission_id);
    } else {
        // Insert new record with this permission enabled
        $insert_sql = "INSERT INTO role_permissions (role_id, permission_id, $permission_type) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, 'iii', $role_id, $permission_id, $value);
    }
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success;
}

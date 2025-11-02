<?php
/**
 * Table-Based Permissions Helper Functions
 * 
 * Functions for managing database table-based access control
 */

/**
 * Scan database and return all tables
 * @param mysqli $conn Database connection
 * @return array List of tables with metadata
 */
function scan_database_tables($conn) {
    $tables = [];
    
    // Get all tables in the database
    $result = mysqli_query($conn, "SHOW TABLES");
    
    if (!$result) {
        return [];
    }
    
    while ($row = mysqli_fetch_array($result)) {
        $table_name = $row[0];
        
        // Skip system/internal tables
        if (in_array($table_name, ['migrations', 'sessions'])) {
            continue;
        }
        
        // Try to guess module based on table name
        $module = guess_module_from_table($table_name);
        
        // Generate display name
        $display_name = generate_display_name($table_name);
        
        // Get table statistics
        $stats = get_table_stats($conn, $table_name);
        
        $tables[] = [
            'table_name' => $table_name,
            'module' => $module,
            'display_name' => $display_name,
            'row_count' => $stats['row_count'],
            'size_mb' => $stats['size_mb']
        ];
    }
    
    mysqli_free_result($result);
    
    // Sort by module, then table name
    usort($tables, function($a, $b) {
        if ($a['module'] === $b['module']) {
            return strcmp($a['table_name'], $b['table_name']);
        }
        return strcmp($a['module'], $b['module']);
    });
    
    return $tables;
}

/**
 * Guess module name from table name
 * Based on 22 modules from sidebar navigation
 */
function guess_module_from_table($table_name) {
    // Exact table name matches (most specific)
    $exact_matches = [
        'employees' => 'Employees',
        'departments' => 'Others',
        'designations' => 'Others',
        'attendance' => 'Attendance',
        'leave_types' => 'Others',
        'leave_requests' => 'Attendance',
        'holidays' => 'Others',
        'shift_types' => 'Attendance',
        'reimbursements' => 'Reimbursements',
        'crm_leads' => 'CRM',
        'crm_deals' => 'CRM',
        'crm_activities' => 'CRM',
        'crm_contacts' => 'CRM',
        'crm_companies' => 'CRM',
        'office_expenses' => 'Expenses',
        'expense_categories' => 'Expenses',
        'salary_records' => 'Salary',
        'salary_slips' => 'Salary',
        'salary_components' => 'Salary',
        'documents' => 'Documents',
        'document_categories' => 'Documents',
        'visitor_logs' => 'Visitor Log',
        'notebook_notes' => 'Notebook',
        'notebook_categories' => 'Notebook',
        'contacts' => 'Contacts',
        'contact_groups' => 'Contacts',
        'clients' => 'Clients',
        'client_contacts' => 'Clients',
        'client_contacts_map' => 'Clients',
        'client_documents' => 'Clients',
        'projects' => 'Projects',
        'project_tasks' => 'Projects',
        'project_milestones' => 'Projects',
        'project_documents' => 'Projects',
        'items_master' => 'Catalog',
        'item_categories' => 'Catalog',
        'item_units' => 'Catalog',
        'quotations' => 'Quotations',
        'quotation_items' => 'Quotations',
        'invoices' => 'Invoices',
        'invoice_items' => 'Invoices',
        'payments' => 'Payments',
        'payment_methods' => 'Payments',
        'payment_invoice_map' => 'Payments',
        'payroll_master' => 'Payroll',
        'payroll_records' => 'Payroll',
        'payroll_allowances' => 'Payroll',
        'payroll_deductions' => 'Payroll',
        'payroll_activity_log' => 'Payroll',
        'data_transfer_logs' => 'Data Transfer',
        'assets_master' => 'Assets',
        'asset_categories' => 'Assets',
        'asset_assignments' => 'Assets',
        'branding_settings' => 'Branding',
        'branding' => 'Branding',
        'roles' => 'Roles & Permissions',
        'permissions' => 'Roles & Permissions',
        'role_permissions' => 'Roles & Permissions',
        'users' => 'Users Management',
        'user_roles' => 'Users Management',
    ];
    
    // Check exact match first
    if (isset($exact_matches[$table_name])) {
        return $exact_matches[$table_name];
    }
    
    // Pattern matching (prefix-based)
    $pattern_matches = [
        'employee' => 'Employees',
        'reimbursement' => 'Reimbursements',
        'crm_' => 'CRM',
        'expense' => 'Expenses',
        'salary' => 'Salary',
        'document' => 'Documents',
        'visitor' => 'Visitor Log',
        'notebook' => 'Notebook',
        'contact' => 'Contacts',
        'client' => 'Clients',
        'project' => 'Projects',
        'item' => 'Catalog',
        'quotation' => 'Quotations',
        'invoice' => 'Invoices',
        'payment' => 'Payments',
        'payroll' => 'Payroll',
        'data_transfer' => 'Data Transfer',
        'asset' => 'Assets',
        'branding' => 'Branding',
        'role' => 'Roles & Permissions',
        'permission' => 'Roles & Permissions',
        'user' => 'Users Management',
    ];
    
    foreach ($pattern_matches as $pattern => $module) {
        if (stripos($table_name, $pattern) !== false) {
            return $module;
        }
    }
    
    return 'Others';
}

/**
 * Generate human-readable display name from table name
 */
function generate_display_name($table_name) {
    // Remove common prefixes
    $name = preg_replace('/^(crm_|tbl_)/', '', $table_name);
    
    // Replace underscores with spaces
    $name = str_replace('_', ' ', $name);
    
    // Capitalize words
    $name = ucwords($name);
    
    return $name;
}

/**
 * Get table statistics (row count and size)
 */
function get_table_stats($conn, $table_name) {
    $stats = [
        'row_count' => 0,
        'size_mb' => 0
    ];
    
    // Get row count
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$table_name`");
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $stats['row_count'] = (int)$count_row['cnt'];
        mysqli_free_result($count_result);
    }
    
    // Get table size
    $db_name = DB_NAME;
    $size_query = "
        SELECT 
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = '$db_name'
        AND table_name = '$table_name'
    ";
    
    $size_result = mysqli_query($conn, $size_query);
    if ($size_result) {
        $size_row = mysqli_fetch_assoc($size_result);
        $stats['size_mb'] = (float)$size_row['size_mb'];
        mysqli_free_result($size_result);
    }
    
    return $stats;
}

/**
 * Sync discovered tables with permissions table
 */
function sync_permissions_with_tables($conn, $discovered_tables) {
    $stats = [
        'new' => 0,
        'updated' => 0,
        'deactivated' => 0,
        'reactivated' => 0
    ];
    
    $current_time = date('Y-m-d H:i:s');
    
    // Get all existing tables from permissions
    $existing_result = mysqli_query($conn, "SELECT table_name, is_active FROM permissions");
    $existing_tables = [];
    
    if ($existing_result) {
        while ($row = mysqli_fetch_assoc($existing_result)) {
            $existing_tables[$row['table_name']] = $row['is_active'];
        }
        mysqli_free_result($existing_result);
    }
    
    // Track which tables were found in this scan
    $found_tables = [];
    
    // Process discovered tables
    foreach ($discovered_tables as $table) {
        $found_tables[] = $table['table_name'];
        
        if (isset($existing_tables[$table['table_name']])) {
            // Table exists - update it
            $was_inactive = $existing_tables[$table['table_name']] == 0;
            
            $stmt = mysqli_prepare($conn, "
                UPDATE permissions 
                SET module = ?, 
                    display_name = ?,
                    is_active = 1,
                    last_scanned = ?
                WHERE table_name = ?
            ");
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssss', 
                    $table['module'], 
                    $table['display_name'],
                    $current_time,
                    $table['table_name']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    if ($was_inactive) {
                        $stats['reactivated']++;
                    } else {
                        $stats['updated']++;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // New table - insert it
            $description = "Access control for " . $table['display_name'] . " table";
            
            $stmt = mysqli_prepare($conn, "
                INSERT INTO permissions 
                (table_name, module, display_name, description, is_active, last_scanned, created_at)
                VALUES (?, ?, ?, ?, 1, ?, NOW())
            ");
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss',
                    $table['table_name'],
                    $table['module'],
                    $table['display_name'],
                    $description,
                    $current_time
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $stats['new']++;
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Deactivate tables that no longer exist
    foreach ($existing_tables as $table_name => $is_active) {
        if (!in_array($table_name, $found_tables) && $is_active == 1) {
            $stmt = mysqli_prepare($conn, "UPDATE permissions SET is_active = 0 WHERE table_name = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $table_name);
                if (mysqli_stmt_execute($stmt)) {
                    $stats['deactivated']++;
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    return $stats;
}

/**
 * Get permissions grouped by module
 */
function get_permissions_grouped($conn) {
    $result = mysqli_query($conn, "
        SELECT * FROM permissions 
        WHERE is_active = 1 
        ORDER BY module, display_name
    ");
    
    if (!$result) {
        return [];
    }
    
    $grouped = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $module = $row['module'];
        if (!isset($grouped[$module])) {
            $grouped[$module] = [];
        }
        $grouped[$module][] = $row;
    }
    
    mysqli_free_result($result);
    
    return $grouped;
}

/**
 * Update role permission for a specific table
 */
function update_table_permission($conn, $role_id, $permission_id, $permission_type, $value) {
    // Check if role-permission mapping exists
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    mysqli_stmt_bind_param($check_stmt, 'ii', $role_id, $permission_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $exists = mysqli_num_rows($check_result) > 0;
    mysqli_stmt_close($check_stmt);
    
    $permission_type = mysqli_real_escape_string($conn, $permission_type);
    
    if ($exists) {
        // Update existing
        $update_query = "UPDATE role_permissions SET $permission_type = ? WHERE role_id = ? AND permission_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, 'iii', $value, $role_id, $permission_id);
    } else {
        // Insert new
        $insert_query = "INSERT INTO role_permissions (role_id, permission_id, $permission_type) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'iii', $role_id, $permission_id, $value);
    }
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * Check if user has permission for a specific table
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $table_name Database table name
 * @param string $permission_type Permission type (create, view_all, view_assigned, view_own, edit_all, edit_assigned, edit_own, delete_all, delete_assigned, delete_own, export)
 * @return bool True if user has permission
 */
function has_table_permission($conn, $user_id, $table_name, $permission_type = 'view_all') {
    $permission_field = 'can_' . strtolower($permission_type);
    
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as has_access
        FROM role_permissions rp
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        INNER JOIN permissions p ON rp.permission_id = p.id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? 
        AND p.table_name = ? 
        AND rp.$permission_field = 1
        AND r.status = 'Active'
        AND p.is_active = 1
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $table_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['has_access'] > 0;
    }
    
    return false;
}

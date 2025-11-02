<?php
/**
 * Asset & Resource Management Module - Helper Functions
 * Core functions for asset CRUD, allocation, maintenance, and activity logging
 */

require_once __DIR__ . '/../../config/db_connect.php';

// ==================== ASSET CODE GENERATION ====================

/**
 * Generate unique asset code
 * Format: AST-YYYY-NNNN
 */
function generateAssetCode($conn) {
    $year = date('Y');
    $prefix = "AST-$year-";
    
    // Get last code for current year
    $query = "SELECT asset_code FROM assets_master WHERE asset_code LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    $search_pattern = $prefix . '%';
    mysqli_stmt_bind_param($stmt, 's', $search_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Extract number and increment
        $last_code = $row['asset_code'];
        $last_num = (int)substr($last_code, -4);
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }
    
    mysqli_stmt_close($stmt);
    
    return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
}

// ==================== CONTEXT VALIDATION ====================

/**
 * Validate context_id exists in respective table
 */
function validateContext($conn, $context_type, $context_id) {
    $table_map = [
        'Employee' => 'employees',
        'Project' => 'projects',
        'Client' => 'clients',
        'Lead' => 'crm_leads'
    ];
    
    if (!isset($table_map[$context_type])) {
        return false;
    }
    
    $table = $table_map[$context_type];
    $query = "SELECT id FROM $table WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $context_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $exists;
}

/**
 * Get context name for display
 */
function getContextName($conn, $context_type, $context_id) {
    // Check if connection is valid and open, handle closed object gracefully
    $is_valid = false;
    if ($conn instanceof mysqli) {
        try {
            $is_valid = @$conn->ping();
        } catch (Throwable $e) {
            $is_valid = false;
        }
    }
    if (!$is_valid) {
        return 'Unknown (DB connection closed)';
    }

    $query_map = [
        'Employee' => "SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?",
        'Project' => "SELECT project_name as name FROM projects WHERE id = ?",
        'Client' => "SELECT client_name as name FROM clients WHERE id = ?",
        'Lead' => "SELECT lead_name as name FROM crm_leads WHERE id = ?"
    ];

    if (!isset($query_map[$context_type])) {
        return 'Unknown';
    }

    $stmt = mysqli_prepare($conn, $query_map[$context_type]);
    if (!$stmt) {
        return 'Unknown (prepare failed)';
    }
    mysqli_stmt_bind_param($stmt, 'i', $context_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $name = 'Unknown';

    if ($row = mysqli_fetch_assoc($result)) {
        $name = $row['name'];
    }

    mysqli_stmt_close($stmt);
    return $name;
}

// ==================== ASSET CRUD ====================

/**
 * Create new asset
 */
function createAsset($conn, $data, $user_id) {
    $asset_code = generateAssetCode($conn);
    
    $query = "INSERT INTO assets_master (
        asset_code, name, category, type, make, model, serial_no,
        department, location, `condition`, status, purchase_date,
        purchase_cost, vendor, warranty_expiry, notes, primary_image,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssssssssdssssi',
        $asset_code,
        $data['name'],
        $data['category'],
        $data['type'],
        $data['make'],
        $data['model'],
        $data['serial_no'],
        $data['department'],
        $data['location'],
        $data['condition'],
        $data['status'],
        $data['purchase_date'],
        $data['purchase_cost'],
        $data['vendor'],
        $data['warranty_expiry'],
        $data['notes'],
        $data['primary_image'],
        $user_id
    );
    
    $success = mysqli_stmt_execute($stmt);
    $asset_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // Log activity
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Create', null, null, "Asset created: {$data['name']}");
    }
    
    return $success ? $asset_id : false;
}

/**
 * Update asset
 */
function updateAsset($conn, $asset_id, $data, $user_id) {
    $query = "UPDATE assets_master SET
        name = ?, category = ?, type = ?, make = ?, model = ?, serial_no = ?,
        department = ?, location = ?, `condition` = ?, status = ?,
        purchase_date = ?, purchase_cost = ?, vendor = ?, warranty_expiry = ?,
        notes = ?, primary_image = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssssssssssdssssi',
        $data['name'],
        $data['category'],
        $data['type'],
        $data['make'],
        $data['model'],
        $data['serial_no'],
        $data['department'],
        $data['location'],
        $data['condition'],
        $data['status'],
        $data['purchase_date'],
        $data['purchase_cost'],
        $data['vendor'],
        $data['warranty_expiry'],
        $data['notes'],
        $data['primary_image'],
        $asset_id
    );
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Update', null, null, "Asset updated: {$data['name']}");
    }
    
    return $success;
}

/**
 * Get asset by ID
 */
function getAssetById($conn, $asset_id) {
    $query = "SELECT a.*, u.full_name as created_by_name
              FROM assets_master a
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $asset = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $asset;
}

/**
 * Get all assets with filters
 */
function getAssets($conn, $filters = []) {
    $query = "SELECT a.*, u.full_name as created_by_name
              FROM assets_master a
              LEFT JOIN users u ON a.created_by = u.id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (!empty($filters['category'])) {
        $query .= " AND a.category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND a.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    if (!empty($filters['department'])) {
        $query .= " AND a.department = ?";
        $params[] = $filters['department'];
        $types .= 's';
    }
    
    if (!empty($filters['search'])) {
        $query .= " AND (a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_no LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    if (!empty($filters['warranty_expiring'])) {
        $days = (int)$filters['warranty_expiring'];
        $query .= " AND a.warranty_expiry IS NOT NULL AND a.warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $params[] = $days;
        $types .= 'i';
    }
    
    $query .= " ORDER BY a.created_at DESC";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }
    
    $assets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $assets[] = $row;
    }
    
    if (isset($stmt)) mysqli_stmt_close($stmt);
    
    return $assets;
}

// ==================== STATUS MANAGEMENT ====================

/**
 * Change asset status
 */
function changeAssetStatus($conn, $asset_id, $new_status, $user_id, $remarks = null) {
    // Get current status
    $asset = getAssetById($conn, $asset_id);
    if (!$asset) return false;
    
    $old_status = $asset['status'];
    
    // Update asset status
    $query = "UPDATE assets_master SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'si', $new_status, $asset_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // Log to status_log
        $log_query = "INSERT INTO asset_status_log (asset_id, old_status, new_status, changed_by, remarks)
                      VALUES (?, ?, ?, ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, 'issss', $asset_id, $old_status, $new_status, $user_id, $remarks);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        // Log activity
        $log_id = mysqli_insert_id($conn);
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Status', 'asset_status_log', $log_id,
            "Status changed from $old_status to $new_status");
    }
    
    return $success;
}

// ==================== ALLOCATION MANAGEMENT ====================

/**
 * Check if asset has active allocation
 */
function hasActiveAllocation($conn, $asset_id) {
    $query = "SELECT id FROM asset_allocation_log WHERE asset_id = ? AND status = 'Active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_active = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $has_active;
}

/**
 * Get active allocation for asset
 */
function getActiveAllocation($conn, $asset_id) {
    $query = "SELECT * FROM asset_allocation_log WHERE asset_id = ? AND status = 'Active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $allocation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $allocation;
}

/**
 * Assign asset to context
 */
function assignAsset($conn, $asset_id, $context_type, $context_id, $purpose, $assigned_by, $expected_return = null) {
    // Validate context
    if (!validateContext($conn, $context_type, $context_id)) {
        return ['success' => false, 'message' => 'Invalid context'];
    }
    
    // Check if asset already has active allocation
    if (hasActiveAllocation($conn, $asset_id)) {
        return ['success' => false, 'message' => 'Asset already has an active allocation'];
    }
    
    // Get asset details
    $asset = getAssetById($conn, $asset_id);
    if (!$asset) {
        return ['success' => false, 'message' => 'Asset not found'];
    }
    
    // Check if asset is available
    if ($asset['status'] === 'Broken' || $asset['status'] === 'Decommissioned') {
        return ['success' => false, 'message' => 'Asset cannot be assigned in current status'];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Create allocation
        $query = "INSERT INTO asset_allocation_log (
            asset_id, context_type, context_id, purpose, assigned_by, assigned_on, expected_return, status
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Active')";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isisss', $asset_id, $context_type, $context_id, $purpose, $assigned_by, $expected_return);
        mysqli_stmt_execute($stmt);
        $allocation_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Update asset status to "In Use"
        changeAssetStatus($conn, $asset_id, 'In Use', $assigned_by, "Assigned to $context_type");
        
        // Log activity
        $context_name = getContextName($conn, $context_type, $context_id);
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $assigned_by, 'Allocate', 'asset_allocation_log', $allocation_id,
            "Assigned to $context_type: $context_name");
        
        mysqli_commit($conn);
        return ['success' => true, 'allocation_id' => $allocation_id];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Return asset from allocation
 */
function returnAsset($conn, $allocation_id, $user_id) {
    $allocation = null;
    $query = "SELECT * FROM asset_allocation_log WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $allocation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $allocation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$allocation || $allocation['status'] !== 'Active') {
        return ['success' => false, 'message' => 'Allocation not found or already closed'];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Update allocation
        $query = "UPDATE asset_allocation_log SET returned_on = NOW(), status = 'Returned' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $allocation_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Update asset status to "Available"
        changeAssetStatus($conn, $allocation['asset_id'], 'Available', $user_id, 'Asset returned');
        
        // Log activity
        logAssetActivity($conn, $allocation['asset_id'], $_SESSION['user_id'] ?? $user_id, 'Return', 'asset_allocation_log', $allocation_id,
            "Asset returned from {$allocation['context_type']}");
        
        mysqli_commit($conn);
        return ['success' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Transfer asset to new context
 */
function transferAsset($conn, $asset_id, $new_context_type, $new_context_id, $purpose, $user_id, $expected_return = null) {
    // Get active allocation
    $active = getActiveAllocation($conn, $asset_id);
    if (!$active) {
        return ['success' => false, 'message' => 'No active allocation found'];
    }
    
    // Validate new context
    if (!validateContext($conn, $new_context_type, $new_context_id)) {
        return ['success' => false, 'message' => 'Invalid new context'];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Close current allocation as Transferred
        $query = "UPDATE asset_allocation_log SET returned_on = NOW(), status = 'Transferred' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $active['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Create new allocation
        $query = "INSERT INTO asset_allocation_log (
            asset_id, context_type, context_id, purpose, assigned_by, assigned_on, expected_return, status
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Active')";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isisss', $asset_id, $new_context_type, $new_context_id, $purpose, $user_id, $expected_return);
        mysqli_stmt_execute($stmt);
        $new_allocation_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Log activity
        $new_context_name = getContextName($conn, $new_context_type, $new_context_id);
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Transfer', 'asset_allocation_log', $new_allocation_id,
            "Transferred from {$active['context_type']} to $new_context_type: $new_context_name");
        
        mysqli_commit($conn);
        return ['success' => true, 'allocation_id' => $new_allocation_id];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get allocation history for asset
 */
function getAllocationHistory($conn, $asset_id) {
    $query = "SELECT a.*, u.full_name as assigned_by_name
              FROM asset_allocation_log a
              LEFT JOIN users u ON a.assigned_by = u.id
              WHERE a.asset_id = ?
              ORDER BY a.assigned_on DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['context_name'] = getContextName($conn, $row['context_type'], $row['context_id']);
        $history[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $history;
}

// ==================== MAINTENANCE MANAGEMENT ====================

/**
 * Add maintenance job
 */
function addMaintenanceJob($conn, $asset_id, $data, $user_id) {
    mysqli_begin_transaction($conn);
    
    try {
        // Create maintenance record
        $query = "INSERT INTO asset_maintenance_log (
            asset_id, job_date, technician, description, cost, next_due, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isssdssi',
            $asset_id,
            $data['job_date'],
            $data['technician'],
            $data['description'],
            $data['cost'],
            $data['next_due'],
            $data['status'],
            $user_id
        );
        mysqli_stmt_execute($stmt);
        $job_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // If job is Open, set asset to Under Maintenance
        if ($data['status'] === 'Open') {
            changeAssetStatus($conn, $asset_id, 'Under Maintenance', $user_id, 'Maintenance job opened');
        }
        
        // Log activity
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Maintenance', 'asset_maintenance_log', $job_id,
            "Maintenance job added: {$data['description']}");
        
        mysqli_commit($conn);
        return ['success' => true, 'job_id' => $job_id];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Close maintenance job
 */
function closeMaintenanceJob($conn, $job_id, $user_id) {
    // Get job details
    $query = "SELECT * FROM asset_maintenance_log WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $job_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $job = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$job || $job['status'] === 'Completed') {
        return ['success' => false, 'message' => 'Job not found or already completed'];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Update job status
        $query = "UPDATE asset_maintenance_log SET status = 'Completed' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $job_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Check if asset has active allocation, if not set to Available
        if (!hasActiveAllocation($conn, $job['asset_id'])) {
            changeAssetStatus($conn, $job['asset_id'], 'Available', $user_id, 'Maintenance completed');
        } else {
            changeAssetStatus($conn, $job['asset_id'], 'In Use', $user_id, 'Maintenance completed');
        }
        
        // Log activity
        logAssetActivity($conn, $job['asset_id'], $_SESSION['user_id'] ?? $user_id, 'Maintenance', 'asset_maintenance_log', $job_id,
            "Maintenance job completed");
        
        mysqli_commit($conn);
        return ['success' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get maintenance history for asset
 */
function getMaintenanceHistory($conn, $asset_id) {
    $query = "SELECT m.*, u.full_name as created_by_name
              FROM asset_maintenance_log m
              LEFT JOIN users u ON m.created_by = u.id
              WHERE m.asset_id = ?
              ORDER BY m.job_date DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $history;
}

// ==================== FILE MANAGEMENT ====================

/**
 * Upload asset file
 */
function uploadAssetFile($conn, $asset_id, $file_type, $file_path, $user_id) {
    $query = "INSERT INTO asset_files (asset_id, file_type, file_path, uploaded_by)
              VALUES (?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'issi', $asset_id, $file_type, $file_path, $user_id);
    $success = mysqli_stmt_execute($stmt);
    $file_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        logAssetActivity($conn, $asset_id, $_SESSION['user_id'] ?? $user_id, 'Attach', 'asset_files', $file_id,
            "File uploaded: $file_type");
    }
    
    return $success ? $file_id : false;
}

/**
 * Get files for asset
 */
function getAssetFiles($conn, $asset_id) {
    $query = "SELECT f.*, u.full_name as uploaded_by_name
              FROM asset_files f
              LEFT JOIN users u ON f.uploaded_by = u.id
              WHERE f.asset_id = ?
              ORDER BY f.uploaded_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $files = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $files[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $files;
}

/**
 * Delete asset file
 */
function deleteAssetFile($conn, $file_id, $user_id) {
    // Get file details
    $query = "SELECT * FROM asset_files WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$file) return false;
    
    // Delete record
    $query = "DELETE FROM asset_files WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $file_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // Delete physical file
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        logAssetActivity($conn, $file['asset_id'], $_SESSION['user_id'] ?? $user_id, 'Detach', 'asset_files', $file_id,
            "File deleted: {$file['file_type']}");
    }
    
    return $success;
}

// ==================== ACTIVITY LOGGING ====================

/**
 * Log asset activity
 */
function logAssetActivity($conn, $asset_id, $user_id, $action, $reference_table = null, $reference_id = null, $description = null) {
    $query = "INSERT INTO asset_activity_log (asset_id, user_id, action, reference_table, reference_id, description)
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'iissis', $asset_id, $user_id, $action, $reference_table, $reference_id, $description);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * Get activity log for asset
 */
function getActivityLog($conn, $asset_id) {
    $query = "SELECT a.*, u.full_name as user_name
              FROM asset_activity_log a
              LEFT JOIN users u ON a.user_id = u.id
              WHERE a.asset_id = ?
              ORDER BY a.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $asset_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $log = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $log[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $log;
}

// ==================== CONTEXT HELPERS ====================

/**
 * Get all employees for allocation dropdown
 */
function getAllEmployees($conn) {
    $query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM employees ORDER BY first_name, last_name";
    $result = mysqli_query($conn, $query);
    
    $employees = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
    
    return $employees;
}

/**
 * Get all projects for allocation dropdown
 */
function getAllProjects($conn) {
    $query = "SELECT id, title as name FROM projects ORDER BY title";
    $result = mysqli_query($conn, $query);
    
    $projects = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }
    
    return $projects;
}

/**
 * Get all clients for allocation dropdown
 */
function getAllClients($conn) {
    $query = "SELECT id, name as name FROM clients ORDER BY name";
    $result = mysqli_query($conn, $query);
    
    $clients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clients[] = $row;
    }
    
    return $clients;
}

/**
 * Get all leads for allocation dropdown
 */
function getAllLeads($conn) {
    $query = "SELECT id, name as name FROM crm_leads ORDER BY name";
    $result = mysqli_query($conn, $query);
    
    $leads = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $leads[] = $row;
    }
    
    return $leads;
}

// ==================== DASHBOARD STATS ====================

/**
 * Get dashboard statistics
 */
function getDashboardStats($conn) {
    $stats = [];
    
    // Total assets
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets_master");
    $stats['total_assets'] = mysqli_fetch_assoc($result)['total'];
    
    // Status breakdown
    $result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM assets_master GROUP BY status");
    $stats['by_status'] = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats['by_status'][$row['status']] = $row['count'];
    }
    
    // Category breakdown
    $result = mysqli_query($conn, "SELECT category, COUNT(*) as count FROM assets_master GROUP BY category");
    $stats['by_category'] = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats['by_category'][$row['category']] = $row['count'];
    }
    
    // Overdue returns
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocation_log 
                                   WHERE status = 'Active' AND expected_return < CURDATE()");
    $stats['overdue_returns'] = mysqli_fetch_assoc($result)['count'];
    
    // Expiring warranties (next 30 days)
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM assets_master 
                                   WHERE warranty_expiry IS NOT NULL 
                                   AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stats['expiring_warranties'] = mysqli_fetch_assoc($result)['count'];
    
    // Open maintenance jobs
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_maintenance_log WHERE status = 'Open'");
    $stats['open_maintenance'] = mysqli_fetch_assoc($result)['count'];
    
    return $stats;
}

/**
 * Get recent activity
 */
function getRecentActivity($conn, $limit = 10) {
    $query = "SELECT a.*, ast.name as asset_name, ast.asset_code, u.full_name as user_name
              FROM asset_activity_log a
              LEFT JOIN assets_master ast ON a.asset_id = ast.id
              LEFT JOIN users u ON a.user_id = u.id
              ORDER BY a.created_at DESC
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $activity = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $activity[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $activity;
}

<?php
/**
 * Asset & Resource Management Module - Database Setup Script
 * Creates all necessary tables for comprehensive asset tracking and management
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_assets_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'assets_master'");
    $already_exists = $check && mysqli_num_rows($check) > 0;

    // 1. Create assets_master table
    $sql_assets = "CREATE TABLE IF NOT EXISTS assets_master (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_code VARCHAR(40) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        category ENUM('IT', 'Vehicle', 'Tool', 'Machine', 'Furniture', 'Space', 'Other') NOT NULL,
        type VARCHAR(120) NULL,
        make VARCHAR(120) NULL,
        model VARCHAR(120) NULL,
        serial_no VARCHAR(150) NULL,
        department VARCHAR(120) NULL,
        location VARCHAR(150) NULL,
        `condition` ENUM('New', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
        status ENUM('Available', 'In Use', 'Under Maintenance', 'Broken', 'Decommissioned') DEFAULT 'Available',
        purchase_date DATE NULL,
        purchase_cost DECIMAL(12,2) NULL,
        vendor VARCHAR(150) NULL,
        warranty_expiry DATE NULL,
        notes TEXT NULL,
        primary_image TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_asset_code (asset_code),
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_warranty_expiry (warranty_expiry),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_assets) === TRUE) {
        $tables_created[] = 'assets_master';
    } else {
        $errors[] = "assets_master: " . $conn->error;
    }

    // 2. Create asset_allocation_log table
    $sql_allocation = "CREATE TABLE IF NOT EXISTS asset_allocation_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        context_type ENUM('Employee', 'Project', 'Client', 'Lead') NOT NULL,
        context_id INT NOT NULL,
        purpose VARCHAR(255) NULL,
        assigned_by INT UNSIGNED NOT NULL,
        assigned_on DATETIME NOT NULL,
        expected_return DATE NULL,
        returned_on DATETIME NULL,
        status ENUM('Active', 'Returned', 'Transferred') DEFAULT 'Active',
        INDEX idx_asset_id (asset_id),
        INDEX idx_context (context_type, context_id),
        INDEX idx_status (status),
        INDEX idx_assigned_on (assigned_on),
        FOREIGN KEY (asset_id) REFERENCES assets_master(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_allocation) === TRUE) {
        $tables_created[] = 'asset_allocation_log';
    } else {
        $errors[] = "asset_allocation_log: " . $conn->error;
    }

    // 3. Create asset_status_log table
    $sql_status_log = "CREATE TABLE IF NOT EXISTS asset_status_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        old_status ENUM('Available', 'In Use', 'Under Maintenance', 'Broken', 'Decommissioned') NULL,
        new_status ENUM('Available', 'In Use', 'Under Maintenance', 'Broken', 'Decommissioned') NOT NULL,
        changed_by INT UNSIGNED NOT NULL,
        remarks VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset_id (asset_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (asset_id) REFERENCES assets_master(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_status_log) === TRUE) {
        $tables_created[] = 'asset_status_log';
    } else {
        $errors[] = "asset_status_log: " . $conn->error;
    }

    // 4. Create asset_maintenance_log table
    $sql_maintenance = "CREATE TABLE IF NOT EXISTS asset_maintenance_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        job_date DATE NOT NULL,
        technician VARCHAR(150) NULL,
        description TEXT NOT NULL,
        cost DECIMAL(12,2) NULL,
        next_due DATE NULL,
        status ENUM('Open', 'Completed') DEFAULT 'Open',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset_id (asset_id),
        INDEX idx_status (status),
        INDEX idx_next_due (next_due),
        FOREIGN KEY (asset_id) REFERENCES assets_master(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_maintenance) === TRUE) {
        $tables_created[] = 'asset_maintenance_log';
    } else {
        $errors[] = "asset_maintenance_log: " . $conn->error;
    }

    // 5. Create asset_files table
    $sql_files = "CREATE TABLE IF NOT EXISTS asset_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        file_type ENUM('Bill', 'Warranty', 'Manual', 'Service', 'Photo', 'Other') NOT NULL,
        file_path TEXT NOT NULL,
        uploaded_by INT UNSIGNED NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset_id (asset_id),
        INDEX idx_file_type (file_type),
        FOREIGN KEY (asset_id) REFERENCES assets_master(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_files) === TRUE) {
        $tables_created[] = 'asset_files';
    } else {
        $errors[] = "asset_files: " . $conn->error;
    }

    // 6. Create asset_activity_log table
    $sql_activity = "CREATE TABLE IF NOT EXISTS asset_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action ENUM('Create', 'Update', 'Allocate', 'Return', 'Transfer', 'Status', 'Maintenance', 'Attach', 'Detach') NOT NULL,
        reference_table VARCHAR(60) NULL,
        reference_id INT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset_id (asset_id),
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (asset_id) REFERENCES assets_master(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_activity) === TRUE) {
        $tables_created[] = 'asset_activity_log';
    } else {
        $errors[] = "asset_activity_log: " . $conn->error;
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Asset tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Asset module tables created: ' . implode(', ', $tables_created)];
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection();
    $result = setup_assets_module($conn);
    closeConnection($conn);
    
    echo "<h1>Asset Management Module Setup</h1>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='../public/assets/index.php'>Go to Asset Management Dashboard</a></p>";
    }
    echo "<p><a href='../setup/index.php'>Back to Setup</a></p>";
}

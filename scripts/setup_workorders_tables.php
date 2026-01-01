<?php
/**
 * Work Orders Module - Database Setup Script
 * Creates all required tables for the Work Order Management system
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_workorders_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'work_orders'");
    $already_exists = $check && mysqli_num_rows($check) > 0;
    
    // 1. Create work_orders table
    $sql_work_orders = "CREATE TABLE IF NOT EXISTS `work_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_code` VARCHAR(20) NOT NULL UNIQUE,
        `order_date` DATE NOT NULL,
        `linked_type` ENUM('Lead', 'Client') NOT NULL,
        `linked_id` INT NOT NULL,
        `service_type` VARCHAR(255) NOT NULL,
        `priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
        `start_date` DATE NOT NULL,
        `due_date` DATE NOT NULL,
        `tat_days` INT GENERATED ALWAYS AS (DATEDIFF(`due_date`, `start_date`)) STORED,
        `status` ENUM('Draft', 'In Progress', 'Internal Review', 'Client Review', 'Delivered', 'Closed') NOT NULL DEFAULT 'Draft',
        `description` TEXT,
        `remarks` TEXT,
        `dependencies` TEXT,
        `exceptions` TEXT,
        `internal_approver` INT,
        `internal_approval_date` DATETIME,
        `client_approver` VARCHAR(255),
        `client_approval_date` DATETIME,
        `quotation_id` INT,
        `invoice_id` INT,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_work_order_code` (`work_order_code`),
        INDEX `idx_linked` (`linked_type`, `linked_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_priority` (`priority`),
        INDEX `idx_dates` (`start_date`, `due_date`),
        INDEX `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_work_orders)) {
        $tables_created[] = 'work_orders';
    } else {
        $errors[] = "work_orders: " . mysqli_error($conn);
    }

    // 2. Create work_order_team table
    $sql_team = "CREATE TABLE IF NOT EXISTS `work_order_team` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `role` VARCHAR(255) NOT NULL,
        `remarks` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_employee` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_team)) {
        $tables_created[] = 'work_order_team';
    } else {
        $errors[] = "work_order_team: " . mysqli_error($conn);
    }

    // 3. Create work_order_deliverables table
    $sql_deliverables = "CREATE TABLE IF NOT EXISTS `work_order_deliverables` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `deliverable_name` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `start_date` DATE NOT NULL,
        `due_date` DATE NOT NULL,
        `assigned_to` INT NOT NULL,
        `attachment_path` TEXT,
        `approval_internal` BOOLEAN DEFAULT 0,
        `approval_internal_date` DATETIME,
        `approval_client` BOOLEAN DEFAULT 0,
        `approval_client_date` DATETIME,
        `delivery_status` ENUM('Pending', 'In Progress', 'Ready', 'Delivered') NOT NULL DEFAULT 'Pending',
        `delivered_date` DATETIME,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_assigned` (`assigned_to`),
        INDEX `idx_status` (`delivery_status`),
        INDEX `idx_dates` (`start_date`, `due_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_deliverables)) {
        $tables_created[] = 'work_order_deliverables';
    } else {
        $errors[] = "work_order_deliverables: " . mysqli_error($conn);
    }

    // 4. Create work_order_files table
    $sql_files = "CREATE TABLE IF NOT EXISTS `work_order_files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` TEXT NOT NULL,
        `file_size` INT,
        `file_type` VARCHAR(50),
        `uploaded_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_uploaded_by` (`uploaded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_files)) {
        $tables_created[] = 'work_order_files';
    } else {
        $errors[] = "work_order_files: " . mysqli_error($conn);
    }

    // 5. Create work_order_activity_log table
    $sql_activity = "CREATE TABLE IF NOT EXISTS `work_order_activity_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `action_by` INT NOT NULL,
        `action_type` ENUM('Create', 'Update', 'Approve Internal', 'Approve Client', 'Deliver', 'Close', 'Comment') NOT NULL,
        `description` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_action_by` (`action_by`),
        INDEX `idx_action_type` (`action_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_activity)) {
        $tables_created[] = 'work_order_activity_log';
    } else {
        $errors[] = "work_order_activity_log: " . mysqli_error($conn);
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Work Orders tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Work Orders module tables created: ' . implode(', ', $tables_created)];
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection(true);
    $result = setup_workorders_module($conn);
    closeConnection($conn);
    
    echo "<h1>Work Orders Module Setup</h1>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='../public/workorders/index.php'>Go to Work Orders Dashboard</a></p>";
    }
    echo "<p><a href='../setup/index.php'>Back to Setup</a></p>";
}

<?php
/**
 * Work Orders Module - Database Setup Script
 * Creates all required tables for the Work Order Management system
 */

require_once __DIR__ . '/../config/db_connect.php';

echo "<!DOCTYPE html><html><head><title>Work Orders Module Setup</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{color:#28a745;padding:10px;background:#d4edda;border-radius:5px;margin:10px 0;}";
echo ".error{color:#dc3545;padding:10px;background:#f8d7da;border-radius:5px;margin:10px 0;}";
echo "h1{color:#003581;}</style></head><body>";

echo "<h1>🔧 Work Orders Module Setup</h1>";

$conn = createConnection(true);

if (!$conn) {
    echo "<div class='error'>❌ Database connection failed: " . mysqli_connect_error() . "</div>";
    exit;
}

$tables_created = 0;
$errors = [];

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
    echo "<div class='success'>✅ Table 'work_orders' created successfully</div>";
    $tables_created++;
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
    echo "<div class='success'>✅ Table 'work_order_team' created successfully</div>";
    $tables_created++;
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
    echo "<div class='success'>✅ Table 'work_order_deliverables' created successfully</div>";
    $tables_created++;
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
    echo "<div class='success'>✅ Table 'work_order_files' created successfully</div>";
    $tables_created++;
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
    echo "<div class='success'>✅ Table 'work_order_activity_log' created successfully</div>";
    $tables_created++;
} else {
    $errors[] = "work_order_activity_log: " . mysqli_error($conn);
}

// Display summary
echo "<hr>";
echo "<h2>📊 Setup Summary</h2>";
echo "<p><strong>Tables Created:</strong> $tables_created / 5</p>";

if (!empty($errors)) {
    echo "<h3>❌ Errors:</h3>";
    foreach ($errors as $error) {
        echo "<div class='error'>$error</div>";
    }
} else {
    echo "<div class='success'><h3>✅ All tables created successfully!</h3>";
    echo "<p>The Work Orders module is now ready to use.</p>";
    echo "<p><a href='../public/workorders/index.php' style='color:#003581;'>→ Go to Work Orders Dashboard</a></p></div>";
}

closeConnection($conn);

echo "</body></html>";
?>

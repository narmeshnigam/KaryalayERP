<?php
/**
 * Deliverables & Approvals Module - Database Setup Script
 * Creates all necessary tables for the Deliverables module
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_deliverables_module($conn) {
    $tables_created = [];
    $errors = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'deliverables'");
    $already_exists = $check && mysqli_num_rows($check) > 0;

    mysqli_begin_transaction($conn);

    try {
        // 1. Main deliverables table
        $sql_deliverables = "CREATE TABLE IF NOT EXISTS `deliverables` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `work_order_id` INT(11) DEFAULT NULL,
            `project_id` INT(11) DEFAULT NULL,
            `deliverable_name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `assigned_to` INT(11) NOT NULL,
            `current_version` INT(11) DEFAULT 1,
            `status` ENUM('Draft','Submitted','Internal Approved','Client Review','Revision Requested','Client Approved','Delivered') DEFAULT 'Draft',
            `created_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_work_order` (`work_order_id`),
            KEY `idx_project` (`project_id`),
            KEY `idx_assigned` (`assigned_to`),
            KEY `idx_status` (`status`),
            KEY `idx_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!mysqli_query($conn, $sql_deliverables)) {
            throw new Exception("Error creating deliverables table: " . mysqli_error($conn));
        }
        $tables_created[] = 'deliverables';

        // 2. Deliverable versions table
        $sql_versions = "CREATE TABLE IF NOT EXISTS `deliverable_versions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `deliverable_id` INT(11) NOT NULL,
            `version_no` INT(11) NOT NULL,
            `submitted_by` INT(11) NOT NULL,
            `submission_notes` TEXT,
            `approval_internal` BOOLEAN DEFAULT FALSE,
            `approval_client` BOOLEAN DEFAULT FALSE,
            `revision_requested` BOOLEAN DEFAULT FALSE,
            `approved_by_internal` INT(11) DEFAULT NULL,
            `approved_by_client` VARCHAR(255) DEFAULT NULL,
            `approval_date_internal` DATETIME DEFAULT NULL,
            `approval_date_client` DATETIME DEFAULT NULL,
            `remarks` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_deliverable` (`deliverable_id`),
            KEY `idx_version` (`deliverable_id`, `version_no`),
            KEY `idx_submitted_by` (`submitted_by`),
            CONSTRAINT `fk_version_deliverable` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!mysqli_query($conn, $sql_versions)) {
            throw new Exception("Error creating deliverable_versions table: " . mysqli_error($conn));
        }
        $tables_created[] = 'deliverable_versions';

        // 3. Deliverable files table
        $sql_files = "CREATE TABLE IF NOT EXISTS `deliverable_files` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `deliverable_id` INT(11) NOT NULL,
            `version_no` INT(11) NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` TEXT NOT NULL,
            `file_size` BIGINT DEFAULT NULL,
            `file_type` VARCHAR(100) DEFAULT NULL,
            `uploaded_by` INT(11) NOT NULL,
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_deliverable` (`deliverable_id`),
            KEY `idx_version` (`deliverable_id`, `version_no`),
            KEY `idx_uploaded_by` (`uploaded_by`),
            CONSTRAINT `fk_file_deliverable` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!mysqli_query($conn, $sql_files)) {
            throw new Exception("Error creating deliverable_files table: " . mysqli_error($conn));
        }
        $tables_created[] = 'deliverable_files';

        // 4. Deliverable activity log table
        $sql_activity = "CREATE TABLE IF NOT EXISTS `deliverable_activity_log` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `deliverable_id` INT(11) NOT NULL,
            `action_by` INT(11) NOT NULL,
            `action_type` ENUM('Create','Submit','Approve Internal','Approve Client','Request Revision','Deliver','Update','Comment') DEFAULT 'Create',
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_deliverable` (`deliverable_id`),
            KEY `idx_action_by` (`action_by`),
            KEY `idx_action_type` (`action_type`),
            CONSTRAINT `fk_activity_deliverable` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!mysqli_query($conn, $sql_activity)) {
            throw new Exception("Error creating deliverable_activity_log table: " . mysqli_error($conn));
        }
        $tables_created[] = 'deliverable_activity_log';

        mysqli_commit($conn);

        if ($already_exists) {
            return ['success' => true, 'message' => 'Deliverables tables already exist or were verified successfully.'];
        }
        
        return ['success' => true, 'message' => 'Deliverables module tables created: ' . implode(', ', $tables_created)];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../config/config.php';
    $conn = createConnection();
    $result = setup_deliverables_module($conn);
    closeConnection($conn);
    
    echo "<h1>Deliverables Module Setup</h1>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='../public/deliverables/'>Go to Deliverables Dashboard</a></p>";
    }
    echo "<p><a href='../public/'>Back to Dashboard</a></p>";
}

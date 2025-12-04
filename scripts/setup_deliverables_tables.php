<?php
/**
 * Deliverables & Approvals Module - Database Setup Script
 * Creates all necessary tables for the Deliverables module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$tables_created = [];
$errors = [];

// Start transaction
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

    if (mysqli_query($conn, $sql_deliverables)) {
        $tables_created[] = 'deliverables';
    } else {
        throw new Exception("Error creating deliverables table: " . mysqli_error($conn));
    }

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

    if (mysqli_query($conn, $sql_versions)) {
        $tables_created[] = 'deliverable_versions';
    } else {
        throw new Exception("Error creating deliverable_versions table: " . mysqli_error($conn));
    }

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

    if (mysqli_query($conn, $sql_files)) {
        $tables_created[] = 'deliverable_files';
    } else {
        throw new Exception("Error creating deliverable_files table: " . mysqli_error($conn));
    }

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

    if (mysqli_query($conn, $sql_activity)) {
        $tables_created[] = 'deliverable_activity_log';
    } else {
        throw new Exception("Error creating deliverable_activity_log table: " . mysqli_error($conn));
    }

    // Commit transaction
    mysqli_commit($conn);

    $success = true;
    $message = "Successfully created " . count($tables_created) . " tables: " . implode(', ', $tables_created);

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    $success = false;
    $message = $e->getMessage();
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverables Module Setup - <?php echo APP_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status.success {
            background: #f0fdf4;
            border: 2px solid #86efac;
            color: #166534;
        }
        .status.error {
            background: #fef2f2;
            border: 2px solid #fca5a5;
            color: #991b1b;
        }
        .status-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .status h2 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        .tables-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .tables-list h3 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .tables-list ul {
            list-style: none;
        }
        .tables-list li {
            padding: 8px 0;
            color: #4a5568;
            display: flex;
            align-items: center;
        }
        .tables-list li:before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            margin-right: 10px;
            font-size: 18px;
        }
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .info-box p {
            color: #1e40af;
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Deliverables & Approvals Module</h1>
        <p class="subtitle">Database Setup Wizard</p>

        <div class="status <?php echo $success ? 'success' : 'error'; ?>">
            <div class="status-icon"><?php echo $success ? '✅' : '❌'; ?></div>
            <h2><?php echo $success ? 'Setup Successful!' : 'Setup Failed'; ?></h2>
            <p><?php echo $message; ?></p>
        </div>

        <?php if ($success && !empty($tables_created)): ?>
        <div class="tables-list">
            <h3>Created Tables:</h3>
            <ul>
                <?php foreach ($tables_created as $table): ?>
                <li><?php echo htmlspecialchars($table); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="info-box">
            <p><strong>Next Steps:</strong></p>
            <p>✓ Database schema created successfully<br>
               ✓ Foreign keys and indexes configured<br>
               ✓ Ready to create deliverables and manage approvals</p>
        </div>

        <div class="actions">
            <a href="../public/deliverables/" class="btn btn-primary">Go to Deliverables Dashboard</a>
            <a href="../public/" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <?php else: ?>
        <div class="actions">
            <a href="javascript:location.reload()" class="btn btn-primary">Retry Setup</a>
            <a href="../public/" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

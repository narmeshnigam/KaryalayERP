<?php
/**
 * Delivery Module - Database Setup Script
 * Creates all necessary tables for the Delivery Management Module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';


$module = isset($_POST['module']) ? $_POST['module'] : '';
$tables_created = [];
$errors = [];
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $module) {
    $conn = createConnection();
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    mysqli_begin_transaction($conn);
    try {
        if ($module === 'work_orders') {
            // Work Orders table
            $sql = "CREATE TABLE IF NOT EXISTS `work_orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `work_order_code` VARCHAR(50) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `client_id` INT,
                `status` VARCHAR(50),
                `created_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql)) {
                $tables_created[] = 'work_orders';
            } else {
                throw new Exception("Error creating work_orders table: " . mysqli_error($conn));
            }
        } elseif ($module === 'deliverables') {
            // Deliverables & Approvals tables
            $sql1 = "CREATE TABLE IF NOT EXISTS `deliverables` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `deliverable_name` VARCHAR(255) NOT NULL,
                `work_order_id` INT,
                `client_id` INT,
                `lead_id` INT,
                `status` VARCHAR(50),
                `created_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $sql2 = "CREATE TABLE IF NOT EXISTS `deliverable_versions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `deliverable_id` INT NOT NULL,
                `version_no` INT NOT NULL,
                `description` TEXT,
                `created_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $sql3 = "CREATE TABLE IF NOT EXISTS `deliverable_files` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `deliverable_id` INT NOT NULL,
                `file_name` VARCHAR(255),
                `file_path` TEXT,
                `file_size` INT,
                `uploaded_by` INT,
                `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $sql4 = "CREATE TABLE IF NOT EXISTS `deliverable_activity_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `deliverable_id` INT NOT NULL,
                `activity_type` VARCHAR(50),
                `description` TEXT,
                `performed_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql1)) $tables_created[] = 'deliverables';
            else throw new Exception("Error creating deliverables table: " . mysqli_error($conn));
            if (mysqli_query($conn, $sql2)) $tables_created[] = 'deliverable_versions';
            else throw new Exception("Error creating deliverable_versions table: " . mysqli_error($conn));
            if (mysqli_query($conn, $sql3)) $tables_created[] = 'deliverable_files';
            else throw new Exception("Error creating deliverable_files table: " . mysqli_error($conn));
            if (mysqli_query($conn, $sql4)) $tables_created[] = 'deliverable_activity_log';
            else throw new Exception("Error creating deliverable_activity_log table: " . mysqli_error($conn));
        } elseif ($module === 'delivery') {
            // ...existing code for delivery tables...
            // 1. Main delivery_items table
            $sql_items = "CREATE TABLE IF NOT EXISTS `delivery_items` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `deliverable_id` INT(11) NOT NULL,
                `work_order_id` INT(11) NOT NULL,
                `client_id` INT(11) DEFAULT NULL,
                `lead_id` INT(11) DEFAULT NULL,
                `channel` ENUM('Email','Portal','WhatsApp','Physical','Courier','Cloud Link','Other') DEFAULT 'Email',
                `status` ENUM('Pending','In Progress','Ready to Deliver','Delivered','Confirmed','Cancelled') DEFAULT 'Pending',
                `delivery_date` DATETIME DEFAULT NULL,
                `confirmation_date` DATETIME DEFAULT NULL,
                `delivered_by` INT(11) DEFAULT NULL,
                `delivered_to_name` VARCHAR(255) DEFAULT NULL,
                `delivered_to_contact` VARCHAR(100) DEFAULT NULL,
                `main_link` TEXT DEFAULT NULL,
                `notes` TEXT DEFAULT NULL,
                `created_by` INT(11) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_deliverable` (`deliverable_id`),
                KEY `idx_work_order` (`work_order_id`),
                KEY `idx_client` (`client_id`),
                KEY `idx_lead` (`lead_id`),
                KEY `idx_status` (`status`),
                KEY `idx_delivered_by` (`delivered_by`),
                CONSTRAINT `chk_client_or_lead` CHECK ((`client_id` IS NOT NULL AND `lead_id` IS NULL) OR (`client_id` IS NULL AND `lead_id` IS NOT NULL) OR (`client_id` IS NULL AND `lead_id` IS NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql_items)) {
                $tables_created[] = 'delivery_items';
            } else {
                throw new Exception("Error creating delivery_items table: " . mysqli_error($conn));
            }
            // 2. Delivery files table
            $sql_files = "CREATE TABLE IF NOT EXISTS `delivery_files` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `delivery_item_id` INT(11) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `file_path` TEXT NOT NULL,
                `file_size` BIGINT DEFAULT NULL,
                `is_primary` BOOLEAN DEFAULT FALSE,
                `uploaded_by` INT(11) NOT NULL,
                `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_delivery_item` (`delivery_item_id`),
                KEY `idx_uploaded_by` (`uploaded_by`),
                CONSTRAINT `fk_file_delivery` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql_files)) {
                $tables_created[] = 'delivery_files';
            } else {
                throw new Exception("Error creating delivery_files table: " . mysqli_error($conn));
            }
            // 3. Delivery POD (Proof of Delivery) table
            $sql_pod = "CREATE TABLE IF NOT EXISTS `delivery_pod` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `delivery_item_id` INT(11) NOT NULL,
                `pod_type` ENUM('Signed Document','Email Screenshot','Courier Slip','Photo','Other') DEFAULT 'Email Screenshot',
                `file_path` TEXT DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `recorded_by` INT(11) NOT NULL,
                `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_delivery_item` (`delivery_item_id`),
                KEY `idx_recorded_by` (`recorded_by`),
                CONSTRAINT `fk_pod_delivery` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql_pod)) {
                $tables_created[] = 'delivery_pod';
            } else {
                throw new Exception("Error creating delivery_pod table: " . mysqli_error($conn));
            }
            // 4. Delivery activity log table
            $sql_activity = "CREATE TABLE IF NOT EXISTS `delivery_activity_log` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `delivery_item_id` INT(11) NOT NULL,
                `action_by` INT(11) NOT NULL,
                `action_type` ENUM('Create','Update','Status Change','Deliver','Confirm','Attach POD','Cancel') DEFAULT 'Create',
                `notes` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_delivery_item` (`delivery_item_id`),
                KEY `idx_action_by` (`action_by`),
                KEY `idx_action_type` (`action_type`),
                CONSTRAINT `fk_activity_delivery` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (mysqli_query($conn, $sql_activity)) {
                $tables_created[] = 'delivery_activity_log';
            } else {
                throw new Exception("Error creating delivery_activity_log table: " . mysqli_error($conn));
            }
        }
        mysqli_commit($conn);
        $success = true;
        $message = "Successfully created " . count($tables_created) . " tables: " . implode(', ', $tables_created);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $success = false;
        $message = $e->getMessage();
    }
    closeConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Module Setup - <?php echo APP_NAME; ?></title>
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
        <h1>🛠️ ERP Module Setup</h1>
        <p class="subtitle">Choose a module to setup its database tables.</p>

        <form method="POST" style="margin-bottom:30px;">
            <label for="module" style="font-weight:500; color:#2d3748;">Select Module:</label>
            <select name="module" id="module" style="margin:0 12px 0 12px; padding:8px 16px; border-radius:8px;">
                <option value="">-- Choose --</option>
                <option value="work_orders" <?php if($module==='work_orders')echo 'selected';?>>Work Orders</option>
                <option value="deliverables" <?php if($module==='deliverables')echo 'selected';?>>Deliverables & Approvals</option>
                <option value="delivery" <?php if($module==='delivery')echo 'selected';?>>Delivery</option>
            </select>
            <button type="submit" class="btn btn-primary">Setup Tables</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
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
               ✓ Ready to manage module data</p>
        </div>

        <div class="actions">
            <?php if($module==='delivery'): ?>
            <a href="../public/delivery/" class="btn btn-primary">Go to Delivery Dashboard</a>
            <?php elseif($module==='deliverables'): ?>
            <a href="../public/deliverables/" class="btn btn-primary">Go to Deliverables Dashboard</a>
            <?php elseif($module==='work_orders'): ?>
            <a href="../public/work_orders/" class="btn btn-primary">Go to Work Orders Dashboard</a>
            <?php endif; ?>
            <a href="../public/" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

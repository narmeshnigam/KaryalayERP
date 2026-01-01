<?php
/**
 * Delivery Module - Database Setup Script
 * Creates all necessary tables for the Delivery Management Module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Setup function for AJAX installation
 * Creates all delivery-related tables
 */
function setup_delivery_module($conn) {
    $tables_created = [];
    $errors = [];
    
    try {
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
            KEY `idx_delivered_by` (`delivered_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql_items) === TRUE) {
            $tables_created[] = 'delivery_items';
        } else {
            $errors[] = "Error creating delivery_items: " . $conn->error;
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
            KEY `idx_uploaded_by` (`uploaded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql_files) === TRUE) {
            $tables_created[] = 'delivery_files';
        } else {
            $errors[] = "Error creating delivery_files: " . $conn->error;
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
            KEY `idx_recorded_by` (`recorded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql_pod) === TRUE) {
            $tables_created[] = 'delivery_pod';
        } else {
            $errors[] = "Error creating delivery_pod: " . $conn->error;
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
            KEY `idx_action_type` (`action_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql_activity) === TRUE) {
            $tables_created[] = 'delivery_activity_log';
        } else {
            $errors[] = "Error creating delivery_activity_log: " . $conn->error;
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode('; ', $errors)];
        }
        
        return ['success' => true, 'message' => 'Delivery module tables created: ' . implode(', ', $tables_created)];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Only run HTML output if called directly (not included via AJAX)
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection();
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    
    $result = setup_delivery_module($conn);
    closeConnection($conn);
    
    $success = $result['success'];
    $message = $result['message'];
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
            text-align: center;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .message {
            color: #4a5568;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
        }
        .success { color: #166534; }
        .error { color: #991b1b; }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: all 0.3s;
            margin: 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="status-icon"><?php echo $success ? '✅' : '❌'; ?></div>
        <h1><?php echo $success ? 'Delivery Module Installed!' : 'Installation Failed'; ?></h1>
        <p class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
        
        <?php if ($success): ?>
        <a href="<?php echo APP_URL; ?>/public/delivery/" class="btn btn-primary">Go to Delivery Dashboard</a>
        <?php else: ?>
        <a href="<?php echo APP_URL; ?>/scripts/setup_delivery_tables.php" class="btn btn-primary">Try Again</a>
        <?php endif; ?>
        <a href="<?php echo APP_URL; ?>/public/" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</body>
</html>
<?php
} // End of direct access check
?>
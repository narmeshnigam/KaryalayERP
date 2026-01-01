<?php
/**
 * Payroll Module - Database Setup Script
 * Creates tables: payroll_master, payroll_items, payroll_activity_log
 */

require_once __DIR__ . '/../config/db_connect.php';

/**
 * Setup function for AJAX installation
 */
function setup_payroll_module($conn) {
    $errors = [];
    $tables_created = [];
    
    // Check if tables already exist
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'payroll_master'");
    $master_exists = $check && mysqli_num_rows($check) > 0;
    
    // Table 1: payroll_master
    $sql_master = "CREATE TABLE IF NOT EXISTS payroll_master (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_type ENUM('Salary','Reimbursement') NOT NULL,
        month_year VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
        total_employees INT NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        transaction_mode ENUM('Bank','UPI','Cash','Cheque','Other') NULL,
        transaction_ref VARCHAR(100) NULL,
        status ENUM('Draft','Locked','Paid') DEFAULT 'Draft',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        locked_at TIMESTAMP NULL,
        INDEX idx_type (payroll_type),
        INDEX idx_month (month_year),
        INDEX idx_status (status),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_master) === TRUE) {
        $tables_created[] = 'payroll_master';
    } else {
        $errors[] = "Error creating 'payroll_master': " . $conn->error;
    }

    // Table 2: payroll_items
    $sql_items = "CREATE TABLE IF NOT EXISTS payroll_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_number VARCHAR(32) NOT NULL UNIQUE,
        payroll_id INT NOT NULL,
        employee_id INT NOT NULL,
        item_type ENUM('Salary','Reimbursement') NOT NULL,
        base_salary DECIMAL(12,2) NULL,
        allowances DECIMAL(12,2) NULL DEFAULT 0.00,
        deductions DECIMAL(12,2) NULL DEFAULT 0.00,
        payable DECIMAL(12,2) NOT NULL,
        attendance_days DECIMAL(5,2) NULL,
        reimbursement_id INT NULL,
        transaction_ref VARCHAR(100) NULL,
        remarks TEXT NULL,
        status ENUM('Pending','Paid') DEFAULT 'Pending',
        UNIQUE KEY unique_payroll_employee (payroll_id, employee_id),
        INDEX idx_payroll (payroll_id),
        INDEX idx_employee (employee_id),
        INDEX idx_reimbursement (reimbursement_id),
        FOREIGN KEY (payroll_id) REFERENCES payroll_master(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_items) === TRUE) {
        $tables_created[] = 'payroll_items';
    } else {
        $errors[] = "Error creating 'payroll_items': " . $conn->error;
    }

    // Table 3: payroll_activity_log
    $sql_log = "CREATE TABLE IF NOT EXISTS payroll_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action ENUM('Create','Update','Lock','Export','Pay') NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payroll (payroll_id),
        INDEX idx_user (user_id),
        FOREIGN KEY (payroll_id) REFERENCES payroll_master(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_log) === TRUE) {
        $tables_created[] = 'payroll_activity_log';
    } else {
        $errors[] = "Error creating 'payroll_activity_log': " . $conn->error;
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($master_exists) {
        return ['success' => true, 'message' => 'Payroll tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Payroll module tables created: ' . implode(', ', $tables_created)];
}

// Run HTML output only if called directly (not included via require/include)
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../config/config.php';
    $conn = createConnection();
    $result = setup_payroll_module($conn);
    $conn->close();
    
    $success = $result['success'];
    $message = $result['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Module Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #003581; color: white; text-decoration: none; border-radius: 5px; margin: 20px 5px 0 0; }
        h1 { color: #003581; }
    </style>
</head>
<body>
    <h1>Payroll Module Setup</h1>
    <div class="<?php echo $success ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php if ($success): ?>
        <p><strong>Setup completed successfully!</strong> The Payroll module is ready to use.</p>
        <a href="<?php echo APP_URL; ?>/public/payroll/index.php" class="btn">Go to Payroll Dashboard</a>
    <?php else: ?>
        <p><strong>Setup encountered errors.</strong> Please check your database configuration.</p>
    <?php endif; ?>
    <a href="<?php echo APP_URL; ?>/public/index.php" class="btn" style="background: #6c757d;">Back to Dashboard</a>
</body>
</html>
<?php
}

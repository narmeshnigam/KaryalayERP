<?php
/**
 * Setup Payroll Module Tables
 * Creates all necessary database tables for the Payroll (Lite) Module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Create connection
$conn = createConnection();

$errors = [];
$success = [];

// Table 1: payroll_master

$sql_payroll_master = "CREATE TABLE IF NOT EXISTS payroll_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
    total_employees INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('Draft', 'Reviewed', 'Locked', 'Paid') DEFAULT 'Draft',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_by INT UNSIGNED NULL,
    locked_at TIMESTAMP NULL,
    paid_by INT UNSIGNED NULL,
    paid_at TIMESTAMP NULL,
    remarks TEXT NULL,
    UNIQUE KEY unique_month (month),
    INDEX idx_status (status),
    INDEX idx_month (month),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payroll_master) === TRUE) {
    $success[] = "Table 'payroll_master' created successfully";
} else {
    $errors[] = "Error creating 'payroll_master': " . $conn->error;
}

// Table 2: payroll_records
$sql_payroll_records = "CREATE TABLE IF NOT EXISTS payroll_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    employee_id INT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    attendance_days INT NOT NULL DEFAULT 0,
    total_days INT NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) DEFAULT 0.00,
    reimbursements DECIMAL(12,2) DEFAULT 0.00,
    deductions DECIMAL(12,2) DEFAULT 0.00,
    bonus DECIMAL(12,2) DEFAULT 0.00,
    penalties DECIMAL(12,2) DEFAULT 0.00,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_ref VARCHAR(100) NULL COMMENT 'Cheque/UTR/Transaction ID',
    payment_date DATE NULL,
    remarks TEXT NULL,
    payslip_path TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payroll_employee (payroll_id, employee_id),
    INDEX idx_employee (employee_id),
    INDEX idx_payroll (payroll_id),
    FOREIGN KEY (payroll_id) REFERENCES payroll_master(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payroll_records) === TRUE) {
    $success[] = "Table 'payroll_records' created successfully";
} else {
    $errors[] = "Error creating 'payroll_records': " . $conn->error;
}

// Table 3: payroll_allowances
$sql_payroll_allowances = "CREATE TABLE IF NOT EXISTS payroll_allowances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    type ENUM('Fixed', 'Percent') DEFAULT 'Fixed',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payroll_allowances) === TRUE) {
    $success[] = "Table 'payroll_allowances' created successfully";
} else {
    $errors[] = "Error creating 'payroll_allowances': " . $conn->error;
}

// Table 4: payroll_deductions
$sql_payroll_deductions = "CREATE TABLE IF NOT EXISTS payroll_deductions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    type ENUM('Fixed', 'Percent') DEFAULT 'Fixed',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payroll_deductions) === TRUE) {
    $success[] = "Table 'payroll_deductions' created successfully";
} else {
    $errors[] = "Error creating 'payroll_deductions': " . $conn->error;
}

// Table 5: payroll_activity_log

$sql_payroll_activity_log = "CREATE TABLE IF NOT EXISTS payroll_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action ENUM('Generate', 'Update', 'Review', 'Lock', 'Pay', 'Delete') NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payroll (payroll_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (payroll_id) REFERENCES payroll_master(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payroll_activity_log) === TRUE) {
    $success[] = "Table 'payroll_activity_log' created successfully";
} else {
    $errors[] = "Error creating 'payroll_activity_log': " . $conn->error;
}

// Insert default allowances
$default_allowances = [
    ['HRA', 'House Rent Allowance', 'Percent', 30.00],
    ['Travel Allowance', 'Travel/Conveyance Allowance', 'Fixed', 2000.00],
    ['Medical Allowance', 'Medical Allowance', 'Fixed', 1500.00],
    ['Special Allowance', 'Special Allowance', 'Fixed', 0.00]
];

foreach ($default_allowances as $allowance) {
    $check = $conn->query("SELECT id FROM payroll_allowances WHERE name = '{$allowance[0]}'");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO payroll_allowances (name, description, type, value, active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("sssd", $allowance[0], $allowance[1], $allowance[2], $allowance[3]);
        if ($stmt->execute()) {
            $success[] = "Default allowance '{$allowance[0]}' created";
        }
        $stmt->close();
    }
}

// Insert default deductions
$default_deductions = [
    ['PF', 'Provident Fund', 'Percent', 12.00],
    ['ESI', 'Employee State Insurance', 'Percent', 0.75],
    ['TDS', 'Tax Deducted at Source', 'Percent', 0.00],
    ['Professional Tax', 'Professional Tax', 'Fixed', 200.00],
    ['Loan Repayment', 'Loan/Advance Repayment', 'Fixed', 0.00]
];

foreach ($default_deductions as $deduction) {
    $check = $conn->query("SELECT id FROM payroll_deductions WHERE name = '{$deduction[0]}'");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO payroll_deductions (name, description, type, value, active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("sssd", $deduction[0], $deduction[1], $deduction[2], $deduction[3]);
        if ($stmt->execute()) {
            $success[] = "Default deduction '{$deduction[0]}' created";
        }
        $stmt->close();
    }
}

closeConnection($conn);

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Module Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #003581;
            border-bottom: 3px solid #003581;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #003581;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #002a66;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 8px 0;
        }
        li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧾 Payroll Module Setup</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <h3>⚠️ Errors:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li style="color: #721c24;"><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success">
                <h3>✅ Success:</h3>
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p style="margin-top: 30px;">
            <strong>Tables Created:</strong>
        </p>
        <ul>
            <li>payroll_master - Manages payroll batches</li>
            <li>payroll_records - Individual employee payroll records</li>
            <li>payroll_allowances - Salary allowance types</li>
            <li>payroll_deductions - Salary deduction types</li>
            <li>payroll_activity_log - Audit trail for payroll actions</li>
        </ul>

        <a href="<?php echo APP_URL; ?>/public/payroll/index.php" class="btn">Go to Payroll Module</a>
        <a href="<?php echo APP_URL; ?>/public/index.php" class="btn" style="background: #6c757d;">Back to Dashboard</a>
    </div>
</body>
</html>

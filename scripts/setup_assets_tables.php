<?php
/**
 * Asset & Resource Management Module - Database Setup Script
 * Creates all necessary tables for comprehensive asset tracking and management
 */

require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

$conn = createConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Asset Management Module - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #003581; border-bottom: 3px solid #003581; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #003581; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #002a66; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #003581; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧰 Asset & Resource Management Module - Database Setup</h1>
        <p>This script will create the necessary database tables for comprehensive asset tracking and management.</p>
";

$tables_created = 0;
$errors = [];

// 1. Create assets_master table
echo "<div class='step'><h3>Creating assets_master table...</h3>";

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
    echo "<div class='success'>✅ Table 'assets_master' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating assets_master table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// 2. Create asset_allocation_log table
echo "<div class='step'><h3>Creating asset_allocation_log table...</h3>";

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
    echo "<div class='success'>✅ Table 'asset_allocation_log' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating asset_allocation_log table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// 3. Create asset_status_log table
echo "<div class='step'><h3>Creating asset_status_log table...</h3>";

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
    echo "<div class='success'>✅ Table 'asset_status_log' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating asset_status_log table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// 4. Create asset_maintenance_log table
echo "<div class='step'><h3>Creating asset_maintenance_log table...</h3>";

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
    echo "<div class='success'>✅ Table 'asset_maintenance_log' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating asset_maintenance_log table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// 5. Create asset_files table
echo "<div class='step'><h3>Creating asset_files table...</h3>";

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
    echo "<div class='success'>✅ Table 'asset_files' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating asset_files table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// 6. Create asset_activity_log table (audit trail)
echo "<div class='step'><h3>Creating asset_activity_log table...</h3>";

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
    echo "<div class='success'>✅ Table 'asset_activity_log' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating asset_activity_log table: " . $conn->error;
    echo "<div class='error'>❌ $error_msg</div>";
    $errors[] = $error_msg;
}

echo "</div>";

// Summary
echo "<div class='step'><h3>📊 Setup Summary</h3>";
if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h4>✅ Setup Completed Successfully!</h4>";
    echo "<p>✓ $tables_created table(s) created</p>";
    echo "<p>✓ All indexes and foreign keys configured</p>";
    echo "<p>✓ Asset master registry ready</p>";
    echo "<p>✓ Context-based allocation tracking enabled</p>";
    echo "<p>✓ Status and maintenance logging active</p>";
    echo "<p>✓ File attachment support ready</p>";
    echo "<p>✓ Complete audit trail configured</p>";
    echo "<p>The Asset & Resource Management Module is now ready to use.</p>";
    echo "</div>";
    echo "<a href='../public/assets/index.php' class='btn'>Go to Asset Management Dashboard →</a>";
} else {
    echo "<div class='error'>";
    echo "<h4>⚠️ Setup completed with errors:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
    echo "<p>Please fix the errors and run the setup again.</p>";
    echo "</div>";
    echo "<a href='setup_assets_tables.php' class='btn'>Retry Setup</a>";
}
echo "</div>";

// Database structure info
echo "<div class='step'><h3>📋 Database Structure Overview</h3>";

$tables = [
    'assets_master' => 'Core asset registry with all attributes',
    'asset_allocation_log' => 'Context-based allocation tracking',
    'asset_status_log' => 'Status change history',
    'asset_maintenance_log' => 'Maintenance and repair records',
    'asset_files' => 'Document and image attachments',
    'asset_activity_log' => 'Complete audit trail'
];

foreach ($tables as $table => $desc) {
    echo "<h4>$table: <small>$desc</small></h4>";
    echo "<pre>";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        echo str_pad("Field", 25) . str_pad("Type", 35) . str_pad("Null", 6) . str_pad("Key", 5) . "Default\n";
        echo str_repeat("-", 100) . "\n";
        while ($row = $result->fetch_assoc()) {
            printf("%-25s %-35s %-6s %-5s %s\n",
                $row['Field'],
                $row['Type'],
                $row['Null'],
                $row['Key'],
                $row['Default'] ?? 'NULL'
            );
        }
    }
    echo "</pre>";
}

echo "</div>";

echo "
    </div>
</body>
</html>";

closeConnection($conn);

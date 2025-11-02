<?php
/**
 * Data Transfer Module - Database Setup Script
 * Creates data_transfer_logs table for tracking import/export operations
 */

require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

$conn = createConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Data Transfer Module - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #003581; border-bottom: 3px solid #003581; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #003581; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #002a66; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #003581; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔁 Data Transfer Module - Database Setup</h1>
        <p>This script will create the necessary database tables for the Export-Import Module.</p>
";

$tables_created = 0;
$errors = [];

// Create data_transfer_logs table
echo "<div class='step'><h3>Creating data_transfer_logs table...</h3>";

$sql_logs = "CREATE TABLE IF NOT EXISTS data_transfer_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    operation ENUM('Import', 'Export') NOT NULL,
    file_path TEXT NULL,
    record_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('Success', 'Partial', 'Failed') NOT NULL DEFAULT 'Success',
    error_log TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_table_name (table_name),
    INDEX idx_operation (operation),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_logs) === TRUE) {
    echo "<div class='success'>✅ Table 'data_transfer_logs' created successfully!</div>";
    $tables_created++;
} else {
    $error_msg = "Error creating data_transfer_logs table: " . $conn->error;
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
    echo "<p>The Data Transfer Module is now ready to use.</p>";
    echo "</div>";
    echo "<a href='../public/data-transfer/index.php' class='btn'>Go to Data Transfer Dashboard →</a>";
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
    echo "<a href='setup_data_transfer_tables.php' class='btn'>Retry Setup</a>";
}
echo "</div>";

// Database structure info
echo "<div class='step'><h3>📋 Database Structure</h3>";
echo "<h4>data_transfer_logs table:</h4>";
echo "<pre>";
$result = $conn->query("DESCRIBE data_transfer_logs");
if ($result) {
    echo "Field           | Type                          | Null | Key | Default | Extra\n";
    echo str_repeat("-", 90) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-15s | %-29s | %-4s | %-3s | %-7s | %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key'],
            $row['Default'] ?? 'NULL',
            $row['Extra']
        );
    }
}
echo "</pre>";
echo "</div>";

echo "
    </div>
</body>
</html>";

closeConnection($conn);

<?php
/**
 * Data Transfer Module - Database Setup Script
 * Creates data_transfer_logs table for tracking import/export operations
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_data_transfer_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'data_transfer_logs'");
    $already_exists = $check && mysqli_num_rows($check) > 0;

    // Create data_transfer_logs table
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
        $tables_created[] = 'data_transfer_logs';
    } else {
        $errors[] = "data_transfer_logs: " . $conn->error;
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Data Transfer tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Data Transfer module tables created: ' . implode(', ', $tables_created)];
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection();
    $result = setup_data_transfer_module($conn);
    closeConnection($conn);
    
    echo "<h1>Data Transfer Module Setup</h1>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='../public/data-transfer/index.php'>Go to Data Transfer Dashboard</a></p>";
    }
    echo "<p><a href='../setup/index.php'>Back to Setup</a></p>";
}

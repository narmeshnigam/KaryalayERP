<?php
/**
 * Setup Script: User Activity Log Table
 * 
 * Creates the user_activity_log table to track login/logout activities
 * and maintain security audit trails.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error() . "\n");
}

echo "🔐 Setting up User Activity Log Table...\n\n";

// Drop table if it exists (for clean setup)
$drop_sql = "DROP TABLE IF EXISTS user_activity_log";
if (mysqli_query($conn, $drop_sql)) {
    echo "✅ Dropped existing user_activity_log table (if any)\n";
}

// Create user_activity_log table
$create_sql = "
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
    device VARCHAR(150) NULL COMMENT 'User agent / device info',
    login_time DATETIME NOT NULL,
    logout_time DATETIME NULL,
    status ENUM('Success', 'Failed') DEFAULT 'Success',
    failure_reason VARCHAR(255) NULL COMMENT 'Reason for failed login attempt',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time),
    INDEX idx_status (status),
    INDEX idx_ip_address (ip_address),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (mysqli_query($conn, $create_sql)) {
    echo "✅ Created user_activity_log table successfully\n\n";
    
    // Display table structure
    echo "📊 Table Structure:\n\n";
    $result = mysqli_query($conn, "DESCRIBE user_activity_log");
    
    echo str_pad("Field", 25) . str_pad("Type", 30) . str_pad("Null", 10) . "Key\n";
    echo str_repeat("-", 75) . "\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo str_pad($row['Field'], 25) . 
             str_pad($row['Type'], 30) . 
             str_pad($row['Null'], 10) . 
             $row['Key'] . "\n";
    }
    mysqli_free_result($result);
    
    echo "\n✅ User Activity Log module is ready!\n";
} else {
    echo "❌ Error creating user_activity_log table: " . mysqli_error($conn) . "\n";
}

closeConnection($conn);
echo "\n🏁 Setup script finished.\n";
?>

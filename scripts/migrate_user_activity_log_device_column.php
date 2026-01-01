<?php
/**
 * Migration Script: Increase device column size in user_activity_log table
 * 
 * This script updates the device column from VARCHAR(100) to VARCHAR(255)
 * to accommodate longer user agent strings from modern browsers.
 * 
 * Usage: Run this script once via browser or CLI
 */

require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

if (!$conn) {
    die("Database connection failed");
}

echo "<h2>User Activity Log - Device Column Migration</h2>";

// Check if table exists
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'user_activity_log'");
if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
    echo "<p style='color: orange;'>⚠️ Table 'user_activity_log' does not exist. No migration needed.</p>";
    closeConnection($conn);
    exit;
}

// Check current column definition
$columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM user_activity_log LIKE 'device'");
if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
    $column = mysqli_fetch_assoc($columnCheck);
    echo "<p>Current device column type: <strong>" . htmlspecialchars($column['Type']) . "</strong></p>";
    
    // Check if already migrated
    if (stripos($column['Type'], 'varchar(255)') !== false) {
        echo "<p style='color: green;'>✅ Device column is already VARCHAR(255). No migration needed.</p>";
        closeConnection($conn);
        exit;
    }
    
    // Perform migration
    echo "<p>Updating device column to VARCHAR(255)...</p>";
    
    $alterSql = "ALTER TABLE user_activity_log MODIFY COLUMN device VARCHAR(255) NULL COMMENT 'User agent string - truncated if needed'";
    
    if (mysqli_query($conn, $alterSql)) {
        echo "<p style='color: green;'>✅ Successfully updated device column to VARCHAR(255)</p>";
        echo "<p>Migration completed successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update device column: " . htmlspecialchars(mysqli_error($conn)) . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Device column not found in user_activity_log table</p>";
}

closeConnection($conn);

echo "<p><a href='../public/index.php'>Go to Dashboard</a></p>";
?>

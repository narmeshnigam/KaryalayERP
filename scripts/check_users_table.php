<?php
/**
 * Check current users table structure
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "📊 Current USERS Table Structure:\n\n";

$result = mysqli_query($conn, "DESCRIBE users");

if ($result) {
    echo str_pad("Field", 25) . str_pad("Type", 25) . str_pad("Null", 10) . str_pad("Key", 10) . str_pad("Default", 15) . "Extra\n";
    echo str_repeat("-", 100) . "\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo str_pad($row['Field'], 25) . 
             str_pad($row['Type'], 25) . 
             str_pad($row['Null'], 10) . 
             str_pad($row['Key'], 10) . 
             str_pad($row['Default'] ?? 'NULL', 15) . 
             $row['Extra'] . "\n";
    }
    
    mysqli_free_result($result);
} else {
    echo "❌ Error: " . mysqli_error($conn) . "\n";
}

closeConnection($conn);
?>

<?php
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("Database connection failed\n");
}

$result = mysqli_query($conn, 'DESCRIBE users');
echo "Current users table structure:\n";
echo str_repeat("-", 70) . "\n";
echo str_pad("Field", 20) . str_pad("Type", 30) . str_pad("Null", 10) . "Key\n";
echo str_repeat("-", 70) . "\n";

while($row = mysqli_fetch_assoc($result)) {
    echo str_pad($row['Field'], 20) . 
         str_pad($row['Type'], 30) . 
         str_pad($row['Null'], 10) . 
         $row['Key'] . "\n";
}

closeConnection($conn);
?>

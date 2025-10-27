<?php
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

echo "Checking roles table structure:\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM roles");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']}\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);

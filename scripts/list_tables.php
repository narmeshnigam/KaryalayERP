<?php
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

if (!$conn) {
    die("Connection failed\n");
}

echo "Database: " . DB_NAME . "\n\n";
echo "Existing tables:\n";

$result = mysqli_query($conn, "SHOW TABLES");

if ($result) {
    while ($row = mysqli_fetch_array($result)) {
        echo "  - " . $row[0] . "\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);

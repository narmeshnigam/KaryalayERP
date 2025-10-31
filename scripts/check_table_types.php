<?php
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

echo "Checking table column types:\n\n";

// Check clients table
$result = $conn->query('DESCRIBE clients');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] == 'id') {
            echo "clients.id type: " . $row['Type'] . "\n";
        }
    }
}

// Check users table
$result2 = $conn->query('DESCRIBE users');
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        if ($row['Field'] == 'id') {
            echo "users.id type: " . $row['Type'] . "\n";
        }
    }
}

$conn->close();
echo "\nDone.\n";

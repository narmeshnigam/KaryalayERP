<?php
/**
 * Drop and recreate clients table with correct INT UNSIGNED type
 * WARNING: This will delete all client data!
 */

require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

echo "<h2>Recreating Clients Table with INT UNSIGNED</h2>";

// Drop all dependent tables first
$tables_to_drop = [
    'client_custom_fields',
    'client_documents',
    'client_contacts_map',
    'client_addresses',
    'clients'
];

foreach ($tables_to_drop as $table) {
    $sql = "DROP TABLE IF EXISTS `$table`";
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ Dropped table '$table'</p>";
    } else {
        echo "<p>❌ Error dropping '$table': " . $conn->error . "</p>";
    }
}

echo "<p><strong>Now re-run the clients setup script:</strong> <a href='setup_clients_tables.php'>setup_clients_tables.php</a></p>";

$conn->close();

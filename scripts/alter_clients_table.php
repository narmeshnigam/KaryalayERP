<?php
/**
 * Alter clients table to use INT UNSIGNED for id column
 * This preserves existing data
 */

require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

echo "<h2>Altering Clients Table Structure</h2>";

// First, drop foreign keys that reference clients.id
$fk_drops = [
    "ALTER TABLE client_addresses DROP FOREIGN KEY client_addresses_ibfk_1",
    "ALTER TABLE client_contacts_map DROP FOREIGN KEY client_contacts_map_ibfk_1",
    "ALTER TABLE client_documents DROP FOREIGN KEY client_documents_ibfk_1",
    "ALTER TABLE client_custom_fields DROP FOREIGN KEY client_custom_fields_ibfk_1"
];

foreach ($fk_drops as $sql) {
    try {
        $conn->query($sql);
        echo "<p>✅ Dropped foreign key</p>";
    } catch (Throwable $e) {
        echo "<p>⚠️ Could not drop FK (may not exist): " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Alter the id column to INT UNSIGNED
try {
    $sql = "ALTER TABLE clients MODIFY id INT UNSIGNED AUTO_INCREMENT";
    $conn->query($sql);
    echo "<p>✅ Altered clients.id to INT UNSIGNED</p>";
} catch (Throwable $e) {
    echo "<p>⚠️ Skipped altering clients.id: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Ensure dependent tables' client_id columns are UNSIGNED
$alter_children = [
    "ALTER TABLE client_addresses MODIFY client_id INT UNSIGNED NOT NULL",
    "ALTER TABLE client_contacts_map MODIFY client_id INT UNSIGNED NOT NULL",
    "ALTER TABLE client_documents MODIFY client_id INT UNSIGNED NOT NULL",
    "ALTER TABLE client_custom_fields MODIFY client_id INT UNSIGNED NOT NULL"
];

foreach ($alter_children as $sql) {
    try {
        $conn->query($sql);
        echo "<p>✅ Updated child table column to UNSIGNED</p>";
    } catch (Throwable $e) {
        echo "<p>❌ Error updating child column: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Recreate foreign keys
$fk_recreates = [
    "ALTER TABLE client_addresses ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE",
    "ALTER TABLE client_contacts_map ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE",
    "ALTER TABLE client_documents ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE",
    "ALTER TABLE client_custom_fields ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE"
];

foreach ($fk_recreates as $sql) {
    try {
        $conn->query($sql);
        echo "<p>✅ Recreated foreign key</p>";
    } catch (Throwable $e) {
        echo "<p>❌ Error recreating FK: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p><strong>Done! Now you can run the projects setup.</strong></p>";

$conn->close();

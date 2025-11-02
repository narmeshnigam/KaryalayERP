<?php
/**
 * Diagnostic: Check actual database content for item ID=1
 */

require_once __DIR__ . '/../../config/db_connect.php';

$item_id = (int)($_GET['id'] ?? 1);

echo "<!DOCTYPE html><html><head><title>Item Diagnostic</title>";
echo "<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f0f0f0;}</style>";
echo "</head><body>";
echo "<h1>Database Diagnostic for Item ID: $item_id</h1>";

// Check table structure
echo "<h2>1. Table Structure</h2>";
$structure = $conn->query("DESCRIBE items_master");
if ($structure) {
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>" . ($row['Default'] ?: 'NULL') . "</td></tr>";
    }
    echo "</table>";
}

// Check actual data
echo "<h2>2. Raw Data for Item $item_id</h2>";
$result = $conn->query("SELECT * FROM items_master WHERE id = $item_id");
if ($result && $result->num_rows > 0) {
    $item = $result->fetch_assoc();
    echo "<table><tr><th>Column</th><th>Value</th><th>Type</th></tr>";
    foreach ($item as $key => $value) {
        $type = gettype($value);
        $display_value = $value === null ? '<em>NULL</em>' : ($value === '' ? '<em>EMPTY STRING</em>' : htmlspecialchars($value));
        echo "<tr><td><strong>$key</strong></td><td>$display_value</td><td>$type</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No item found with ID $item_id</p>";
}

// Check all items
echo "<h2>3. All Items (first 10)</h2>";
$all = $conn->query("SELECT id, sku, name, type, base_price, status FROM items_master LIMIT 10");
if ($all && $all->num_rows > 0) {
    echo "<table><tr><th>ID</th><th>SKU</th><th>Name</th><th>Type</th><th>Base Price</th><th>Status</th></tr>";
    while ($row = $all->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . ($row['sku'] ?: '<em>NULL</em>') . "</td>";
        echo "<td>" . ($row['name'] ?: '<em>NULL</em>') . "</td>";
        echo "<td>" . ($row['type'] ?: '<em>NULL</em>') . "</td>";
        echo "<td>" . ($row['base_price'] ?: '<em>NULL</em>') . "</td>";
        echo "<td>" . ($row['status'] ?: '<em>NULL</em>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No items in database</p>";
}

echo "<p><a href='add.php'>Add New Item</a> | <a href='view.php?id=$item_id'>View Item $item_id</a></p>";
echo "</body></html>";

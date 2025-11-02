<?php
/**
 * Debug script to check what data is being returned
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

$item_id = (int)($_GET['id'] ?? 1);

echo "<h1>Debug Item ID: $item_id</h1>";

// Check if table exists
if (!catalog_tables_exist($conn)) {
    echo "<p style='color: red;'>Tables don't exist!</p>";
    exit;
}

// Raw query
$result = $conn->query("SELECT * FROM items_master WHERE id = $item_id");
if ($result && $result->num_rows > 0) {
    $raw_item = $result->fetch_assoc();
    echo "<h2>Raw Query Result:</h2>";
    echo "<pre>" . print_r($raw_item, true) . "</pre>";
} else {
    echo "<p style='color: red;'>No item found with ID $item_id in raw query</p>";
}

// Using helper function
$item = get_item_by_id($conn, $item_id);
echo "<h2>Helper Function Result:</h2>";
if ($item) {
    echo "<pre>" . print_r($item, true) . "</pre>";
} else {
    echo "<p style='color: red;'>Helper function returned null</p>";
}

// Check all items
$all_items = $conn->query("SELECT id, sku, name, type, status FROM items_master LIMIT 5");
echo "<h2>All Items (first 5):</h2>";
if ($all_items && $all_items->num_rows > 0) {
    while ($row = $all_items->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>No items in database</p>";
}

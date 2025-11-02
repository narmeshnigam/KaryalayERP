<?php
/**
 * Test insert to diagnose the empty values issue
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

echo "<h1>Test Item Insert</h1>";

// Test data
$test_data = [
    'sku' => 'TEST-' . date('YmdHis'),
    'name' => 'Test Product ' . date('H:i:s'),
    'type' => 'Product',
    'category' => 'Test Category',
    'description_html' => '<p>This is a test product description</p>',
    'base_price' => 999.99,
    'tax_percent' => 18.00,
    'default_discount' => 50.00,
    'expiry_date' => null,
    'current_stock' => 100,
    'low_stock_threshold' => 10,
    'status' => 'Active',
    'primary_image' => null,
    'brochure_pdf' => null
];

echo "<h2>Data being inserted:</h2>";
echo "<pre>" . print_r($test_data, true) . "</pre>";

// Call create function
$result = create_catalog_item($conn, $test_data, $CURRENT_USER_ID);

echo "<h2>Result:</h2>";
echo "<pre>" . print_r($result, true) . "</pre>";

if ($result['success']) {
    $item_id = $result['item_id'];
    echo "<p><a href='view.php?id=$item_id'>View Created Item</a></p>";
    
    // Fetch the item back
    $item = get_item_by_id($conn, $item_id);
    echo "<h2>Fetched Item:</h2>";
    echo "<pre>" . print_r($item, true) . "</pre>";
} else {
    echo "<p style='color: red;'>Error: " . $result['message'] . "</p>";
}

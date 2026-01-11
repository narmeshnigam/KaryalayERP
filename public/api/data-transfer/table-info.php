<?php
/**
 * API Endpoint - Get Table Information
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../data-transfer/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$table_name = $_GET['table'] ?? '';

if (empty($table_name)) {
    echo json_encode(['success' => false, 'message' => 'Table name is required']);
    exit;
}

// Get record count
$result = $conn->query("SELECT COUNT(*) as count FROM `$table_name`");
$record_count = 0;
if ($result) {
    $row = $result->fetch_assoc();
    $record_count = (int)$row['count'];
}

// Get table structure
$structure = get_table_structure($conn, $table_name);
$column_count = count($structure);

// Identify mandatory fields (NOT NULL without default, excluding auto-generated fields)
$mandatory_fields = [];
$optional_fields = [];
$auto_fields = ['id', 'created_at', 'updated_at'];

foreach ($structure as $field) {
    $field_name = $field['field'];
    
    // Skip auto-generated fields
    if (in_array($field_name, $auto_fields) || $field['extra'] === 'auto_increment') {
        continue;
    }
    
    // Check if mandatory (NOT NULL and no default value)
    $is_mandatory = $field['null'] === false && $field['default'] === null;
    
    $field_info = [
        'name' => $field_name,
        'type' => $field['type'],
        'nullable' => $field['null'],
        'default' => $field['default']
    ];
    
    if ($is_mandatory) {
        $mandatory_fields[] = $field_info;
    } else {
        $optional_fields[] = $field_info;
    }
}

echo json_encode([
    'success' => true,
    'table_name' => $table_name,
    'record_count' => $record_count,
    'column_count' => $column_count,
    'mandatory_fields' => $mandatory_fields,
    'optional_fields' => $optional_fields,
    'total_mandatory' => count($mandatory_fields),
    'total_optional' => count($optional_fields)
]);

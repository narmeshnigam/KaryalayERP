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

// Get column count
$structure = get_table_structure($conn, $table_name);
$column_count = count($structure);

echo json_encode([
    'success' => true,
    'record_count' => $record_count,
    'column_count' => $column_count
]);

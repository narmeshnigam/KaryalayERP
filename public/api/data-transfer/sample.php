<?php
/**
 * API Endpoint - Generate Sample CSV
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

$result = generate_sample_csv($conn, $table_name);

echo json_encode($result);

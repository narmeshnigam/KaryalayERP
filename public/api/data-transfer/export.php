<?php
/**
 * API Endpoint - Export Table to CSV
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

// Export table
$result = export_table_to_csv($conn, $table_name);

if ($result['success']) {
    // Log the export
    log_data_transfer($conn, $CURRENT_USER_ID, $table_name, 'Export', $result['filepath'], $result['record_count'], $result['record_count'], 0, 'Success', null);
    
    // Add download URL
    $result['download_url'] = '../../uploads/exports/' . $result['filename'];
}

echo json_encode($result);

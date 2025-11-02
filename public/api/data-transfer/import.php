<?php
/**
 * API Endpoint - Import CSV Data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../data-transfer/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$table_name = $_POST['table_name'] ?? '';

if (empty($table_name)) {
    echo json_encode(['success' => false, 'message' => 'Table name is required']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if ($file_ext !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed']);
    exit;
}

// Validate file size (10 MB max)
if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10 MB limit']);
    exit;
}

// Create imports directory if not exists
$imports_dir = __DIR__ . '/../../../uploads/imports/';
if (!is_dir($imports_dir)) {
    mkdir($imports_dir, 0755, true);
}

// Save uploaded file
$filename = 'import_' . $table_name . '_' . date('Ymd_His') . '.csv';
$filepath = $imports_dir . $filename;

if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Process import
$result = import_csv_to_table($conn, $table_name, $filepath, $CURRENT_USER_ID);

echo json_encode($result);

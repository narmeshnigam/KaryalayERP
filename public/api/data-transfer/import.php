<?php
/**
 * API Endpoint - Import CSV Data
 */

// Set JSON header first and start output buffering to catch any PHP errors
ob_start();
header('Content-Type: application/json');

// Start session to check auth before including auth_check (which redirects)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication before including auth_check to prevent HTML redirect
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit;
}

// Custom error handler to return JSON instead of HTML
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../../../includes/auth_check.php';
    require_once __DIR__ . '/../../data-transfer/helpers.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

restore_error_handler();

// Clear any output that might have been generated
ob_end_clean();

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

// Process import with error handling
try {
    $result = import_csv_to_table($conn, $table_name, $filepath, $CURRENT_USER_ID);
    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Import error: ' . $e->getMessage()]);
}

<?php
/**
 * API Endpoint - Download Exported CSV File
 * Forces browser to download the file instead of displaying it
 */

require_once __DIR__ . '/../../../includes/auth_check.php';

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo 'File parameter is required';
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

// Only allow .csv files
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'csv') {
    http_response_code(400);
    echo 'Invalid file type';
    exit;
}

// Build the full path to the exports directory
$export_dir = __DIR__ . '/../../../uploads/exports/';
$filepath = $export_dir . $filename;

// Check if file exists and is readable
if (!file_exists($filepath) || !is_readable($filepath)) {
    http_response_code(404);
    echo 'File not found or not readable';
    exit;
}

// Get file size
$filesize = filesize($filepath);

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers to force download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Output the file
readfile($filepath);
exit;

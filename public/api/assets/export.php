<?php
/**
 * Asset API - Export Assets
 * GET endpoint to export assets to CSV
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../assets/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$conn = createConnection(true);

try {
    // Get filters from query params
    $filters = [
        'category' => $_GET['category'] ?? '',
        'status' => $_GET['status'] ?? '',
        'department' => $_GET['department'] ?? '',
        'warranty' => $_GET['warranty'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    // Get assets
    $assets = getAssets($conn, $filters);
    
    if (empty($assets)) {
        throw new Exception('No assets found to export');
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="assets_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'Asset Code',
        'Name',
        'Category',
        'Type',
        'Make/Brand',
        'Model',
        'Serial Number',
        'Status',
        'Condition',
        'Location',
        'Department',
        'Purchase Date',
        'Purchase Cost',
        'Vendor',
        'Warranty Expiry',
        'Created By',
        'Created On'
    ]);
    
    // Write data rows
    foreach ($assets as $asset) {
        fputcsv($output, [
            $asset['asset_code'],
            $asset['name'],
            $asset['category'],
            $asset['type'] ?: '',
            $asset['make'] ?: '',
            $asset['model'] ?: '',
            $asset['serial_no'] ?: '',
            $asset['status'],
            $asset['condition'],
            $asset['location'] ?: '',
            $asset['department'] ?: '',
            $asset['purchase_date'] ? date('Y-m-d', strtotime($asset['purchase_date'])) : '',
            $asset['purchase_cost'] ?: '',
            $asset['vendor'] ?: '',
            $asset['warranty_expiry'] ? date('Y-m-d', strtotime($asset['warranty_expiry'])) : '',
            $asset['created_by_name'],
            date('Y-m-d H:i:s', strtotime($asset['created_at']))
        ]);
    }
    
    fclose($output);
    
    // Log export activity
    logAssetActivity($conn, 0, 'Export', 'Exported ' . count($assets) . ' assets to CSV', $_SESSION['user_id']);
    
} catch (Exception $e) {
    http_response_code(400);
    die('Export failed: ' . $e->getMessage());
} finally {
    closeConnection($conn);
}

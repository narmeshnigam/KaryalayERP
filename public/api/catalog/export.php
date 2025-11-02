<?php
/**
 * Catalog API - Export Catalog Data
 * Export items to CSV format
 */

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../catalog/helpers.php';

// All logged-in users can export catalog
// Get filters from query string
$filters = [
    'search' => $_GET['search'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'low_stock' => $_GET['low_stock'] ?? '',
    'expiring_days' => $_GET['expiring_days'] ?? ''
];

// Export as CSV
export_catalog_csv($conn, $filters);

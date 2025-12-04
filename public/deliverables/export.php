<?php
/**
 * Deliverables Module - Export Report
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$format = $_GET['format'] ?? 'csv';

// Build WHERE clause
$where = ["1=1"];

if ($status_filter !== 'all') {
    $where[] = "d.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

$where_clause = implode(" AND ", $where);

// Fetch deliverables
$query = "SELECT d.*, 
    CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
    e.employee_code,
    CONCAT(u.username) as creator_name,
    wo.work_order_code,
    (SELECT COUNT(*) FROM deliverable_versions dv WHERE dv.deliverable_id = d.id) as total_versions,
    (SELECT COUNT(*) FROM deliverable_files df WHERE df.deliverable_id = d.id) as total_files
    FROM deliverables d
    LEFT JOIN employees e ON d.assigned_to = e.id
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN work_orders wo ON d.work_order_id = wo.id
    WHERE {$where_clause}
    ORDER BY d.created_at DESC";

$result = mysqli_query($conn, $query);
$deliverables = [];

while ($row = mysqli_fetch_assoc($result)) {
    $deliverables[] = $row;
}

if ($format === 'csv') {
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="deliverables_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'ID',
        'Deliverable Name',
        'Work Order',
        'Assigned To',
        'Status',
        'Current Version',
        'Total Versions',
        'Total Files',
        'Created By',
        'Created At',
        'Updated At'
    ]);
    
    // Data
    foreach ($deliverables as $d) {
        fputcsv($output, [
            $d['id'],
            $d['deliverable_name'],
            $d['work_order_code'] ?? 'N/A',
            $d['assigned_name'] . ' (' . $d['employee_code'] . ')',
            $d['status'],
            $d['current_version'],
            $d['total_versions'],
            $d['total_files'],
            $d['creator_name'],
            $d['created_at'],
            $d['updated_at'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    closeConnection($conn);
    exit;
}

closeConnection($conn);
?>

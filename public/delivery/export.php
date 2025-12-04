<?php
/**
 * Delivery Module - Export to CSV
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if (!empty($_GET['status'])) {
    $where_clauses[] = "di.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

if (!empty($_GET['channel'])) {
    $where_clauses[] = "di.channel = ?";
    $params[] = $_GET['channel'];
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT 
          di.id,
          di.status,
          di.channel,
          d.deliverable_name,
          wo.work_order_code,
          COALESCE(c.client_name, l.company_name) as client,
          CONCAT(e.first_name, ' ', e.last_name) as delivered_by,
          di.delivered_to_name,
          di.delivered_to_contact,
          di.main_link,
          di.delivered_at,
          di.created_at,
          di.notes
          FROM delivery_items di
          INNER JOIN deliverables d ON di.deliverable_id = d.id
          LEFT JOIN work_orders wo ON di.work_order_id = wo.id
          LEFT JOIN clients c ON di.client_id = c.id
          LEFT JOIN crm_leads l ON di.lead_id = l.id
          LEFT JOIN employees e ON di.delivered_by = e.id
          $where_sql
          ORDER BY di.created_at DESC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=delivery_export_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
fputcsv($output, [
    'ID',
    'Status',
    'Channel',
    'Deliverable',
    'Work Order',
    'Client',
    'Delivered By',
    'Recipient Name',
    'Recipient Contact',
    'Main Link',
    'Delivered At',
    'Created At',
    'Notes'
]);

// Write data rows
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['id'],
        $row['status'],
        $row['channel'],
        $row['deliverable_name'],
        $row['work_order_code'],
        $row['client'],
        $row['delivered_by'],
        $row['delivered_to_name'],
        $row['delivered_to_contact'],
        $row['main_link'],
        $row['delivered_at'],
        $row['created_at'],
        $row['notes']
    ]);
}

fclose($output);
closeConnection($conn);
exit;
?>

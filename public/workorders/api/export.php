require_once __DIR__ . '/../../../config/config.php';
<?php
/**
 * Work Orders API - Export to CSV
 */

// Removed auth_check.php include

// Permission checks removed

// Get filters from request
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$linked_type = isset($_GET['linked_type']) ? $_GET['linked_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query
$query = "SELECT wo.work_order_code, wo.order_date, wo.linked_type, 
          CASE 
              WHEN wo.linked_type = 'Lead' THEN l.company_name
              WHEN wo.linked_type = 'Client' THEN c.company_name
          END as linked_name,
          wo.service_type, wo.priority, wo.status, 
          wo.start_date, wo.due_date, wo.TAT_days,
          wo.internal_approval_status, wo.client_approval_status,
          wo.description, wo.created_at
          FROM work_orders wo
          LEFT JOIN crm_leads l ON wo.linked_type = 'Lead' AND wo.linked_id = l.id
          LEFT JOIN clients c ON wo.linked_type = 'Client' AND wo.linked_id = c.id
          WHERE 1=1";

$params = [];
$types = '';

if ($search) {
    $query .= " AND (wo.work_order_code LIKE ? OR wo.service_type LIKE ? OR l.company_name LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if ($status) {
    $query .= " AND wo.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($priority) {
    $query .= " AND wo.priority = ?";
    $params[] = $priority;
    $types .= 's';
}

if ($linked_type) {
    $query .= " AND wo.linked_type = ?";
    $params[] = $linked_type;
    $types .= 's';
}

if ($start_date && $end_date) {
    $query .= " AND wo.order_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}

$query .= " ORDER BY wo.order_date DESC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="work_orders_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, [
    'Work Order Code', 'Order Date', 'Linked Type', 'Linked Name', 
    'Service Type', 'Priority', 'Status', 'Start Date', 'Due Date', 
    'TAT (Days)', 'Internal Approval', 'Client Approval', 
    'Description', 'Created At'
]);

// Write data
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['work_order_code'],
        $row['order_date'],
        $row['linked_type'],
        $row['linked_name'],
        $row['service_type'],
        $row['priority'],
        $row['status'],
        $row['start_date'],
        $row['due_date'],
        $row['TAT_days'],
        $row['internal_approval_status'],
        $row['client_approval_status'],
        $row['description'],
        $row['created_at']
    ]);
}

fclose($output);

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
exit;
?>

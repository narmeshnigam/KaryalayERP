<?php
/**
 * Payments Module - Export to Excel
 * Export filtered payments list
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Build filter query
$where_clauses = [];
$params = [];
$types = '';

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clauses[] = "(p.payment_no LIKE ? OR c.name LIKE ? OR p.reference_no LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

// Client filter
if (!empty($_GET['client_id'])) {
    $where_clauses[] = "p.client_id = ?";
    $params[] = (int)$_GET['client_id'];
    $types .= 'i';
}

// Payment mode filter
if (!empty($_GET['payment_mode'])) {
    $where_clauses[] = "p.payment_mode = ?";
    $params[] = $_GET['payment_mode'];
    $types .= 's';
}

// Date range filters
if (!empty($_GET['date_from'])) {
    $where_clauses[] = "p.payment_date >= ?";
    $params[] = $_GET['date_from'];
    $types .= 's';
}
if (!empty($_GET['date_to'])) {
    $where_clauses[] = "p.payment_date <= ?";
    $params[] = $_GET['date_to'];
    $types .= 's';
}

// Build query
$sql = "SELECT p.payment_no, p.payment_date, p.payment_mode, p.reference_no, p.amount_received, 
               c.name as client_name, u.username as created_by_name,
               COALESCE(SUM(pim.allocated_amount), 0) as total_allocated,
               (p.amount_received - COALESCE(SUM(pim.allocated_amount), 0)) as unallocated_amount,
               p.remarks
        FROM payments p
        LEFT JOIN clients c ON p.client_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN payment_invoice_map pim ON p.id = pim.payment_id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " GROUP BY p.id ORDER BY p.payment_date DESC, p.created_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Generate filename
$filename = 'payments_export_' . date('Y-m-d_His') . '.csv';

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Payment No',
    'Payment Date',
    'Client Name',
    'Payment Mode',
    'Reference No',
    'Amount Received',
    'Total Allocated',
    'Unallocated Balance',
    'Remarks',
    'Created By'
]);

// Write data
foreach ($payments as $payment) {
    fputcsv($output, [
        $payment['payment_no'],
        date('d/m/Y', strtotime($payment['payment_date'])),
        $payment['client_name'],
        $payment['payment_mode'],
        $payment['reference_no'] ?? '',
        number_format($payment['amount_received'], 2, '.', ''),
        number_format($payment['total_allocated'], 2, '.', ''),
        number_format($payment['unallocated_amount'], 2, '.', ''),
        $payment['remarks'] ?? '',
        $payment['created_by_name'] ?? ''
    ]);
}

fclose($output);
exit;

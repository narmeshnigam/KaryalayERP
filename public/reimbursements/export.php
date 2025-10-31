<?php
/**
 * Export Reimbursements to CSV
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'reimbursements', 'export');

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

if (!($conn instanceof mysqli)) {
    flash_add('error', 'Unable to connect to the database.', 'reimbursements');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'reimbursements');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    display_prerequisite_error('reimbursements', $prereq_check['missing_modules']);
    exit;
}

if (!reimbursements_table_exists($conn)) {
    flash_add('error', 'Reimbursements module not set up yet.', 'reimbursements');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

$reimbursement_permissions = authz_get_permission_set($conn, 'reimbursements');
$can_view_all = $IS_SUPER_ADMIN || !empty($reimbursement_permissions['can_view_all']);
$can_view_own = !empty($reimbursement_permissions['can_view_own']);
$can_view_assigned = !empty($reimbursement_permissions['can_view_assigned']);

$current_employee_id = reimbursements_current_employee_id($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_view_all) {
    if ($can_view_own && $current_employee_id) {
        $restricted_employee_id = $current_employee_id;
    } elseif ($can_view_assigned) {
        authz_require_permission($conn, 'reimbursements', 'view_all');
    } else {
        authz_require_permission($conn, 'reimbursements', 'view_all');
    }
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

if ($restricted_employee_id !== null) {
    $employee_filter = $restricted_employee_id;
}

$where = ['r.expense_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($employee_filter > 0) {
    $where[] = 'r.employee_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($status_filter !== 'All') {
    $where[] = 'r.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($category_filter !== '') {
    $where[] = 'r.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where);
$sql = "SELECT r.id, r.date_submitted, r.expense_date, r.category, r.amount, r.description, r.status, r.proof_file, r.admin_remarks, r.action_date,
               e.employee_code, e.first_name, e.last_name, e.department
        FROM reimbursements r
        INNER JOIN employees e ON r.employee_id = e.id
        WHERE $where_clause
        ORDER BY r.date_submitted DESC, r.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    flash_add('error', 'Unable to prepare export query.', 'reimbursements');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

if ($types !== '') {
    reimbursements_stmt_bind($stmt, $types, $params);
}

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    flash_add('error', 'Failed to run export query.', 'reimbursements');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

$result = mysqli_stmt_get_result($stmt);

$filename = 'reimbursements_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$headers = [
    'Claim ID',
    'Employee Code',
    'Employee Name',
    'Department',
    'Date Submitted',
    'Expense Date',
    'Category',
    'Amount',
    'Status',
    'Admin Remarks',
    'Action Date',
    'Proof Path',
    'Description',
];
fputcsv($output, $headers);

while ($row = mysqli_fetch_assoc($result)) {
    $csv_row = [
        $row['id'],
        $row['employee_code'],
        trim($row['first_name'] . ' ' . $row['last_name']),
        $row['department'],
        $row['date_submitted'],
        $row['expense_date'],
        $row['category'],
        number_format((float) $row['amount'], 2, '.', ''),
        $row['status'],
        $row['admin_remarks'],
        $row['action_date'],
        $row['proof_file'],
        preg_replace("/\s+/", ' ', $row['description']),
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
mysqli_stmt_close($stmt);
$closeManagedConnection();
exit;
?>

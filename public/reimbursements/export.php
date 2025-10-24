<?php
/**
 * Export Reimbursements to CSV
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'manager'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    die('Unable to connect to database');
}

function tableExists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

if (!tableExists($conn, 'reimbursements')) {
    closeConnection($conn);
    die('Reimbursements table not found.');
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ['r.expense_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($status_filter !== 'All') {
    $where[] = 'r.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($employee_filter > 0) {
    $where[] = 'r.employee_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
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
$bind_params = [];
$bind_params[] = &$types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);
mysqli_stmt_execute($stmt);
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
    'Description'
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
        number_format((float)$row['amount'], 2, '.', ''),
        $row['status'],
        $row['admin_remarks'],
        $row['action_date'],
        $row['proof_file'],
        preg_replace("/\s+/", ' ', $row['description'])
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
mysqli_stmt_close($stmt);
closeConnection($conn);
exit;
?>

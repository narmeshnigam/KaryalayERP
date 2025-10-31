<?php
/**
 * Expense Tracker - CSV Export
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'office_expenses', 'export');

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

if (!($conn instanceof mysqli)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to connect to the database.';
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'office_expenses');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Prerequisites missing for office expenses module.';
    exit;
}

if (!office_expenses_table_exists($conn)) {
    $closeManagedConnection();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Expense table not found. Please run the setup script.';
    exit;
}

$expense_permissions = authz_get_permission_set($conn, 'office_expenses');
$can_view_all = !empty($expense_permissions['can_view_all']);
$can_view_own = !empty($expense_permissions['can_view_own']);

$current_employee = office_expenses_current_employee($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_view_all) {
    if ($can_view_own && $current_employee) {
        $restricted_employee_id = (int) $current_employee['id'];
    } else {
        $closeManagedConnection();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'You do not have permission to export these expenses.';
        exit;
    }
}

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-01-01');
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$payment_filter = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : '';
$vendor_filter = isset($_GET['vendor']) ? trim($_GET['vendor']) : '';

$where = ['e.date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($category_filter !== '') {
    $where[] = 'e.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($payment_filter !== '') {
    $where[] = 'e.payment_mode = ?';
    $params[] = $payment_filter;
    $types .= 's';
}
if ($vendor_filter !== '') {
    $where[] = 'e.vendor_name LIKE ?';
    $params[] = '%' . $vendor_filter . '%';
    $types .= 's';
}
if ($restricted_employee_id !== null) {
    $where[] = 'e.added_by = ?';
    $params[] = $restricted_employee_id;
    $types .= 'i';
}
$where_clause = implode(' AND ', $where);

$sql = "SELECT e.date, e.category, e.vendor_name, e.description, e.amount, e.payment_mode, e.receipt_file,
               emp.employee_code, emp.first_name, emp.last_name
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE $where_clause
        ORDER BY e.date ASC, e.id ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $closeManagedConnection();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to prepare export statement.';
    exit;
}

if (!empty($params)) {
    office_expenses_stmt_bind($stmt, $types, $params);
}

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $closeManagedConnection();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to execute export query.';
    exit;
}

$result = mysqli_stmt_get_result($stmt);

if (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'office_expenses_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Category', 'Vendor / Payee', 'Description', 'Amount (INR)', 'Payment Mode', 'Recorded By', 'Receipt URL']);

while ($row = mysqli_fetch_assoc($result)) {
    $employee_code = $row['employee_code'] ?? '';
    $employee_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $recorded_by = trim($employee_code . ' ' . $employee_name);
    $receipt_url = '';
    if (!empty($row['receipt_file'])) {
        $receipt_url = APP_URL . '/' . ltrim($row['receipt_file'], '/');
    }
    fputcsv($output, [
        $row['date'],
        $row['category'],
        $row['vendor_name'],
        preg_replace("/\r?\n/", ' ', $row['description']),
        sprintf('%.2f', (float) $row['amount']),
        $row['payment_mode'],
        $recorded_by,
        $receipt_url,
    ]);
}

fclose($output);
mysqli_stmt_close($stmt);
$closeManagedConnection();
exit;

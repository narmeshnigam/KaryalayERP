<?php
/**
 * Expense Tracker - CSV Export
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
    header('Location: ../index.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to connect to the database.';
    exit;
}

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function refValues(array &$arr)
{
    $refs = [];
    foreach ($arr as $key => &$value) {
        $refs[$key] = &$value;
    }
    return $refs;
}

if (!tableExists($conn, 'office_expenses')) {
    closeConnection($conn);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Expense table not found. Please run the setup script.';
    exit;
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
$where_clause = implode(' AND ', $where);

$sql = "SELECT e.date, e.category, e.vendor_name, e.description, e.amount, e.payment_mode, e.receipt_file,
               emp.employee_code, emp.first_name, emp.last_name
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE $where_clause
        ORDER BY e.date ASC, e.id ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    closeConnection($conn);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to prepare export statement.';
    exit;
}

if (!empty($params)) {
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = $params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], refValues($bind));
}

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    closeConnection($conn);
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
closeConnection($conn);
exit;

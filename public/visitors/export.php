<?php
/**
 * Visitor Log Module - CSV Export
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
    header('Content-Type: text/plain');
    http_response_code(500);
    echo 'Database connection failed.';
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

if (!tableExists($conn, 'visitor_logs')) {
    closeConnection($conn);
    header('Content-Type: text/plain');
    http_response_code(503);
    echo 'Visitor Log module not initialised.';
    exit;
}

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');
$employee_filter = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$visitor_filter = isset($_GET['visitor']) ? trim($_GET['visitor']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = ['vl.deleted_at IS NULL', 'DATE(vl.check_in_time) BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($employee_filter > 0) {
    $where[] = 'vl.employee_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($visitor_filter !== '') {
    $where[] = 'vl.visitor_name LIKE ?';
    $params[] = '%' . $visitor_filter . '%';
    $types .= 's';
}

if ($status_filter === 'checked_in') {
    $where[] = 'vl.check_out_time IS NULL';
} elseif ($status_filter === 'checked_out') {
    $where[] = 'vl.check_out_time IS NOT NULL';
}

$where_clause = implode(' AND ', $where);
$sql = "SELECT vl.visitor_name, vl.phone, vl.purpose, vl.check_in_time, vl.check_out_time,
               emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
               added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
        FROM visitor_logs vl
        LEFT JOIN employees emp ON vl.employee_id = emp.id
        LEFT JOIN employees added ON vl.added_by = added.id
        WHERE $where_clause
        ORDER BY vl.check_in_time DESC";

$stmt = mysqli_prepare($conn, $sql);
$bind = [$types];
foreach ($params as $index => $value) {
    $bind[] = &$params[$index];
}
call_user_func_array([$stmt, 'bind_param'], $bind);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$filename = 'visitor_logs_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Visitor Name', 'Phone', 'Purpose', 'Check-in', 'Check-out', 'Meeting With', 'Logged By']);

while ($row = mysqli_fetch_assoc($result)) {
    $meeting_with = trim(($row['visiting_code'] ?? '') . ' ' . ($row['visiting_first'] ?? '') . ' ' . ($row['visiting_last'] ?? ''));
    $logged_by = trim(($row['added_code'] ?? '') . ' ' . ($row['added_first'] ?? '') . ' ' . ($row['added_last'] ?? ''));
    fputcsv($output, [
        $row['visitor_name'],
        $row['phone'],
        $row['purpose'],
        date('d M Y H:i', strtotime($row['check_in_time'])),
        $row['check_out_time'] ? date('d M Y H:i', strtotime($row['check_out_time'])) : '',
        $meeting_with,
        $logged_by
    ]);
}

fclose($output);
mysqli_stmt_close($stmt);
closeConnection($conn);
exit;

<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
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
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Visitor Log module not initialised']);
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
$sql = "SELECT vl.id, vl.visitor_name, vl.phone, vl.purpose, vl.check_in_time, vl.check_out_time, vl.photo,
               vl.employee_id, vl.added_by,
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

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

mysqli_stmt_close($stmt);
closeConnection($conn);

echo json_encode(['success' => true, 'count' => count($data), 'data' => $data]);
?>

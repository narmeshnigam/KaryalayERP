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
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Reimbursement module not initialized']);
    exit;
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$employee_filter = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ['r.expense_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if (in_array($status_filter, ['Pending','Approved','Rejected'], true)) {
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
$sql = "SELECT r.*, e.employee_code, e.first_name, e.last_name, e.department
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
$claims = [];
while ($row = mysqli_fetch_assoc($result)) {
    $claims[] = $row;
}
mysqli_stmt_close($stmt);
closeConnection($conn);

echo json_encode(['success' => true, 'count' => count($claims), 'data' => $claims]);
?>

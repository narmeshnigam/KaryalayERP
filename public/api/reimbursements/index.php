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

$user_id = $_SESSION['user_id'];
$emp_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_res = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_res);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Employee profile not found']);
    exit;
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ['employee_id = ?', 'expense_date BETWEEN ? AND ?'];
$params = [$employee['id'], $from_date, $to_date];
$types = 'iss';

if (in_array($status_filter, ['Pending','Approved','Rejected'], true)) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where);
$sql = "SELECT id, date_submitted, expense_date, category, amount, description, status, proof_file, admin_remarks, action_date
        FROM reimbursements WHERE $where_clause ORDER BY expense_date DESC, id DESC";
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

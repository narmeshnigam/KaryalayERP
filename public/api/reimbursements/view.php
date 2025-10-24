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

$claim_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($claim_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid claim id']);
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
$user_role = $_SESSION['role'] ?? 'user';
$employee_id = null;

if (!in_array($user_role, ['admin', 'manager'], true)) {
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
    $employee_id = $employee['id'];
}

if ($employee_id !== null) {
    $sql = 'SELECT * FROM reimbursements WHERE id = ? AND employee_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $claim_id, $employee_id);
} else {
    $sql = 'SELECT r.*, e.employee_code, e.first_name, e.last_name, e.department FROM reimbursements r INNER JOIN employees e ON r.employee_id = e.id WHERE r.id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $claim_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$claim = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
closeConnection($conn);

if (!$claim) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Claim not found']);
    exit;
}

echo json_encode(['success' => true, 'data' => $claim]);
?>

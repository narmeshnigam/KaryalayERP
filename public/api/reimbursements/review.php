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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$claim_id = isset($_POST['claim_id']) ? (int) $_POST['claim_id'] : 0;
$decision = $_POST['decision'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if ($claim_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid claim id']);
    exit;
}

$allowed = ['Approved', 'Rejected', 'Pending'];
if (!in_array($decision, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid decision']);
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

$update_sql = 'UPDATE reimbursements SET status = ?, admin_remarks = ?, action_date = NOW() WHERE id = ?';
$stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($stmt, 'ssi', $decision, $remarks, $claim_id);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) {
    mysqli_stmt_close($stmt);
    closeConnection($conn);
    echo json_encode(['success' => true, 'message' => 'Claim updated']);
    exit;
}

$error = mysqli_error($conn);
mysqli_stmt_close($stmt);
closeConnection($conn);
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to update claim', 'error' => $error]);
?>

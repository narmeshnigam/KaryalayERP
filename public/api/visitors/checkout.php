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
if (!in_array($user_role, ['admin', 'manager', 'guard'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$visitor_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($visitor_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid visitor id']);
    exit;
}

$checkout_input = trim($_POST['checkout_time'] ?? '');
$checkout_time = $checkout_input !== '' ? strtotime($checkout_input) : time();
if ($checkout_time === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid checkout_time format']);
    exit;
}
$checkout_time_formatted = date('Y-m-d H:i:s', $checkout_time);

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

$select_stmt = mysqli_prepare($conn, 'SELECT check_in_time, check_out_time FROM visitor_logs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
mysqli_stmt_bind_param($select_stmt, 'i', $visitor_id);
mysqli_stmt_execute($select_stmt);
$result = mysqli_stmt_get_result($select_stmt);
$visitor = mysqli_fetch_assoc($result);
mysqli_stmt_close($select_stmt);

if (!$visitor) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visitor log not found']);
    exit;
}

if (!empty($visitor['check_out_time'])) {
    closeConnection($conn);
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Visitor already checked out']);
    exit;
}

if (strtotime($visitor['check_in_time']) > $checkout_time) {
    closeConnection($conn);
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Checkout time cannot be earlier than check-in']);
    exit;
}

$update_stmt = mysqli_prepare($conn, 'UPDATE visitor_logs SET check_out_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
mysqli_stmt_bind_param($update_stmt, 'si', $checkout_time_formatted, $visitor_id);
if (!mysqli_stmt_execute($update_stmt)) {
    $error = mysqli_error($conn);
    mysqli_stmt_close($update_stmt);
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update checkout time', 'error' => $error]);
    exit;
}
mysqli_stmt_close($update_stmt);
closeConnection($conn);

echo json_encode(['success' => true, 'message' => 'Checkout time updated']);
?>

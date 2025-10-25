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

$visitor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($visitor_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid visitor id']);
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

$sql = "SELECT vl.*, emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
               added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
        FROM visitor_logs vl
        LEFT JOIN employees emp ON vl.employee_id = emp.id
        LEFT JOIN employees added ON vl.added_by = added.id
        WHERE vl.id = ? AND vl.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $visitor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$visitor = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
closeConnection($conn);

if (!$visitor) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visitor not found']);
    exit;
}

echo json_encode(['success' => true, 'data' => $visitor]);
?>

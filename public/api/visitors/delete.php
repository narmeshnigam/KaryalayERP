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

if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

$stmt = mysqli_prepare($conn, 'UPDATE visitor_logs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL');
mysqli_stmt_bind_param($stmt, 'i', $visitor_id);
if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to archive visitor log', 'error' => $error]);
    exit;
}

$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
closeConnection($conn);

if ($affected === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visitor log not found or already archived']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Visitor log archived']);
?>

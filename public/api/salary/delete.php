<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/module_dependencies.php';
require_once __DIR__ . '/../../../includes/authz.php';
require_once __DIR__ . '/../../salary/helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'DELETE' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$record_id = 0;
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $payload);
    $record_id = isset($payload['id']) ? (int) $payload['id'] : 0;
} else {
    $record_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
}

if ($record_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record id']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!authz_user_can($conn, 'salary_records', 'delete_all')) {
    closeConnection($conn);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'salary');
if (!$prereq_check['allowed']) {
    closeConnection($conn);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Salary module prerequisites missing',
        'missing' => $prereq_check['missing_modules'],
    ]);
    exit;
}

if (!salary_table_exists($conn)) {
    closeConnection($conn);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Salary module not initialised']);
    exit;
}

$select = mysqli_prepare($conn, 'SELECT slip_path, is_locked FROM salary_records WHERE id = ? LIMIT 1');
if (!$select) {
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load salary record']);
    exit;
}

mysqli_stmt_bind_param($select, 'i', $record_id);
mysqli_stmt_execute($select);
$result = mysqli_stmt_get_result($select);
$record = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($select);

if (!$record) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Salary record not found']);
    exit;
}

if ((int) $record['is_locked'] === 1) {
    closeConnection($conn);
    http_response_code(423);
    echo json_encode(['success' => false, 'message' => 'Locked salary records cannot be deleted']);
    exit;
}

$delete = mysqli_prepare($conn, 'DELETE FROM salary_records WHERE id = ?');
if (!$delete) {
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement']);
    exit;
}

mysqli_stmt_bind_param($delete, 'i', $record_id);
if (!mysqli_stmt_execute($delete)) {
    mysqli_stmt_close($delete);
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete salary record']);
    exit;
}
mysqli_stmt_close($delete);

if (!empty($record['slip_path'])) {
    $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($record['slip_path']);
    if (is_file($file)) {
        unlink($file);
    }
}

closeConnection($conn);

echo json_encode(['success' => true, 'message' => 'Salary record deleted']);
?>

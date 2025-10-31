<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/module_dependencies.php';
require_once __DIR__ . '/../../../includes/authz.php';
require_once __DIR__ . '/../../salary/helpers.php';

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

$record_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

$hasViewAll = authz_user_can($conn, 'salary_records', 'view_all');
$hasViewOwn = authz_user_can($conn, 'salary_records', 'view_own');

$current_employee_id = salary_current_employee_id($conn, (int) $_SESSION['user_id']);

$sql = "SELECT sr.*, emp.employee_code, emp.first_name, emp.last_name
        FROM salary_records sr
        INNER JOIN employees emp ON sr.employee_id = emp.id
        WHERE sr.id = ?
        LIMIT 1";
$select = mysqli_prepare($conn, $sql);
if (!$select) {
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
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

$owns_record = $current_employee_id && ((int) $record['employee_id'] === (int) $current_employee_id);
if (!$hasViewAll && (!$hasViewOwn || !$owns_record)) {
    closeConnection($conn);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

closeConnection($conn);

echo json_encode(['success' => true, 'data' => $record]);
?>

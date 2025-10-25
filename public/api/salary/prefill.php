<?php
/**
 * Salary Prefill API
 * GET/POST: employee_id, month (YYYY-MM)
 * Returns: { employee: {...}, attendance: {...} }
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../salary/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$employee_id = isset($_REQUEST['employee_id']) ? (int) $_REQUEST['employee_id'] : 0;
$month = isset($_REQUEST['month']) ? trim($_REQUEST['month']) : date('Y-m');

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

if ($employee_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    closeConnection($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$employee = salary_fetch_employee_snapshot($conn, $employee_id);
$attendance = salary_compute_monthly_attendance($conn, $employee_id, $month);

closeConnection($conn);

echo json_encode([
    'success' => true,
    'employee' => $employee,
    'attendance' => $attendance,
]);

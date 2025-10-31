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
$hasViewAssigned = authz_user_can($conn, 'salary_records', 'view_assigned');

$current_employee_id = salary_current_employee_id($conn, (int) $_SESSION['user_id']);

$requested_employee = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
if (!$hasViewAll) {
    if ($hasViewOwn) {
        $requested_employee = (int) ($current_employee_id ?: 0);
    } else {
        closeConnection($conn);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not allowed to view salary records']);
        exit;
    }
}

if ($requested_employee <= 0) {
    closeConnection($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee profile not found for current user']);
    exit;
}

$defaults = salary_month_range_default();
$from_month = isset($_GET['from_month']) ? trim($_GET['from_month']) : $defaults[0];
$to_month = isset($_GET['to_month']) ? trim($_GET['to_month']) : $defaults[1];

$validate_month = static function (string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback;
};

$from_month = $validate_month($from_month, $defaults[0]);
$to_month = $validate_month($to_month, $defaults[1]);

$sql = "SELECT id, employee_id, month, base_salary, allowances, deductions, net_pay, slip_path, is_locked, created_at, updated_at
        FROM salary_records
        WHERE employee_id = ? AND month BETWEEN ? AND ?
        ORDER BY month DESC";

$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $requested_employee, $from_month, $to_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

closeConnection($conn);

echo json_encode([
    'success' => true,
    'employee_id' => $requested_employee,
    'count' => count($rows),
    'data' => $rows,
]);
?>

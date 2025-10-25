<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../salary/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
if (!salary_role_can_manage($user_role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if (!salary_table_exists($conn)) {
    closeConnection($conn);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Salary module not initialised']);
    exit;
}

$employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
$month = isset($_POST['month']) ? trim($_POST['month']) : '';
$base_salary = trim($_POST['base_salary'] ?? '');
$allowances = trim($_POST['allowances'] ?? '');
$deductions = trim($_POST['deductions'] ?? '');
$is_locked = isset($_POST['is_locked']) && $_POST['is_locked'] === '1';

$errors = [];
if ($employee_id <= 0) {
    $errors[] = 'employee_id is required.';
}
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $errors[] = 'month must be in YYYY-MM format.';
}

$base = is_numeric($base_salary) ? (float) $base_salary : null;
$allow = ($allowances === '' ? 0.0 : (is_numeric($allowances) ? (float) $allowances : null));
$deduct = ($deductions === '' ? 0.0 : (is_numeric($deductions) ? (float) $deductions : null));

if ($base === null || $base < 0) {
    $errors[] = 'base_salary must be a non-negative number.';
}
if ($allow === null || $allow < 0) {
    $errors[] = 'allowances must be a non-negative number.';
}
if ($deduct === null || $deduct < 0) {
    $errors[] = 'deductions must be a non-negative number.';
}

if (!empty($errors)) {
    closeConnection($conn);
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$employee_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE id = ? LIMIT 1');
$employee_exists = false;
if ($employee_stmt) {
    mysqli_stmt_bind_param($employee_stmt, 'i', $employee_id);
    mysqli_stmt_execute($employee_stmt);
    $res = mysqli_stmt_get_result($employee_stmt);
    $employee_exists = (bool) ($res && mysqli_fetch_assoc($res));
    mysqli_stmt_close($employee_stmt);
}
if (!$employee_exists) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

$slip_path = null;
if (isset($_FILES['slip']) && $_FILES['slip']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['slip'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        closeConnection($conn);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error uploading salary slip']);
        exit;
    }
    $size_limit = 5 * 1024 * 1024;
    if ($file['size'] > $size_limit) {
        closeConnection($conn);
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => 'Salary slip exceeds 5 MB limit']);
        exit;
    }
    $detected = @mime_content_type($file['tmp_name']);
    if ($detected !== 'application/pdf') {
        closeConnection($conn);
        http_response_code(415);
        echo json_encode(['success' => false, 'message' => 'Salary slip must be a PDF']);
        exit;
    }
    if (!salary_ensure_upload_directory()) {
        closeConnection($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to create salary slip directory']);
        exit;
    }
    try {
        $token = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
    }
    $filename = 'salary_' . $employee_id . '_' . str_replace('-', '', $month) . '_' . $token . '.pdf';
    $destination = salary_upload_directory() . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        closeConnection($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to store uploaded slip']);
        exit;
    }
    $slip_path = 'uploads/salary_slips/' . $filename;
}

$net = ($base ?? 0) + ($allow ?? 0) - ($deduct ?? 0);
$uploaded_by = salary_current_employee_id($conn, (int) $_SESSION['user_id']) ?: null;
$insert = mysqli_prepare($conn, 'INSERT INTO salary_records (employee_id, month, base_salary, allowances, deductions, net_pay, slip_path, is_locked, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$insert) {
    if ($slip_path) {
        $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($slip_path);
        if (is_file($file)) {
            unlink($file);
        }
    }
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement']);
    exit;
}

$locked_flag = $is_locked ? 1 : 0;
mysqli_stmt_bind_param($insert, 'isddddsii', $employee_id, $month, $base, $allow, $deduct, $net, $slip_path, $locked_flag, $uploaded_by);
if (!mysqli_stmt_execute($insert)) {
    $error_code = mysqli_errno($conn);
    mysqli_stmt_close($insert);
    if ($slip_path) {
        $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($slip_path);
        if (is_file($file)) {
            unlink($file);
        }
    }
    closeConnection($conn);
    if ($error_code === 1062) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Salary record already exists for this employee and month']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create salary record']);
    }
    exit;
}

$created_id = mysqli_insert_id($conn);
mysqli_stmt_close($insert);
salary_notify_salary_uploaded($conn, $created_id);
closeConnection($conn);

echo json_encode(['success' => true, 'message' => 'Salary record created', 'id' => $created_id]);
?>

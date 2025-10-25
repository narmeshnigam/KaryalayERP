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
if (!salary_role_can_edit($user_role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if (!salary_table_exists($conn)) {
    closeConnection($conn);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Salary module not initialised']);
    exit;
}

$select = mysqli_prepare($conn, 'SELECT * FROM salary_records WHERE id = ? LIMIT 1');
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
    echo json_encode(['success' => false, 'message' => 'Salary record is locked and cannot be edited']);
    exit;
}

$employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : (int) $record['employee_id'];
$month = isset($_POST['month']) ? trim($_POST['month']) : $record['month'];
$base_salary = trim($_POST['base_salary'] ?? (string) $record['base_salary']);
$allowances = trim($_POST['allowances'] ?? (string) $record['allowances']);
$deductions = trim($_POST['deductions'] ?? (string) $record['deductions']);
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

$new_slip_path = null;
$old_slip = $record['slip_path'];
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
    $new_slip_path = 'uploads/salary_slips/' . $filename;
}

$net = ($base ?? 0) + ($allow ?? 0) - ($deduct ?? 0);
$locked_flag = $is_locked ? 1 : 0;
$slip_to_save = $new_slip_path ?? $old_slip;

$update = mysqli_prepare($conn, 'UPDATE salary_records SET employee_id = ?, month = ?, base_salary = ?, allowances = ?, deductions = ?, net_pay = ?, slip_path = ?, is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
if (!$update) {
    if ($new_slip_path) {
        $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($new_slip_path);
        if (is_file($file)) {
            unlink($file);
        }
    }
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement']);
    exit;
}

mysqli_stmt_bind_param($update, 'isddddsii', $employee_id, $month, $base, $allow, $deduct, $net, $slip_to_save, $locked_flag, $record_id);
if (!mysqli_stmt_execute($update)) {
    $error_code = mysqli_errno($conn);
    mysqli_stmt_close($update);
    if ($new_slip_path) {
        $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($new_slip_path);
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
        echo json_encode(['success' => false, 'message' => 'Failed to update salary record']);
    }
    exit;
}

mysqli_stmt_close($update);
if ($new_slip_path && $old_slip && $new_slip_path !== $old_slip) {
    $old_file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($old_slip);
    if (is_file($old_file)) {
        unlink($old_file);
    }
}

closeConnection($conn);

echo json_encode(['success' => true, 'message' => 'Salary record updated']);
?>

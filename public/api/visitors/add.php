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

$user_id = (int) $_SESSION['user_id'];
$emp_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_res = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_res);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    closeConnection($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee profile not found for current user']);
    exit;
}

$visitor_name = trim($_POST['visitor_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
$check_in_input = trim($_POST['check_in_time'] ?? '');

$errors = [];
if ($visitor_name === '') {
    $errors[] = 'Visitor name is required.';
}
if ($purpose === '') {
    $errors[] = 'Purpose of visit is required.';
}
if ($employee_id <= 0) {
    $errors[] = 'Valid employee_id is required.';
}
if ($check_in_input === '' || strtotime($check_in_input) === false) {
    $errors[] = 'Valid check_in_time is required.';
} else {
    $check_in_time_db = date('Y-m-d H:i:s', strtotime($check_in_input));
}

if (!empty($errors)) {
    closeConnection($conn);
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$emp_valid_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($emp_valid_stmt, 'i', $employee_id);
mysqli_stmt_execute($emp_valid_stmt);
$emp_valid_res = mysqli_stmt_get_result($emp_valid_stmt);
$target_employee = mysqli_fetch_assoc($emp_valid_res);
mysqli_stmt_close($emp_valid_stmt);

if (!$target_employee) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        closeConnection($conn);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        exit;
    }
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 2 * 1024 * 1024;
    $detected = @mime_content_type($file['tmp_name']);
    if ($detected === false || !in_array($detected, $allowed_types, true)) {
        closeConnection($conn);
        http_response_code(415);
        echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
        exit;
    }
    if ($file['size'] > $max_size) {
        closeConnection($conn);
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => 'File exceeds 2 MB limit']);
        exit;
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    try {
        $token = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
    }
    $filename = 'visitor_' . time() . '_' . $token . '.' . $extension;
    $dest_dir = __DIR__ . '/../../../uploads/visitor_logs';
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }
    $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        closeConnection($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to store uploaded file']);
        exit;
    }
    $photo_path = 'uploads/visitor_logs/' . $filename;
}

$insert_sql = 'INSERT INTO visitor_logs (visitor_name, phone, purpose, check_in_time, employee_id, photo, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)';
$stmt = mysqli_prepare($conn, $insert_sql);
$phone_param = $phone !== '' ? $phone : null;
mysqli_stmt_bind_param($stmt, 'ssssisi', $visitor_name, $phone_param, $purpose, $check_in_time_db, $employee_id, $photo_path, $employee['id']);
if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create visitor log', 'error' => $error]);
    exit;
}
$created_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
closeConnection($conn);

echo json_encode(['success' => true, 'message' => 'Visitor log created', 'id' => $created_id]);
?>

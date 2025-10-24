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

function tableExists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

if (!tableExists($conn, 'reimbursements')) {
    closeConnection($conn);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Reimbursement module not initialized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$emp_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_res = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_res);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    closeConnection($conn);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Employee profile not found']);
    exit;
}

$expense_date = $_POST['expense_date'] ?? '';
$category = $_POST['category'] ?? '';
$amount = $_POST['amount'] ?? '';
$description = trim($_POST['description'] ?? '');
$categories = ['Travel', 'Food', 'Internet', 'Accommodation', 'Supplies', 'Other'];

$errors = [];
if (empty($expense_date)) {
    $errors[] = 'expense_date is required';
}
if (!in_array($category, $categories, true)) {
    $errors[] = 'category is invalid';
}
if (!is_numeric($amount) || (float)$amount <= 0) {
    $errors[] = 'amount must be positive';
}
if ($description === '') {
    $errors[] = 'description is required';
}
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'proof file is required';
}

if (!empty($errors)) {
    closeConnection($conn);
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$file = $_FILES['proof'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    closeConnection($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 2 * 1024 * 1024;
$file_type = mime_content_type($file['tmp_name']);
if ($file_type === false || !in_array($file_type, $allowed_types, true)) {
    closeConnection($conn);
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
    exit;
}
if ($file['size'] > $max_size) {
    closeConnection($conn);
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'File exceeds 2MB limit']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
try {
    $token = bin2hex(random_bytes(4));
} catch (Exception $e) {
    $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
}
$safe_name = 'reimb_' . time() . '_' . $token . '.' . strtolower($ext);
$upload_dir = __DIR__ . '/../../../uploads/reimbursements';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$destination = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    closeConnection($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to store file']);
    exit;
}

$proof_path = 'uploads/reimbursements/' . $safe_name;
$insert_sql = 'INSERT INTO reimbursements (employee_id, date_submitted, expense_date, category, amount, description, status, proof_file) VALUES (?, ?, ?, ?, ?, ?, "Pending", ?)';
$today = date('Y-m-d');
$amount_value = round((float)$amount, 2);
$stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($stmt, 'isssdss', $employee['id'], $today, $expense_date, $category, $amount_value, $description, $proof_path);

if (mysqli_stmt_execute($stmt)) {
    $claim_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    closeConnection($conn);
    echo json_encode(['success' => true, 'message' => 'Claim submitted', 'claim_id' => $claim_id]);
    exit;
}

mysqli_stmt_close($stmt);
closeConnection($conn);
@unlink(__DIR__ . '/../../../' . $proof_path);
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to save claim']);
?>

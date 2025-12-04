require_once __DIR__ . '/../../../config/config.php';
<?php
/**
 * Work Orders API - Delete File
 */

// Removed auth_check.php include

// Permission checks removed

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

if (!$file_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
    exit;
}

// Fetch file details
$query = "SELECT * FROM work_order_files WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $file_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$file = mysqli_fetch_assoc($result);

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Delete physical file
$file_path = __DIR__ . '/../../../' . $file['file_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Delete database record
$delete_query = "DELETE FROM work_order_files WHERE id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($delete_stmt, 'i', $file_id);

if (mysqli_stmt_execute($delete_stmt)) {
    // Log activity
    $activity_query = "INSERT INTO work_order_activity_log (work_order_id, action_type, action_by, description) VALUES (?, 'Comment', ?, ?)";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    $user_id = $_SESSION['user_id'];
    $remarks = "Deleted file: " . $file['file_name'];
    mysqli_stmt_bind_param($activity_stmt, 'iis', $file['work_order_id'], $user_id, $remarks);
    mysqli_stmt_execute($activity_stmt);
    
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

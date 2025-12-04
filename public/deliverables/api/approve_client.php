<?php
/**
 * API: Approve Deliverable by Client
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = createConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 1;
$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$deliverable_id) {
    $_SESSION['flash_message'] = "Invalid deliverable ID";
    $_SESSION['flash_type'] = 'error';
    header('Location: ../index.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Check current status
    $check_query = "SELECT status, current_version FROM deliverables WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, 'i', $deliverable_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $current = mysqli_fetch_assoc($check_result);

    if (!$current || $current['status'] !== 'Client Review') {
        throw new Exception("Deliverable is not in client review status");
    }

    // Update deliverable status
    $update_query = "UPDATE deliverables SET status = 'Client Approved', updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'i', $deliverable_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Failed to update status");
    }

    // Update version approval
    $approver_name = $_SESSION['username'] ?? 'Client';
    $version_query = "UPDATE deliverable_versions 
        SET approval_client = 1, approved_by_client = ?, approval_date_client = NOW() 
        WHERE deliverable_id = ? AND version_no = ?";
    $version_stmt = mysqli_prepare($conn, $version_query);
    mysqli_stmt_bind_param($version_stmt, 'sii', $approver_name, $deliverable_id, $current['current_version']);
    mysqli_stmt_execute($version_stmt);

    // Log activity
    $activity_query = "INSERT INTO deliverable_activity_log 
        (deliverable_id, action_by, action_type, notes, created_at) 
        VALUES (?, ?, 'Approve Client', 'Deliverable approved by client', NOW())";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    mysqli_stmt_bind_param($activity_stmt, 'ii', $deliverable_id, $user_id);
    mysqli_stmt_execute($activity_stmt);

    mysqli_commit($conn);

    $_SESSION['flash_message'] = "Deliverable approved by client!";
    $_SESSION['flash_type'] = 'success';

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
}

closeConnection($conn);
header('Location: ../view.php?id=' . $deliverable_id);
exit;
?>

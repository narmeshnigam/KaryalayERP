<?php
/**
 * API: Update Deliverable
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = createConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Invalid request method";
    $_SESSION['flash_type'] = 'error';
    header('Location: ../index.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    $deliverable_id = isset($_POST['deliverable_id']) ? intval($_POST['deliverable_id']) : 0;
    $work_order_id = isset($_POST['work_order_id']) ? intval($_POST['work_order_id']) : 0;
    $deliverable_name = trim($_POST['deliverable_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0;

    if (!$deliverable_id || !$work_order_id || empty($deliverable_name) || empty($description) || !$assigned_to) {
        throw new Exception("All required fields must be filled");
    }

    // Update deliverable
    $update_query = "UPDATE deliverables SET 
        work_order_id = ?, 
        deliverable_name = ?, 
        description = ?, 
        assigned_to = ?, 
        updated_at = NOW() 
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'issii', $work_order_id, $deliverable_name, $description, $assigned_to, $deliverable_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update deliverable: " . mysqli_error($conn));
    }

    // Log activity
    $activity_query = "INSERT INTO deliverable_activity_log 
        (deliverable_id, action_by, action_type, notes, created_at) 
        VALUES (?, ?, 'Update', 'Deliverable information updated', NOW())";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    mysqli_stmt_bind_param($activity_stmt, 'ii', $deliverable_id, $user_id);
    mysqli_stmt_execute($activity_stmt);

    mysqli_commit($conn);

    $_SESSION['flash_message'] = "Deliverable updated successfully!";
    $_SESSION['flash_type'] = 'success';

    header('Location: ../view.php?id=' . $deliverable_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    
    $deliverable_id = $_POST['deliverable_id'] ?? 0;
    header('Location: ../edit.php?id=' . $deliverable_id);
    exit;
}

closeConnection($conn);
?>

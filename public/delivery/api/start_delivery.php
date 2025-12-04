<?php
/**
 * API: Start Delivery (Pending → In Progress)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = createConnection();

try {
    $delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
    $performed_by = 1; // TODO: Replace with session user_id
    
    if (!$delivery_id) {
        throw new Exception('Delivery ID is required');
    }
    
    // Verify delivery exists and is pending
    $check_query = "SELECT id, status FROM delivery_items WHERE id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $delivery = mysqli_fetch_assoc($result);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    if ($delivery['status'] !== 'Pending') {
        throw new Exception('Only pending deliveries can be started');
    }
    
    mysqli_begin_transaction($conn);
    
    // Update status
    $update_query = "UPDATE delivery_items SET status = 'In Progress' WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'i', $delivery_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update delivery status');
    }
    
    // Log activity
    $log_query = "INSERT INTO delivery_activity_log 
                  (delivery_id, activity_type, description, performed_by) 
                  VALUES (?, 'status_changed', 'Status changed to In Progress', ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($log_stmt, 'ii', $delivery_id, $performed_by);
    mysqli_stmt_execute($log_stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Delivery started successfully'
    ]);
    
    header('Location: ../view.php?id=' . $delivery_id);
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

closeConnection($conn);
?>

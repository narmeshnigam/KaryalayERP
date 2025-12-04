<?php
/**
 * API: Update Delivery Item
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
    $channel = isset($_POST['channel']) ? trim($_POST['channel']) : '';
    
    if (!$delivery_id || empty($channel)) {
        throw new Exception('Delivery ID and channel are required');
    }
    
    // Verify delivery exists
    $check_query = "SELECT id FROM delivery_items WHERE id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Delivery not found');
    }
    
    // Get form data
    $delivered_by = !empty($_POST['delivered_by']) ? intval($_POST['delivered_by']) : null;
    $main_link = !empty($_POST['main_link']) ? trim($_POST['main_link']) : null;
    $delivered_to_name = !empty($_POST['delivered_to_name']) ? trim($_POST['delivered_to_name']) : null;
    $delivered_to_contact = !empty($_POST['delivered_to_contact']) ? trim($_POST['delivered_to_contact']) : null;
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
    $updated_by = 1; // TODO: Replace with session user_id
    
    mysqli_begin_transaction($conn);
    
    // Update delivery
    $update_query = "UPDATE delivery_items 
                     SET channel = ?, delivered_by = ?, main_link = ?, 
                         delivered_to_name = ?, delivered_to_contact = ?, notes = ?
                     WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'sisssi',
        $channel,
        $delivered_by,
        $main_link,
        $delivered_to_name,
        $delivered_to_contact,
        $notes,
        $delivery_id
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update delivery: ' . mysqli_error($conn));
    }
    
    // Log activity
    $log_query = "INSERT INTO delivery_activity_log 
                  (delivery_id, activity_type, description, performed_by) 
                  VALUES (?, 'updated', 'Delivery details updated', ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($log_stmt, 'ii', $delivery_id, $updated_by);
    mysqli_stmt_execute($log_stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Delivery updated successfully'
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

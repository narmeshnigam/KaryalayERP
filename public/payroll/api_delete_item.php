<?php
/**
 * API: Delete Payroll Item
 * Removes an individual item from draft payroll
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

// Check permissions
if (!authz_user_can($conn, 'employees.delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id']) || !isset($input['payroll_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$item_id = (int)$input['item_id'];
$payroll_id = (int)$input['payroll_id'];

// Verify payroll exists and is in Draft status
$payroll = get_payroll_by_id($conn, $payroll_id);
if (!$payroll) {
    echo json_encode(['success' => false, 'message' => 'Payroll not found']);
    exit;
}

if ($payroll['status'] !== 'Draft') {
    echo json_encode(['success' => false, 'message' => 'Cannot delete items from locked or paid payroll']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Verify item belongs to this payroll
    $check = $conn->prepare("SELECT payable FROM payroll_items WHERE id = ? AND payroll_id = ?");
    $check->bind_param("ii", $item_id, $payroll_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Item not found in payroll');
    }
    
    $item = $result->fetch_assoc();
    
    // Delete the item
    $delete = $conn->prepare("DELETE FROM payroll_items WHERE id = ?");
    $delete->bind_param("i", $item_id);
    
    if (!$delete->execute()) {
        throw new Exception('Failed to delete item');
    }
    
    // Recalculate payroll total
    $total_result = $conn->prepare("SELECT SUM(payable) as total, COUNT(*) as count FROM payroll_items WHERE payroll_id = ?");
    $total_result->bind_param("i", $payroll_id);
    $total_result->execute();
    $total_row = $total_result->get_result()->fetch_assoc();
    
    $update_master = $conn->prepare("UPDATE payroll_master SET total_amount = ?, total_employees = ? WHERE id = ?");
    $update_master->bind_param("dii", $total_row['total'], $total_row['count'], $payroll_id);
    $update_master->execute();
    
    // Log activity
    log_payroll_activity($conn, $payroll_id, 'Update', $_SESSION['user_id'], 
        "Deleted payroll item (₹" . number_format($item['payable'], 2) . ")");
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item deleted successfully',
        'new_total' => $total_row['total']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

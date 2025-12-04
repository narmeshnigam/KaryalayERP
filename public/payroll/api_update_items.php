<?php
/**
 * API: Update Payroll Items
 * Handles bulk updates to transaction numbers and amounts
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

// Check permissions
if (!authz_user_can($conn, 'employees.create')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['payroll_id']) || !isset($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$payroll_id = (int)$input['payroll_id'];
$items = $input['items'];

// Verify payroll exists and is in Draft status
$payroll = get_payroll_by_id($conn, $payroll_id);
if (!$payroll) {
    echo json_encode(['success' => false, 'message' => 'Payroll not found']);
    exit;
}

if ($payroll['status'] !== 'Draft') {
    echo json_encode(['success' => false, 'message' => 'Cannot edit locked or paid payroll']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    $updated_count = 0;
    $errors = [];
    
    foreach ($items as $item_id => $data) {
        $item_id = (int)$item_id;
        
        // Verify item belongs to this payroll
        $check = $conn->prepare("SELECT id FROM payroll_items WHERE id = ? AND payroll_id = ?");
        $check->bind_param("ii", $item_id, $payroll_id);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $errors[] = "Item ID $item_id not found in payroll";
            continue;
        }
        
        // Check for duplicate transaction number
        if (!empty($data['transaction_number'])) {
            $dup_check = $conn->prepare("SELECT id FROM payroll_items WHERE transaction_number = ? AND id != ?");
            $dup_check->bind_param("si", $data['transaction_number'], $item_id);
            $dup_check->execute();
            
            if ($dup_check->get_result()->num_rows > 0) {
                $errors[] = "Transaction number {$data['transaction_number']} already exists";
                continue;
            }
        }
        
        // Update the item
        if (update_payroll_item($conn, $item_id, $data)) {
            $updated_count++;
        } else {
            $errors[] = "Failed to update item ID $item_id";
        }
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
    log_payroll_activity($conn, $payroll_id, 'Update', $_SESSION['user_id'], "Updated $updated_count payroll items");
    
    $conn->commit();
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => true,
            'updated' => $updated_count,
            'message' => "$updated_count items updated with " . count($errors) . " errors",
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'updated' => $updated_count,
            'message' => "$updated_count items updated successfully"
        ]);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();

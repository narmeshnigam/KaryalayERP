<?php
/**
 * Payroll Module - Action Handlers
 * Handle AJAX requests for payroll operations
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$payroll_id = isset($_POST['payroll_id']) ? (int)$_POST['payroll_id'] : 0;

if (!$payroll_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid payroll ID']);
    exit;
}

// Get payroll
$payroll = get_payroll_by_id($conn, $payroll_id);
if (!$payroll) {
    echo json_encode(['success' => false, 'message' => 'Payroll not found']);
    exit;
}

try {
    switch ($action) {
        case 'update_record':
            // Update individual payroll record
            if (!in_array($payroll['status'], ['Draft', 'Reviewed'])) {
                throw new Exception('Cannot edit locked payroll');
            }
            
            $record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
            $bonus = isset($_POST['bonus']) ? (float)$_POST['bonus'] : 0;
            $penalties = isset($_POST['penalties']) ? (float)$_POST['penalties'] : 0;
            $remarks = trim($_POST['remarks'] ?? '');
            
            // Get current record
            $record = get_payroll_record($conn, $record_id);
            if (!$record || $record['payroll_id'] != $payroll_id) {
                throw new Exception('Record not found');
            }
            
            // Recalculate net pay
            $net_pay = calculate_net_pay(
                $record['base_salary'],
                $record['allowances'],
                $record['reimbursements'],
                $record['deductions'],
                $bonus,
                $penalties
            );
            
            // Update record
            $stmt = $conn->prepare("UPDATE payroll_records SET bonus = ?, penalties = ?, net_pay = ?, remarks = ? WHERE id = ?");
            $stmt->bind_param("dddsi", $bonus, $penalties, $net_pay, $remarks, $record_id);
            $stmt->execute();
            
            // Update payroll master total
            $total_result = $conn->query("SELECT SUM(net_pay) as total FROM payroll_records WHERE payroll_id = $payroll_id");
            $total = $total_result->fetch_assoc()['total'];
            
            $stmt2 = $conn->prepare("UPDATE payroll_master SET total_amount = ? WHERE id = ?");
            $stmt2->bind_param("di", $total, $payroll_id);
            $stmt2->execute();
            
            // Log activity
            log_payroll_activity($conn, $payroll_id, $_SESSION['user_id'], 'Update', "Updated record for employee ID $record[employee_id]");
            
            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
            break;
            
        case 'mark_reviewed':
            // Mark payroll as reviewed
            if ($payroll['status'] !== 'Draft') {
                throw new Exception('Only Draft payroll can be marked as reviewed');
            }
            
            $stmt = $conn->prepare("UPDATE payroll_master SET status = 'Reviewed' WHERE id = ?");
            $stmt->bind_param("i", $payroll_id);
            $stmt->execute();
            
            log_payroll_activity($conn, $payroll_id, $_SESSION['user_id'], 'Review', 'Payroll marked as reviewed');
            
            echo json_encode(['success' => true, 'message' => 'Payroll marked as reviewed']);
            break;
            
        case 'lock_payroll':
            // Lock payroll
            if ($payroll['status'] !== 'Reviewed') {
                throw new Exception('Only Reviewed payroll can be locked');
            }
            
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("UPDATE payroll_master SET status = 'Locked', locked_by = ?, locked_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $user_id, $payroll_id);
            $stmt->execute();
            
            log_payroll_activity($conn, $payroll_id, $user_id, 'Lock', 'Payroll locked and finalized');
            
            echo json_encode(['success' => true, 'message' => 'Payroll locked successfully']);
            break;
            
        case 'mark_paid':
            // Mark payroll as paid
            if ($payroll['status'] !== 'Locked') {
                throw new Exception('Only Locked payroll can be marked as paid');
            }
            
            $payment_ref = trim($_POST['payment_ref'] ?? '');
            $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
            $remarks = trim($_POST['remarks'] ?? '');
            
            $conn->begin_transaction();
            
            // Update payroll master
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("UPDATE payroll_master SET status = 'Paid', paid_by = ?, paid_at = NOW(), remarks = ? WHERE id = ?");
            $stmt->bind_param("isi", $user_id, $remarks, $payroll_id);
            $stmt->execute();
            
            // Update all records with payment info
            if ($payment_ref) {
                $stmt2 = $conn->prepare("UPDATE payroll_records SET payment_ref = ?, payment_date = ? WHERE payroll_id = ?");
                $stmt2->bind_param("ssi", $payment_ref, $payment_date, $payroll_id);
                $stmt2->execute();
            }
            
            log_payroll_activity($conn, $payroll_id, $user_id, 'Pay', "Payroll marked as paid. Reference: $payment_ref");
            
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Payroll marked as paid']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

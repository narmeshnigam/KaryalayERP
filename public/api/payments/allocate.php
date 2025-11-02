<?php
/**
 * API Endpoint: Allocate Payment to Invoices
 * Map existing payment to one or more invoices
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../payments/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'payments');
if (empty($permissions['can_edit_all'])) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $payment_id = !empty($input['payment_id']) ? (int)$input['payment_id'] : 0;
    $allocations = !empty($input['allocations']) ? $input['allocations'] : [];

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        exit;
    }

    if (empty($allocations) || !is_array($allocations)) {
        echo json_encode(['success' => false, 'message' => 'Allocations data is required']);
        exit;
    }

    // Allocate payment
    $result = allocate_payment_to_invoices($conn, $payment_id, $allocations, $CURRENT_USER_ID);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

<?php
/**
 * API Endpoint: Delete Payment
 * Remove payment (only if unallocated)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../payments/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'payments');
if (empty($permissions['can_delete_all'])) {
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

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        exit;
    }

    // Delete payment
    $result = delete_payment($conn, $payment_id, $CURRENT_USER_ID);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

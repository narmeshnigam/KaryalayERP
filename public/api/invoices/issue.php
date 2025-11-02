<?php
/**
 * API Endpoint: Issue Invoice
 * Mark invoice as issued and deduct inventory
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../invoices/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'invoices');
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
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;

    if (empty($invoice_id)) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
        exit;
    }

    // Issue invoice
    $result = issue_invoice($conn, $invoice_id, $CURRENT_USER_ID);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

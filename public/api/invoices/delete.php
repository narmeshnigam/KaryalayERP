<?php
/**
 * API Endpoint: Delete Invoice
 * Delete a draft invoice
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../invoices/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'invoices');
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
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;

    if (empty($invoice_id)) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
        exit;
    }

    // Get invoice
    $invoice = get_invoice_by_id($conn, $invoice_id);
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }

    // Only allow deletion of draft invoices
    if ($invoice['status'] !== 'Draft') {
        echo json_encode(['success' => false, 'message' => 'Only draft invoices can be deleted']);
        exit;
    }

    // Delete invoice (CASCADE will delete items and activity log)
    $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
    } else {
        $error = $stmt->error;
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to delete invoice: ' . $error]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

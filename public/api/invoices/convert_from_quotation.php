<?php
/**
 * API Endpoint: Convert Quotation to Invoice
 * Create an invoice from an accepted quotation
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../invoices/helpers.php';

// Check if quotations module exists
$quotations_helpers = __DIR__ . '/../../quotations/helpers.php';
if (!file_exists($quotations_helpers)) {
    echo json_encode(['success' => false, 'message' => 'Quotations module not installed']);
    exit;
}
require_once $quotations_helpers;

// Check permissions
$permissions = authz_get_permission_set($conn, 'invoices');
if (empty($permissions['can_create'])) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $quotation_id = !empty($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : 0;

    if (empty($quotation_id)) {
        echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
        exit;
    }

    // Get quotation
    $quotation = get_quotation_by_id($conn, $quotation_id);
    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Quotation not found']);
        exit;
    }

    // Check if quotation is accepted
    if ($quotation['status'] !== 'Accepted') {
        echo json_encode(['success' => false, 'message' => 'Only accepted quotations can be converted to invoices']);
        exit;
    }

    // Check if already converted
    $check = $conn->prepare("SELECT id FROM invoices WHERE quotation_id = ? LIMIT 1");
    $check->bind_param('i', $quotation_id);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $check->close();
        echo json_encode([
            'success' => false, 
            'message' => 'This quotation has already been converted to an invoice',
            'invoice_id' => $existing['id']
        ]);
        exit;
    }
    $check->close();

    // Get quotation items
    $quotation_items = get_quotation_items($conn, $quotation_id);
    if (empty($quotation_items)) {
        echo json_encode(['success' => false, 'message' => 'Quotation has no items']);
        exit;
    }

    // Create invoice data from quotation
    $invoice_data = [
        'quotation_id' => $quotation_id,
        'client_id' => $quotation['client_id'],
        'project_id' => $quotation['project_id'] ?? null,
        'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'payment_terms' => 'NET 30',
        'currency' => $quotation['currency'],
        'subtotal' => $quotation['subtotal'],
        'tax_amount' => $quotation['tax_amount'],
        'discount_amount' => $quotation['discount_amount'],
        'round_off' => 0,
        'total_amount' => $quotation['total_amount'],
        'notes' => $quotation['notes'],
        'terms' => $quotation['terms'],
        'status' => 'Draft'
    ];

    // Create invoice
    $result = create_invoice($conn, $invoice_data, $CURRENT_USER_ID);

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $invoice_id = $result['invoice_id'];

    // Copy items from quotation to invoice
    foreach ($quotation_items as $q_item) {
        $item_data = [
            'item_id' => $q_item['item_id'],
            'description' => $q_item['description'],
            'quantity' => $q_item['quantity'],
            'unit' => $q_item['unit'] ?? 'pcs',
            'unit_price' => $q_item['unit_price'],
            'discount' => $q_item['discount'],
            'discount_type' => 'Amount',
            'tax_percent' => $q_item['tax_percent'],
            'line_total' => $q_item['line_total']
        ];

        $item_result = add_invoice_item($conn, $invoice_id, $item_data);
        if (!$item_result['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to copy items']);
            exit;
        }
    }

    // Log activity
    log_invoice_activity($conn, $invoice_id, $CURRENT_USER_ID, 'Create', "Invoice created from Quotation #{$quotation['quotation_no']}");

    echo json_encode([
        'success' => true,
        'message' => 'Invoice created from quotation successfully',
        'invoice_id' => $invoice_id,
        'invoice_no' => $result['invoice_no']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

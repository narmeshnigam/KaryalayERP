<?php
/**
 * API Endpoint: Update Invoice
 * Update an existing draft invoice
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

    // Check if invoice exists
    $invoice = get_invoice_by_id($conn, $invoice_id);
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }

    // Check if invoice is draft
    if ($invoice['status'] !== 'Draft') {
        echo json_encode(['success' => false, 'message' => 'Only draft invoices can be edited']);
        exit;
    }

    // Extract form data
    $data = [
        'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
        'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'payment_terms' => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
        'currency' => $_POST['currency'] ?? 'INR',
        'subtotal' => (float)($_POST['subtotal'] ?? 0),
        'tax_amount' => (float)($_POST['tax_amount'] ?? 0),
        'discount_amount' => (float)($_POST['discount_amount'] ?? 0),
        'round_off' => (float)($_POST['round_off'] ?? 0),
        'total_amount' => (float)($_POST['total_amount'] ?? 0),
        'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
        'terms' => !empty($_POST['terms']) ? trim($_POST['terms']) : null
    ];

    // Handle file upload (attachment)
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = __DIR__ . '/../../../uploads/invoices/';
        $allowed_extensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }

        if ($_FILES['attachment']['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
            exit;
        }

        $filename = 'attachment_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
            $data['attachment'] = 'uploads/invoices/' . $filename;
            
            // Delete old attachment if exists
            if (!empty($invoice['attachment']) && file_exists(__DIR__ . '/../../../' . $invoice['attachment'])) {
                @unlink(__DIR__ . '/../../../' . $invoice['attachment']);
            }
        }
    }

    // Extract and validate items
    $items_data = [];
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (empty($item['item_id'])) continue;

            $items_data[] = [
                'item_id' => (int)$item['item_id'],
                'description' => !empty($item['description']) ? trim($item['description']) : null,
                'quantity' => (float)($item['quantity'] ?? 1),
                'unit' => !empty($item['unit']) ? trim($item['unit']) : null,
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'discount' => (float)($item['discount'] ?? 0),
                'discount_type' => $item['discount_type'] ?? 'Amount',
                'tax_percent' => (float)($item['tax_percent'] ?? 0),
                'line_total' => (float)($item['line_total'] ?? 0)
            ];
        }
    }

    if (empty($items_data)) {
        echo json_encode(['success' => false, 'message' => 'At least one item is required']);
        exit;
    }

    // Update invoice
    $result = update_invoice($conn, $invoice_id, $data, $CURRENT_USER_ID);

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    // Delete old items and add new ones
    delete_invoice_items($conn, $invoice_id);
    
    foreach ($items_data as $item_data) {
        $item_result = add_invoice_item($conn, $invoice_id, $item_data);
        if (!$item_result['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to add item']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invoice updated successfully',
        'invoice_id' => $invoice_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

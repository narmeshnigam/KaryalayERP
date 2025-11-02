<?php
/**
 * API Endpoint: Add Invoice
 * Create a new invoice with items
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../invoices/helpers.php';

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
        'terms' => !empty($_POST['terms']) ? trim($_POST['terms']) : null,
        'status' => 'Draft' // Always create as draft initially
    ];

    // Validate required fields
    if (empty($data['client_id'])) {
        echo json_encode(['success' => false, 'message' => 'Client is required']);
        exit;
    }

    if (empty($data['issue_date'])) {
        echo json_encode(['success' => false, 'message' => 'Issue date is required']);
        exit;
    }

    // Handle file upload (attachment)
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = __DIR__ . '/../../../uploads/invoices/';
        $allowed_extensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, PNG, JPG']);
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
        }
    }

    // Extract and validate items
    $items_data = [];
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            // Skip if no item_id
            if (empty($item['item_id'])) {
                continue;
            }

            $item_data = [
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

            // Validate item
            if ($item_data['quantity'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Item quantity must be greater than 0']);
                exit;
            }

            $items_data[] = $item_data;
        }
    }

    if (empty($items_data)) {
        echo json_encode(['success' => false, 'message' => 'At least one item is required']);
        exit;
    }

    // Create invoice
    $result = create_invoice($conn, $data, $CURRENT_USER_ID);

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $invoice_id = $result['invoice_id'];

    // Add items
    foreach ($items_data as $item_data) {
        $item_result = add_invoice_item($conn, $invoice_id, $item_data);
        if (!$item_result['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to add item: ' . $item_result['message']]);
            exit;
        }
    }

    // If action is 'issue', issue the invoice immediately
    if (isset($_POST['action']) && $_POST['action'] === 'issue') {
        $issue_result = issue_invoice($conn, $invoice_id, $CURRENT_USER_ID);
        if (!$issue_result['success']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Invoice created but could not be issued: ' . $issue_result['message'],
                'invoice_id' => $invoice_id
            ]);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoice_id,
        'invoice_no' => $result['invoice_no']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

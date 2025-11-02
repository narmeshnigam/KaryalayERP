<?php
/**
 * API Endpoint: Add Payment
 * Create new payment and optionally allocate to invoices
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../payments/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'payments');
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
    // Handle file upload
    $attachment = null;
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = __DIR__ . '/../../../uploads/payments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG allowed.']);
            exit;
        }

        if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
            exit;
        }

        $new_filename = 'payment_' . time() . '_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
            $attachment = 'uploads/payments/' . $new_filename;
        }
    }

    // Prepare payment data
    $payment_data = [
        'client_id' => $_POST['client_id'] ?? null,
        'project_id' => $_POST['project_id'] ?? null,
        'payment_date' => $_POST['payment_date'] ?? null,
        'payment_mode' => $_POST['payment_mode'] ?? 'Cash',
        'reference_no' => $_POST['reference_no'] ?? null,
        'amount_received' => $_POST['amount_received'] ?? 0,
        'remarks' => $_POST['remarks'] ?? null,
        'attachment' => $attachment
    ];

    // Create payment
    $result = create_payment($conn, $payment_data, $CURRENT_USER_ID);

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $payment_id = $result['payment_id'];

    // Handle invoice allocations if provided
    if (!empty($_POST['allocations'])) {
        $allocations = json_decode($_POST['allocations'], true);
        
        if (is_array($allocations) && !empty($allocations)) {
            $allocation_result = allocate_payment_to_invoices($conn, $payment_id, $allocations, $CURRENT_USER_ID);
            
            if (!$allocation_result['success']) {
                // Payment was created but allocation failed
                echo json_encode([
                    'success' => true,
                    'payment_id' => $payment_id,
                    'payment_no' => $result['payment_no'],
                    'warning' => 'Payment created but allocation failed: ' . $allocation_result['message']
                ]);
                exit;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'payment_no' => $result['payment_no'],
        'message' => 'Payment recorded successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

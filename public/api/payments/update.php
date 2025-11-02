<?php
/**
 * API Endpoint: Update Payment
 * Edit payment details (not allocations)
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
    $payment_id = !empty($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        exit;
    }

    // Prepare update data
    $payment_data = [];
    
    if (isset($_POST['payment_date'])) {
        $payment_data['payment_date'] = $_POST['payment_date'];
    }
    if (isset($_POST['payment_mode'])) {
        $payment_data['payment_mode'] = $_POST['payment_mode'];
    }
    if (isset($_POST['reference_no'])) {
        $payment_data['reference_no'] = $_POST['reference_no'];
    }
    if (isset($_POST['remarks'])) {
        $payment_data['remarks'] = $_POST['remarks'];
    }

    // Handle file upload if provided
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
            $payment_data['attachment'] = 'uploads/payments/' . $new_filename;
        }
    }

    // Update payment
    $result = update_payment($conn, $payment_id, $payment_data, $CURRENT_USER_ID);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

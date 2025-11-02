<?php
/**
 * API Endpoint: Get Pending Invoices for Client
 * Returns unpaid/partially paid invoices for allocation
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../payments/helpers.php';

// Check permissions
$permissions = authz_get_permission_set($conn, 'payments');
if (empty($permissions['can_view_all'])) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $client_id = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

    if (empty($client_id)) {
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        exit;
    }

    // Get pending invoices
    $invoices = get_pending_invoices_for_client($conn, $client_id);

    echo json_encode([
        'success' => true,
        'invoices' => $invoices
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

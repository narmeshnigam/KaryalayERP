<?php
/**
 * Asset API - Change Status
 * POST endpoint to change asset status
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../assets/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = createConnection(true);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $asset_id = (int)($input['asset_id'] ?? 0);
    $new_status = trim($input['new_status'] ?? '');
    $reason = trim($input['reason'] ?? '');
    
    // Validation
    if (!$asset_id) {
        throw new Exception('Asset ID is required');
    }
    
    $valid_statuses = ['Available', 'In Use', 'Under Maintenance', 'Broken', 'Decommissioned'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status. Allowed: ' . implode(', ', $valid_statuses));
    }
    
    // Get current asset status
    $asset = getAssetById($conn, $asset_id);
    if (!$asset) {
        throw new Exception('Asset not found');
    }
    
    if ($asset['status'] === $new_status) {
        throw new Exception('Asset is already in ' . $new_status . ' status');
    }
    
    // Change status
    $result = changeAssetStatus($conn, $asset_id, $new_status, $reason, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Status changed successfully',
            'old_status' => $asset['status'],
            'new_status' => $new_status
        ]);
    } else {
        throw new Exception('Failed to change status');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    closeConnection($conn);
}

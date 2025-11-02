<?php
/**
 * Asset API - Transfer Asset
 * POST endpoint to transfer an asset from one context to another
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
    $new_context_type = trim($input['new_context_type'] ?? '');
    $new_context_id = (int)($input['new_context_id'] ?? 0);
    $purpose = trim($input['purpose'] ?? '');
    $expected_return = $input['expected_return'] ?? null;
    
    // Validation
    if (!$asset_id) {
        throw new Exception('Asset ID is required');
    }
    
    if (!in_array($new_context_type, ['Employee', 'Project', 'Client', 'Lead'])) {
        throw new Exception('Invalid context type');
    }
    
    if (!$new_context_id) {
        throw new Exception('Context ID is required');
    }
    
    // Validate new context exists
    if (!validateContext($conn, $new_context_type, $new_context_id)) {
        throw new Exception('Invalid ' . $new_context_type . ' ID');
    }
    
    // Validate expected_return date format if provided
    if ($expected_return && !strtotime($expected_return)) {
        throw new Exception('Invalid expected return date format');
    }
    
    // Transfer asset
    $result = transferAsset($conn, $asset_id, $new_context_type, $new_context_id, $purpose, $_SESSION['user_id'], $expected_return);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Asset transferred successfully'
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Failed to transfer asset');
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

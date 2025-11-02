<?php
/**
 * Asset API - Assign Asset
 * POST endpoint to assign an asset to Employee/Project/Client/Lead
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
    $context_type = trim($input['context_type'] ?? '');
    $context_id = (int)($input['context_id'] ?? 0);
    $purpose = trim($input['purpose'] ?? '');
    $expected_return = $input['expected_return'] ?? null;
    
    // Validation
    if (!$asset_id) {
        throw new Exception('Asset ID is required');
    }
    
    if (!in_array($context_type, ['Employee', 'Project', 'Client', 'Lead'])) {
        throw new Exception('Invalid context type');
    }
    
    if (!$context_id) {
        throw new Exception('Context ID is required');
    }
    
    // Validate context exists
    if (!validateContext($conn, $context_type, $context_id)) {
        throw new Exception('Invalid ' . $context_type . ' ID');
    }
    
    // Validate expected_return date format if provided
    if ($expected_return && !strtotime($expected_return)) {
        throw new Exception('Invalid expected return date format');
    }
    
    // Assign asset
    $result = assignAsset($conn, $asset_id, $context_type, $context_id, $purpose, $_SESSION['user_id'], $expected_return);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Asset assigned successfully'
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Failed to assign asset');
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

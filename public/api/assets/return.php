<?php
/**
 * Asset API - Return Asset
 * POST endpoint to mark an asset as returned
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
    $return_notes = trim($input['return_notes'] ?? '');
    $new_condition = trim($input['new_condition'] ?? '');
    
    // Validation
    if (!$asset_id) {
        throw new Exception('Asset ID is required');
    }
    
    // Get active allocation
    $active_allocation = getActiveAllocation($conn, $asset_id);
    if (!$active_allocation) {
        throw new Exception('No active allocation found for this asset');
    }
    
    // Validate condition if provided
    if ($new_condition && !in_array($new_condition, ['New', 'Good', 'Fair', 'Poor'])) {
        throw new Exception('Invalid condition value');
    }
    
    // Return asset
    $result = returnAsset($conn, $active_allocation['id'], $_SESSION['user_id']);
    
    if ($result['success']) {
        // Update condition if provided
        if ($new_condition) {
            $asset = getAssetById($conn, $asset_id);
            if ($asset && $asset['condition'] !== $new_condition) {
                $update_query = "UPDATE assets_master SET `condition` = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'si', $new_condition, $asset_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                logAssetActivity($conn, $asset_id, $_SESSION['user_id'], 'Update', 'assets_master', $asset_id, 'Condition changed to ' . $new_condition);
            }
        }
        
        // Log return notes if provided
        if ($return_notes) {
            logAssetActivity($conn, $asset_id, $_SESSION['user_id'], 'Return', 'asset_allocation_log', $active_allocation['id'], 'Return notes: ' . $return_notes);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Asset returned successfully'
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Failed to return asset');
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

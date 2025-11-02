<?php
/**
 * Asset API - Maintenance Management
 * POST endpoint to add or close maintenance jobs
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
    
    $action = trim($input['action'] ?? '');
    
    if ($action === 'add') {
        // Add new maintenance job
        $asset_id = (int)($input['asset_id'] ?? 0);
        $job_date = $input['job_date'] ?? null;
        $description = trim($input['description'] ?? '');
        $technician = trim($input['technician'] ?? '');
        $cost = $input['cost'] ?? null;
        $next_due = $input['next_due'] ?? null;
        $status = trim($input['status'] ?? 'Open');
        
        // Validation
        if (!$asset_id) {
            throw new Exception('Asset ID is required');
        }
        
        if (!$job_date || !strtotime($job_date)) {
            throw new Exception('Valid job date is required');
        }
        
        if (empty($description)) {
            throw new Exception('Description is required');
        }
        
        // Validate dates
        if ($next_due && !strtotime($next_due)) {
            throw new Exception('Invalid next due date format');
        }
        
        // Validate status
        if (!in_array($status, ['Open', 'Completed'])) {
            throw new Exception('Invalid status value');
        }
        
        // Add maintenance job
        $data = [
            'job_date' => $job_date,
            'technician' => $technician,
            'description' => $description,
            'cost' => $cost,
            'next_due' => $next_due,
            'status' => $status
        ];
        
        $result = addMaintenanceJob($conn, $asset_id, $data, $_SESSION['user_id']);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance job added successfully'
            ]);
        } else {
            throw new Exception($result['message'] ?? 'Failed to add maintenance job');
        }
        
    } elseif ($action === 'close') {
        // Close maintenance job
        $maintenance_id = (int)($input['maintenance_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        
        // Validation
        if (!$maintenance_id) {
            throw new Exception('Maintenance ID is required');
        }
        
        // Close maintenance job
        $result = closeMaintenanceJob($conn, $maintenance_id, $notes, $_SESSION['user_id']);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance job closed successfully'
            ]);
        } else {
            throw new Exception('Failed to close maintenance job');
        }
        
    } else {
        throw new Exception('Invalid action. Use "add" or "close"');
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

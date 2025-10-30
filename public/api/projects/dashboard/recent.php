<?php
/**
 * API: Projects Dashboard Recent Projects
 * Returns recent, top, and high-task-load projects
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../../includes/auth_check.php';
require_once __DIR__ . '/../../../projects/helpers.php';

// Check permission (Admin or Manager only)
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Admin or Manager role required.']);
    exit;
}

try {
    $data = [
        'recent' => get_dashboard_recent_projects($conn),
        'top_performers' => get_dashboard_top_projects($conn),
        'high_task_load' => get_dashboard_high_task_load_projects($conn)
    ];
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch projects data', 'message' => $e->getMessage()]);
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }

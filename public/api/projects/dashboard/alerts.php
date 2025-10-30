<?php
/**
 * API: Projects Dashboard Alerts
 * Returns deadline warnings and stagnant project alerts
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
    $alerts = get_dashboard_alerts($conn);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $alerts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch alerts', 'message' => $e->getMessage()]);
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }

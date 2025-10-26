<?php
/**
 * Branding API: Get Settings
 * Returns current branding and organization settings
 */

require_once __DIR__ . '/../../branding/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$settings = branding_get_settings($conn);

if (!$settings) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Branding settings not configured']);
    closeConnection($conn);
    exit;
}

// Return settings
echo json_encode([
    'success' => true,
    'data' => $settings
]);

closeConnection($conn);
?>

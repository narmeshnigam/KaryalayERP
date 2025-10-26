<?php
/**
 * Branding API: Delete Logo
 * Removes a specific logo (light, dark, or square)
 */

require_once __DIR__ . '/../../branding/helpers.php';

// Prevent PHP warnings/notices from breaking JSON output
@ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!branding_user_can_edit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get type from POST or query string
$type = $_POST['type'] ?? $_GET['type'] ?? null;

if (empty($type) || !in_array($type, ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing logo type']);
    closeConnection($conn);
    exit;
}

$success = branding_delete_logo($conn, $type);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' logo deleted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete logo']);
}

closeConnection($conn);
?>

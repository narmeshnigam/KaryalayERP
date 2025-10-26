<?php
/**
 * Branding API: Upload Logo
 * Handles logo file upload for light, dark, or square variants
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Validate logo type
if (empty($_POST['type']) || !in_array($_POST['type'], ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing logo type']);
    closeConnection($conn);
    exit;
}

$type = $_POST['type'];

// Check if file was uploaded
if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error occurred']);
    closeConnection($conn);
    exit;
}

// Upload logo
$result = branding_upload_logo($conn, $_FILES['logo'], $type);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' logo uploaded successfully',
        'path' => $result['path'],
        'url' => '../../' . $result['path']
    ]);
} else {
    http_response_code(400);
    echo json_encode($result);
}

closeConnection($conn);
?>

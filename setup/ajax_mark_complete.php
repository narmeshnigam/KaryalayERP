<?php
/**
 * AJAX Mark Setup Complete Handler
 * Marks the module installer/setup as complete
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/setup_functions.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate user authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Mark setup as complete
try {
    $conn = createConnection(true);
    
    // Ensure system_settings table exists
    ensure_system_settings_table($conn);
    
    $success = markModuleInstallerComplete($conn);
    
    if ($conn) {
        closeConnection($conn);
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Setup marked as complete']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark setup as complete']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

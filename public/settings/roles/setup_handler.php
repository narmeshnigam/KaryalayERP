<?php
/**
 * Setup Handler for Roles & Permissions
 * Executes the setup script and returns JSON response
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Path to setup script
$setup_script = __DIR__ . '/../../../scripts/setup_roles_permissions_tables.php';

if (!file_exists($setup_script)) {
    echo json_encode([
        'success' => false,
        'message' => 'Setup script not found',
        'details' => 'The setup script is missing from: ' . $setup_script
    ]);
    exit;
}

// Execute the setup script and capture output
ob_start();
try {
    include $setup_script;
    $output = ob_get_clean();
    
    // Check if setup was successful by looking for success indicators in output
    $success = (strpos($output, '🎉') !== false || strpos($output, 'setup complete') !== false);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Roles & Permissions module has been set up successfully! All tables created and default data inserted.',
            'details' => $output
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Setup completed with warnings or errors. Please check the details.',
            'details' => $output
        ]);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during setup',
        'details' => $e->getMessage()
    ]);
}

<?php
/**
 * AJAX Single Module Installation Handler
 * Installs a single module by executing its setup script
 */

// Prevent any output before JSON response - start buffering immediately
ob_start();

// Define constant to prevent HTML output from setup scripts
define('AJAX_MODULE_INSTALL', true);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/setup_functions.php';

// Set JSON response headers
header('Content-Type: application/json');

/**
 * Send JSON response and exit
 */
function send_response(array $data, int $status_code = 200): void {
    // Clear any buffered output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/**
 * Validate CSRF token
 */
function validate_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    
    if (empty($session_token)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return false;
    }
    
    return hash_equals($session_token, $token);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Validate user authentication
if (!isset($_SESSION['user_id'])) {
    send_response(['success' => false, 'message' => 'Authentication required'], 401);
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// Validate CSRF
if (!validate_csrf()) {
    send_response(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

// Validate module parameter
if (!isset($data['module']) || !is_string($data['module'])) {
    send_response(['success' => false, 'message' => 'Module name required'], 400);
}

$module_name = trim($data['module']);

// Sanitize module name
if (!preg_match('/^[a-z0-9_-]+$/i', $module_name)) {
    send_response(['success' => false, 'message' => 'Invalid module name'], 400);
}

// Module configuration - maps module names to their setup scripts and functions
$module_config = [
    'employees' => [
        'script' => 'scripts/setup_employees_table.php',
        'function' => 'setup_employees_module',
        'accepts_conn' => true
    ],
    'clients' => [
        'script' => 'scripts/setup_clients_tables.php',
        'function' => 'setup_clients_module',
        'accepts_conn' => true
    ],
    'contacts' => [
        'script' => 'scripts/setup_contacts_tables.php',
        'function' => 'setup_contacts_module',
        'accepts_conn' => true
    ],
    'attendance' => [
        'script' => 'scripts/setup_attendance_table.php',
        'function' => 'setupAttendanceModule',
        'accepts_conn' => false
    ],
    'salary' => [
        'script' => 'scripts/setup_salary_records_table.php',
        'function' => 'salary_setup_create',
        'accepts_conn' => false
    ],
    'payroll' => [
        'script' => 'scripts/setup_payroll_tables.php',
        'function' => 'setup_payroll_module',
        'accepts_conn' => true
    ],
    'reimbursements' => [
        'script' => 'scripts/setup_reimbursements_table.php',
        'function' => 'setupReimbursementModule',
        'accepts_conn' => false
    ],
    'documents' => [
        'script' => 'scripts/setup_documents_table.php',
        'function' => 'setup_document_vault',
        'accepts_conn' => false
    ],
    'visitors' => [
        'script' => 'scripts/setup_visitor_logs_table.php',
        'function' => 'setupVisitorLogModule',
        'accepts_conn' => false
    ],
    'crm' => [
        'script' => 'scripts/setup_crm_tables.php',
        'function' => 'crm_setup_create',
        'accepts_conn' => false
    ],
    'branding' => [
        'script' => 'scripts/setup_branding_table.php',
        'function' => 'branding_setup_create',
        'accepts_conn' => false
    ],
    'projects' => [
        'script' => 'scripts/setup_projects_tables.php',
        'function' => 'setup_projects_module',
        'accepts_conn' => true
    ],
    'workorders' => [
        'script' => 'scripts/setup_workorders_tables.php',
        'function' => 'setup_workorders_module',
        'accepts_conn' => true
    ],
    'invoices' => [
        'script' => 'scripts/setup_invoices_tables.php',
        'function' => 'invoices_setup_create',
        'accepts_conn' => false
    ],
    'quotations' => [
        'script' => 'scripts/setup_quotations_tables.php',
        'function' => 'quotations_setup_create',
        'accepts_conn' => false
    ],
    'payments' => [
        'script' => 'scripts/setup_payments_tables.php',
        'function' => 'payments_setup_create',
        'accepts_conn' => false
    ],
    'catalog' => [
        'script' => 'scripts/setup_catalog_tables.php',
        'function' => 'setup_catalog_tables',
        'accepts_conn' => true
    ],
    'expenses' => [
        'script' => 'scripts/setup_office_expenses_table.php',
        'function' => 'setupExpenseTracker',
        'accepts_conn' => false
    ],
    'deliverables' => [
        'script' => 'scripts/setup_deliverables_tables.php',
        'function' => 'setup_deliverables_module',
        'accepts_conn' => true
    ],
    'delivery' => [
        'script' => 'scripts/setup_delivery_tables.php',
        'function' => 'setup_delivery_module',
        'accepts_conn' => true
    ],
    'notebook' => [
        'script' => 'scripts/setup_notebook_tables.php',
        'function' => 'setup_notebook_module',
        'accepts_conn' => true
    ],
    'assets' => [
        'script' => 'scripts/setup_assets_tables.php',
        'function' => 'setup_assets_module',
        'accepts_conn' => true
    ],
    'data-transfer' => [
        'script' => 'scripts/setup_data_transfer_tables.php',
        'function' => 'setup_data_transfer_module',
        'accepts_conn' => true
    ]
];

// Check if module exists in config
if (!isset($module_config[$module_name])) {
    send_response(['success' => false, 'message' => 'Unknown module: ' . $module_name], 400);
}

$config = $module_config[$module_name];
$script_path = __DIR__ . '/../' . $config['script'];

// Check if script exists
if (!file_exists($script_path)) {
    send_response(['success' => false, 'message' => 'Setup script not found: ' . $config['script']], 404);
}

// Get database connection
$conn = null;
try {
    $conn = createConnection(true);
    if (!$conn) {
        send_response(['success' => false, 'message' => 'Database connection failed'], 500);
    }
} catch (Exception $e) {
    send_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

// Include the setup script
try {
    // Suppress any output from the script with multiple buffer levels
    ob_start();
    ob_start();
    require_once $script_path;
    ob_end_clean();
    ob_end_clean();
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($conn) closeConnection($conn);
    send_response(['success' => false, 'message' => 'Error loading script: ' . $e->getMessage()], 500);
}

// Check if function exists
$function_name = $config['function'];
if (!function_exists($function_name)) {
    if ($conn) closeConnection($conn);
    send_response(['success' => false, 'message' => 'Setup function not found: ' . $function_name], 500);
}

// Execute the setup function
try {
    ob_start();
    ob_start();
    
    if ($config['accepts_conn']) {
        $result = $function_name($conn);
    } else {
        $result = $function_name();
    }
    
    // Capture any output
    $output = ob_get_clean();
    ob_end_clean();
    
    // Normalize result
    if (!is_array($result)) {
        $result = ['success' => false, 'message' => 'Invalid result from setup function'];
    }
    
    // Add any captured output to the result for debugging
    if (!empty($output) && !$result['success']) {
        $result['debug_output'] = substr($output, 0, 500);
    }
    
    if ($conn) closeConnection($conn);
    
    send_response($result);
    
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($conn) closeConnection($conn);
    send_response(['success' => false, 'message' => 'Exception: ' . $e->getMessage()], 500);
}

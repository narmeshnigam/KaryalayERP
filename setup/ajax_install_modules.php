<?php
/**
 * AJAX Installation Handler
 * Handles asynchronous module installation requests
 */

// Start session and output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/installation_engine.php';

// Set JSON response headers
header('Content-Type: application/json');

/**
 * Send JSON response and exit
 */
function send_json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/**
 * Validate CSRF token
 */
function validate_csrf_token(): bool {
    // Get token from request
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    // Get token from session
    $session_token = $_SESSION['csrf_token'] ?? '';
    
    // If no session token exists, generate one for future requests
    if (empty($session_token)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return false;
    }
    
    // Compare tokens using timing-safe comparison
    return hash_equals($session_token, $token);
}

/**
 * Generate CSRF token if not exists
 */
function ensure_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request method. Only POST is allowed.'
    ], 405);
}

// Validate user authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    send_json_response([
        'success' => false,
        'error' => 'Authentication required. Please log in.'
    ], 401);
}

// Get database connection
try {
    $conn = createConnection(true);
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ], 500);
}

// Get authorization context
$authz_context = authz_context($conn);
$user_id = $authz_context['user_id'];

// Validate user authorization (Super Admin or Admin only)
$is_authorized = false;

if ($authz_context['is_super_admin']) {
    $is_authorized = true;
} else {
    // Check if user has Admin role
    foreach ($authz_context['roles'] as $role) {
        $role_name = strtolower($role['name'] ?? '');
        if ($role_name === 'admin' || $role_name === 'super admin') {
            $is_authorized = true;
            break;
        }
    }
}

if (!$is_authorized) {
    send_json_response([
        'success' => false,
        'error' => 'Insufficient permissions. Super Admin or Admin role required.'
    ], 403);
}

// Validate CSRF token
if (!validate_csrf_token()) {
    send_json_response([
        'success' => false,
        'error' => 'Invalid CSRF token. Please refresh the page and try again.'
    ], 403);
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Fallback to POST data if JSON parsing fails
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// Validate modules parameter - allow empty array (mandatory modules will be added)
if (!isset($data['modules'])) {
    $data['modules'] = [];
}

if (!is_array($data['modules'])) {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request. "modules" parameter must be an array.'
    ], 400);
}

$modules = $data['modules'];

// If no modules selected, still install mandatory modules
if (empty($modules)) {
    $modules = [];
}

// Validate module names (basic sanitization)
$valid_modules = [];
foreach ($modules as $module) {
    if (!is_string($module)) {
        continue;
    }
    
    // Remove any potentially dangerous characters
    $module = trim($module);
    if (preg_match('/^[a-z0-9_-]+$/i', $module)) {
        $valid_modules[] = $module;
    }
}

// Always include mandatory modules (employees, catalog)
require_once __DIR__ . '/../includes/dependency_resolver.php';
$mandatory_modules = get_mandatory_module_list();

foreach ($mandatory_modules as $mandatory) {
    if (!in_array($mandatory, $valid_modules)) {
        array_unshift($valid_modules, $mandatory); // Add at beginning
    }
}

// At this point we should always have at least the mandatory modules
if (empty($valid_modules)) {
    send_json_response([
        'success' => false,
        'error' => 'No valid modules provided.'
    ], 400);
}

// Store installation progress in session
$_SESSION['installation_progress'] = [
    'in_progress' => true,
    'modules' => $valid_modules,
    'current_module' => null,
    'completed' => [],
    'total' => count($valid_modules),
    'started_at' => time()
];

// Execute installation
try {
    $result = install_modules($conn, $valid_modules, $user_id);
    
    // Update session progress
    $_SESSION['installation_progress']['in_progress'] = false;
    $_SESSION['installation_progress']['completed_at'] = time();
    
    // Mark module installer as complete (whether all succeeded or some failed)
    // The user has gone through the installation process
    markModuleInstallerComplete($conn);
    
    // Send successful response
    send_json_response($result, 200);
    
} catch (Exception $e) {
    // Update session progress
    $_SESSION['installation_progress']['in_progress'] = false;
    $_SESSION['installation_progress']['error'] = $e->getMessage();
    
    send_json_response([
        'success' => false,
        'error' => 'Installation failed: ' . $e->getMessage(),
        'results' => []
    ], 500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

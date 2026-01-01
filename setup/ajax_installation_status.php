<?php
/**
 * AJAX Installation Status Handler
 * Provides real-time progress updates for module installation
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

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

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request method. Only GET is allowed.'
    ], 405);
}

// Validate user authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    send_json_response([
        'success' => false,
        'error' => 'Authentication required. Please log in.'
    ], 401);
}

// Get installation progress from session
$progress = $_SESSION['installation_progress'] ?? null;

// If no installation progress exists, return empty state
if ($progress === null) {
    send_json_response([
        'success' => true,
        'in_progress' => false,
        'current_module' => null,
        'completed' => [],
        'total' => 0,
        'percentage' => 0,
        'message' => 'No installation in progress'
    ], 200);
}

// Calculate completion percentage
$total = $progress['total'] ?? 0;
$completed_count = count($progress['completed'] ?? []);
$percentage = $total > 0 ? round(($completed_count / $total) * 100) : 0;

// Prepare response
$response = [
    'success' => true,
    'in_progress' => $progress['in_progress'] ?? false,
    'current_module' => $progress['current_module'] ?? null,
    'completed' => $progress['completed'] ?? [],
    'total' => $total,
    'completed_count' => $completed_count,
    'percentage' => $percentage
];

// Add timing information if available
if (isset($progress['started_at'])) {
    $response['started_at'] = $progress['started_at'];
    $response['elapsed_seconds'] = time() - $progress['started_at'];
}

if (isset($progress['completed_at'])) {
    $response['completed_at'] = $progress['completed_at'];
    $response['total_duration'] = $progress['completed_at'] - ($progress['started_at'] ?? time());
}

// Add error information if available
if (isset($progress['error'])) {
    $response['error'] = $progress['error'];
}

// Send response
send_json_response($response, 200);

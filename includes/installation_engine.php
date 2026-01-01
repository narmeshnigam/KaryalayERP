<?php
/**
 * Installation Engine
 * Orchestrates module installation by executing setup scripts in dependency order
 */

require_once __DIR__ . '/dependency_resolver.php';
require_once __DIR__ . '/module_discovery.php';

/**
 * Install multiple modules in dependency order
 * 
 * @param mysqli $conn Database connection
 * @param array $module_names Array of module names to install
 * @param int $user_id User ID performing the installation
 * @return array Installation results with 'success' (bool), 'results' (array), and 'summary' (array)
 */
function install_modules(mysqli $conn, array $module_names, int $user_id): array {
    $start_time = microtime(true);
    
    // Resolve installation order
    try {
        $ordered_modules = resolve_installation_order($module_names);
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Dependency resolution failed: ' . $e->getMessage(),
            'results' => [],
            'summary' => [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0
            ]
        ];
    }
    
    $results = [];
    $successful = 0;
    $failed = 0;
    $skipped = 0;
    
    // Install each module in order
    foreach ($ordered_modules as $module_name) {
        $module_start = microtime(true);
        
        // Update session progress - current module
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['installation_progress'])) {
            $_SESSION['installation_progress']['current_module'] = $module_name;
        }
        
        // Check if already installed
        if (check_module_installed($conn, $module_name)) {
            $result = [
                'module' => $module_name,
                'success' => true,
                'message' => 'Module already installed',
                'skipped' => true,
                'duration' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $skipped++;
        } else {
            // Execute setup
            $result = execute_module_setup($conn, $module_name, $user_id);
            $result['duration'] = microtime(true) - $module_start;
            $result['timestamp'] = date('Y-m-d H:i:s');
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
        }
        
        $results[] = $result;
        
        // Update session progress - completed module
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['installation_progress'])) {
            $_SESSION['installation_progress']['completed'][] = $result;
        }
        
        // Log installation attempt
        log_installation($module_name, $result['success'], $result['message'], $user_id);
    }
    
    $total_duration = microtime(true) - $start_time;
    
    return [
        'success' => ($failed === 0),
        'results' => $results,
        'summary' => [
            'total' => count($ordered_modules),
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'duration' => $total_duration
        ]
    ];
}

/**
 * Execute setup for a single module
 * 
 * @param mysqli|null $conn Database connection (null for testing)
 * @param string $module_name Module identifier
 * @param int $user_id User ID performing the installation
 * @return array Result with 'module', 'success' (bool), and 'message' (string)
 */
function execute_module_setup(?mysqli $conn, string $module_name, int $user_id): array {
    $metadata = get_module_metadata($module_name);
    
    if (!$metadata) {
        return [
            'module' => $module_name,
            'success' => false,
            'message' => 'Module not found in registry'
        ];
    }
    
    $setup_script = __DIR__ . '/../' . $metadata['setup_script'];
    
    if (!file_exists($setup_script)) {
        return [
            'module' => $module_name,
            'success' => false,
            'message' => 'Setup script not found: ' . $metadata['setup_script']
        ];
    }
    
    // Define constant to prevent direct output from setup scripts
    if (!defined('AJAX_MODULE_INSTALL')) {
        define('AJAX_MODULE_INSTALL', true);
    }
    
    // Include the setup script
    require_once $setup_script;
    
    // Determine the function name (convention: setup_{module_name}_module)
    $function_name = 'setup_' . str_replace('-', '_', $module_name) . '_module';
    
    // Check if function exists
    if (!function_exists($function_name)) {
        return [
            'module' => $module_name,
            'success' => false,
            'message' => 'Setup function not found: ' . $function_name . '(). Script may not be refactored yet.'
        ];
    }
    
    // Execute the setup function with output buffering
    try {
        ob_start();
        $result = $function_name($conn);
        ob_end_clean();
        
        // Normalize result format
        if (!is_array($result)) {
            return [
                'module' => $module_name,
                'success' => false,
                'message' => 'Setup function returned invalid format'
            ];
        }
        
        return [
            'module' => $module_name,
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'No message provided',
            'tables_created' => $result['tables_created'] ?? [],
            'already_exists' => $result['already_exists'] ?? false
        ];
        
    } catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        return [
            'module' => $module_name,
            'success' => false,
            'message' => 'Exception during setup: ' . $e->getMessage()
        ];
    } catch (Error $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        return [
            'module' => $module_name,
            'success' => false,
            'message' => 'Error during setup: ' . $e->getMessage()
        ];
    }
}

/**
 * Log installation attempt to a log file
 * 
 * @param string $module_name Module identifier
 * @param bool $success Whether installation succeeded
 * @param string $message Installation message
 * @param int $user_id User ID performing the installation
 * @return void
 */
function log_installation(string $module_name, bool $success, string $message, int $user_id): void {
    $log_dir = __DIR__ . '/../uploads/logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/module_installation.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $log_entry = sprintf(
        "[%s] [%s] Module: %s | User: %d | Message: %s\n",
        $timestamp,
        $status,
        $module_name,
        $user_id,
        $message
    );
    
    // Append to log file
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Get installation progress for tracking
 * This is a simple implementation that could be enhanced with session storage
 * 
 * @return array Progress information
 */
function get_installation_progress(): array {
    // For now, return empty progress
    // In a real implementation, this would read from session or cache
    return [
        'in_progress' => false,
        'current_module' => null,
        'completed' => [],
        'total' => 0,
        'percentage' => 0
    ];
}

/**
 * Track installation progress
 * This is a simple implementation that could be enhanced with session storage
 * 
 * @param string $module_name Current module being installed
 * @param int $completed Number of completed modules
 * @param int $total Total number of modules
 * @return void
 */
function track_installation_progress(string $module_name, int $completed, int $total): void {
    // For now, this is a no-op
    // In a real implementation, this would write to session or cache
    // Could use $_SESSION['installation_progress'] or a cache system
}
?>

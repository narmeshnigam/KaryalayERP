<?php
/**
 * Configuration File
 * 
 * This file loads configuration from environment variables with auto-detection.
 * Database credentials and other settings are stored in .env file.
 * If APP_URL is not set, it will be auto-detected from server environment.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Load server detection utility
require_once __DIR__ . '/server_detector.php';

// Database configuration constants (loaded from .env)
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', 'karyalay_db'));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Application configuration (loaded from .env with auto-detection fallback)
define('APP_NAME', EnvLoader::get('APP_NAME', 'Karyalay ERP'));

// Gather auto-detected server information once
$detected_info = ServerDetector::detect();

// APP_URL with intelligent auto-detection
// Priority: 1) explicit .env value (unless placeholder), 2) auto-detection from server
$app_url_from_env = EnvLoader::get('APP_URL', null);
$app_url_from_env = is_string($app_url_from_env) ? trim($app_url_from_env) : '';

$should_use_env_url = $app_url_from_env !== '';

if ($should_use_env_url) {
    // Treat localhost placeholders as empty when running on a real host
    $env_host = parse_url($app_url_from_env, PHP_URL_HOST);
    $env_host = $env_host ? strtolower($env_host) : '';
    if ($env_host === '' && stripos($app_url_from_env, 'localhost') !== false) {
        $env_host = 'localhost';
    }
    $is_env_placeholder = $env_host === 'localhost' || $env_host === '127.0.0.1' || $env_host === '::1';

    if ($is_env_placeholder && !empty($detected_info['server_name']) && !ServerDetector::isLocalhost()) {
        $should_use_env_url = false;
    }
}

if ($should_use_env_url) {
    define('APP_URL', rtrim($app_url_from_env, '/'));
} else {
    define('APP_URL', rtrim($detected_info['base_url'], '/'));
}

// Session configuration (loaded from .env)
define('SESSION_NAME', EnvLoader::get('SESSION_NAME', 'karyalay_session'));
define('SESSION_LIFETIME', EnvLoader::get('SESSION_LIFETIME', 3600));

// Timezone (loaded from .env)
$timezone = EnvLoader::get('TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($timezone);

// Environment mode (auto-detected if not set in .env)
$environment = EnvLoader::get('ENVIRONMENT', null);
$environment = is_string($environment) ? trim($environment) : $environment;
if (empty($environment)) {
    // Auto-detect environment type
    $environment = ServerDetector::getEnvironment();
}
define('APP_ENVIRONMENT', $environment);

$debug_mode = EnvLoader::get('DEBUG_MODE', null);
if ($debug_mode !== null) {
    $debug_mode = trim((string) $debug_mode);
    if ($debug_mode === '') {
        $debug_mode = null;
    }
}

if ($debug_mode === null) {
    // Auto-detect debug mode based on environment
    $debug_mode = ($environment === 'development');
} else {
    $debug_mode = ($debug_mode === 'true' || $debug_mode === '1');
}
define('APP_DEBUG', $debug_mode);

// Error reporting (controlled by environment)
if ($environment === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', $debug_mode ? 1 : 0);
}

// Store detection info for diagnostics (optional)
define('SERVER_AUTO_DETECTED', !$should_use_env_url);
?>

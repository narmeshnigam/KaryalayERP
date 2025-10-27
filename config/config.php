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

// APP_URL with intelligent auto-detection
// Priority: 1) .env file, 2) Auto-detection from server
$app_url_from_env = EnvLoader::get('APP_URL', null);
if (!empty($app_url_from_env)) {
    // Use .env value if explicitly set
    define('APP_URL', $app_url_from_env);
} else {
    // Auto-detect from server environment
    define('APP_URL', ServerDetector::getAppUrl());
}

// Session configuration (loaded from .env)
define('SESSION_NAME', EnvLoader::get('SESSION_NAME', 'karyalay_session'));
define('SESSION_LIFETIME', EnvLoader::get('SESSION_LIFETIME', 3600));

// Timezone (loaded from .env)
$timezone = EnvLoader::get('TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($timezone);

// Environment mode (auto-detected if not set in .env)
$environment = EnvLoader::get('ENVIRONMENT', null);
if (empty($environment)) {
    // Auto-detect environment type
    $environment = ServerDetector::getEnvironment();
}
define('APP_ENVIRONMENT', $environment);

$debug_mode = EnvLoader::get('DEBUG_MODE', null);
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
define('SERVER_AUTO_DETECTED', empty($app_url_from_env));
?>

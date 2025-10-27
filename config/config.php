<?php
/**
 * Configuration File
 * 
 * This file loads configuration from environment variables.
 * Database credentials and other settings are stored in .env file.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Database configuration constants (loaded from .env)
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', 'karyalay_db'));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Application configuration (loaded from .env)
define('APP_NAME', EnvLoader::get('APP_NAME', 'Karyalay ERP'));
define('APP_URL', EnvLoader::get('APP_URL', 'http://localhost/KaryalayERP'));

// Session configuration (loaded from .env)
define('SESSION_NAME', EnvLoader::get('SESSION_NAME', 'karyalay_session'));
define('SESSION_LIFETIME', EnvLoader::get('SESSION_LIFETIME', 3600));

// Timezone (loaded from .env)
$timezone = EnvLoader::get('TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($timezone);

// Environment mode (loaded from .env)
$environment = EnvLoader::get('ENVIRONMENT', 'development');
$debug_mode = EnvLoader::get('DEBUG_MODE', 'true') === 'true';

// Error reporting (controlled by environment)
if ($environment === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', $debug_mode ? 1 : 0);
}
?>

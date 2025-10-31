<?php
/**
 * Database Configuration File
 * 
 * This file contains all database connection constants.
 * Modify these values according to your local setup.
 */

// Database configuration constants
define('DB_HOST', 'localhost');      // Database host (usually localhost)
define('DB_USER', 'root');           // Database username
define('DB_PASS', '');               // Database password (empty for XAMPP default)
define('DB_NAME', 'karyalay');    // Database name
define('DB_CHARSET', 'utf8mb4');     // Character set

// Application configuration
define('APP_NAME', 'Karyalay ERP');  // Application name
// Dynamically set APP_URL based on environment
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1')) {
	// Online/production: use actual domain and path
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
	$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
	define('APP_URL', $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? $basePath : ''));
} else {
	// Localhost/dev: use static local URL
	define('APP_URL', 'http://localhost/KaryalayERP');
}

// Session configuration
define('SESSION_NAME', 'karyalay_session'); // Session name
define('SESSION_LIFETIME', 3600);    // Session lifetime in seconds (1 hour)

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

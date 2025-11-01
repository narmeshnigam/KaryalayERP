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
$host = $_SERVER['HTTP_HOST'] ?? null;
if ($host && $host !== 'localhost' && $host !== '127.0.0.1') {
	$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
	$protocol = $isSecure ? 'https://' : 'http://';

	$appRoot = realpath(__DIR__ . '/..');
	$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

	$basePath = '';
	if ($appRoot && $docRoot && strpos($appRoot, $docRoot) === 0) {
		$relative = substr($appRoot, strlen($docRoot));
		$relative = trim(str_replace('\\', '/', $relative), '/');
		$basePath = $relative !== '' ? '/' . $relative : '';
	}

	define('APP_URL', rtrim($protocol . $host . $basePath, '/'));
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

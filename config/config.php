<?php
/**
 * Database Configuration File
 * 
 * This file contains all database connection constants.
 * Modify these values according to your local setup.
 */

// Database configuration constants
define('DB_HOST', 'localhost');      // Database host (use 127.0.0.1 for TCP connection instead of socket)
define('DB_USER', 'root');           // Database username
define('DB_PASS', '');               // Database password (empty for XAMPP default)
define('DB_NAME', 'karyalay_db');    // Database name
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
	// Localhost/dev: use REQUEST_URI to get the actual URL path used by browser
	$basePath = '';
	if (isset($_SERVER['REQUEST_URI'])) {
		// Extract the base path from the current request
		$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		// Find the app root by looking for known paths
		if (preg_match('#^(/[^/]+)(?:/|$)#', $requestUri, $matches)) {
			$basePath = $matches[1];
		}
	} else {
		// Fallback for CLI: use directory name
		$basePath = '/' . basename(dirname(__DIR__));
	}

	define('APP_URL', 'http://localhost' . $basePath);
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

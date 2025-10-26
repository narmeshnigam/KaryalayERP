<?php
/**
 * Index Page - Main Entry Point
 * 
 * This file:
 * 1. Checks if database and tables exist
 * 2. Automatically runs setup if needed
 * 3. Redirects logged-in users to dashboard
 * 4. Redirects logged-out users to login page
 */

// Start session
session_start();

// Include configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/setup_helper.php';

// Check if setup is complete
if (!isSetupComplete()) {
    // Redirect to setup wizard
    header('Location: setup/index.php');
    exit;
}

// If user is logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: public/index.php');
    exit;
}

// Otherwise, redirect to login page
header('Location: public/login.php');
exit;
?>

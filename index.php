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
require_once __DIR__ . '/config/db_connect.php';

/**
 * Check if system needs initial setup
 * Returns true if setup is needed, false otherwise
 */
function needsSetup() {
    // Check if database exists
    if (!databaseExists()) {
        return true;
    }
    
    // Check if users table exists
    if (!usersTableExists()) {
        return true;
    }
    
    return false;
}

// Check if setup is needed
if (needsSetup()) {
    // Redirect to setup script
    header('Location: scripts/setup_db.php');
    exit;
}

// If user is logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard.php');
    exit;
}

// Otherwise, redirect to login page
header('Location: public/login.php');
exit;
?>

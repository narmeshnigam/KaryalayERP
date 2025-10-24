<?php
/**
 * Logout Page
 * 
 * Destroys user session and redirects to login page.
 * Implements secure logout by clearing all session data.
 */

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with logout message
header('Location: login.php?logout=success');
exit;
?>

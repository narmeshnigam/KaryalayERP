<?php
/**
 * Logout Page
 * 
 * Destroys user session and redirects to login page.
 * Implements secure logout by clearing all session data.
 */

// Start session
session_start();

// Include DB and helper to record logout
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/users/helpers.php';

// Record logout time for the user if logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $conn = createConnection(true);
    if ($conn) {
        update_user_logout($conn, (int)$_SESSION['user_id']);
        closeConnection($conn);
    }
}

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

<?php
/**
 * Users Management - Delete User
 * Permanently remove user from the system
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'users', 'delete_all');

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly.");
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Get user ID from POST
$delete_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($delete_user_id <= 0) {
    flash_add('error', 'Invalid user ID', 'users');
    header('Location: index.php');
    exit;
}

// Prevent deleting yourself
if ($delete_user_id === $CURRENT_USER_ID) {
    flash_add('error', 'You cannot delete your own account', 'users');
    header('Location: index.php');
    exit;
}

// Fetch user details for logging
$user = get_user_by_id($conn, $delete_user_id);

if (!$user) {
    flash_add('error', 'User not found', 'users');
    header('Location: index.php');
    exit;
}

// Attempt to delete user
if (delete_user($conn, $delete_user_id)) {
    flash_add('success', "User '{$user['username']}' deleted successfully", 'users');
} else {
    flash_add('error', 'Failed to delete user. The user may have related records', 'users');
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
header('Location: index.php');
exit;
?>

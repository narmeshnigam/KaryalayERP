<?php
/**
 * Common authentication and authorization bootstrap for protected pages.
 *
 * Usage:
 *   require_once __DIR__ . '/auth_check.php';
 * After inclusion, the following variables are available:
 *   $AUTHZ_CONTEXT, $CURRENT_USER_ID, $CURRENT_USER_ROLES,
 *   $CURRENT_USER_PERMISSIONS, $IS_SUPER_ADMIN.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/authz.php';

// Ensure the user is authenticated.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['attempted_page'] = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

// Reuse existing connection when available, otherwise open a scoped connection.
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = createConnection(true);
    $GLOBALS['AUTHZ_CONN_MANAGED'] = true;
} else {
    $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
}

$AUTHZ_CONTEXT = authz_context($conn);
$CURRENT_USER_ID = $AUTHZ_CONTEXT['user_id'];
$CURRENT_USER_ROLES = $AUTHZ_CONTEXT['roles'];
$CURRENT_USER_PERMISSIONS = $AUTHZ_CONTEXT['permissions'];
$IS_SUPER_ADMIN = $AUTHZ_CONTEXT['is_super_admin'];

require_once __DIR__ . '/auto_guard.php';
?>

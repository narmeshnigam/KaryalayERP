<?php
/**
 * Delete Role
 * Handle role deletion with validation and confirmation
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$conn = createConnection(true);

// Check if tables exist
if (!roles_tables_exist($conn)) {
    header('Location: onboarding.php');
    exit;
}

// Check if user has permission to delete roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'delete');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: index.php');
    exit;
}

$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

if ($role_id <= 0) {
    $_SESSION['error_message'] = 'Invalid role ID.';
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Fetch role details
$stmt = mysqli_prepare($conn, "SELECT id, name, is_system_role FROM roles WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $role_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$role = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Check if role exists
if (!$role) {
    $_SESSION['error_message'] = 'Role not found.';
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Check if it's a system role (cannot delete)
if ($role['is_system_role']) {
    $_SESSION['error_message'] = 'System roles cannot be deleted.';
    closeConnection($conn);
    header('Location: view.php?id=' . $role_id);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get count of users with this role (for audit)
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as user_count FROM user_roles WHERE role_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count_result = mysqli_fetch_assoc($result);
    $user_count = $count_result['user_count'];
    mysqli_stmt_close($stmt);
    
    // Delete role_permissions entries
    $stmt = mysqli_prepare($conn, "DELETE FROM role_permissions WHERE role_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error deleting permissions: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    // Delete user_roles entries
    $stmt = mysqli_prepare($conn, "DELETE FROM user_roles WHERE role_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error deleting user assignments: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    // Delete the role itself
    $stmt = mysqli_prepare($conn, "DELETE FROM roles WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error deleting role: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    // Log the deletion to audit trail
    log_permission_audit($conn, $user_id, 'DELETE', 'role', $role_id, [
        'name' => $role['name'],
        'is_system_role' => $role['is_system_role'],
        'users_affected' => $user_count
    ]);
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Set success message and redirect
    $_SESSION['success_message'] = "Role '" . $role['name'] . "' has been deleted successfully. " . $user_count . " user(s) were unassigned from this role.";
    
    closeConnection($conn);
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    $_SESSION['error_message'] = 'Error deleting role: ' . $e->getMessage();
    
    closeConnection($conn);
    header('Location: view.php?id=' . $role_id);
    exit;
}

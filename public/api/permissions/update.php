<?php
/**
 * AJAX Handler for Permission Updates
 * Handles real-time checkbox updates from permissions matrix
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../roles/helpers.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Check if user has permission to manage permissions
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'edit');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['role_id']) || !isset($data['permission_id']) || !isset($data['permission_type']) || !isset($data['value'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$role_id = (int)$data['role_id'];
$permission_id = (int)$data['permission_id'];
$permission_type = $data['permission_type'];
$value = (int)$data['value'];

// Validate inputs
if ($role_id <= 0 || $permission_id <= 0 || !in_array($value, [0, 1])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Verify role exists
$role_check = mysqli_prepare($conn, "SELECT id, name FROM roles WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($role_check, 'i', $role_id);
mysqli_stmt_execute($role_check);
$role_result = mysqli_stmt_get_result($role_check);

if (mysqli_num_rows($role_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Role not found']);
    exit;
}

$role = mysqli_fetch_assoc($role_result);
mysqli_stmt_close($role_check);

// Verify permission exists
$perm_check = mysqli_prepare($conn, "SELECT id, page_path, page_name FROM permissions WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($perm_check, 'i', $permission_id);
mysqli_stmt_execute($perm_check);
$perm_result = mysqli_stmt_get_result($perm_check);

if (mysqli_num_rows($perm_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Permission not found']);
    exit;
}

$permission = mysqli_fetch_assoc($perm_result);
mysqli_stmt_close($perm_check);

// Update the permission
$success = update_role_permission($conn, $role_id, $permission_id, $permission_type, $value);

if ($success) {
    // Log the change
    log_permission_audit($conn, $user_id, 'UPDATE', 'role_permissions', $role_id, [
        'role' => $role['name'],
        'page' => $permission['page_name'],
        'permission_type' => $permission_type,
        'new_value' => $value
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Permission updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update permission'
    ]);
}

closeConnection($conn);

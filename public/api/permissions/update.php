<?php
error_reporting(0);
ini_set('display_errors', 0);

/**
 * AJAX Handler for Permission Updates
 * Handles real-time checkbox updates from permissions matrix
 */

session_start();
ob_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../settings/roles/helpers.php';
require_once __DIR__ . '/../../settings/permissions/helpers_table_based.php';

ob_end_clean();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];
    $conn = createConnection(true);

// Check if user has permission to manage permissions
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'edit');

// Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['role_id']) || !isset($data['permission_id']) || !isset($data['permission_type']) || !isset($data['value'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }

    $role_id = (int)$data['role_id'];
    $permission_id = (int)$data['permission_id'];
    $permission_type = $data['permission_type'];
    $value = (int)$data['value'];

    // Validate inputs
    $valid_types = [
        'can_create',
        'can_view_all', 'can_view_assigned', 'can_view_own',
        'can_edit_all', 'can_edit_assigned', 'can_edit_own',
        'can_delete_all', 'can_delete_assigned', 'can_delete_own',
        'can_export'
    ];
    if ($role_id <= 0 || $permission_id <= 0 || !in_array($value, [0, 1]) || !in_array($permission_type, $valid_types, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

// Verify role exists
    $role_check = mysqli_prepare($conn, "SELECT id, name FROM roles WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($role_check, 'i', $role_id);
mysqli_stmt_execute($role_check);
$role_result = mysqli_stmt_get_result($role_check);

    if (mysqli_num_rows($role_result) === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Role not found']);
        exit;
    }

$role = mysqli_fetch_assoc($role_result);
mysqli_stmt_close($role_check);

// Verify permission exists
    $perm_check = mysqli_prepare($conn, "SELECT id, table_name, display_name FROM permissions WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($perm_check, 'i', $permission_id);
mysqli_stmt_execute($perm_check);
$perm_result = mysqli_stmt_get_result($perm_check);

    if (mysqli_num_rows($perm_result) === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Permission not found']);
        exit;
    }

$permission = mysqli_fetch_assoc($perm_result);
mysqli_stmt_close($perm_check);

// Update the permission
    $success = update_table_permission($conn, $role_id, $permission_id, $permission_type, $value);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Permission updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update permission'
        ]);
    }

    closeConnection($conn);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}

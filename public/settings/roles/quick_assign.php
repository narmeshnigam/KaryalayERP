<?php
/**
 * Quick Role Assignment
 * Allows users to assign a role to themselves during initial setup
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

if ($role_id <= 0) {
    $_SESSION['error_message'] = 'Invalid role selected.';
    header('Location: index.php');
    exit;
}

$conn = createConnection(true);

// Verify role exists and is active
$stmt = mysqli_prepare($conn, "SELECT name FROM roles WHERE id = ? AND status = 'Active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $role_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$role = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$role) {
    $_SESSION['error_message'] = 'Selected role not found or is inactive.';
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Check if user already has this role
$stmt = mysqli_prepare($conn, "SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $role_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$existing = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($existing) {
    $_SESSION['info_message'] = 'You already have the ' . htmlspecialchars($role['name']) . ' role assigned.';
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Assign the role
$stmt = mysqli_prepare($conn, "INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $role_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    // Log the assignment
    log_permission_audit($conn, $user_id, 'ASSIGN', 'user_role', mysqli_insert_id($conn), [
        'user_id' => $user_id,
        'role_id' => $role_id,
        'role_name' => $role['name'],
        'self_assigned' => true
    ]);
    
    $_SESSION['success_message'] = '✓ Successfully assigned ' . htmlspecialchars($role['name']) . ' role to your account!';
} else {
    $_SESSION['error_message'] = 'Error assigning role: ' . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
closeConnection($conn);

header('Location: index.php');
exit;

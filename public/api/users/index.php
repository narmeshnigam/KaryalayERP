<?php
/**
 * Users API - Main Endpoint Router
 * RESTful API for user management operations
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../users/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // GET: Fetch all users with optional filters
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['role_id'])) $filters['role_id'] = (int)$_GET['role_id'];
            if (!empty($_GET['entity_type'])) $filters['entity_type'] = $_GET['entity_type'];
            if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
            
            $users = get_all_users($conn, $filters);
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'get':
            // GET: Fetch specific user
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID', 400);
            }
            
            $user = get_user_by_id($conn, $user_id);
            if (!$user) {
                throw new Exception('User not found', 404);
            }
            
            echo json_encode(['success' => true, 'data' => $user]);
            break;
            
        case 'create':
            // POST: Create new user
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $data = [
                'entity_id' => $input['entity_id'] ?? null,
                'entity_type' => $input['entity_type'] ?? null,
                'username' => trim($input['username'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'phone' => trim($input['phone'] ?? ''),
                'password' => $input['password'] ?? '',
                'role_id' => (int)($input['role_id'] ?? 0),
                'status' => $input['status'] ?? 'Active',
                'created_by' => $current_user_id
            ];
            
            // Validate
            $errors = validate_user_data($data, false);
            
            if (username_exists($conn, $data['username'])) {
                $errors[] = "Username already exists";
            }
            
            if (!empty($data['email']) && email_exists($conn, $data['email'])) {
                $errors[] = "Email already exists";
            }
            
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors), 400);
            }
            
            // Hash password
            $data['password_hash'] = hash_password($data['password']);
            
            $new_user_id = create_user($conn, $data);
            if (!$new_user_id) {
                throw new Exception('Failed to create user', 500);
            }
            
            $user = get_user_by_id($conn, $new_user_id);
            echo json_encode(['success' => true, 'message' => 'User created successfully', 'data' => $user]);
            break;
            
        case 'update':
            // POST: Update existing user
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $user_id = isset($input['id']) ? (int)$input['id'] : 0;
            
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID', 400);
            }
            
            $data = [
                'username' => trim($input['username'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'phone' => trim($input['phone'] ?? ''),
                'role_id' => (int)($input['role_id'] ?? 0),
                'status' => $input['status'] ?? 'Active'
            ];
            
            // Validate
            $errors = validate_user_data($data, true);
            
            if (username_exists($conn, $data['username'], $user_id)) {
                $errors[] = "Username already exists";
            }
            
            if (!empty($data['email']) && email_exists($conn, $data['email'], $user_id)) {
                $errors[] = "Email already exists";
            }
            
            // Prevent self-deactivation
            if ($user_id === $current_user_id && $data['status'] !== 'Active') {
                $errors[] = "You cannot deactivate your own account";
            }
            
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors), 400);
            }
            
            if (!update_user($conn, $user_id, $data)) {
                throw new Exception('Failed to update user', 500);
            }
            
            $user = get_user_by_id($conn, $user_id);
            echo json_encode(['success' => true, 'message' => 'User updated successfully', 'data' => $user]);
            break;
            
        case 'reset-password':
            // POST: Reset user password
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $user_id = isset($input['id']) ? (int)$input['id'] : 0;
            $new_password = $input['password'] ?? '';
            
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID', 400);
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters', 400);
            }
            
            $password_hash = hash_password($new_password);
            if (!update_user_password($conn, $user_id, $password_hash)) {
                throw new Exception('Failed to update password', 500);
            }
            
            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            break;
            
        case 'delete':
            // POST: Delete user
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $user_id = isset($input['id']) ? (int)$input['id'] : 0;
            
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID', 400);
            }
            
            if ($user_id === $current_user_id) {
                throw new Exception('You cannot delete your own account', 400);
            }
            
            if (!delete_user($conn, $user_id)) {
                throw new Exception('Failed to delete user', 500);
            }
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            break;
            
        case 'activity-log':
            // GET: Fetch user activity log
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $activities = get_user_activity_log($conn, $user_id, $limit);
            echo json_encode(['success' => true, 'data' => $activities]);
            break;
            
        case 'statistics':
            // GET: Fetch user statistics
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $stats = get_user_statistics($conn);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'available-employees':
            // GET: Fetch available employees for linking
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $employees = get_available_employees($conn);
            echo json_encode(['success' => true, 'data' => $employees]);
            break;
            
        case 'roles':
            // GET: Fetch all active roles
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $roles = get_active_roles($conn);
            echo json_encode(['success' => true, 'data' => $roles]);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

closeConnection($conn);
?>

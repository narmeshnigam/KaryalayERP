<?php
/**
 * Users Management - List All Users
 * View, filter, and manage system users
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'users', 'view_all');

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

// Get permissions
$users_permissions = authz_get_permission_set($conn, 'users');

// Get filter parameters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['role_id'])) {
    $filters['role_id'] = (int)$_GET['role_id'];
}
if (!empty($_GET['entity_type'])) {
    $filters['entity_type'] = $_GET['entity_type'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Fetch all users with filters
$users = get_all_users($conn, $filters);

// Get user statistics
$stats = get_user_statistics($conn);

// Get all roles for filter dropdown
$roles = get_active_roles($conn);

$page_title = 'Users Management - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">👥 Users Management</h1>
                    <p style="color: #6c757d; margin: 0;">Manage system users, assign roles, and control access</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="activity-log.php" class="btn btn-secondary">📊 Activity Log</a>
                    <?php if ($users_permissions['can_create'] || $IS_SUPER_ADMIN): ?>
                    <a href="add.php" class="btn btn-primary">➕ Add New User</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $stats['total']; ?></div>
                <div style="opacity: 0.9;">Total Users</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $stats['active']; ?></div>
                <div style="opacity: 0.9;">Active Users</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $stats['employees']; ?></div>
                <div style="opacity: 0.9;">Employee Users</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $stats['recent_logins']; ?></div>
                <div style="opacity: 0.9;">Recent Logins (7 days)</div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">🔍 Filter Users</h3>
            
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 14px; color: #495057;">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo ($filters['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($filters['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Suspended" <?php echo ($filters['status'] ?? '') === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 14px; color: #495057;">Role</label>
                    <select name="role_id" class="form-control">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo ($filters['role_id'] ?? 0) == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 14px; color: #495057;">User Type</label>
                    <select name="entity_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Employee" <?php echo ($filters['entity_type'] ?? '') === 'Employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="Client" <?php echo ($filters['entity_type'] ?? '') === 'Client' ? 'selected' : ''; ?>>Client</option>
                        <option value="Other" <?php echo ($filters['entity_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 14px; color: #495057;">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Username, Email, Phone..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                </div>
                
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">
                👥 All Users (<?php echo count($users); ?>)
            </h3>
            
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">👤</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Users Found</h3>
                    <p>No users match your filter criteria.</p>
                    <a href="add.php" class="btn btn-primary" style="margin-top: 16px;">➕ Add First User</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Entity</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created By</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['entity_type'] === 'Employee' && $user['employee_code']): ?>
                                        <div style="font-size: 11px; color: #6c757d;">
                                            <?php echo htmlspecialchars($user['employee_code']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($user['entity_type'] === 'Employee' && ($user['employee_first_name'] || $user['employee_last_name'])): ?>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            👨‍💼 <?php echo htmlspecialchars(trim($user['employee_first_name'] . ' ' . $user['employee_last_name'])); ?>
                                        </span>
                                    <?php elseif ($user['entity_type'] === 'Client'): ?>
                                        <span style="background: #f3e5f5; color: #7b1fa2; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            🏢 Client
                                        </span>
                                    <?php elseif ($user['entity_type']): ?>
                                        <span style="background: #fafafa; color: #616161; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo htmlspecialchars($user['entity_type']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9e9e9e;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role_name']): ?>
                                        <span style="background: #003581; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #dc3545;">No Role</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'Active'): ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Active</span>
                                    <?php elseif ($user['status'] === 'Suspended'): ?>
                                        <span style="color: #ffc107; font-weight: 600;">⚠ Suspended</span>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-weight: 600;">✗ Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?>
                                    <?php else: ?>
                                        <span style="color: #9e9e9e;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['created_by_username']): ?>
                                        <span style="font-size: 12px; color: #495057;">
                                            <?php echo htmlspecialchars($user['created_by_username']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9e9e9e;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View Details">👁️</a>
                                        <?php if ($users_permissions['can_edit_all'] || $IS_SUPER_ADMIN): ?>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Edit User">✏️</a>
                                        <?php endif; ?>
                                        <?php if (($users_permissions['can_delete_all'] || $IS_SUPER_ADMIN) && $user['id'] != $CURRENT_USER_ID): // Can't delete yourself ?>
                                            <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete User">🗑️</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #1b2a57;
    border-bottom: 2px solid #dee2e6;
    font-size: 13px;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}
</style>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

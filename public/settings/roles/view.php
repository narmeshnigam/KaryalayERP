<?php
/**
 * View Role Details
 * Display complete information about a role including permissions and assigned users
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
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = createConnection(true);

// Check if tables exist
if (!roles_tables_exist($conn)) {
    header('Location: onboarding.php');
    exit;
}

// Check if user has permission to view roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'view');

$not_found = false;
$role = null;
$assigned_users = [];
$role_permissions = [];

// Fetch role details
if ($role_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT r.*,
               (SELECT CONCAT(u.username, ' (', e.first_name, ' ', e.last_name, ')')
                FROM users u
                LEFT JOIN employees e ON u.id = e.user_id
                WHERE u.id = r.created_by
                LIMIT 1) as creator_name
        FROM roles r 
        WHERE r.id = ?
    ");
    
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $role = mysqli_fetch_assoc($result);
    } else {
        $not_found = true;
    }
    mysqli_stmt_close($stmt);
    
    // If role found, get assigned users
    if ($role) {
        $stmt = mysqli_prepare($conn, "
            SELECT ur.id, ur.user_id, ur.assigned_at,
                   u.username,
                   CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')) as full_name,
                   e.employee_code,
                   (SELECT CONCAT(u2.username, ' (', e2.first_name, ' ', e2.last_name, ')')
                    FROM users u2
                    LEFT JOIN employees e2 ON u2.id = e2.user_id
                    WHERE u2.id = ur.assigned_by
                    LIMIT 1) as assigned_by_name
            FROM user_roles ur
            INNER JOIN users u ON ur.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            WHERE ur.role_id = ?
            ORDER BY ur.assigned_at DESC
        ");
        
        mysqli_stmt_bind_param($stmt, 'i', $role_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $assigned_users[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        // Get permissions assigned to this role
        $stmt = mysqli_prepare($conn, "
            SELECT p.table_name, p.module, p.display_name,
                   rp.can_create, rp.can_view_all, rp.can_view_assigned, rp.can_view_own,
                   rp.can_edit_all, rp.can_edit_assigned, rp.can_edit_own,
                   rp.can_delete_all, rp.can_delete_assigned, rp.can_delete_own,
                   rp.can_export
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
            AND (
                rp.can_create = 1 OR rp.can_view_all = 1 OR rp.can_view_assigned = 1 OR rp.can_view_own = 1 OR
                rp.can_edit_all = 1 OR rp.can_edit_assigned = 1 OR rp.can_edit_own = 1 OR
                rp.can_delete_all = 1 OR rp.can_delete_assigned = 1 OR rp.can_delete_own = 1 OR
                rp.can_export = 1
            )
            ORDER BY p.module, p.display_name
        ");
        
        mysqli_stmt_bind_param($stmt, 'i', $role_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $role_permissions[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $not_found = true;
}

$page_title = $role ? 'View Role: ' . $role['name'] . ' - ' . APP_NAME : 'Role Not Found - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <?php if ($not_found): ?>
        <!-- Not Found Message -->
        <div class="card" style="text-align: center; padding: 48px;">
            <div style="font-size: 64px; margin-bottom: 16px;">🔍</div>
            <h2 style="color: #dc3545; margin-bottom: 12px;">Role Not Found</h2>
            <p style="color: #6c757d; margin-bottom: 24px;">
                The role you're looking for doesn't exist or has been deleted.
            </p>
            <a href="index.php" class="btn btn-primary">← Back to Roles List</a>
        </div>
        
        <?php else: ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <h1 style="margin: 0;">🔐 <?php echo htmlspecialchars($role['name']); ?></h1>
                        <?php if ($role['is_system_role']): ?>
                            <span style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                🔒 System Role
                            </span>
                        <?php endif; ?>
                        <?php if ($role['status'] === 'Active'): ?>
                            <span style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                Active
                            </span>
                        <?php else: ?>
                            <span style="background: #6c757d; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #6c757d; margin: 0;"><?php echo htmlspecialchars($role['description']); ?></p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-secondary">← Back to List</a>
                    <?php if (has_permission($conn, $user_id, 'settings/roles', 'edit')): ?>
                        <a href="edit.php?id=<?php echo $role_id; ?>" class="btn btn-primary">✏️ Edit Role</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Role Information Card -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                📋 Role Information
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Role Name</div>
                    <div style="font-weight: 600; color: #1b2a57;"><?php echo htmlspecialchars($role['name']); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Status</div>
                    <div style="font-weight: 600;">
                        <?php if ($role['status'] === 'Active'): ?>
                            <span style="color: #28a745;">✓ Active</span>
                        <?php else: ?>
                            <span style="color: #6c757d;">✗ Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">System Role</div>
                    <div style="font-weight: 600;">
                        <?php echo $role['is_system_role'] ? '🔒 Yes (Protected)' : 'No (Can be deleted)'; ?>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Created At</div>
                    <div style="font-weight: 600;"><?php echo date('M d, Y h:i A', strtotime($role['created_at'])); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Created By</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($role['creator_name'] ?? '—'); ?></div>
                </div>
                
                <?php if ($role['updated_at']): ?>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Last Updated</div>
                    <div style="font-weight: 600;"><?php echo date('M d, Y h:i A', strtotime($role['updated_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Description</div>
                <div style="line-height: 1.6; color: #495057;"><?php echo nl2br(htmlspecialchars($role['description'])); ?></div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo count($assigned_users); ?></div>
                <div style="opacity: 0.9;">Users Assigned</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo count($role_permissions); ?></div>
                <div style="opacity: 0.9;">Permissions Granted</div>
            </div>
        </div>

        <!-- Assigned Users -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                👥 Assigned Users (<?php echo count($assigned_users); ?>)
            </h3>
            
            <?php if (empty($assigned_users)): ?>
                <div style="text-align: center; padding: 32px; color: #6c757d;">
                    <div style="font-size: 48px; margin-bottom: 12px;">👤</div>
                    <p>No users have been assigned this role yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Employee Code</th>
                                <th>Assigned Date</th>
                                <th>Assigned By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars(trim($user['full_name']) ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($user['employee_code'] ?? '—'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['assigned_at'])); ?></td>
                                <td><?php echo htmlspecialchars($user['assigned_by_name'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Permissions -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                🔑 Permissions (<?php echo count($role_permissions); ?>)
            </h3>
            
            <?php if (empty($role_permissions)): ?>
                <div style="text-align: center; padding: 32px; color: #6c757d;">
                    <div style="font-size: 48px; margin-bottom: 12px;">🔐</div>
                    <p>No permissions have been assigned to this role yet.</p>
                    <p style="font-size: 14px;">Users with this role won't have access to any protected pages.</p>
                </div>
            <?php else: ?>
                <?php
                // Group permissions by module
                $grouped = [];
                foreach ($role_permissions as $perm) {
                    $module = $perm['module'] ?? 'General';
                    if (!isset($grouped[$module])) {
                        $grouped[$module] = [];
                    }
                    $grouped[$module][] = $perm;
                }
                ?>
                
                <?php foreach ($grouped as $module => $perms): ?>
                <div style="margin-bottom: 24px;">
                    <h4 style="color: #003581; margin: 0 0 12px 0; font-size: 16px;">
                        📁 <?php echo htmlspecialchars($module); ?>
                    </h4>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Page</th>
                                    <th>View</th>
                                    <th>Create</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                    <th>Export</th>
                                    <th>Approve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perms as $perm): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($perm['display_name']); ?></strong>
                                        <div style="font-size: 11px; color: #6c757d;">Table: <?php echo htmlspecialchars($perm['table_name']); ?></div>
                                    </td>
                                    <td><?php echo ($perm['can_view_all'] || $perm['can_view_assigned'] || $perm['can_view_own']) ? '<span style="color:#28a745;">✓</span>' : '<span style="color:#ddd;">—</span>'; ?></td>
                                    <td><?php echo $perm['can_create'] ? '<span style="color:#28a745;">✓</span>' : '<span style="color:#ddd;">—</span>'; ?></td>
                                    <td><?php echo ($perm['can_edit_all'] || $perm['can_edit_assigned'] || $perm['can_edit_own']) ? '<span style="color:#28a745;">✓</span>' : '<span style="color:#ddd;">—</span>'; ?></td>
                                    <td><?php echo ($perm['can_delete_all'] || $perm['can_delete_assigned'] || $perm['can_delete_own']) ? '<span style="color:#28a745;">✓</span>' : '<span style="color:#ddd;">—</span>'; ?></td>
                                    <td><?php echo $perm['can_export'] ? '<span style="color:#28a745;">✓</span>' : '<span style="color:#ddd;">—</span>'; ?></td>
                                    <td>—</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
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
</style>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php'; 
?>

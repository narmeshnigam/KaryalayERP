<?php
/**
 * Users Management - View User Profile
 * Display detailed user information and activity history
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

// Get permissions
$users_permissions = authz_get_permission_set($conn, 'users');

// Get user ID from URL
$view_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($view_user_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch user details
$user = get_user_by_id($conn, $view_user_id);

if (!$user) {
    flash_add('error', 'User not found', 'users');
    header('Location: index.php');
    exit;
}

// Get user activity log (last 20 records)
$activity_log = get_user_activity_log($conn, $view_user_id, 20);

$page_title = 'View User: ' . $user['username'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <h1 style="margin: 0;">👤 <?php echo htmlspecialchars($user['username']); ?></h1>
                        <?php if ($user['status'] === 'Active'): ?>
                            <span style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                Active
                            </span>
                        <?php elseif ($user['status'] === 'Suspended'): ?>
                            <span style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                Suspended
                            </span>
                        <?php else: ?>
                            <span style="background: #6c757d; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #6c757d; margin: 0;">Complete user profile and activity information</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-secondary">← Back to List</a>
                    <?php if ($users_permissions['can_edit_all'] || $IS_SUPER_ADMIN): ?>
                    <a href="edit.php?id=<?php echo $view_user_id; ?>" class="btn btn-primary">✏️ Edit User</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php echo flash_render(); ?>

        <!-- User Information Card -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                👤 User Information
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Username</div>
                    <div style="font-weight: 600; color: #1b2a57;"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Full Name</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['full_name'] ?? '—'); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Email</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Phone</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Role</div>
                    <div>
                        <span style="background: #003581; color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                            <?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?>
                        </span>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Status</div>
                    <div style="font-weight: 600;">
                        <?php if ($user['status'] === 'Active'): ?>
                            <span style="color: #28a745;">✓ Active</span>
                        <?php elseif ($user['status'] === 'Suspended'): ?>
                            <span style="color: #ffc107;">⚠ Suspended</span>
                        <?php else: ?>
                            <span style="color: #6c757d;">✗ Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Created Date</div>
                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
                
                <?php if ($user['created_by_username']): ?>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Created By</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['created_by_username']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Entity Information (if linked) -->
        <?php if ($user['entity_type'] === 'Employee' && $user['employee_first_name']): ?>
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                👨‍💼 Linked Employee Information
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Full Name</div>
                    <div style="font-weight: 600;">
                        <?php echo htmlspecialchars($user['employee_first_name'] . ' ' . $user['employee_last_name']); ?>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Employee Code</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['employee_code'] ?? '—'); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Department</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['department'] ?? '—'); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Designation</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['designation'] ?? '—'); ?></div>
                </div>
            </div>
        </div>
        <?php elseif ($user['entity_type']): ?>
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                🔗 Linked Entity Information
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Entity Type</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['entity_type']); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Entity ID</div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['entity_id'] ?? '—'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $user['login_count']; ?></div>
                <div style="opacity: 0.9;">Successful Logins</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $user['failed_login_count']; ?></div>
                <div style="opacity: 0.9;">Failed Login Attempts</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <div style="font-size: 18px; font-weight: bold;">
                    <?php echo $user['last_successful_login'] ? date('M d, Y', strtotime($user['last_successful_login'])) : 'Never'; ?>
                </div>
                <div style="opacity: 0.9;">Last Login</div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                📊 Recent Activity (Last 20 Records)
            </h3>
            
            <?php if (empty($activity_log)): ?>
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📭</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Activity Yet</h3>
                    <p>This user hasn't logged in yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                                <th>Device/Browser</th>
                                <th>Status</th>
                                <th>Logout Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_log as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['login_time'])); ?></td>
                                <td style="font-family: monospace;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['device'] ?? ''); ?>">
                                    <?php echo htmlspecialchars(substr($log['device'] ?? '—', 0, 50)); ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] === 'Success'): ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Success</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: 600;">✗ Failed</span>
                                        <?php if ($log['failure_reason']): ?>
                                            <div style="font-size: 11px; color: #6c757d;"><?php echo htmlspecialchars($log['failure_reason']); ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $log['logout_time'] ? date('M d, Y h:i A', strtotime($log['logout_time'])) : '—'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 16px; text-align: center;">
                    <a href="activity-log.php?user_id=<?php echo $view_user_id; ?>" class="btn btn-secondary">
                        View Full Activity History →
                    </a>
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
</style>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

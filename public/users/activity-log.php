<?php
/**
 * Users Management - Activity Log
 * View all user login/logout activities across the system
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

// Get optional user_id filter
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Get activity log
$activity_log = get_user_activity_log($conn, $filter_user_id, 100);

// If filtering by specific user, get user details
$filtered_user = null;
if ($filter_user_id) {
    $filtered_user = get_user_by_id($conn, $filter_user_id);
}

$page_title = 'User Activity Log - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">📊 User Activity Log</h1>
                    <p style="color: #6c757d; margin: 0;">
                        <?php if ($filtered_user): ?>
                            Activity history for user: <strong><?php echo htmlspecialchars($filtered_user['username']); ?></strong>
                        <?php else: ?>
                            View all user login and logout activities
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <?php if ($filter_user_id): ?>
                        <a href="activity-log.php" class="btn btn-secondary">View All Users</a>
                        <a href="view.php?id=<?php echo $filter_user_id; ?>" class="btn btn-secondary">👁️ View Profile</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back to Users</a>
                </div>
            </div>
        </div>

        <!-- Activity Log Table -->
        <div class="card">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">
                📋 Activity Records (<?php echo count($activity_log); ?>)
            </h3>
            
            <?php if (empty($activity_log)): ?>
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📭</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Activity Records</h3>
                    <p>No login activity has been recorded yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php if (!$filter_user_id): ?>
                                <th>Username</th>
                                <th>Role</th>
                                <?php endif; ?>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>IP Address</th>
                                <th>Device/Browser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_log as $log): ?>
                            <tr>
                                <?php if (!$filter_user_id): ?>
                                <td>
                                    <a href="view.php?id=<?php echo $log['user_id']; ?>" style="color: #003581; text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="background: #003581; color: white; padding: 2px 8px; border-radius: 8px; font-size: 11px;">
                                        <?php echo htmlspecialchars($log['role_name'] ?? 'No Role'); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['login_time'])); ?></td>
                                <td>
                                    <?php if ($log['logout_time']): ?>
                                        <?php echo date('M d, Y h:i A', strtotime($log['logout_time'])); ?>
                                    <?php else: ?>
                                        <span style="color: #9e9e9e;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: monospace; font-size: 13px;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                </td>
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
</style>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

<?php
/**
 * My Account - User Profile & Password Change
 * Personal profile page for logged-in users
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

$user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

// Fetch user details
$user = get_user_by_id($conn, $user_id);

if (!$user) {
    $_SESSION['flash_error'] = "User profile not found.";
    header('Location: ../logout.php');
    exit;
}

$errors = [];
$success_message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Verify old password
    if (!verify_password($old_password, $user['password_hash'])) {
        $errors[] = "Current password is incorrect";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $password_hash = hash_password($new_password);
        if (update_user_password($conn, $user_id, $password_hash)) {
            $success_message = "Password changed successfully!";
            $_SESSION['flash_success'] = $success_message;
            
            // Refresh user data
            $user = get_user_by_id($conn, $user_id);
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
    }
}

// Get recent activity
$recent_activity = get_user_activity_log($conn, $user_id, 10);

$page_title = 'My Account - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 style="margin: 0 0 8px 0;">👤 My Account</h1>
                <p style="color: #6c757d; margin: 0;">View and manage your personal profile</p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
        <div style="background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong>✓ Success!</strong> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong>⚠️ Please fix the following errors:</strong>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Profile Information Card -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                ℹ️ Profile Information
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Username</div>
                    <div style="font-weight: 600; color: #1b2a57;"><?php echo htmlspecialchars($user['username']); ?></div>
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
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Member Since</div>
                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>

            <?php if ($user['role_description']): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Role Description</div>
                <div style="line-height: 1.6; color: #495057;"><?php echo nl2br(htmlspecialchars($user['role_description'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Entity Information (if linked) -->
        <?php if ($user['entity_type'] === 'Employee' && $user['employee_first_name']): ?>
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                👨‍💼 Employee Information
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
        <?php endif; ?>

        <!-- Change Password Form -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                🔑 Change Password
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="change_password" value="1">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <label for="old_password" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Current Password <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="old_password" 
                            name="old_password" 
                            class="form-control" 
                            required
                            autocomplete="current-password"
                        >
                        <small style="color: #6c757d;">Enter your current password</small>
                    </div>
                    
                    <div>
                        <label for="new_password" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            New Password <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-control" 
                            required
                            autocomplete="new-password"
                        >
                        <small style="color: #6c757d;">Minimum 6 characters</small>
                    </div>
                    
                    <div>
                        <label for="confirm_password" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Confirm New Password <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            required
                            autocomplete="new-password"
                        >
                        <small style="color: #6c757d;">Re-enter new password</small>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">🔑 Change Password</button>
                </div>
            </form>
        </div>

        <!-- Activity Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="font-size: 32px; font-weight: bold;"><?php echo $user['login_count']; ?></div>
                <div style="opacity: 0.9;">Total Logins</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <div style="font-size: 18px; font-weight: bold;">
                    <?php echo $user['last_successful_login'] ? date('M d, Y', strtotime($user['last_successful_login'])) : 'Never'; ?>
                </div>
                <div style="opacity: 0.9;">Last Login</div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                📊 Recent Login Activity
            </h3>
            
            <?php if (empty($recent_activity)): ?>
                <div style="text-align: center; padding: 32px; color: #6c757d;">
                    <p>No recent activity recorded.</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['login_time'])); ?></td>
                                <td style="font-family: monospace;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars(substr($log['device'] ?? '—', 0, 50)); ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] === 'Success'): ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Success</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: 600;">✗ Failed</span>
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

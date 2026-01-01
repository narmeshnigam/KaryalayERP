<?php
/**
 * Users Management - Edit User
 * Modify user details, role assignment, and status
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'users', 'edit_all');

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

// Get user ID from URL
$edit_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($edit_user_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch user details
$user = get_user_by_id($conn, $edit_user_id);

if (!$user) {
    flash_add('error', 'User not found', 'users');
    header('Location: index.php');
    exit;
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'username' => trim($_POST['username']),
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'role_id' => isset($_POST['role_id']) ? (int)$_POST['role_id'] : null,
        'status' => $_POST['status'] ?? 'Active'
    ];
    
    // Validate data
    $errors = validate_user_data($data, true);
    
    // Check if username exists (excluding current user)
    if (username_exists($conn, $data['username'], $edit_user_id)) {
        $errors[] = "Username already exists";
    }
    
    // Check if email exists (excluding current user)
    if (!empty($data['email']) && email_exists($conn, $data['email'], $edit_user_id)) {
        $errors[] = "Email already exists";
    }
    
    // Prevent disabling yourself
    if ($edit_user_id === $CURRENT_USER_ID && $data['status'] !== 'Active') {
        $errors[] = "You cannot deactivate your own account";
    }
    
    // If no errors, update user
    if (empty($errors)) {
        if (update_user($conn, $edit_user_id, $data)) {
            $success_message = "User updated successfully!";
            flash_add('success', $success_message, 'users');
            
            // Refresh user data
            $user = get_user_by_id($conn, $edit_user_id);
        } else {
            $errors[] = "Failed to update user. Please try again.";
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        $password_hash = hash_password($new_password);
        if (update_user_password($conn, $edit_user_id, $password_hash)) {
            $success_message = "Password updated successfully!";
            $_SESSION['flash_success'] = $success_message;
        } else {
            $errors[] = "Failed to update password";
        }
    }
}

// Get all active roles
$roles = get_active_roles($conn);

$page_title = 'Edit User: ' . $user['username'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">✏️ Edit User: <?php echo htmlspecialchars($user['username']); ?></h1>
                    <p style="color: #6c757d; margin: 0;">Modify user account details and access permissions</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="view.php?id=<?php echo $edit_user_id; ?>" class="btn btn-secondary">👁️ View Profile</a>
                    <a href="index.php" class="btn btn-secondary">← Back to List</a>
                </div>
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

        <!-- Edit User Form -->
        <form method="POST" action="">
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    👤 User Information
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Username -->
                    <div>
                        <label for="username" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Username <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($user['username']); ?>"
                            required
                        >
                        <small style="color: #6c757d;">Unique identifier for login</small>
                    </div>
                    
                    <!-- Full Name -->
                    <div>
                        <label for="full_name" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Full Name <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                            required
                        >
                        <small style="color: #6c757d;">Complete name of the user</small>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Email <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                            required
                        >
                        <small style="color: #6c757d;">For notifications and password reset</small>
                    </div>
                    
                    <!-- Phone -->
                    <div>
                        <label for="phone" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Phone Number <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                            required
                        >
                        <small style="color: #6c757d;">Contact number</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    🔐 Access & Permissions
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Role Assignment Info (Read-only) -->
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Current Roles
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                            <?php if (!empty($user['role_name'])): ?>
                                <strong>🔑 <?php echo htmlspecialchars($user['role_name']); ?></strong>
                            <?php else: ?>
                                <em style="color: #6c757d;">No roles assigned</em>
                            <?php endif; ?>
                        </div>
                        <small style="color: #6c757d;">
                            To manage roles, use 
                            <a href="../settings/assign-roles/" style="color: #003581; text-decoration: none; font-weight: 600;">
                                Roles & Permissions
                            </a> module
                        </small>
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Status <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" required <?php echo ($edit_user_id === $CURRENT_USER_ID) ? 'disabled title="Cannot change your own status"' : ''; ?>>
                            <option value="Active" <?php echo ($user['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($user['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Suspended" <?php echo ($user['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <small style="color: #6c757d;">Account access status</small>
                        <?php if ($edit_user_id === $CURRENT_USER_ID): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($user['status']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Entity Info (Read-only) -->
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Linked Entity
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                            <?php if ($user['entity_type'] === 'Employee' && $user['employee_first_name']): ?>
                                <strong>👨‍💼 Employee:</strong> <?php echo htmlspecialchars($user['employee_first_name'] . ' ' . $user['employee_last_name']); ?>
                                <?php if ($user['employee_code']): ?>
                                    (<?php echo htmlspecialchars($user['employee_code']); ?>)
                                <?php endif; ?>
                            <?php elseif ($user['entity_type']): ?>
                                <strong><?php echo htmlspecialchars($user['entity_type']); ?></strong>
                            <?php else: ?>
                                <em style="color: #6c757d;">No linked entity</em>
                            <?php endif; ?>
                        </div>
                        <small style="color: #6c757d;">Entity linking cannot be changed after creation</small>
                    </div>
                    
                    <!-- Created By (Info Display) -->
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Created By
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                            <?php if ($user['created_by_username']): ?>
                                <strong><?php echo htmlspecialchars($user['created_by_username']); ?></strong>
                            <?php else: ?>
                                <em style="color: #6c757d;">Unknown</em>
                            <?php endif; ?>
                        </div>
                        <small style="color: #6c757d;">User who created this account</small>
                    </div>
                    
                    <!-- Last Login (Info Display) -->
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Last Login
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                            <?php if ($user['last_login']): ?>
                                <strong><?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?></strong>
                            <?php else: ?>
                                <em style="color: #9e9e9e;">Never logged in</em>
                            <?php endif; ?>
                        </div>
                        <small style="color: #6c757d;">Most recent login timestamp</small>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">✓ Update User</button>
            </div>
        </form>

        <!-- Password Reset Section -->
        <div class="card" style="margin-top: 24px;">
            <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                🔑 Reset Password
            </h3>
            
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to reset this user\'s password?');">
                <input type="hidden" name="reset_password" value="1">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
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
                        >
                        <small style="color: #6c757d;">Re-enter new password</small>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-warning">🔑 Reset Password</button>
                </div>
            </form>
        </div>

        <!-- Account Info -->
        <div class="card" style="margin-top: 24px;">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">📊 Account Statistics</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="padding: 12px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Created</div>
                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
                
                <div style="padding: 12px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Last Login</div>
                    <div style="font-weight: 600;">
                        <?php echo $user['last_successful_login'] ? date('M d, Y h:i A', strtotime($user['last_successful_login'])) : 'Never'; ?>
                    </div>
                </div>
                
                <div style="padding: 12px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Total Logins</div>
                    <div style="font-weight: 600;"><?php echo $user['login_count']; ?></div>
                </div>
                
                <div style="padding: 12px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Failed Attempts</div>
                    <div style="font-weight: 600; color: <?php echo ($user['failed_login_count'] > 5) ? '#dc3545' : 'inherit'; ?>">
                        <?php echo $user['failed_login_count']; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

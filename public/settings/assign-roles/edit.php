<?php
/**
 * Edit User Roles
 * Assign or remove roles for a specific user
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../roles/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$admin_user_id = (int)$_SESSION['user_id'];
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$conn = createConnection(true);

// Check if tables exist
if (!roles_tables_exist($conn)) {
    header('Location: ../roles/onboarding.php');
    exit;
}

// Check if user has permission to assign roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $admin_user_id, 'settings/roles', 'view');

$errors = [];
$not_found = false;
$target_user = null;
$current_roles = [];
$all_active_roles = [];

// Fetch target user details
if ($target_user_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $target_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $target_user = mysqli_fetch_assoc($result);
    } else {
        $not_found = true;
    }
    mysqli_stmt_close($stmt);
    
    // If user found, get their current roles
    if ($target_user) {
        $stmt = mysqli_prepare($conn, "
            SELECT r.id, r.name
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.status = 'Active'
            ORDER BY r.name
        ");
        
        mysqli_stmt_bind_param($stmt, 'i', $target_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $current_roles[] = $row['id'];
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $not_found = true;
}

// Get all active roles for selection
$stmt = mysqli_prepare($conn, "
    SELECT id, name, description
    FROM roles
    WHERE status = 'Active'
    ORDER BY name
");

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $all_active_roles[] = $row;
}
mysqli_stmt_close($stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target_user) {
    $selected_roles = isset($_POST['roles']) ? array_map('intval', $_POST['roles']) : [];
    
    // Validate all selected roles exist and are active
    $valid_roles = array_map(function($r) { return $r['id']; }, $all_active_roles);
    $invalid_roles = array_diff($selected_roles, $valid_roles);
    
    if (!empty($invalid_roles)) {
        $errors[] = 'One or more selected roles are invalid or inactive.';
    }
    
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Remove all current role assignments
            $stmt = mysqli_prepare($conn, "DELETE FROM user_roles WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $target_user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error removing current roles: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
            
            // Add new role assignments
            $assigned_roles = [];
            foreach ($selected_roles as $role_id) {
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                mysqli_stmt_bind_param($stmt, 'iii', $target_user_id, $role_id, $admin_user_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error assigning role: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);
                
                // Get role name for audit
                $role_stmt = mysqli_prepare($conn, "SELECT name FROM roles WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($role_stmt, 'i', $role_id);
                mysqli_stmt_execute($role_stmt);
                $role_result = mysqli_stmt_get_result($role_stmt);
                $role_row = mysqli_fetch_assoc($role_result);
                mysqli_stmt_close($role_stmt);
                
                $assigned_roles[] = $role_row['name'];
            }
            
            // Log the changes
            log_permission_audit($conn, $admin_user_id, 'ASSIGN', 'user_roles', $target_user_id, [
                'target_user' => $target_user['username'],
                'assigned_roles' => $assigned_roles,
                'role_count' => count($assigned_roles)
            ]);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['success_message'] = "Roles updated for user '{$target_user['username']}'! " . 
                                         (count($assigned_roles) > 0 ? "Assigned " . count($assigned_roles) . " role(s)." : "All roles removed.");
            
            closeConnection($conn);
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            mysqli_rollback($conn);
            $errors[] = 'Error updating roles: ' . $e->getMessage();
        }
    }
}

$page_title = $target_user ? 'Manage Roles: ' . $target_user['username'] . ' - ' . APP_NAME : 'User Not Found - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <?php if ($not_found): ?>
        <!-- Not Found Message -->
        <div class="card" style="text-align:center;padding:48px;">
            <div style="font-size:64px;margin-bottom:16px;">🔍</div>
            <h2 style="color:#dc3545;margin-bottom:12px;">User Not Found</h2>
            <p style="color:#6c757d;margin-bottom:24px;">
                The user you're looking for doesn't exist in the system.
            </p>
            <a href="index.php" class="btn btn-primary">← Back to Assign Roles</a>
        </div>
        
        <?php else: ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>👤 Manage Roles: <?php echo htmlspecialchars($target_user['username']); ?></h1>
                    <p>Assign or remove roles for this user</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="index.php" class="btn btn-secondary">← Back to User List</a>
                </div>
            </div>
        </div>

        <!-- User Information Card -->
        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin:0 0 16px 0;color:#1b2a57;">👤 User Information</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;">
                <div>
                    <div style="font-size:12px;color:#6c757d;margin-bottom:4px;">Username</div>
                    <div style="font-weight:600;color:#1b2a57;"><?php echo htmlspecialchars($target_user['username']); ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:#6c757d;margin-bottom:4px;">Email</div>
                    <div style="font-weight:600;color:#1b2a57;"><?php echo htmlspecialchars($target_user['email'] ?? '—'); ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:#6c757d;margin-bottom:4px;">Current Roles</div>
                    <div style="font-weight:600;color:#1b2a57;"><?php echo count($current_roles); ?></div>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div style="background:#f8d7da;border-left:4px solid #dc3545;padding:16px;margin-bottom:24px;border-radius:6px;color:#721c24;">
            <strong>❌ Error:</strong>
            <ul style="margin:8px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Role Assignment Form -->
        <div class="card">
            <h3 style="margin:0 0 20px 0;color:#1b2a57;border-bottom:2px solid #e5e7eb;padding-bottom:12px;">
                🔑 Assign Roles
            </h3>

            <form method="POST" action="" id="rolesForm">
                <?php if (empty($all_active_roles)): ?>
                    <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;border-radius:6px;color:#856404;">
                        <strong>⚠️ No Roles Available</strong>
                        <p>There are no active roles available for assignment. Please create and activate roles first.</p>
                    </div>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:24px;">
                        <?php foreach ($all_active_roles as $role): ?>
                        <div style="border:2px solid #e5e7eb;border-radius:8px;padding:16px;transition:all 0.3s;">
                            <input type="checkbox" id="role_<?php echo $role['id']; ?>" name="roles[]" value="<?php echo $role['id']; ?>"
                                <?php echo in_array($role['id'], $current_roles) ? 'checked' : ''; ?> 
                                onchange="this.parentElement.style.borderColor = this.checked ? '#28a745' : '#e5e7eb'; this.parentElement.style.backgroundColor = this.checked ? '#d4edda' : 'white';"
                                style="margin-right:8px;">
                            <label for="role_<?php echo $role['id']; ?>" style="cursor:pointer;font-weight:600;color:#1b2a57;">
                                <?php echo htmlspecialchars($role['name']); ?>
                            </label>
                            <p style="margin:8px 0 0 0;color:#6c757d;font-size:13px;line-height:1.5;">
                                <?php echo htmlspecialchars($role['description']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Information Card -->
                    <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:16px;margin-bottom:24px;border-radius:6px;">
                        <h4 style="margin:0 0 8px 0;color:#0066cc;">ℹ️ About Role Assignment</h4>
                        <ul style="margin:8px 0 0 20px;color:#004080;line-height:1.6;font-size:14px;">
                            <li>Users can have <strong>multiple roles</strong> at the same time</li>
                            <li>Permissions are <strong>combined</strong> from all assigned roles</li>
                            <li>Changes take effect <strong>immediately</strong></li>
                            <li>Unchecking a role will <strong>remove that access</strong></li>
                            <li>Only <strong>active roles</strong> are shown here</li>
                        </ul>
                    </div>

                    <!-- Form Actions -->
                    <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid #e5e7eb;">
                        <button type="submit" class="btn btn-primary">
                            ✓ Save Role Changes
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.btn-primary {
    background: #003581;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-primary:hover {
    background: #002456;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
}

.btn-secondary:hover {
    background: #5a6268;
}
</style>

<script>
// Form validation
document.getElementById('rolesForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('input[name="roles[]"]');
    
    if (checkboxes.length === 0) {
        return; // No roles available
    }
    
    const checked = document.querySelector('input[name="roles[]"]:checked');
    
    if (!checked) {
        const confirm_no_roles = confirm('Are you sure you want to remove all roles from this user?\n\nThis will effectively restrict their access to the system.');
        if (!confirm_no_roles) {
            e.preventDefault();
            return false;
        }
    }
});

// Initial styling
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="roles[]"]');
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            checkbox.parentElement.style.borderColor = '#28a745';
            checkbox.parentElement.style.backgroundColor = '#d4edda';
        }
    });
});
</script>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php'; 
?>

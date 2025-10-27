<?php
/**
 * Edit Role
 * Modify an existing role's details
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

// Check if user has permission to edit roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'edit');

$errors = [];
$not_found = false;
$role = null;

// Fetch role details
if ($role_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM roles WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $role = mysqli_fetch_assoc($result);
    } else {
        $not_found = true;
    }
    mysqli_stmt_close($stmt);
} else {
    $not_found = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role) {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Role name is required';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Role name must be 100 characters or less';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (!in_array($status, ['Active', 'Inactive'])) {
        $errors[] = 'Invalid status';
    }
    
    // Check if role name already exists (excluding current role)
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE name = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $name, $role_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_fetch_assoc($result)) {
            $errors[] = 'A role with this name already exists';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Update the role
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "
            UPDATE roles 
            SET name = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        mysqli_stmt_bind_param($stmt, 'sssi', $name, $description, $status, $role_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Log the audit
            log_permission_audit($conn, $user_id, 'UPDATE', 'role', $role_id, [
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'old_name' => $role['name'],
                'old_description' => $role['description'],
                'old_status' => $role['status']
            ]);
            
            $_SESSION['success_message'] = "Role '$name' updated successfully!";
            mysqli_stmt_close($stmt);
            closeConnection($conn);
            
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
    
    // Update role array with submitted values for form repopulation
    if (!empty($errors)) {
        $role['name'] = $name;
        $role['description'] = $description;
        $role['status'] = $status;
    }
}

$page_title = $role ? 'Edit Role: ' . $role['name'] . ' - ' . APP_NAME : 'Role Not Found - ' . APP_NAME;
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
                The role you're trying to edit doesn't exist or has been deleted.
            </p>
            <a href="index.php" class="btn btn-primary">← Back to Roles List</a>
        </div>
        
        <?php else: ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>✏️ Edit Role</h1>
                    <p>Modify role details for <strong><?php echo htmlspecialchars($role['name']); ?></strong></p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="view.php?id=<?php echo $role_id; ?>" class="btn btn-secondary">👁️ View Details</a>
                    <a href="index.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </div>
        </div>

        <!-- System Role Warning -->
        <?php if ($role['is_system_role']): ?>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:24px;border-radius:6px;">
            <h3 style="margin:0 0 8px 0;font-size:16px;color:#856404;">⚠️ System Role</h3>
            <p style="margin:0;color:#856404;">
                This is a system role and should be modified with caution. 
                System roles are protected and cannot be deleted.
            </p>
        </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin-bottom: 24px;">
            <strong>❌ Please fix the following errors:</strong>
            <ul style="margin: 8px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Edit Role Form -->
        <div class="card">
            <form method="POST" action="" id="editRoleForm">
                <div style="display: grid; gap: 24px;">
                    
                    <!-- Role Name -->
                    <div class="form-group">
                        <label for="name" class="form-label required">Role Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-input" 
                            required 
                            maxlength="100"
                            placeholder="e.g., Project Manager, Sales Lead, etc."
                            value="<?php echo htmlspecialchars($role['name']); ?>"
                        >
                        <small class="form-help">A unique, descriptive name for this role (max 100 characters)</small>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description" class="form-label required">Description</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="form-input" 
                            required 
                            rows="4"
                            placeholder="Describe the responsibilities and scope of this role..."
                        ><?php echo htmlspecialchars($role['description']); ?></textarea>
                        <small class="form-help">Provide a clear description of what this role is for and who should have it</small>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status" class="form-label required">Status</label>
                        <select id="status" name="status" class="form-input" required>
                            <option value="Active" <?php echo $role['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $role['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small class="form-help">
                            Only active roles can be assigned to users. 
                            <?php if ($role['status'] === 'Active'): ?>
                                <span style="color:#dc3545;">⚠️ Setting to Inactive will not remove existing user assignments.</span>
                            <?php endif; ?>
                        </small>
                    </div>

                    <!-- Role Metadata -->
                    <div style="background:#f8f9fa;border-radius:6px;padding:16px;">
                        <h4 style="margin:0 0 12px 0;font-size:14px;color:#6c757d;">📊 Role Metadata</h4>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:13px;">
                            <div>
                                <strong>Created:</strong> 
                                <?php echo date('M d, Y h:i A', strtotime($role['created_at'])); ?>
                            </div>
                            <?php if ($role['updated_at']): ?>
                            <div>
                                <strong>Last Updated:</strong> 
                                <?php echo date('M d, Y h:i A', strtotime($role['updated_at'])); ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <strong>Type:</strong> 
                                <?php echo $role['is_system_role'] ? '🔒 System Role' : 'Custom Role'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Information Card -->
                    <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:16px;border-radius:6px;">
                        <h4 style="margin:0 0 8px 0;color:#0066cc;">ℹ️ After Saving</h4>
                        <ul style="margin:8px 0 0 20px;color:#004080;line-height:1.6;font-size:14px;">
                            <li>Changes will apply immediately to all users with this role</li>
                            <li>To modify permissions, use the <strong>Manage Permissions</strong> page</li>
                            <li>To manage user assignments, use the <strong>Assign Roles</strong> page</li>
                            <li>An audit log entry will be created for this change</li>
                        </ul>
                    </div>

                    <!-- Form Actions -->
                    <div style="display: flex; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <button type="submit" class="btn btn-primary">
                            ✓ Save Changes
                        </button>
                        <a href="view.php?id=<?php echo $role_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            Back to List
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Danger Zone (for non-system roles) -->
        <?php if (!$role['is_system_role'] && has_permission($conn, $user_id, 'settings/roles', 'delete')): ?>
        <div class="card" style="margin-top: 24px; border-left: 4px solid #dc3545;">
            <h3 style="margin: 0 0 12px 0; color: #dc3545;">⚠️ Danger Zone</h3>
            <p style="color: #6c757d; margin-bottom: 16px;">
                Deleting this role will remove all user assignments. This action cannot be undone.
            </p>
            <form method="POST" action="delete.php" style="display: inline;" 
                  onsubmit="return confirm('⚠️ WARNING: Are you absolutely sure you want to delete this role?\n\nThis will:\n- Remove this role from all users\n- Cannot be undone\n\nType DELETE to confirm.');">
                <input type="hidden" name="role_id" value="<?php echo $role_id; ?>">
                <button type="submit" class="btn btn-danger" style="background:#dc3545;">
                    🗑️ Delete This Role
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<style>
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label {
    font-weight: 600;
    color: #1b2a57;
    font-size: 14px;
}

.form-label.required::after {
    content: ' *';
    color: #dc3545;
}

.form-input {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #003581;
    box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
}

.form-help {
    color: #6c757d;
    font-size: 13px;
    font-style: italic;
}

textarea.form-input {
    resize: vertical;
    font-family: inherit;
}

select.form-input {
    cursor: pointer;
    background: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-danger:hover {
    background: #c82333;
}
</style>

<script>
// Form validation
document.getElementById('editRoleForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const description = document.getElementById('description').value.trim();
    
    if (name === '') {
        e.preventDefault();
        alert('Please enter a role name');
        document.getElementById('name').focus();
        return false;
    }
    
    if (description === '') {
        e.preventDefault();
        alert('Please enter a description');
        document.getElementById('description').focus();
        return false;
    }
    
    // Confirm submission
    if (!confirm('Save changes to this role?\n\nName: ' + name + '\n\nChanges will apply immediately to all users with this role.')) {
        e.preventDefault();
        return false;
    }
});

// Auto-focus on first field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('name').focus();
});
</script>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php'; 
?>

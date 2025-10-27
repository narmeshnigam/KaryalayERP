<?php
/**
 * Add New Role
 * Create a new role for access control
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
$conn = createConnection(true);

// Check if tables exist
if (!roles_tables_exist($conn)) {
    header('Location: onboarding.php');
    exit;
}

// Check if user has permission to create roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'create');

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
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
    
    // Check if role name already exists
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_fetch_assoc($result)) {
            $errors[] = 'A role with this name already exists';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Insert the role
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO roles (name, description, is_system_role, status, created_by, created_at) 
            VALUES (?, ?, 0, ?, ?, NOW())
        ");
        
        mysqli_stmt_bind_param($stmt, 'sssi', $name, $description, $status, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_role_id = mysqli_insert_id($conn);
            
            // Log the audit
            log_permission_audit($conn, $user_id, 'CREATE', 'role', $new_role_id, [
                'name' => $name,
                'description' => $description,
                'status' => $status
            ]);
            
            $_SESSION['success_message'] = "Role '$name' created successfully!";
            mysqli_stmt_close($stmt);
            closeConnection($conn);
            
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

$page_title = 'Add New Role - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>➕ Add New Role</h1>
                    <p>Create a new role for access control</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        ← Back to Roles
                    </a>
                </div>
            </div>
        </div>

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

        <!-- Add Role Form -->
        <div class="card">
            <form method="POST" action="" id="addRoleForm">
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
                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
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
                        ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small class="form-help">Provide a clear description of what this role is for and who should have it</small>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status" class="form-label required">Status</label>
                        <select id="status" name="status" class="form-input" required>
                            <option value="Active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small class="form-help">Only active roles can be assigned to users</small>
                    </div>

                    <!-- Information Card -->
                    <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:16px;border-radius:6px;">
                        <h4 style="margin:0 0 8px 0;color:#0066cc;">ℹ️ What happens next?</h4>
                        <ul style="margin:8px 0 0 20px;color:#004080;line-height:1.6;">
                            <li>The role will be created with no permissions assigned</li>
                            <li>You can assign permissions to this role from the <strong>Manage Permissions</strong> page</li>
                            <li>Then assign this role to users from the <strong>Assign Roles</strong> page</li>
                            <li>This role will not be a system role (can be edited or deleted)</li>
                        </ul>
                    </div>

                    <!-- Form Actions -->
                    <div style="display: flex; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <button type="submit" class="btn btn-primary">
                            ✓ Create Role
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Help Card -->
        <div class="card" style="margin-top: 24px; border-left: 4px solid #ffc107;">
            <h3 style="margin: 0 0 12px 0; color: #856404;">💡 Tips for Creating Roles</h3>
            <ul style="margin: 0; padding-left: 20px; color: #6c757d; line-height: 1.8;">
                <li><strong>Be Specific:</strong> Use clear, descriptive names like "Sales Manager" instead of just "Manager"</li>
                <li><strong>Document Purpose:</strong> Write detailed descriptions so others understand the role's scope</li>
                <li><strong>Follow Naming Convention:</strong> Use consistent naming patterns (e.g., "Department + Level")</li>
                <li><strong>Plan Permissions:</strong> Think about what pages and actions this role should access</li>
                <li><strong>Start Restrictive:</strong> It's easier to add permissions later than remove them</li>
            </ul>
        </div>
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
</style>

<script>
// Form validation
document.getElementById('addRoleForm').addEventListener('submit', function(e) {
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
    if (!confirm('Create this role?\n\nName: ' + name + '\n\nYou can assign permissions to it after creation.')) {
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

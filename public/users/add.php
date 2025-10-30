<?php
/**
 * Users Management - Add New User
 * Create new user account with role assignment and entity linking
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'users', 'create');

// Check if tables exist
if (!users_tables_exist($conn)) {
    die("Users module tables are not set up properly. Please run the setup scripts.");
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'entity_id' => !empty($_POST['entity_id']) ? (int)$_POST['entity_id'] : null,
        'entity_type' => $_POST['entity_type'] ?? null,
        'username' => trim($_POST['username']),
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'password' => $_POST['password'],
        'password_confirm' => $_POST['password_confirm'],
        'role_id' => (int)$_POST['role_id'],
        'status' => $_POST['status'] ?? 'Active',
        'created_by' => $CURRENT_USER_ID
    ];
    
    // Validate data
    $errors = validate_user_data($data, false);
    
    // Check password confirmation
    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if username exists
    if (username_exists($conn, $data['username'])) {
        $errors[] = "Username already exists";
    }
    
    // Check if email exists
    if (!empty($data['email']) && email_exists($conn, $data['email'])) {
        $errors[] = "Email already exists";
    }
    
    // If no errors, create user
    if (empty($errors)) {
        // Hash password
        $data['password_hash'] = hash_password($data['password']);
        
        $new_user_id = create_user($conn, $data);
        
        if ($new_user_id) {
            $success_message = "User created successfully!";
            $_SESSION['flash_success'] = $success_message;
            header('Location: view.php?id=' . $new_user_id);
            exit;
        } else {
            $errors[] = "Failed to create user. Please try again.";
        }
    }
}

// Get available employees
$available_employees = get_available_employees($conn);

// Get all active roles
$roles = get_active_roles($conn);

$page_title = 'Add New User - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">➕ Add New User</h1>
                    <p style="color: #6c757d; margin: 0;">Create a new user account and assign access permissions</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">← Back to Users List</a>
                </div>
            </div>
        </div>

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

        <!-- Add User Form -->
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
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
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
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
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
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
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
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                            required
                        >
                        <small style="color: #6c757d;">Contact number</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    🔐 Security & Access
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Password -->
                    <div>
                        <label for="password" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Password <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required
                        >
                        <small style="color: #6c757d;">Minimum 6 characters</small>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirm" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Confirm Password <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            class="form-control" 
                            required
                        >
                        <small style="color: #6c757d;">Re-enter password</small>
                    </div>
                    
                    <!-- Role -->
                    <div>
                        <label for="role_id" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Role <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="role_id" name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo (($_POST['role_id'] ?? 0) == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                    <?php if ($role['description']): ?>
                                        - <?php echo htmlspecialchars($role['description']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6c757d;">Defines user permissions</small>
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Status <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Suspended" <?php echo (($_POST['status'] ?? '') === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <small style="color: #6c757d;">Account access status</small>
                    </div>
                    
                    <!-- Created By (Info Display) -->
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Created By
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Current User'); ?></strong>
                        </div>
                        <small style="color: #6c757d;">User will be created by you</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    🔗 Entity Linking (Optional)
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Entity Type -->
                    <div>
                        <label for="entity_type" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Entity Type
                        </label>
                        <select id="entity_type" name="entity_type" class="form-control" onchange="handleEntityTypeChange(this.value)">
                            <option value="">None (Standalone User)</option>
                            <option value="Employee" <?php echo (($_POST['entity_type'] ?? '') === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                            <option value="Client" <?php echo (($_POST['entity_type'] ?? '') === 'Client') ? 'selected' : ''; ?>>Client</option>
                            <option value="Other" <?php echo (($_POST['entity_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <small style="color: #6c757d;">Link user to existing record</small>
                    </div>
                    
                    <!-- Entity Selection -->
                    <div id="employee_selection" style="display: none;">
                        <label for="entity_id" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Link to Employee
                        </label>
                        <select id="entity_id" name="entity_id" class="form-control">
                            <option value="">Select Employee</option>
                            <?php foreach ($available_employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    data-email="<?php echo htmlspecialchars($emp['email'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($emp['phone'] ?? ''); ?>"
                                    <?php echo (($_POST['entity_id'] ?? 0) == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    <?php if ($emp['department']): ?>
                                        (<?php echo htmlspecialchars($emp['department']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6c757d;">Auto-fill from employee record</small>
                    </div>
                </div>
                
                <div style="margin-top: 16px; padding: 12px; background: #e3f2fd; border-radius: 8px; color: #1565c0;">
                    <strong>ℹ️ Entity Linking:</strong> Linking a user to an employee or client record allows data synchronization and contextual access control.
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">✓ Create User</button>
            </div>
        </form>

    </div>
</div>

<script>
// Handle entity type change
function handleEntityTypeChange(type) {
    const employeeSelection = document.getElementById('employee_selection');
    const entityIdField = document.getElementById('entity_id');
    
    if (type === 'Employee') {
        employeeSelection.style.display = 'block';
    } else {
        employeeSelection.style.display = 'none';
        entityIdField.value = '';
    }
}

// Auto-fill email and phone from selected employee
document.getElementById('entity_id')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const emailField = document.getElementById('email');
    const phoneField = document.getElementById('phone');
    
    if (selectedOption.value) {
        const email = selectedOption.getAttribute('data-email');
        const phone = selectedOption.getAttribute('data-phone');
        
        if (email && !emailField.value) {
            emailField.value = email;
        }
        if (phone && !phoneField.value) {
            phoneField.value = phone;
        }
    }
});

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
    const entityTypeField = document.getElementById('entity_type');
    if (entityTypeField.value === 'Employee') {
        handleEntityTypeChange('Employee');
    }
});
</script>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

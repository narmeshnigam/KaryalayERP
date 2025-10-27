<?php
/**
 * Roles Management - List All Roles
 * View, create, edit, and delete roles
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

// Check if tables exist, redirect to onboarding if not
if (!roles_tables_exist($conn)) {
    header('Location: onboarding.php');
    exit;
}

// Check if user has permission to manage roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'view');

// Check if current user has any roles assigned
$current_user_roles = get_user_roles($conn, $user_id);
$show_setup_notice = empty($current_user_roles);

// Handle role deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role_id'])) {
    $role_id = (int)$_POST['delete_role_id'];
    
    // Check if it's a system role
    $stmt = mysqli_prepare($conn, "SELECT is_system_role, name FROM roles WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $role = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($role && !$role['is_system_role']) {
        $stmt = mysqli_prepare($conn, "DELETE FROM roles WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $role_id);
        
        if (mysqli_stmt_execute($stmt)) {
            log_permission_audit($conn, $user_id, 'DELETE', 'role', $role_id, ['name' => $role['name']]);
            $success_message = "Role deleted successfully!";
        } else {
            $error_message = "Error deleting role: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "System roles cannot be deleted.";
    }
}

// Fetch all roles
$roles = [];
$result = mysqli_query($conn, "
    SELECT r.*, 
           COUNT(DISTINCT ur.user_id) as user_count,
           COUNT(DISTINCT rp.permission_id) as permission_count
    FROM roles r
    LEFT JOIN user_roles ur ON r.id = ur.role_id
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    GROUP BY r.id
    ORDER BY r.is_system_role DESC, r.name ASC
");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row;
    }
    mysqli_free_result($result);
}

$page_title = 'Manage Roles - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Setup Notice for Users with No Roles -->
        <?php if ($show_setup_notice): ?>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:24px;border-radius:6px;">
            <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">⚠️ Initial Setup Required</h3>
            <p style="margin:0 0 12px 0;color:#856404;">
                You currently have no roles assigned. To fully utilize the Roles & Permissions system, 
                you should assign yourself a role (e.g., Super Admin) to manage permissions properly.
            </p>
            <a href="#assign-role-section" onclick="document.getElementById('assign-role-section').scrollIntoView({behavior:'smooth'});" 
               class="btn btn-warning" style="display:inline-block;padding:8px 16px;background:#ffc107;color:#000;text-decoration:none;border-radius:4px;">
                👤 Assign Role to Yourself
            </a>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>🔐 Manage Roles</h1>
                    <p>Create and configure user roles for access control</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="../permissions/" class="btn btn-secondary">🔑 Manage Permissions</a>
                    <a href="../assign-roles/" class="btn btn-secondary">👥 Assign Roles</a>
                    <?php if (has_permission($conn, $user_id, 'settings/roles', 'create')): ?>
                    <a href="add.php" class="btn btn-primary">➕ Add New Role</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <strong>✅ Success:</strong> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <strong>✅ Success:</strong> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <strong>❌ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <strong>❌ Error:</strong> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert" style="background:#d1ecf1;border-left:4px solid #17a2b8;color:#0c5460;">
            <strong>ℹ️ Info:</strong> <?php echo htmlspecialchars($_SESSION['info_message']); ?>
        </div>
        <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <!-- Roles Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Users</th>
                            <th>Permissions</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d;">
                                <div style="font-size: 48px; margin-bottom: 16px;">🔐</div>
                                <p>No roles found. Create your first role to get started.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                    <?php if ($role['is_system_role']): ?>
                                    <span class="badge" style="background: #17a2b8; color: white; margin-left: 8px;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($role['description'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($role['is_system_role']): ?>
                                    <span style="color: #17a2b8;">🔒 System Role</span>
                                    <?php else: ?>
                                    <span style="color: #28a745;">✏️ Custom Role</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: #007bff;">
                                        <?php echo (int)$role['user_count']; ?> user<?php echo $role['user_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #6c757d;">
                                        <?php echo (int)$role['permission_count']; ?> permission<?php echo $role['permission_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($role['status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($role['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="view.php?id=<?php echo $role['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                            👁️ View
                                        </a>
                                        <?php if (has_permission($conn, $user_id, 'settings/roles', 'edit')): ?>
                                        <a href="edit.php?id=<?php echo $role['id']; ?>" class="btn btn-sm btn-primary" title="Edit Role">
                                            ✏️ Edit
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (has_permission($conn, $user_id, 'settings/roles', 'delete') && !$role['is_system_role']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this role? All user assignments will be removed.');">
                                            <input type="hidden" name="delete_role_id" value="<?php echo $role['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Role">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Information Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 24px;">
            <div class="card" style="border-left: 4px solid #007bff;">
                <h3 style="color: #007bff; margin-bottom: 12px;">ℹ️ About Roles</h3>
                <p style="color: #6c757d; line-height: 1.6;">
                    Roles define sets of permissions that can be assigned to users. Each role controls what pages and actions users can access throughout the system.
                </p>
            </div>
            
            <div class="card" style="border-left: 4px solid #28a745;">
                <h3 style="color: #28a745; margin-bottom: 12px;">🔒 System Roles</h3>
                <p style="color: #6c757d; line-height: 1.6;">
                    System roles (marked with 🔒) are protected and cannot be deleted. They can be modified but should be handled with care.
                </p>
            </div>
            
            <div class="card" style="border-left: 4px solid #ffc107;">
                <h3 style="color: #ffc107; margin-bottom: 12px;">⚠️ Best Practices</h3>
                <p style="color: #6c757d; line-height: 1.6;">
                    Always test new roles with a test account before assigning to real users. Use descriptive names and clear descriptions.
                </p>
            </div>
        </div>

        <!-- Quick Role Assignment Section -->
        <?php if ($show_setup_notice): ?>
        <div class="card" id="assign-role-section" style="margin-top:24px;border:2px solid #ffc107;background:#fffbf0;">
            <h3 style="color:#856404;margin-bottom:16px;">👤 Assign Role to Yourself</h3>
            <p style="color:#856404;margin-bottom:16px;">
                Select a role to assign to your account. We recommend starting with <strong>Super Admin</strong> to have full access.
            </p>
            <form method="POST" action="quick_assign.php" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:250px;">
                    <label style="display:block;margin-bottom:6px;font-weight:600;color:#856404;">Select Role:</label>
                    <select name="role_id" required class="form-input" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <option value="">-- Choose a role --</option>
                        <?php foreach ($roles as $role): ?>
                            <?php if ($role['status'] === 'Active'): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $role['name'] === 'Super Admin' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                                <?php if ($role['is_system_role']): ?>🔒<?php endif; ?>
                                - <?php echo htmlspecialchars($role['description']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">
                    ✓ Assign Role to My Account
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

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
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>

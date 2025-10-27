<?php
/**
 * Assign Roles to Users
 * Manage user-role assignments
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

$user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Check if tables exist
if (!roles_tables_exist($conn)) {
    header('Location: ../roles/onboarding.php');
    exit;
}

// Check if user has permission to assign roles
// TEMPORARILY DISABLED - Rebuilding permission system
// require_permission($conn, $user_id, 'settings/roles', 'view');

// Get all users with their current roles
$users_with_roles = [];
$stmt = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.email,
           GROUP_CONCAT(r.name SEPARATOR ', ') as role_names,
           COUNT(ur.id) as role_count,
           GROUP_CONCAT(r.id SEPARATOR ',') as role_ids
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id AND r.status = 'Active'
    GROUP BY u.id, u.username, u.email
    ORDER BY u.username ASC
");

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $users_with_roles[] = $row;
}
mysqli_stmt_close($stmt);

// Get all active roles for assignment
$all_roles = [];
$stmt = mysqli_prepare($conn, "
    SELECT id, name, description, status
    FROM roles
    WHERE status = 'Active'
    ORDER BY name ASC
");

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $all_roles[] = $row;
}
mysqli_stmt_close($stmt);

$page_title = 'Assign Roles to Users - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>👥 Assign Roles to Users</h1>
                    <p>Manage user role assignments and permissions</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="../roles/index.php" class="btn btn-secondary">🔐 Manage Roles</a>
                    <a href="../../index.php" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div style="background:#d4edda;border-left:4px solid #28a745;padding:16px;margin-bottom:24px;border-radius:6px;color:#155724;">
            <strong>✓ Success:</strong> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div style="background:#f8d7da;border-left:4px solid #dc3545;padding:16px;margin-bottom:24px;border-radius:6px;color:#721c24;">
            <strong>✗ Error:</strong> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Information Card -->
        <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:20px;margin-bottom:24px;border-radius:6px;">
            <h3 style="margin:0 0 8px 0;color:#0066cc;">ℹ️ How Role Assignment Works</h3>
            <ul style="margin:8px 0 0 20px;color:#004080;line-height:1.6;">
                <li>Users can have <strong>multiple roles</strong> assigned at the same time</li>
                <li>Permissions are <strong>cumulative</strong> - if any role grants a permission, the user has it</li>
                <li>Click on a user's row to manage their role assignments</li>
                <li>Only <strong>active roles</strong> can be assigned to users</li>
                <li>Role changes take effect <strong>immediately</strong></li>
            </ul>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h3 style="margin:0 0 20px 0;color:#1b2a57;border-bottom:2px solid #e5e7eb;padding-bottom:12px;">
                📋 Users & Their Roles
            </h3>

            <?php if (empty($users_with_roles)): ?>
            <div style="text-align:center;padding:48px;color:#6c757d;">
                <div style="font-size:48px;margin-bottom:12px;">👤</div>
                <p>No users found in the system.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Current Roles</th>
                            <th>Role Count</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_with_roles as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                            <td>
                                <?php if ($user['role_count'] > 0): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        <?php foreach (explode(', ', $user['role_names']) as $role_name): ?>
                                        <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;">
                                            <?php echo htmlspecialchars($role_name); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#6c757d;font-style:italic;">No roles assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($user['role_count'] > 0): ?>
                                    <strong style="color:#28a745;"><?php echo $user['role_count']; ?></strong>
                                <?php else: ?>
                                    <strong style="color:#dc3545;">0</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                    ✏️ Manage Roles
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Card -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-top:24px;">
            <div class="card" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                <div style="font-size:32px;font-weight:bold;"><?php echo count($users_with_roles); ?></div>
                <div style="opacity:0.9;">Total Users</div>
            </div>

            <div class="card" style="background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);color:white;">
                <div style="font-size:32px;font-weight:bold;">
                    <?php echo array_sum(array_map(function($u) { return $u['role_count']; }, $users_with_roles)); ?>
                </div>
                <div style="opacity:0.9;">Total Assignments</div>
            </div>

            <div class="card" style="background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);color:white;">
                <div style="font-size:32px;font-weight:bold;"><?php echo count($all_roles); ?></div>
                <div style="opacity:0.9;">Active Roles</div>
            </div>
        </div>

        <!-- Help Card -->
        <div class="card" style="margin-top:24px;border-left:4px solid #ffc107;">
            <h3 style="margin:0 0 12px 0;color:#856404;">💡 Best Practices</h3>
            <ul style="margin:0;padding-left:20px;color:#6c757d;line-height:1.8;">
                <li><strong>Principle of Least Privilege:</strong> Assign only the roles necessary for the user's job</li>
                <li><strong>Multiple Roles:</strong> Users can have multiple roles - permissions are cumulative</li>
                <li><strong>Regular Reviews:</strong> Periodically review user assignments to ensure they're still appropriate</li>
                <li><strong>Documentation:</strong> Keep records of why each role was assigned for audit purposes</li>
                <li><strong>Testing:</strong> Test new role assignments with the user before finalizing</li>
            </ul>
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

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php'; 
?>

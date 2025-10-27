<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../roles/helpers.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$conn = createConnection(true);

// Skip roles table check for now - we're rebuilding the system
// if (!roles_tables_exist($conn)) { header('Location: ../roles/onboarding.php'); exit; }

// Auto-scan and sync pages FIRST
$public_path = __DIR__ . '/../../';
$discovered_pages = scan_public_pages($public_path);
$sync_stats = sync_permissions_table($conn, $discovered_pages);

// Check permission (after pages are synced)
// TEMPORARILY DISABLED - Rebuilding permission system
// Allow access if permissions table is empty (initial setup)
// $perm_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM permissions");
// $perm_row = mysqli_fetch_assoc($perm_count);
// if ($perm_row['cnt'] > 0) {
//     // Permissions exist, do the check
//     require_permission($conn, $user_id, 'settings/roles/index.php', 'view');
// }

$permissions = get_permissions_grouped($conn, false);

$stmt = mysqli_prepare($conn, "SELECT id, name, is_system_role FROM roles WHERE status = 'Active' ORDER BY CASE WHEN is_system_role = 1 THEN 0 ELSE 1 END, name");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$roles = [];
while ($row = mysqli_fetch_assoc($result)) { $roles[] = $row; }
mysqli_stmt_close($stmt);

$rp_result = mysqli_query($conn, "SELECT role_id, permission_id, can_create, can_view_all, can_view_assigned, can_view_own, can_edit_all, can_edit_assigned, can_edit_own, can_delete_all, can_delete_assigned, can_delete_own, can_export FROM role_permissions");
$role_permissions = [];
while ($row = mysqli_fetch_assoc($rp_result)) {
    $role_permissions[$row['role_id'] . '_' . $row['permission_id']] = $row;
}

$total_pages = 0; $total_modules = count($permissions); $total_submodules = 0;
foreach ($permissions as $module_data) {
    $total_pages += count($module_data['pages']);
    $total_submodules += count($module_data['submodules']);
    foreach ($module_data['submodules'] as $sp) { $total_pages += count($sp); }
}

$page_title = 'Permissions Management - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🔐 Permissions Management</h1>
                    <p>Real-time permission matrix with automatic page detection</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="../roles/index.php" class="btn btn-secondary">← Back to Roles</a>
                </div>
            </div>
        </div>

        <!-- Sync Status -->
        <?php if ($sync_stats['new'] > 0 || $sync_stats['reactivated'] > 0 || $sync_stats['deactivated'] > 0): ?>
        <div style="background:#d4edda;border-left:4px solid #28a745;padding:16px;margin-bottom:24px;border-radius:6px;">
            <strong>🔄 Auto-Sync Completed:</strong>
            <?php if ($sync_stats['new'] > 0): ?>
                <span style="margin-left:12px;">✨ <?php echo $sync_stats['new']; ?> new page(s) added</span>
            <?php endif; ?>
            <?php if ($sync_stats['reactivated'] > 0): ?>
                <span style="margin-left:12px;">✅ <?php echo $sync_stats['reactivated']; ?> page(s) reactivated</span>
            <?php endif; ?>
            <?php if ($sync_stats['deactivated'] > 0): ?>
                <span style="margin-left:12px;">⚠️ <?php echo $sync_stats['deactivated']; ?> page(s) deactivated</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">📄</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">TOTAL PAGES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_pages; ?></div>
            </div>
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">📦</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">MODULES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_modules; ?></div>
            </div>
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">📁</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">SUBMODULES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_submodules; ?></div>
            </div>
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">👥</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">ACTIVE ROLES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo count($roles); ?></div>
            </div>
        </div>

        <!-- Permission Type Legend -->
        <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:16px;border-radius:6px;margin-bottom:24px;">
            <h4 style="margin:0 0 12px 0;color:#0066cc;">🎯 Granular Permission Types</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:13px;">
                <div><strong>➕ Create:</strong> Create new records</div>
                <div><strong>👁️ View All:</strong> View all records</div>
                <div><strong>👤 View Assigned:</strong> View assigned records</div>
                <div><strong>✍️ View Own:</strong> View own records</div>
                <div><strong>✏️ Edit All:</strong> Edit all records</div>
                <div><strong>📝 Edit Assigned:</strong> Edit assigned records</div>
                <div><strong>🖊️ Edit Own:</strong> Edit own records</div>
                <div><strong>🗑️ Delete All:</strong> Delete all records</div>
                <div><strong>❌ Delete Assigned:</strong> Delete assigned records</div>
                <div><strong>🚮 Delete Own:</strong> Delete own records</div>
                <div><strong>📊 Export:</strong> Export data</div>
            </div>
        </div>

        <?php if (empty($roles)): ?>
        <!-- No Roles Warning -->
        <div class="card">
            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;border-radius:6px;color:#856404;">
                <strong>⚠️ No Roles Available</strong>
                <p>Please create at least one active role before managing permissions.</p>
                <a href="../roles/add.php" class="btn btn-primary">Create Role</a>
            </div>
        </div>
        
        <?php elseif (empty($permissions)): ?>
        <!-- No Pages Warning -->
        <div class="card">
            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;border-radius:6px;color:#856404;">
                <strong>⚠️ No Pages Found</strong>
                <p>No pages were detected. Please check your /public folder structure.</p>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- Permissions Matrix -->
        <div class="card">
            <h3 style="margin:0 0 20px 0;color:#1b2a57;border-bottom:2px solid #e5e7eb;padding-bottom:12px;">
                📊 Interactive Permissions Matrix
            </h3>

            <div style="overflow-x:auto;">
                <table id="permissionsMatrix" style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead style="position:sticky;top:0;background:white;z-index:10;">
                        <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                            <th rowspan="2" style="text-align:left;padding:12px;font-weight:600;color:#1b2a57;min-width:250px;position:sticky;left:0;background:#f8f9fa;z-index:11;">
                                Module · Page
                            </th>
                            <?php foreach ($roles as $role): ?>
                                <th colspan="11" style="text-align:center;padding:8px;font-weight:600;color:#1b2a57;border-left:2px solid #003581;">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                    <?php if ($role['is_system_role']): ?>
                                        <span style="font-size:10px;">🔒</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background:#f8f9fa;border-bottom:1px solid #dee2e6;">
                            <?php foreach ($roles as $role): ?>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;border-left:2px solid #003581;" title="Create">C</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="View All">VA</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="View Assigned">VAs</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="View Own">VO</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Edit All">EA</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Edit Assigned">EAs</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Edit Own">EO</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Delete All">DA</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Delete Assigned">DAs</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Delete Own">DO</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Export">Ex</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_num = 0;
                        foreach ($permissions as $module => $module_data):
                        ?>
                            <!-- Module Header -->
                            <tr style="background:#e3f2fd;">
                                <td colspan="<?php echo 1 + (count($roles) * 11); ?>" style="padding:10px 12px;font-weight:700;color:#0066cc;font-size:14px;position:sticky;left:0;background:#e3f2fd;z-index:11;">
                                    📦 <?php echo htmlspecialchars($module); ?>
                                </td>
                            </tr>
                            
                            <!-- Module Pages (no submodule) -->
                            <?php foreach ($module_data['pages'] as $page):
                                $row_num++;
                                $row_color = ($row_num % 2 === 0) ? '#fafafa' : 'white';
                            ?>
                            <tr style="background:<?php echo $row_color; ?>;border-bottom:1px solid #eee;">
                                <td style="padding:10px 12px;position:sticky;left:0;background:<?php echo $row_color; ?>;z-index:9;">
                                    <div style="font-weight:600;color:#1b2a57;margin-bottom:2px;"><?php echo htmlspecialchars($page['page_name']); ?></div>
                                    <div style="font-size:10px;color:#6c757d;"><?php echo htmlspecialchars($page['page_path']); ?></div>
                                </td>
                                <?php foreach ($roles as $role):
                                    $key = $role['id'] . '_' . $page['id'];
                                    $perms = $role_permissions[$key] ?? null;
                                    
                                    $permission_types = [
                                        'can_create',
                                        'can_view_all', 'can_view_assigned', 'can_view_own',
                                        'can_edit_all', 'can_edit_assigned', 'can_edit_own',
                                        'can_delete_all', 'can_delete_assigned', 'can_delete_own',
                                        'can_export'
                                    ];
                                    
                                    foreach ($permission_types as $ptype):
                                        $is_checked = $perms && $perms[$ptype] == 1;
                                        $first_col = ($ptype === 'can_create');
                                ?>
                                    <td style="text-align:center;padding:4px;<?php echo $first_col ? 'border-left:2px solid #003581;' : ''; ?>">
                                        <input type="checkbox" 
                                               class="perm-checkbox"
                                               data-role="<?php echo $role['id']; ?>"
                                               data-permission="<?php echo $page['id']; ?>"
                                               data-type="<?php echo $ptype; ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>
                                               style="cursor:pointer;width:14px;height:14px;">
                                    </td>
                                <?php endforeach; endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Submodules -->
                            <?php foreach ($module_data['submodules'] as $submodule => $submodule_pages): ?>
                                <tr style="background:#fff3cd;">
                                    <td colspan="<?php echo 1 + (count($roles) * 11); ?>" style="padding:8px 12px 8px 24px;font-weight:600;color:#856404;font-size:13px;position:sticky;left:0;background:#fff3cd;z-index:11;">
                                        📁 <?php echo htmlspecialchars($submodule); ?> <span style="font-weight:normal;font-size:11px;">(Submodule)</span>
                                    </td>
                                </tr>
                                
                                <?php foreach ($submodule_pages as $page):
                                    $row_num++;
                                    $row_color = ($row_num % 2 === 0) ? '#fafafa' : 'white';
                                ?>
                                <tr style="background:<?php echo $row_color; ?>;border-bottom:1px solid #eee;">
                                    <td style="padding:10px 12px 10px 36px;position:sticky;left:0;background:<?php echo $row_color; ?>;z-index:9;">
                                        <div style="font-weight:600;color:#1b2a57;margin-bottom:2px;"><?php echo htmlspecialchars($page['page_name']); ?></div>
                                        <div style="font-size:10px;color:#6c757d;"><?php echo htmlspecialchars($page['page_path']); ?></div>
                                    </td>
                                    <?php foreach ($roles as $role):
                                        $key = $role['id'] . '_' . $page['id'];
                                        $perms = $role_permissions[$key] ?? null;
                                        
                                        $permission_types = [
                                            'can_create',
                                            'can_view_all', 'can_view_assigned', 'can_view_own',
                                            'can_edit_all', 'can_edit_assigned', 'can_edit_own',
                                            'can_delete_all', 'can_delete_assigned', 'can_delete_own',
                                            'can_export'
                                        ];
                                        
                                        foreach ($permission_types as $ptype):
                                            $is_checked = $perms && $perms[$ptype] == 1;
                                            $first_col = ($ptype === 'can_create');
                                    ?>
                                        <td style="text-align:center;padding:4px;<?php echo $first_col ? 'border-left:2px solid #003581;' : ''; ?>">
                                            <input type="checkbox" 
                                                   class="perm-checkbox"
                                                   data-role="<?php echo $role['id']; ?>"
                                                   data-permission="<?php echo $page['id']; ?>"
                                                   data-type="<?php echo $ptype; ?>"
                                                   <?php echo $is_checked ? 'checked' : ''; ?>
                                                   style="cursor:pointer;width:14px;height:14px;">
                                        </td>
                                    <?php endforeach; endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Information -->
        <div style="background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:6px;margin-top:24px;">
            <h4 style="margin:0 0 8px 0;color:#155724;">ℹ️ How It Works</h4>
            <ul style="margin:8px 0 0 20px;color:#155724;line-height:1.6;font-size:13px;">
                <li><strong>Auto-Discovery:</strong> Pages are automatically detected from /public folder on every page load</li>
                <li><strong>Real-Time Updates:</strong> Click any checkbox to instantly update permissions (no save button needed)</li>
                <li><strong>Granular Control:</strong> Set different permission levels for view, edit, and delete operations</li>
                <li><strong>Module Organization:</strong> Pages are grouped by folder structure (Module → Submodule → Page)</li>
                <li><strong>Sticky Headers:</strong> Column headers stay visible while scrolling</li>
            </ul>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s;
}

.stat-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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

.btn-primary {
    background: #003581;
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

.btn-primary:hover {
    background: #002456;
}

.perm-checkbox {
    transition: all 0.2s;
}

.perm-checkbox:hover {
    transform: scale(1.2);
}

.perm-checkbox.updating {
    opacity: 0.5;
    cursor: wait;
}

#permissionsMatrix thead {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>

<script>
// Real-time permission update handler
document.querySelectorAll('.perm-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const roleId = this.dataset.role;
        const permissionId = this.dataset.permission;
        const permissionType = this.dataset.type;
        const value = this.checked ? 1 : 0;
        
        // Visual feedback
        this.classList.add('updating');
        this.disabled = true;
        
        // Send AJAX request
        fetch('<?php echo APP_URL; ?>/public/api/permissions/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                role_id: roleId,
                permission_id: permissionId,
                permission_type: permissionType,
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - keep the checkbox state
                this.classList.remove('updating');
                this.disabled = false;
                
                // Brief green highlight
                const td = this.parentElement;
                const originalBg = td.style.backgroundColor;
                td.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    td.style.backgroundColor = originalBg;
                }, 500);
            } else {
                // Error - revert checkbox
                this.checked = !this.checked;
                this.classList.remove('updating');
                this.disabled = false;
                alert('Error: ' + (data.error || 'Failed to update permission'));
            }
        })
        .catch(error => {
            // Network error - revert checkbox
            this.checked = !this.checked;
            this.classList.remove('updating');
            this.disabled = false;
            alert('Network error: ' + error.message);
        });
    });
});

// Tooltip for column headers
document.querySelectorAll('th[title]').forEach(th => {
    th.style.cursor = 'help';
});
</script>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>

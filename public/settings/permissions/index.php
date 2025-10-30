<?php
/**
 * Table-Based Permissions Manager
 * Manage permissions by database tables instead of page paths
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "Table Permissions Manager - " . APP_NAME;

$conn = createConnection(true);

// Include table-based helpers
require_once __DIR__ . '/helpers_table_based.php';

// Auto-scan and sync database tables
$discovered_tables = scan_database_tables($conn);
$sync_stats = sync_permissions_with_tables($conn, $discovered_tables);

// Get all active roles
$roles_result = mysqli_query($conn, "SELECT * FROM roles WHERE status = 'Active' ORDER BY name");
$roles = [];
while ($row = mysqli_fetch_assoc($roles_result)) {
    $roles[] = $row;
}
mysqli_free_result($roles_result);

// Get permissions grouped by module
$permissions = get_permissions_grouped($conn);

// Load role permissions for matrix display
$role_permissions = [];
$rp_result = mysqli_query($conn, "SELECT * FROM role_permissions");
while ($row = mysqli_fetch_assoc($rp_result)) {
    $key = $row['role_id'] . '_' . $row['permission_id'];
    $role_permissions[$key] = $row;
}
mysqli_free_result($rp_result);

// Calculate statistics
$total_tables = count($discovered_tables);
$total_modules = count($permissions);
$total_roles = count($roles);

// Include headers
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

closeConnection($conn);
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🗄️ Table-Based Permissions Manager</h1>
                    <p>Control user access by database tables with granular permissions</p>
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
                <span style="margin-left:12px;">✨ <?php echo $sync_stats['new']; ?> new table(s) added</span>
            <?php endif; ?>
            <?php if ($sync_stats['reactivated'] > 0): ?>
                <span style="margin-left:12px;">✅ <?php echo $sync_stats['reactivated']; ?> table(s) reactivated</span>
            <?php endif; ?>
            <?php if ($sync_stats['deactivated'] > 0): ?>
                <span style="margin-left:12px;">⚠️ <?php echo $sync_stats['deactivated']; ?> table(s) deactivated</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">🗄️</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">DATABASE TABLES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_tables; ?></div>
            </div>
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">📦</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">MODULES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_modules; ?></div>
            </div>
            <div class="stat-card">
                <div style="font-size:32px;margin-bottom:8px;">👥</div>
                <div style="color:#6c757d;font-size:12px;font-weight:600;">ACTIVE ROLES</div>
                <div style="font-size:28px;font-weight:700;color:#003581;margin-top:8px;"><?php echo $total_roles; ?></div>
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
        <!-- No Tables Warning -->
        <div class="card">
            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;border-radius:6px;color:#856404;">
                <strong>⚠️ No Database Tables Found</strong>
                <p>No tables were detected in the database. Please check your database structure.</p>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- Permissions Matrix -->
        <div class="card">
            <h3 style="margin:0 0 20px 0;color:#1b2a57;border-bottom:2px solid #e5e7eb;padding-bottom:12px;">
                📊 Interactive Table Permissions Matrix
            </h3>

            <div style="overflow-x:auto;">
                <table id="permissionsMatrix" style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead style="position:sticky;top:0;background:white;z-index:10;">
                        <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                            <th rowspan="2" style="text-align:left;padding:12px;font-weight:600;color:#1b2a57;min-width:250px;position:sticky;left:0;background:#f8f9fa;z-index:11;">
                                Module · Table
                            </th>
                            <?php foreach ($roles as $role): ?>
                                <th colspan="12" style="text-align:center;padding:8px 12px;font-weight:600;color:#1b2a57;border-left:2px solid #003581;min-width:520px;">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                    <?php if ($role['is_system_role']): ?>
                                        <span style="font-size:10px;">🔒</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background:#f8f9fa;border-bottom:1px solid #dee2e6;">
                            <?php foreach ($roles as $role): ?>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;border-left:2px solid #003581;" title="Row: Select/Clear all for this role">ALL</th>
                                <th style="padding:6px 4px;font-size:10px;font-weight:600;color:#6c757d;" title="Create">C</th>
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
                        foreach ($permissions as $module => $tables):
                            $module_id = strtolower(preg_replace('/[^a-z0-9]+/i','-',$module));
                        ?>
                            <!-- Module Header with per-role bulk controls -->
                            <tr class="module-row" style="background:#e3f2fd;" data-module="<?php echo $module_id; ?>">
                                <td style="padding:10px 12px;font-weight:700;color:#0066cc;font-size:14px;position:sticky;left:0;background:#e3f2fd;z-index:11;">
                                    📦 <?php echo htmlspecialchars($module); ?>
                                </td>
                                <?php foreach ($roles as $role): ?>
                                    <td colspan="12" style="padding:6px 8px;border-left:2px solid #003581;background:#e3f2fd;">
                                        <button type="button" class="mini-btn module-select" data-action="select" data-module="<?php echo $module_id; ?>" data-role="<?php echo (int)$role['id']; ?>">✓ All</button>
                                        <button type="button" class="mini-btn module-select" data-action="clear" data-module="<?php echo $module_id; ?>" data-role="<?php echo (int)$role['id']; ?>">✕ None</button>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Module Tables -->
                            <?php foreach ($tables as $table):
                                $row_num++;
                                $row_color = ($row_num % 2 === 0) ? '#fafafa' : 'white';
                            ?>
                            <tr class="table-row" data-module="<?php echo $module_id; ?>" style="background:<?php echo $row_color; ?>;border-bottom:1px solid #eee;">
                                <td style="padding:10px 12px;position:sticky;left:0;background:<?php echo $row_color; ?>;z-index:9;">
                                    <div style="font-weight:600;color:#1b2a57;margin-bottom:2px;"><?php echo htmlspecialchars($table['display_name']); ?></div>
                                    <div style="font-size:10px;color:#6c757d;font-family:monospace;"><?php echo htmlspecialchars($table['table_name']); ?></div>
                                </td>
                                <?php foreach ($roles as $role):
                                    $key = $role['id'] . '_' . $table['id'];
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
                                        if ($first_col): ?>
                                            <td style="text-align:center;padding:4px;border-left:2px solid #003581;">
                                                <button type="button" class="mini-btn row-select" data-action="select" data-role="<?php echo (int)$role['id']; ?>" title="Select all in this row for role">✓</button>
                                                <button type="button" class="mini-btn row-select" data-action="clear" data-role="<?php echo (int)$role['id']; ?>" title="Clear all in this row for role">✕</button>
                                            </td>
                                        <?php endif; ?>
                                    <td style="text-align:center;padding:6px 8px;">
                                        <input type="checkbox" 
                                               class="perm-checkbox"
                                               data-role="<?php echo $role['id']; ?>"
                                               data-permission="<?php echo $table['id']; ?>"
                                               data-type="<?php echo $ptype; ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>
                                               style="cursor:pointer;width:16px;height:16px;">
                                    </td>
                                <?php endforeach; endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Information -->
        <div style="background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:6px;margin-top:24px;">
            <h4 style="margin:0 0 8px 0;color:#155724;">ℹ️ How Table-Based Permissions Work</h4>
            <ul style="margin:8px 0 0 20px;color:#155724;line-height:1.6;font-size:13px;">
                <li><strong>Auto-Discovery:</strong> Tables are automatically detected from your database on every page load</li>
                <li><strong>Real-Time Updates:</strong> Click any checkbox to instantly update permissions (no save button needed)</li>
                <li><strong>Granular Control:</strong> Set different permission levels for view, edit, and delete operations</li>
                <li><strong>Module Organization:</strong> Tables are grouped by their functional modules</li>
                <li><strong>Table-Centric:</strong> Permissions apply to entire database tables, giving you full control over data access</li>
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

/* Small action buttons for bulk selects */
.mini-btn {
    padding: 2px 6px;
    font-size: 11px;
    border: 1px solid #003581;
    background: #f8fbff;
    color: #003581;
    border-radius: 4px;
    cursor: pointer;
    margin: 0 2px;
}

.mini-btn:hover {
    background: #e7f1ff;
}

.mini-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                role_id: roleId,
                permission_id: permissionId,
                permission_type: permissionType,
                value: value
            })
        })
        .then(async (response) => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Unexpected response (${response.status}): ${text.substring(0, 200)}`);
            }
        })
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
            // Network/error - revert checkbox
            this.checked = !this.checked;
            this.classList.remove('updating');
            this.disabled = false;
            alert('Network error: ' + error.message);
            console.error('Permissions update failed:', error);
        });
    });
});

// Tooltip for column headers
document.querySelectorAll('th[title]').forEach(th => {
    th.style.cursor = 'help';
});

// Helper to toggle and dispatch change only when needed
function toggleCheckbox(cb, shouldCheck) {
    if (cb.checked !== shouldCheck) {
        cb.checked = shouldCheck;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

// Row-level bulk select/clear for a specific role within that row
document.querySelectorAll('.row-select').forEach(btn => {
    btn.addEventListener('click', function () {
        const roleId = this.dataset.role;
        const action = this.dataset.action; // 'select' or 'clear'
        const tr = this.closest('tr.table-row');
        if (!tr) return;
        const shouldCheck = action === 'select';
        const boxes = tr.querySelectorAll('input.perm-checkbox[data-role="' + roleId + '"]');
        boxes.forEach(cb => toggleCheckbox(cb, shouldCheck));
    });
});

// Module-level bulk select/clear for a specific role across all rows in module
document.querySelectorAll('.module-select').forEach(btn => {
    btn.addEventListener('click', function () {
        const roleId = this.dataset.role;
        const moduleId = this.dataset.module;
        const action = this.dataset.action; // 'select' or 'clear'
        const shouldCheck = action === 'select';
        // Find all rows for this module
        const rows = document.querySelectorAll('tr.table-row[data-module="' + moduleId + '"]');
        rows.forEach(tr => {
            const boxes = tr.querySelectorAll('input.perm-checkbox[data-role="' + roleId + '"]');
            boxes.forEach(cb => toggleCheckbox(cb, shouldCheck));
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>

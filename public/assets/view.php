<?php
/**
 * Asset & Resource Management - View Asset Details
 * Comprehensive view with tabs: Overview, Allocations, Maintenance, Files, Activity
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$conn = createConnection(true);

// Check if module is set up
$table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'assets_master'");
if (!$table_check || mysqli_num_rows($table_check) == 0) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

require_once __DIR__ . '/helpers.php';

// Get asset ID
$asset_id = (int)($_GET['id'] ?? 0);
if (!$asset_id) {
    header('Location: list.php');
    exit;
}

// Get asset details
$asset = getAssetById($conn, $asset_id);
if (!$asset) {
    header('Location: list.php');
    exit;
}

// Get related data
$allocation_history = getAllocationHistory($conn, $asset_id);
$maintenance_history = getMaintenanceHistory($conn, $asset_id);
$files = getAssetFiles($conn, $asset_id);
$activity_log = getActivityLog($conn, $asset_id);
$active_allocation = getActiveAllocation($conn, $asset_id);

// Get context data for modals
$employees = getAllEmployees($conn);
$projects = getAllProjects($conn);
$clients = getAllClients($conn);
$leads = getAllLeads($conn);

$page_title = $asset['asset_code'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

closeConnection($conn);
?>

<div class="main-wrapper">
<style>
.assets-view-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.assets-view-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}

@media (max-width:768px){
.assets-view-header-flex{flex-direction:column;align-items:stretch;}
.assets-view-header-buttons{width:100%;flex-direction:column;gap:10px;}
.assets-view-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.assets-view-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="assets-view-header-flex">
                <div>
                    <h1 style="margin: 0;"><?php echo htmlspecialchars($asset['asset_code']); ?></h1>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 18px; font-weight: 600;">
                        <?php echo htmlspecialchars($asset['name']); ?>
                    </p>
                </div>
                <div class="assets-view-header-buttons">
                    <a href="edit.php?id=<?php echo $asset_id; ?>" class="btn">✏️ Edit</a>
                    <a href="list.php" class="btn btn-accent">← Back to List</a>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="card" style="text-align: center; padding: 15px;">
                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">Category</div>
                <div style="font-weight: 700; font-size: 18px; color: #003581;">
                    <?php echo htmlspecialchars($asset['category']); ?>
                </div>
            </div>
            
            <div class="card" style="text-align: center; padding: 15px;">
                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">Status</div>
                <div>
                    <span class="badge badge-<?php 
                        echo match($asset['status']) {
                            'Available' => 'success',
                            'In Use' => 'warning',
                            'Under Maintenance' => 'purple',
                            'Broken' => 'danger',
                            'Decommissioned' => 'secondary',
                            default => 'secondary'
                        };
                    ?>" style="font-size: 14px; padding: 6px 12px;">
                        <?php echo $asset['status']; ?>
                    </span>
                </div>
            </div>
            
            <div class="card" style="text-align: center; padding: 15px;">
                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">Condition</div>
                <div>
                    <span class="badge badge-<?php 
                        echo match($asset['condition']) {
                            'New' => 'success',
                            'Good' => 'info',
                            'Fair' => 'warning',
                            'Poor' => 'danger',
                            default => 'secondary'
                        };
                    ?>" style="font-size: 14px; padding: 6px 12px;">
                        <?php echo $asset['condition']; ?>
                    </span>
                </div>
            </div>
            
            <div class="card" style="text-align: center; padding: 15px;">
                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">Location</div>
                <div style="font-weight: 600; font-size: 14px; color: #003581;">
                    <?php echo htmlspecialchars($asset['location'] ?: 'Not set'); ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3 style="margin: 0 0 15px 0; color: #003581;">⚡ Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <?php if (!$active_allocation && $asset['status'] !== 'Broken' && $asset['status'] !== 'Decommissioned'): ?>
                <button onclick="openAssignModal()" class="btn" style="width: 100%;">🔖 Assign Asset</button>
                <?php endif; ?>
                
                <?php if ($active_allocation): ?>
                <button onclick="openReturnModal()" class="btn" style="width: 100%;">↩️ Return Asset</button>
                <button onclick="openTransferModal()" class="btn" style="width: 100%;">🔄 Transfer Asset</button>
                <?php endif; ?>
                
                <button onclick="openStatusModal()" class="btn" style="width: 100%;">🔧 Change Status</button>
                <button onclick="openMaintenanceModal()" class="btn" style="width: 100%;">🛠️ Add Maintenance</button>
                <button onclick="openFileModal()" class="btn" style="width: 100%;">📎 Upload File</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('overview')">📋 Overview</button>
                <button class="tab-btn" onclick="switchTab('allocation')">🔖 Allocations (<?php echo count($allocation_history); ?>)</button>
                <button class="tab-btn" onclick="switchTab('maintenance')">🔧 Maintenance (<?php echo count($maintenance_history); ?>)</button>
                <button class="tab-btn" onclick="switchTab('files')">📎 Files (<?php echo count($files); ?>)</button>
                <button class="tab-btn" onclick="switchTab('activity')">📝 Activity (<?php echo count($activity_log); ?>)</button>
            </div>

            <!-- Overview Tab -->
            <div id="tab-overview" class="tab-content active">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                    <!-- Image -->
                    <div>
                        <?php if ($asset['primary_image']): ?>
                        <img src="../../<?php echo htmlspecialchars($asset['primary_image']); ?>" 
                             alt="<?php echo htmlspecialchars($asset['name']); ?>"
                             style="width: 100%; border-radius: 8px; border: 2px solid #e9ecef;">
                        <?php else: ?>
                        <div style="width: 100%; aspect-ratio: 1; background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 64px; border: 2px solid #e9ecef;">
                            📦
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($active_allocation): ?>
                        <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #faa718; border-radius: 6px;">
                            <div style="font-weight: 700; color: #856404; margin-bottom: 5px;">Currently Assigned</div>
                            <div style="font-size: 14px; color: #666;">
                                <strong><?php echo htmlspecialchars($active_allocation['context_type']); ?>:</strong>
                                <?php
                                // Try to use context_name from allocation if available, else fallback to getContextName
                                if (isset($active_allocation['context_name']) && $active_allocation['context_name']) {
                                    echo htmlspecialchars($active_allocation['context_name']);
                                } else {
                                    // Fallback: try to get name with a new connection (if needed)
                                    require_once __DIR__ . '/../../config/db_connect.php';
                                    $tmp_conn = createConnection(true);
                                    $name = getContextName($tmp_conn, $active_allocation['context_type'], $active_allocation['context_id']);
                                    closeConnection($tmp_conn);
                                    echo htmlspecialchars($name);
                                }
                                ?>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                Since: <?php echo date('M d, Y', strtotime($active_allocation['assigned_on'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div>
                        <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Asset Details</h3>
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666; width: 35%;">Asset Code</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['asset_code']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Name</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['name']); ?></td>
                            </tr>
                            <?php if ($asset['type']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Type</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['type']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['make']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Make/Brand</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['make']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['model']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Model</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['model']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['serial_no']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Serial Number</td>
                                <td style="padding: 12px 0; font-family: monospace;"><?php echo htmlspecialchars($asset['serial_no']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['department']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Department</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['department']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['purchase_date']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Purchase Date</td>
                                <td style="padding: 12px 0;"><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['purchase_cost']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Purchase Cost</td>
                                <td style="padding: 12px 0; font-weight: 700; color: #003581;">₹<?php echo number_format($asset['purchase_cost'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['vendor']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Vendor</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['vendor']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($asset['warranty_expiry']): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Warranty Expiry</td>
                                <td style="padding: 12px 0;">
                                    <?php 
                                    $days_left = (strtotime($asset['warranty_expiry']) - time()) / 86400;
                                    $color = $days_left < 7 ? '#dc3545' : ($days_left < 30 ? '#fd7e14' : '#28a745');
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                        <?php echo date('M d, Y', strtotime($asset['warranty_expiry'])); ?>
                                    </span>
                                    <?php if ($days_left > 0): ?>
                                    <small style="color: #666;"> (<?php echo ceil($days_left); ?> days remaining)</small>
                                    <?php else: ?>
                                    <small style="color: #dc3545;"> (Expired)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Created By</td>
                                <td style="padding: 12px 0;"><?php echo htmlspecialchars($asset['created_by_name']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; font-weight: 600; color: #666;">Created On</td>
                                <td style="padding: 12px 0;"><?php echo date('M d, Y H:i', strtotime($asset['created_at'])); ?></td>
                            </tr>
                        </table>

                        <?php if ($asset['notes']): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0; color: #003581;">Notes</h4>
                            <p style="margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($asset['notes']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Allocation History Tab -->
            <div id="tab-allocation" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #003581;">Allocation History</h3>
                    <div style="display: flex; gap: 10px;">
                        <?php if ($active_allocation): ?>
                            <!-- Currently allocated - show return and transfer options -->
                            <button onclick="openReturnModal()" class="btn" style="padding: 8px 16px; font-size: 13px;">
                                ↩️ Return Asset
                            </button>
                            <button onclick="openTransferModal()" class="btn" style="padding: 8px 16px; font-size: 13px;">
                                🔄 Transfer/Reallocate
                            </button>
                        <?php elseif ($asset['status'] !== 'Broken' && $asset['status'] !== 'Decommissioned'): ?>
                            <!-- Not allocated and available - show assign option -->
                            <button onclick="openAssignModal()" class="btn" style="padding: 8px 16px; font-size: 13px;">
                                🔖 Assign Asset
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($active_allocation): ?>
                <!-- Current Allocation Card -->
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); border-left: 4px solid #faa718; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <div style="font-weight: 700; color: #856404; font-size: 16px; margin-bottom: 10px;">
                                ⚡ Currently Allocated
                            </div>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 15px; font-size: 14px;">
                                <strong>Assigned To:</strong>
                                <span><?php echo htmlspecialchars($active_allocation['context_type']); ?>: <?php echo htmlspecialchars(getContextName($GLOBALS['conn'], $active_allocation['context_type'], $active_allocation['context_id'])); ?></span>
                                
                                <?php if ($active_allocation['purpose']): ?>
                                <strong>Purpose:</strong>
                                <span><?php echo htmlspecialchars($active_allocation['purpose']); ?></span>
                                <?php endif; ?>
                                
                                <strong>Assigned On:</strong>
                                <span><?php echo date('M d, Y', strtotime($active_allocation['assigned_on'])); ?></span>
                                
                                <?php if ($active_allocation['expected_return']): ?>
                                <strong>Expected Return:</strong>
                                <span>
                                    <?php 
                                    $return_date = strtotime($active_allocation['expected_return']);
                                    $days_diff = floor(($return_date - time()) / 86400);
                                    $is_overdue = $days_diff < 0;
                                    ?>
                                    <?php echo date('M d, Y', $return_date); ?>
                                    <?php if ($is_overdue): ?>
                                        <span style="color: #dc3545; font-weight: 600;"> (⚠️ Overdue by <?php echo abs($days_diff); ?> days)</span>
                                    <?php else: ?>
                                        <span style="color: #666;"> (<?php echo $days_diff; ?> days remaining)</span>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                                
                                <strong>Assigned By:</strong>
                                <span><?php echo isset($active_allocation['assigned_by_name']) && $active_allocation['assigned_by_name'] !== null ? htmlspecialchars($active_allocation['assigned_by_name']) : '-'; ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="openReturnModal()" class="btn btn-accent" style="padding: 8px 16px; font-size: 13px; white-space: nowrap;">
                                Return
                            </button>
                            <button onclick="openTransferModal()" class="btn" style="padding: 8px 16px; font-size: 13px; white-space: nowrap;">
                                Transfer
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <h4 style="color: #003581; margin: 20px 0 15px 0;">📜 Past Allocations</h4>
                <?php if (!empty($allocation_history)): ?>
                <div class="card-section" style="background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-top: 0; padding: 0 0 20px 0;">
                    <div style="padding: 20px 24px 10px 24px; border-radius: 10px 10px 0 0; background: linear-gradient(90deg, #003581 0%, #0056b3 100%); color: #fff;">
                        <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; letter-spacing: 1px;">🔖 Past Allocations</h3>
                    </div>
                    <div style="padding: 0 24px;">
                        <div class="table-responsive" style="margin-top: 20px;">
                            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; color: #003581;">
                                        <th style="padding: 12px 8px; font-weight: 700;">Context</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Assigned To</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Purpose</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Assigned On</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Expected Return</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Returned On</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Status</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Assigned By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allocation_history as $i => $alloc): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef; background: <?php echo $i % 2 === 0 ? '#fff' : '#f6f8fa'; ?>;">
                                        <td style="padding: 10px 8px;"><span class="badge badge-info" style="font-size: 13px; padding: 5px 12px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px;"><?php echo $alloc['context_type']; ?></span></td>
                                        <td style="padding: 10px 8px;"><strong><?php echo htmlspecialchars($alloc['context_name']); ?></strong></td>
                                        <td style="padding: 10px 8px; color: #444; font-size: 14px;"><?php echo htmlspecialchars($alloc['purpose'] ?: '-'); ?></td>
                                        <td style="padding: 10px 8px; color: #333; font-size: 14px; white-space: nowrap;"><?php echo date('M d, Y', strtotime($alloc['assigned_on'])); ?></td>
                                        <td style="padding: 10px 8px; color: #333; font-size: 14px; white-space: nowrap;"><?php echo $alloc['expected_return'] ? date('M d, Y', strtotime($alloc['expected_return'])) : '-'; ?></td>
                                        <td style="padding: 10px 8px; color: #333; font-size: 14px; white-space: nowrap;"><?php echo $alloc['returned_on'] ? date('M d, Y', strtotime($alloc['returned_on'])) : '-'; ?></td>
                                        <td style="padding: 10px 8px;">
                                            <span class="badge badge-<?php 
                                                echo match($alloc['status']) {
                                                    'Active' => 'warning',
                                                    'Returned' => 'success',
                                                    'Transferred' => 'info',
                                                    default => 'secondary'
                                                };
                                            ?>" style="font-size: 13px; padding: 5px 12px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px;">
                                                <?php echo $alloc['status']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 8px; color: #003581; font-weight: 600; font-size: 14px;">
                                            <?php echo isset($alloc['assigned_by_name']) && $alloc['assigned_by_name'] !== null ? htmlspecialchars($alloc['assigned_by_name']) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">No past allocations</p>
                <?php endif; ?>
            </div>

            <!-- Maintenance History Tab -->
            <div id="tab-maintenance" class="tab-content">
                <h3 style="margin-top: 0; color: #003581;">Maintenance History</h3>
                <?php if (!empty($maintenance_history)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Job Date</th>
                                <th>Description</th>
                                <th>Technician</th>
                                <th>Cost</th>
                                <th>Next Due</th>
                                <th>Status</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_history as $maint): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($maint['job_date'])); ?></td>
                                <td><?php echo htmlspecialchars($maint['description']); ?></td>
                                <td><?php echo htmlspecialchars($maint['technician'] ?: '-'); ?></td>
                                <td><?php echo $maint['cost'] ? '₹' . number_format($maint['cost'], 2) : '-'; ?></td>
                                <td><?php echo $maint['next_due'] ? date('M d, Y', strtotime($maint['next_due'])) : '-'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $maint['status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                        <?php echo $maint['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($maint['created_by_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">No maintenance records</p>
                <?php endif; ?>
            </div>

            <!-- Files Tab -->
            <div id="tab-files" class="tab-content">
                <h3 style="margin-top: 0; color: #003581;">Attached Files</h3>
                <?php if (!empty($files)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach ($files as $file): ?>
                    <div class="card" style="padding: 15px; text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 10px;">
                            <?php 
                            $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                            echo match($ext) {
                                'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                'pdf' => '📄',
                                'doc', 'docx' => '📝',
                                'xls', 'xlsx' => '📊',
                                default => '📎'
                            };
                            ?>
                        </div>
                        <div style="font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #003581;">
                            <?php echo htmlspecialchars($file['file_type']); ?>
                        </div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 10px;">
                            <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                        </div>
                        <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" 
                           target="_blank" 
                           class="btn" 
                           style="font-size: 12px; padding: 6px 12px; width: 100%;">
                            Download
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">No files attached</p>
                <?php endif; ?>
            </div>

            <!-- Activity Log Tab -->
            <div id="tab-activity" class="tab-content">
                <div class="card-section" style="background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-top: 0; padding: 0 0 20px 0;">
                    <div style="padding: 20px 24px 10px 24px; border-radius: 10px 10px 0 0; background: linear-gradient(90deg, #003581 0%, #0056b3 100%); color: #fff;">
                        <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; letter-spacing: 1px;">📝 Activity Log</h3>
                    </div>
                    <div style="padding: 0 24px;">
                        <?php if (!empty($activity_log)): ?>
                        <div class="table-responsive" style="margin-top: 20px;">
                            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; color: #003581;">
                                        <th style="padding: 12px 8px; font-weight: 700;">Timestamp</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Action</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">User</th>
                                        <th style="padding: 12px 8px; font-weight: 700;">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_log as $i => $log): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef; background: <?php echo $i % 2 === 0 ? '#fff' : '#f6f8fa'; ?>;">
                                        <td style="padding: 10px 8px; color: #333; font-size: 14px; white-space: nowrap;">
                                            <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td style="padding: 10px 8px;">
                                            <span class="badge badge-<?php 
                                                echo match($log['action']) {
                                                    'Create' => 'success',
                                                    'Update' => 'info',
                                                    'Allocate' => 'primary',
                                                    'Return' => 'secondary',
                                                    'Transfer' => 'warning',
                                                    'Status' => 'info',
                                                    'Maintenance' => 'purple',
                                                    default => 'secondary'
                                                };
                                            ?>" style="font-size: 13px; padding: 5px 12px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px;">
                                                <?php echo $log['action']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 8px; color: #003581; font-weight: 600; font-size: 14px;">
                                            <?php echo htmlspecialchars($log['user_name']); ?>
                                        </td>
                                        <td style="padding: 10px 8px; color: #444; font-size: 14px;">
                                            <?php echo htmlspecialchars($log['description'] ?: '-'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 40px;">No activity logged</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modals -->

<!-- Assign Asset Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('assignModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">🔖 Assign Asset</h2>
        <form id="assignForm">
            <div class="form-group">
                <label>Assign To <span style="color: #dc3545;">*</span></label>
                <select id="assign_context_type" class="form-control" required onchange="updateAssignContextOptions()">
                    <option value="">Select Context</option>
                    <option value="Employee">Employee</option>
                    <option value="Project">Project</option>
                    <option value="Client">Client</option>
                    <option value="Lead">Lead</option>
                </select>
            </div>
            
            <div class="form-group">
                <label id="assign_context_label">Select <span style="color: #dc3545;">*</span></label>
                <select id="assign_context_id" class="form-control" required>
                    <option value="">Select context first</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Purpose</label>
                <input type="text" id="assign_purpose" class="form-control" placeholder="Purpose of allocation">
            </div>
            
            <div class="form-group">
                <label>Expected Return Date</label>
                <input type="date" id="assign_expected_return" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('assignModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- Return Asset Modal -->
<div id="returnModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('returnModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">↩️ Return Asset</h2>
        <?php if ($active_allocation): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Currently assigned to:</strong><br>
            <?php echo $active_allocation['context_type']; ?>: <?php echo htmlspecialchars(getContextName($GLOBALS['conn'], $active_allocation['context_type'], $active_allocation['context_id'])); ?><br>
            <small>Since: <?php echo date('M d, Y', strtotime($active_allocation['assigned_on'])); ?></small>
        </div>
        <?php endif; ?>
        <form id="returnForm">
            <input type="hidden" id="return_allocation_id" value="<?php echo $active_allocation['id'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Asset Condition After Return</label>
                <select id="return_condition" class="form-control">
                    <option value="">Keep current condition</option>
                    <option value="New">New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Return Notes</label>
                <textarea id="return_notes" class="form-control" rows="3" placeholder="Any notes about the return..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('returnModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Return Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Transfer Asset Modal -->
<div id="transferModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('transferModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">🔄 Transfer Asset</h2>
        <?php if ($active_allocation): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Currently assigned to:</strong><br>
            <?php echo $active_allocation['context_type']; ?>: <?php echo htmlspecialchars(getContextName($GLOBALS['conn'], $active_allocation['context_type'], $active_allocation['context_id'])); ?>
        </div>
        <?php endif; ?>
        <form id="transferForm">
            <div class="form-group">
                <label>Transfer To <span style="color: #dc3545;">*</span></label>
                <select id="transfer_context_type" class="form-control" required onchange="updateTransferContextOptions()">
                    <option value="">Select Context</option>
                    <option value="Employee">Employee</option>
                    <option value="Project">Project</option>
                    <option value="Client">Client</option>
                    <option value="Lead">Lead</option>
                </select>
            </div>
            
            <div class="form-group">
                <label id="transfer_context_label">Select <span style="color: #dc3545;">*</span></label>
                <select id="transfer_context_id" class="form-control" required>
                    <option value="">Select context first</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Purpose</label>
                <input type="text" id="transfer_purpose" class="form-control" placeholder="Purpose of transfer">
            </div>
            
            <div class="form-group">
                <label>Expected Return Date</label>
                <input type="date" id="transfer_expected_return" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('transferModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('statusModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">🔧 Change Asset Status</h2>
        <form id="statusForm">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Current Status:</strong> 
                <span class="badge badge-<?php 
                    echo match($asset['status']) {
                        'Available' => 'success',
                        'In Use' => 'warning',
                        'Under Maintenance' => 'purple',
                        'Broken' => 'danger',
                        'Decommissioned' => 'secondary',
                        default => 'secondary'
                    };
                ?>"><?php echo $asset['status']; ?></span>
            </div>
            
            <div class="form-group">
                <label>New Status <span style="color: #dc3545;">*</span></label>
                <select id="new_status" class="form-control" required>
                    <option value="">Select Status</option>
                    <option value="Available">Available</option>
                    <option value="In Use">In Use</option>
                    <option value="Under Maintenance">Under Maintenance</option>
                    <option value="Broken">Broken</option>
                    <option value="Decommissioned">Decommissioned</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reason</label>
                <textarea id="status_reason" class="form-control" rows="3" placeholder="Reason for status change..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('statusModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Change Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('maintenanceModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">🛠️ Add Maintenance Job</h2>
        <form id="maintenanceForm">
            <div class="form-group">
                <label>Job Date <span style="color: #dc3545;">*</span></label>
                <input type="date" id="job_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label>Description <span style="color: #dc3545;">*</span></label>
                <textarea id="maintenance_description" class="form-control" rows="3" required placeholder="Describe the maintenance work..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Technician/Vendor</label>
                <input type="text" id="technician" class="form-control" placeholder="Name of technician or vendor">
            </div>
            
            <div class="form-group">
                <label>Cost</label>
                <input type="number" step="0.01" id="maintenance_cost" class="form-control" placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Next Maintenance Due</label>
                <input type="date" id="next_due" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label>Status <span style="color: #dc3545;">*</span></label>
                <select id="maintenance_status" class="form-control" required>
                    <option value="Open">Open (In Progress)</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            
            <div style="background: #fff3cd; border-left: 4px solid #faa718; padding: 12px; border-radius: 4px; margin: 15px 0;">
                <strong>ℹ️ Note:</strong> If status is "Open", the asset will be marked as "Under Maintenance".
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('maintenanceModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Add Job</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload File Modal -->
<div id="fileModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('fileModal')">&times;</span>
        <h2 style="margin-top: 0; color: #003581;">📎 Upload File</h2>
        <form id="fileForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>File Type <span style="color: #dc3545;">*</span></label>
                <select id="file_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="Invoice">Invoice</option>
                    <option value="Warranty">Warranty Document</option>
                    <option value="Manual">User Manual</option>
                    <option value="Photo">Photo</option>
                    <option value="Certificate">Certificate</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>File <span style="color: #dc3545;">*</span></label>
                <input type="file" id="file" class="form-control" required>
                <small style="color: #666;">Allowed: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, TXT (Max 10MB)</small>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('fileModal')" class="btn btn-accent">Cancel</button>
                <button type="submit" class="btn">Upload</button>
            </div>
        </form>
    </div>
</div>

<style>
.tabs {
    display: flex;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
    gap: 5px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    transition: all 0.3s;
    font-size: 14px;
}

.tab-btn:hover {
    color: #003581;
    background: #f8f9fa;
}

.tab-btn.active {
    color: #003581;
    border-bottom-color: #003581;
    background: #f8f9fa;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.badge-purple {
    background-color: #6f42c1;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 30px;
    border: none;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    line-height: 1;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
}
</style>

<script>
// Context data from PHP
const contextData = {
    Employee: <?php echo json_encode($employees); ?>,
    Project: <?php echo json_encode($projects); ?>,
    Client: <?php echo json_encode($clients); ?>,
    Lead: <?php echo json_encode($leads); ?>
};

const assetId = <?php echo $asset_id; ?>;

function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Mark button as active
    event.target.classList.add('active');
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openAssignModal() {
    openModal('assignModal');
}

function openReturnModal() {
    openModal('returnModal');
}

function openTransferModal() {
    openModal('transferModal');
}

function openStatusModal() {
    openModal('statusModal');
}

function openMaintenanceModal() {
    openModal('maintenanceModal');
}

function openFileModal() {
    openModal('fileModal');
}

// Update context options for assign modal
function updateAssignContextOptions() {
    const contextType = document.getElementById('assign_context_type').value;
    const contextId = document.getElementById('assign_context_id');
    const label = document.getElementById('assign_context_label');
    
    contextId.innerHTML = '<option value="">Select...</option>';
    
    if (contextType && contextData[contextType]) {
        label.textContent = contextType + ' *';
        contextData[contextType].forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            contextId.appendChild(option);
        });
    } else {
        label.textContent = 'Select *';
        contextId.innerHTML = '<option value="">Select context first</option>';
    }
}

// Update context options for transfer modal
function updateTransferContextOptions() {
    const contextType = document.getElementById('transfer_context_type').value;
    const contextId = document.getElementById('transfer_context_id');
    const label = document.getElementById('transfer_context_label');
    
    contextId.innerHTML = '<option value="">Select...</option>';
    
    if (contextType && contextData[contextType]) {
        label.textContent = contextType + ' *';
        contextData[contextType].forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            contextId.appendChild(option);
        });
    } else {
        label.textContent = 'Select *';
        contextId.innerHTML = '<option value="">Select context first</option>';
    }
}

// Handle assign form submission
document.getElementById('assignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        asset_id: assetId,
        context_type: document.getElementById('assign_context_type').value,
        context_id: document.getElementById('assign_context_id').value,
        purpose: document.getElementById('assign_purpose').value,
        expected_return: document.getElementById('assign_expected_return').value || null
    };
    
    try {
        const response = await fetch('../api/assets/assign.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Asset assigned successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Handle return form submission
document.getElementById('returnForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        asset_id: assetId,
        return_notes: document.getElementById('return_notes').value,
        new_condition: document.getElementById('return_condition').value || null
    };
    
    try {
        const response = await fetch('../api/assets/return.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Asset returned successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Handle transfer form submission
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        asset_id: assetId,
        new_context_type: document.getElementById('transfer_context_type').value,
        new_context_id: document.getElementById('transfer_context_id').value,
        purpose: document.getElementById('transfer_purpose').value,
        expected_return: document.getElementById('transfer_expected_return').value || null
    };
    
    try {
        const response = await fetch('../api/assets/transfer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Asset transferred successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Handle status form submission
document.getElementById('statusForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        asset_id: assetId,
        new_status: document.getElementById('new_status').value,
        reason: document.getElementById('status_reason').value
    };
    
    try {
        const response = await fetch('../api/assets/status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Status changed successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Handle maintenance form submission
document.getElementById('maintenanceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        action: 'add',
        asset_id: assetId,
        job_date: document.getElementById('job_date').value,
        description: document.getElementById('maintenance_description').value,
        technician: document.getElementById('technician').value,
        cost: document.getElementById('maintenance_cost').value || null,
        next_due: document.getElementById('next_due').value || null,
        status: document.getElementById('maintenance_status').value
    };
    
    try {
        const response = await fetch('../api/assets/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Maintenance job added successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Handle file upload form submission
document.getElementById('fileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('asset_id', assetId);
    formData.append('file_type', document.getElementById('file_type').value);
    formData.append('file', document.getElementById('file').files[0]);
    
    try {
        const response = await fetch('../api/assets/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('File uploaded successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

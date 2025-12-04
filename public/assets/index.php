<?php
/**
 * Asset & Resource Management - Dashboard
 * Overview with KPIs, status distribution, and recent activity
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

// Check if user is logged in
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

$page_title = 'Asset Management - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get dashboard stats
$stats = getDashboardStats($conn);
$recent_activity = getRecentActivity($conn, 15);

// Get alerts
$overdue_allocations = [];
$query = "SELECT aal.*, am.name, am.asset_code, am.category
          FROM asset_allocation_log aal
          JOIN assets_master am ON aal.asset_id = am.id
          WHERE aal.status = 'Active' AND aal.expected_return < CURDATE()
          ORDER BY aal.expected_return ASC
          LIMIT 10";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $row['context_name'] = getContextName($conn, $row['context_type'], $row['context_id']);
    $overdue_allocations[] = $row;
}

$expiring_warranties = [];
$query = "SELECT * FROM assets_master
          WHERE warranty_expiry IS NOT NULL 
          AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          ORDER BY warranty_expiry ASC
          LIMIT 10";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $expiring_warranties[] = $row;
}

$open_maintenance = [];
$query = "SELECT aml.*, am.name, am.asset_code, am.category
          FROM asset_maintenance_log aml
          JOIN assets_master am ON aml.asset_id = am.id
          WHERE aml.status = 'Open'
          ORDER BY aml.job_date ASC
          LIMIT 10";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $open_maintenance[] = $row;
}

closeConnection($conn);
?>

<div class="main-wrapper">
<style>
.assets-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.assets-header-buttons{display:flex;gap:10px;}

@media (max-width:768px){
.assets-header-flex{flex-direction:column;align-items:stretch;}
.assets-header-buttons{width:100%;flex-direction:column;gap:10px;}
.assets-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.assets-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="assets-header-flex">
                <div>
                    <h1 style="margin: 0;">🧰 Asset & Resource Management</h1>
                    <p style="margin: 5px 0 0 0; color: #666;">Track, allocate, and manage organizational assets</p>
                </div>
                <div class="assets-header-buttons">
                    <a href="list.php" class="btn btn-accent">
                        📋 View All Assets
                    </a>
                    <a href="add.php" class="btn">
                        ➕ Add New Asset
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card" style="background: linear-gradient(135deg, #003581 0%, #0047a8 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Total Assets</h3>
                <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['total_assets']); ?></div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Available</h3>
                <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['by_status']['Available'] ?? 0); ?></div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #faa718 0%, #ff8c00 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">In Use</h3>
                <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['by_status']['In Use'] ?? 0); ?></div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #6f42c1 0%, #9b6bff 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Under Maintenance</h3>
                <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['by_status']['Under Maintenance'] ?? 0); ?></div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #dc3545 0%, #e76874 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Overdue Returns</h3>
                <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['overdue_returns']); ?></div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #fd7e14 0%, #ffb347 100%); color: white; border: none;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Warranties Expiring</h3>
                <div style="font-size: 36px; font-weight: 700; font-size: 28px;"><?php echo number_format($stats['expiring_warranties']); ?> <span style="font-size: 14px; opacity: 0.9;">(30 days)</span></div>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($overdue_allocations) || !empty($expiring_warranties) || !empty($open_maintenance)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: #003581; margin-bottom: 15px;">🔔 Alerts & Notifications</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                <!-- Overdue Returns -->
                <?php if (!empty($overdue_allocations)): ?>
                <div class="card" style="border-left: 4px solid #dc3545;">
                    <h3 style="margin: 0 0 15px 0; color: #dc3545; font-size: 16px;">⚠️ Overdue Returns (<?php echo count($overdue_allocations); ?>)</h3>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($overdue_allocations as $item): ?>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                            <div style="font-weight: 600; color: #003581;">
                                <a href="view.php?id=<?php echo $item['asset_id']; ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($item['asset_code'] . ' - ' . $item['name']); ?>
                                </a>
                            </div>
                            <div style="font-size: 13px; color: #666; margin-top: 4px;">
                                Assigned to: <strong><?php echo htmlspecialchars($item['context_type'] . ' - ' . $item['context_name']); ?></strong>
                            </div>
                            <div style="font-size: 13px; color: #dc3545; margin-top: 4px;">
                                Expected: <?php echo date('M d, Y', strtotime($item['expected_return'])); ?>
                                (<?php echo abs((strtotime($item['expected_return']) - time()) / 86400); ?> days overdue)
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Expiring Warranties -->
                <?php if (!empty($expiring_warranties)): ?>
                <div class="card" style="border-left: 4px solid #fd7e14;">
                    <h3 style="margin: 0 0 15px 0; color: #fd7e14; font-size: 16px;">📅 Warranties Expiring Soon (<?php echo count($expiring_warranties); ?>)</h3>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($expiring_warranties as $item): ?>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                            <div style="font-weight: 600; color: #003581;">
                                <a href="view.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($item['asset_code'] . ' - ' . $item['name']); ?>
                                </a>
                            </div>
                            <div style="font-size: 13px; color: #666; margin-top: 4px;">
                                Category: <strong><?php echo htmlspecialchars($item['category']); ?></strong>
                            </div>
                            <div style="font-size: 13px; color: #fd7e14; margin-top: 4px;">
                                Expires: <?php echo date('M d, Y', strtotime($item['warranty_expiry'])); ?>
                                (<?php echo ceil((strtotime($item['warranty_expiry']) - time()) / 86400); ?> days remaining)
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Open Maintenance -->
                <?php if (!empty($open_maintenance)): ?>
                <div class="card" style="border-left: 4px solid #6f42c1;">
                    <h3 style="margin: 0 0 15px 0; color: #6f42c1; font-size: 16px;">🔧 Open Maintenance Jobs (<?php echo count($open_maintenance); ?>)</h3>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($open_maintenance as $item): ?>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                            <div style="font-weight: 600; color: #003581;">
                                <a href="view.php?id=<?php echo $item['asset_id']; ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($item['asset_code'] . ' - ' . $item['name']); ?>
                                </a>
                            </div>
                            <div style="font-size: 13px; color: #666; margin-top: 4px;">
                                <?php echo htmlspecialchars(substr($item['description'], 0, 60)) . (strlen($item['description']) > 60 ? '...' : ''); ?>
                            </div>
                            <div style="font-size: 13px; color: #6f42c1; margin-top: 4px;">
                                Job Date: <?php echo date('M d, Y', strtotime($item['job_date'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts and Tables -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Category Distribution -->
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">📊 Assets by Category</h3>
                <?php if (!empty($stats['by_category'])): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php 
                        $colors = [
                            'IT' => '#003581',
                            'Vehicle' => '#28a745',
                            'Tool' => '#faa718',
                            'Machine' => '#6f42c1',
                            'Furniture' => '#17a2b8',
                            'Space' => '#fd7e14',
                            'Other' => '#6c757d'
                        ];
                        foreach ($stats['by_category'] as $category => $count): 
                            $percentage = ($count / $stats['total_assets']) * 100;
                        ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px;">
                                <span style="font-weight: 600;"><?php echo $category; ?></span>
                                <span style="color: #666;"><?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: <?php echo $colors[$category] ?? '#6c757d'; ?>; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No assets registered yet</p>
                <?php endif; ?>
            </div>

            <!-- Status Distribution -->
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">📈 Assets by Status</h3>
                <?php if (!empty($stats['by_status'])): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php 
                        $status_colors = [
                            'Available' => '#28a745',
                            'In Use' => '#faa718',
                            'Under Maintenance' => '#6f42c1',
                            'Broken' => '#dc3545',
                            'Decommissioned' => '#6c757d'
                        ];
                        foreach ($stats['by_status'] as $status => $count): 
                            $percentage = ($count / $stats['total_assets']) * 100;
                        ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px;">
                                <span style="font-weight: 600;"><?php echo $status; ?></span>
                                <span style="color: #666;"><?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: <?php echo $status_colors[$status] ?? '#6c757d'; ?>; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No assets registered yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; color: #003581;">📝 Recent Activity</h3>
            <?php if (!empty($recent_activity)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Timestamp</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Asset</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Action</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">User</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                    <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                </td>
                                <td style="padding: 12px;">
                                    <a href="view.php?id=<?php echo $activity['asset_id']; ?>" style="text-decoration: none; color: #003581; font-weight: 600;">
                                        <?php echo htmlspecialchars($activity['asset_code'] . ' - ' . $activity['asset_name']); ?>
                                    </a>
                                </td>
                                <td style="padding: 12px;">
                                    <?php
                                    $action_colors = [
                                        'Create' => 'background: #d4edda; color: #155724;',
                                        'Update' => 'background: #d1ecf1; color: #0c5460;',
                                        'Allocate' => 'background: #cce5ff; color: #004085;',
                                        'Return' => 'background: #e2e3e5; color: #383d41;',
                                        'Transfer' => 'background: #fff3cd; color: #856404;',
                                        'Status' => 'background: #d1ecf1; color: #0c5460;',
                                        'Maintenance' => 'background: #e2d9f3; color: #5a3d8a;',
                                        'Attach' => 'background: #d6d8db; color: #383d41;',
                                        'Detach' => 'background: #d6d8db; color: #383d41;',
                                    ];
                                    $action_style = $action_colors[$activity['action']] ?? 'background: #e2e3e5; color: #383d41;';
                                    ?>
                                    <span style="<?php echo $action_style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 13px;">
                                    <?php echo htmlspecialchars($activity['user_name']); ?>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                    <?php echo htmlspecialchars($activity['description'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No recent activity</p>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 30px;">
            <a href="list.php" class="card" style="text-decoration: none; color: inherit; transition: all 0.3s; border-left: 4px solid #003581;">
                <h4 style="margin: 0 0 10px 0; color: #003581;">📋 Asset Registry</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">View and manage all assets</p>
            </a>
            
            <a href="add.php" class="card" style="text-decoration: none; color: inherit; transition: all 0.3s; border-left: 4px solid #28a745;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">➕ Add New Asset</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Register a new asset</p>
            </a>
            
            <a href="list.php?status=In Use" class="card" style="text-decoration: none; color: inherit; transition: all 0.3s; border-left: 4px solid #faa718;">
                <h4 style="margin: 0 0 10px 0; color: #faa718;">🔖 Active Allocations</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">View currently allocated assets</p>
            </a>
            
            <a href="list.php?status=Under Maintenance" class="card" style="text-decoration: none; color: inherit; transition: all 0.3s; border-left: 4px solid #6f42c1;">
                <h4 style="margin: 0 0 10px 0; color: #6f42c1;">🔧 Maintenance</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Track maintenance jobs</p>
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

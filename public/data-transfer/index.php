<?php
/**
 * Data Transfer Module - Dashboard
 * Overview of import/export activities with statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!data_transfer_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get statistics and recent activities
$stats = get_data_transfer_stats($conn);
$recent_activities = get_recent_activities($conn, 10);

$page_title = 'Data Transfer - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>🔁 Data Transfer</h1>
                    <p>Import and export data with full audit trail and validation</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="import.php" class="btn btn-primary">📥 Import Data</a>
                    <a href="export.php" class="btn btn-accent">📤 Export Data</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['flash_success']); 
                unset($_SESSION['flash_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['flash_error']); 
                unset($_SESSION['flash_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['imports_this_month']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Imports This Month</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['exports_this_month']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Exports This Month</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #6610f2 0%, #8142f5 100%); color: white;">
                <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($stats['rows_imported']); ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Rows Imported</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #faa718 0%, #ffc107 100%); color: #000;">
                <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($stats['rows_exported']); ?></div>
                <div style="font-size: 14px; opacity: 0.8;">Rows Exported</div>
            </div>
        </div>

        <!-- Last Action Summary -->
        <?php if ($stats['last_action']): ?>
        <div class="card" style="margin-bottom: 25px; background: linear-gradient(to right, #f8f9fa, #ffffff);">
            <h3 style="color: #003581; margin: 0 0 15px 0;">📌 Last Action</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Operation</div>
                    <div style="font-size: 16px; font-weight: 600; color: #003581;"><?php echo htmlspecialchars($stats['last_action']['operation']); ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Table</div>
                    <div style="font-size: 16px; font-weight: 600; color: #003581;"><?php echo htmlspecialchars($stats['last_action']['table_name']); ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Records</div>
                    <div style="font-size: 16px; font-weight: 600; color: #003581;"><?php echo number_format($stats['last_action']['record_count']); ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Status</div>
                    <div style="font-size: 16px; font-weight: 600;">
                        <?php
                        $status_colors = [
                            'Success' => '#28a745',
                            'Partial' => '#ffc107',
                            'Failed' => '#dc3545'
                        ];
                        $color = $status_colors[$stats['last_action']['status']] ?? '#6c757d';
                        ?>
                        <span style="color: <?php echo $color; ?>;"><?php echo htmlspecialchars($stats['last_action']['status']); ?></span>
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">When</div>
                    <div style="font-size: 16px; font-weight: 600; color: #003581;"><?php echo date('d M Y, H:i', strtotime($stats['last_action']['created_at'])); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #003581;">📊 Recent Activity</h3>
                <a href="logs.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">View All Logs →</a>
            </div>

            <?php if (!empty($recent_activities)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Date & Time</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Operation</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Table</th>
                            <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Records</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Performed By</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $activity): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                <?php echo date('d M Y', strtotime($activity['created_at'])); ?><br>
                                <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                            </td>
                            <td style="padding: 12px;">
                                <?php if ($activity['operation'] === 'Import'): ?>
                                    <span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">📥 Import</span>
                                <?php else: ?>
                                    <span style="background: #cfe2ff; color: #084298; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">📤 Export</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; font-weight: 600; color: #003581;">
                                <?php echo htmlspecialchars($activity['table_name']); ?>
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: 600;">
                                <?php echo number_format($activity['record_count']); ?>
                                <?php if ($activity['operation'] === 'Import' && $activity['failed_count'] > 0): ?>
                                    <br><small style="color: #dc3545;">(<?php echo $activity['failed_count']; ?> failed)</small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <?php
                                $status_styles = [
                                    'Success' => 'background: #d4edda; color: #155724;',
                                    'Partial' => 'background: #fff3cd; color: #856404;',
                                    'Failed' => 'background: #f8d7da; color: #721c24;'
                                ];
                                $style = $status_styles[$activity['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                ?>
                                <span style="<?php echo $style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($activity['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #495057;">
                                <?php echo htmlspecialchars($activity['performed_by'] ?? 'Unknown'); ?>
                            </td>
                            <td style="padding: 12px; font-size: 12px; color: #6c757d;">
                                <?php echo htmlspecialchars(basename($activity['file_path'] ?? '-')); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                <h3 style="color: #003581; margin-bottom: 10px;">No Activity Yet</h3>
                <p>Start by importing or exporting data to see activity here.</p>
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                    <a href="import.php" class="btn btn-primary">📥 Import Data</a>
                    <a href="export.php" class="btn btn-accent">📤 Export Data</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

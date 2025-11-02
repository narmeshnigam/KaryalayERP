<?php
/**
 * Data Transfer Module - Activity Logs
 * View all import/export operations with filters
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!data_transfer_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get filters
$filters = [
    'table_name' => $_GET['table_name'] ?? '',
    'operation' => $_GET['operation'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = !empty($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// Get logs

$result = get_activity_logs($conn, $filters, $page, $per_page);
if (!is_array($result) || !isset($result['logs'])) {
    echo '<div class="alert alert-error" style="margin: 30px;">Unable to fetch logs. Please check your database connection or PHP MySQL driver.</div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}
$logs = $result['logs'];
$total_pages = $result['total_pages'];

// Get accessible tables for filter
$accessible_tables = get_accessible_tables($conn);

$page_title = 'Activity Logs - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📊 Activity Logs</h1>
                    <p>Complete history of all import/export operations</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="logs.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Table</label>
                    <select name="table_name" class="form-control">
                        <option value="">All Tables</option>
                        <?php foreach ($accessible_tables as $table): ?>
                            <option value="<?php echo htmlspecialchars($table); ?>" <?php echo $filters['table_name'] === $table ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($table); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Operation</label>
                    <select name="operation" class="form-control">
                        <option value="">All Operations</option>
                        <option value="Import" <?php echo $filters['operation'] === 'Import' ? 'selected' : ''; ?>>Import</option>
                        <option value="Export" <?php echo $filters['operation'] === 'Export' ? 'selected' : ''; ?>>Export</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="logs.php" class="btn btn-accent" style="text-decoration: none;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                📋 Activity Records
                <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo $result['total']; ?> total)</span>
            </h3>

            <?php if (!empty($logs)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">ID</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Date & Time</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Operation</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Table</th>
                            <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Records</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">User</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px; font-weight: 600; color: #6c757d;">
                                #<?php echo $log['id']; ?>
                            </td>
                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                <?php echo date('d M Y', strtotime($log['created_at'])); ?><br>
                                <small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                            </td>
                            <td style="padding: 12px;">
                                <?php if ($log['operation'] === 'Import'): ?>
                                    <span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">📥 Import</span>
                                <?php else: ?>
                                    <span style="background: #cfe2ff; color: #084298; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">📤 Export</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; font-weight: 600; color: #003581;">
                                <?php echo htmlspecialchars($log['table_name']); ?>
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: 600;">
                                <?php echo number_format($log['record_count']); ?>
                                <?php if ($log['operation'] === 'Import'): ?>
                                    <br><small style="color: #28a745;">(<?php echo $log['success_count']; ?> ✓)</small>
                                    <?php if ($log['failed_count'] > 0): ?>
                                        <small style="color: #dc3545;">(<?php echo $log['failed_count']; ?> ✗)</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <?php
                                $status_styles = [
                                    'Success' => 'background: #d4edda; color: #155724;',
                                    'Partial' => 'background: #fff3cd; color: #856404;',
                                    'Failed' => 'background: #f8d7da; color: #721c24;'
                                ];
                                $style = $status_styles[$log['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                ?>
                                <span style="<?php echo $style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($log['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #495057;">
                                <?php echo htmlspecialchars($log['performed_by'] ?? 'Unknown'); ?>
                            </td>
                            <td style="padding: 12px; font-size: 12px; color: #6c757d;">
                                <?php echo htmlspecialchars(basename($log['file_path'] ?? '-')); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e9ecef; display: flex; justify-content: center; gap: 10px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>" class="btn" style="text-decoration: none;">« Previous</a>
                <?php endif; ?>
                
                <span style="padding: 8px 16px; color: #6c757d;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>" class="btn" style="text-decoration: none;">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                <h3 style="color: #003581; margin-bottom: 10px;">No Logs Found</h3>
                <p>No activity logs match your filter criteria.</p>
                <a href="logs.php" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">Clear Filters</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

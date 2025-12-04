<?php
require_once __DIR__ . '/../../config/config.php';
/**
 * Work Orders Module - Dashboard
 * Lists all work orders with filters and statistics
 */

// Removed auth_check.php include

// Permission checks removed
$can_create = true;
$can_edit = true;
$can_export = true;

$page_title = "Work Orders - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Check if work_orders table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'work_orders'");
$table_exists = mysqli_num_rows($table_check) > 0;

// Get filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? mysqli_real_escape_string($conn, $_GET['priority']) : '';
$linked_type_filter = isset($_GET['linked_type']) ? mysqli_real_escape_string($conn, $_GET['linked_type']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Sortable columns whitelist
$sortable_columns = [
    'work_order_code' => 'work_order_code',
    'order_date' => 'order_date',
    'service_type' => 'service_type',
    'priority' => 'priority',
    'status' => 'status',
    'due_date' => 'due_date',
    'created_at' => 'created_at'
];
$sort_column = isset($sortable_columns[$sort_by]) ? $sortable_columns[$sort_by] : 'created_at';

$work_orders = [];
$total_records = 0;

if ($table_exists) {
    // Build WHERE clause
    $where_clauses = ["order_date BETWEEN '$from_date' AND '$to_date'"];
    
    if ($search) {
        $where_clauses[] = "(work_order_code LIKE '%$search%' OR service_type LIKE '%$search%' OR description LIKE '%$search%')";
    }
    if ($status_filter) {
        $where_clauses[] = "status = '$status_filter'";
    }
    if ($priority_filter) {
        $where_clauses[] = "priority = '$priority_filter'";
    }
    if ($linked_type_filter) {
        $where_clauses[] = "linked_type = '$linked_type_filter'";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM work_orders WHERE $where_sql";
    $count_result = mysqli_query($conn, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    
    // Fetch work orders
    $query = "SELECT wo.*, 
              u.username as creator_name,
              (SELECT COUNT(*) FROM work_order_deliverables WHERE work_order_id = wo.id) as deliverable_count,
              (SELECT COUNT(*) FROM work_order_deliverables WHERE work_order_id = wo.id AND delivery_status = 'Delivered') as delivered_count
              FROM work_orders wo
              LEFT JOIN users u ON wo.created_by = u.id
              WHERE " . str_replace(["work_order_code", "service_type", "description", "order_date", "status", "priority", "linked_type"], ["wo.work_order_code", "wo.service_type", "wo.description", "wo.order_date", "wo.status", "wo.priority", "wo.linked_type"], $where_sql) .
              " ORDER BY wo.$sort_column $sort_order
              LIMIT $per_page OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $work_orders[] = $row;
    }
    
    // Get statistics
    $stats_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_count,
                    SUM(CASE WHEN priority = 'High' AND status NOT IN ('Delivered', 'Closed') THEN 1 ELSE 0 END) as high_priority_count
                    FROM work_orders WHERE $where_sql";
    $stats_result = mysqli_query($conn, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
}

$total_pages = ceil($total_records / $per_page);

// Helper functions for sort URLs
function getSortUrl($column) {
    global $search, $status_filter, $priority_filter, $linked_type_filter, $from_date, $to_date, $sort_by, $sort_order;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'desc' : 'asc';
    $params = array_filter([
        'search' => $search,
        'status' => $status_filter,
        'priority' => $priority_filter,
        'linked_type' => $linked_type_filter,
        'from_date' => $from_date,
        'to_date' => $to_date,
        'sort' => $column,
        'order' => $new_order
    ]);
    return 'index.php?' . http_build_query($params);
}

function getSortIndicator($column) {
    global $sort_by, $sort_order;
    if ($sort_by === $column) {
        return $sort_order === 'ASC' ? ' ▲' : ' ▼';
    }
    return '';
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="wo-header-flex emp-header-flex">
                <div>
                    <h1>📋 Work Orders</h1>
                    <p>Manage and track client work orders and deliverables</p>
                </div>
                <?php if ($table_exists && $can_create): ?>
                    <div class="wo-header-btn emp-header-btn-mobile">
                        <?php if ($can_export): ?>
                            <a href="api/export.php?<?php echo http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'priority' => $priority_filter, 'linked_type' => $linked_type_filter, 'start_date' => $from_date, 'end_date' => $to_date])); ?>" class="btn btn-accent">
                                📥 Export CSV
                            </a>
                        <?php endif; ?>
                        <a href="create.php" class="btn btn-inline-icon">
                            <span class="btn-icon">➕</span> Create Work Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$table_exists): ?>
            <!-- Setup Required -->
            <div class="alert alert-warning" style="margin-bottom:20px;">
                <strong>⚠️ Setup Required</strong><br>
                Work Orders module database tables need to be created first.
            </div>
            <div class="wo-setup-wrapper">
                <div class="wo-setup-icon">📋</div>
                <h2 class="wo-setup-title">Work Orders Module Not Set Up</h2>
                <p class="wo-setup-text">
                    Create the required database tables to start managing work orders
                </p>
                <a href="<?php echo APP_URL; ?>/scripts/setup_workorders_tables.php" class="btn wo-setup-btn">
                    🚀 Setup Work Orders Module
                </a>
            </div>
        <?php else: ?>
            <!-- Success Message -->
            <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="alert alert-success" style="margin-bottom:20px;">
                    <strong>✅ Success!</strong><br>
                    Work Order <?php echo isset($_GET['code']) ? htmlspecialchars($_GET['code']) : ''; ?> has been created successfully!
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
                <div class="emp-stats-grid wo-stats-grid">
                    <div class="card emp-stat-card wo-stat-card emp-stat-blue">
                        <div class="wo-stat-value emp-stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                        <div class="wo-stat-label emp-stat-label">Total Orders</div>
                    </div>
                
                    <div class="card emp-stat-card wo-stat-card emp-stat-amber">
                        <div class="wo-stat-value emp-stat-value"><?php echo $stats['in_progress_count'] ?? 0; ?></div>
                        <div class="wo-stat-label emp-stat-label">In Progress</div>
                    </div>
                
                    <div class="card emp-stat-card wo-stat-card emp-stat-green">
                        <div class="wo-stat-value emp-stat-value"><?php echo $stats['delivered_count'] ?? 0; ?></div>
                        <div class="wo-stat-label emp-stat-label">Delivered</div>
                    </div>
                
                    <div class="card emp-stat-card wo-stat-card emp-stat-purple">
                        <div class="wo-stat-value emp-stat-value"><?php echo $stats['high_priority_count'] ?? 0; ?></div>
                        <div class="wo-stat-label emp-stat-label">High Priority Pending</div>
                    </div>
                </div>

            <!-- Filters -->
            <div class="card emp-search-card wo-filter-card">
                <h3 class="emp-filter-heading wo-filter-heading">🔍 Filter Work Orders</h3>
                <form method="GET" action="index.php" class="emp-search-form wo-filter-form">
                    <div class="form-group form-group-inline">
                        <label>📅 From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>📅 To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>🔍 Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Code, Service Type..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>📊 Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Draft" <?php echo $status_filter == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Internal Review" <?php echo $status_filter == 'Internal Review' ? 'selected' : ''; ?>>Internal Review</option>
                            <option value="Client Review" <?php echo $status_filter == 'Client Review' ? 'selected' : ''; ?>>Client Review</option>
                            <option value="Delivered" <?php echo $status_filter == 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Closed" <?php echo $status_filter == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>🎯 Priority</label>
                        <select name="priority" class="form-control">
                            <option value="">All Priority</option>
                            <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>👤 Type</label>
                        <select name="linked_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Lead" <?php echo $linked_type_filter == 'Lead' ? 'selected' : ''; ?>>Lead</option>
                            <option value="Client" <?php echo $linked_type_filter == 'Client' ? 'selected' : ''; ?>>Client</option>
                        </select>
                    </div>
                    
                    <div class="emp-search-actions wo-filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="index.php" class="btn btn-accent">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Work Orders Table -->
            <div class="card">
                <div class="wo-card-header">
                    <h3 class="wo-card-title">
                        📋 Work Orders List 
                        <span class="wo-card-count">(<?php echo $total_records; ?> records)</span>
                    </h3>
                    <?php if ($can_export): ?>
                        <div class="wo-card-actions">
                            <a href="api/export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-accent btn-small">
                                📊 Export to Excel
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($work_orders) > 0): ?>
                    <div class="wo-table-wrapper">
                        <table class="table wo-table">
                            <thead>
                                <tr>
                                    <th class="sortable"><a href="<?php echo getSortUrl('work_order_code'); ?>">WO Code<?php echo getSortIndicator('work_order_code'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('order_date'); ?>">Order Date<?php echo getSortIndicator('order_date'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('service_type'); ?>">Service Type<?php echo getSortIndicator('service_type'); ?></a></th>
                                    <th>Linked To</th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('priority'); ?>">Priority<?php echo getSortIndicator('priority'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('due_date'); ?>">Due Date<?php echo getSortIndicator('due_date'); ?></a></th>
                                    <th>Deliverables</th>
                                    <th class="sortable wo-table-center"><a href="<?php echo getSortUrl('status'); ?>">Status<?php echo getSortIndicator('status'); ?></a></th>
                                    <th class="wo-table-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($work_orders as $wo): ?>
                                    <tr>
                                        <td class="wo-table-code"><?php echo htmlspecialchars($wo['work_order_code']); ?></td>
                                        <td class="wo-table-date"><?php echo date('d-M-Y', strtotime($wo['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($wo['service_type']); ?></td>
                                        <td>
                                            <span class="wo-type-badge wo-type-<?php echo strtolower($wo['linked_type']); ?>">
                                                <?php echo htmlspecialchars($wo['linked_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_class = [
                                                'Low' => 'wo-priority-low',
                                                'Medium' => 'wo-priority-medium',
                                                'High' => 'wo-priority-high'
                                            ][$wo['priority']] ?? 'wo-priority-medium';
                                            ?>
                                            <span class="wo-priority-badge <?php echo $priority_class; ?>">
                                                <?php echo htmlspecialchars($wo['priority']); ?>
                                            </span>
                                        </td>
                                        <td class="wo-table-date">
                                            <?php 
                                            echo date('d-M-Y', strtotime($wo['due_date']));
                                            $days_left = ceil((strtotime($wo['due_date']) - time()) / 86400);
                                            if ($days_left < 0 && !in_array($wo['status'], ['Delivered', 'Closed'])) {
                                                echo '<br><span class="wo-overdue">Overdue</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="wo-table-center">
                                            <span class="wo-deliverable-count"><?php echo $wo['delivered_count']; ?>/<?php echo $wo['deliverable_count']; ?></span>
                                        </td>
                                        <td class="wo-table-center">
                                            <?php
                                            $status_classes = [
                                                'Draft' => 'wo-status-draft',
                                                'In Progress' => 'wo-status-in-progress',
                                                'Internal Review' => 'wo-status-review',
                                                'Client Review' => 'wo-status-review',
                                                'Delivered' => 'wo-status-delivered',
                                                'Closed' => 'wo-status-closed'
                                            ];
                                            $badge_class = $status_classes[$wo['status']] ?? 'wo-status-draft';
                                            ?>
                                            <span class="wo-status-badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($wo['status']); ?>
                                            </span>
                                        </td>
                                        <td class="wo-table-center">
                                            <div class="wo-row-actions">
                                                <a href="view.php?id=<?php echo $wo['id']; ?>" class="btn btn-small">👁️ View</a>
                                                <?php if ($can_edit && !in_array($wo['status'], ['Delivered', 'Closed'])): ?>
                                                    <a href="edit.php?id=<?php echo $wo['id']; ?>" class="btn btn-accent btn-small">✏️ Edit</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="wo-pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="btn btn-small">
                                    « Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="wo-pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="btn btn-small">
                                    Next »
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="wo-empty-state">
                        <div class="wo-empty-icon">📭</div>
                        <h3 class="wo-empty-title">No Work Orders Found</h3>
                        <p class="wo-empty-text">No work orders match your search criteria. Try adjusting your filters.</p>
                        <a href="index.php" class="btn btn-accent wo-empty-btn">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

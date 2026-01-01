<?php
/**
 * Delivery Module - Dashboard with Pipeline View
 * Main landing page with statistics and delivery items organized by status
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();

// Check if delivery tables exist
$tables_to_check = ['delivery_items', 'delivery_files', 'delivery_pod', 'delivery_activity_log'];
$missing_tables = [];

foreach ($tables_to_check as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$check || mysqli_num_rows($check) === 0) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    die("
        <h1>Delivery Module Not Installed</h1>
        <p>The following tables are missing: <strong>" . implode(', ', $missing_tables) . "</strong></p>
        <p>Please install the Delivery module first:</p>
        <p><a href='" . APP_URL . "/scripts/setup_delivery_tables.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Install Delivery Module</a></p>
        <p><a href='" . APP_URL . "/public/index.php'>Back to Dashboard</a></p>
    ");
}

$page_title = "Delivery Dashboard - " . APP_NAME;

// Get view mode (pipeline or list)
$view_mode = $_GET['view'] ?? 'pipeline';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$channel_filter = $_GET['channel'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where = ["1=1"];
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where[] = "di.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($channel_filter)) {
    $where[] = "di.channel = ?";
    $params[] = $channel_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where[] = "(d.deliverable_name LIKE ? OR wo.work_order_code LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where);

// Fetch statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Ready to Deliver' THEN 1 ELSE 0 END) as ready,
    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM delivery_items di";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch delivery items
$query = "SELECT di.*, 
    d.deliverable_name,
    d.current_version,
    wo.work_order_code,
    COALESCE(c.name, l.name) as client_name,
    CONCAT(e.first_name, ' ', e.last_name) as delivered_by_name,
    CONCAT(u.username) as creator_name,
    (SELECT COUNT(*) FROM delivery_files df WHERE df.delivery_item_id = di.id) as file_count,
    (SELECT COUNT(*) FROM delivery_pod dp WHERE dp.delivery_item_id = di.id) as pod_count
    FROM delivery_items di
    LEFT JOIN deliverables d ON di.deliverable_id = d.id
    LEFT JOIN work_orders wo ON di.work_order_id = wo.id
    LEFT JOIN clients c ON di.client_id = c.id
    LEFT JOIN crm_leads l ON di.lead_id = l.id
    LEFT JOIN employees e ON di.delivered_by = e.id
    LEFT JOIN users u ON di.created_by = u.id
    WHERE {$where_clause}
    ORDER BY 
        CASE di.status
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'Ready to Deliver' THEN 3
            WHEN 'Delivered' THEN 4
            WHEN 'Confirmed' THEN 5
            WHEN 'Cancelled' THEN 6
        END,
        di.created_at DESC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$delivery_items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $delivery_items[] = $row;
}

// Group by status for pipeline view
$pipeline = [
    'Pending' => [],
    'In Progress' => [],
    'Ready to Deliver' => [],
    'Delivered' => [],
    'Confirmed' => []
];

foreach ($delivery_items as $item) {
    if ($item['status'] !== 'Cancelled') {
        $pipeline[$item['status']][] = $item;
    }
}

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.main-wrapper { background: #f8fafc; min-height: 100vh; }
.main-content { padding: 24px; max-width: 1600px; margin: 0 auto; }

.page-header {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.page-header-top {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.page-header h1 {
    font-size: 28px;
    color: #1a202c;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header p {
    color: #718096;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.stat-card-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 4px;
}

.stat-card-label {
    font-size: 13px;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
}

.view-toggle-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.view-toggle-btn.active {
    background: white;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Pipeline View */
.pipeline-container {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 16px;
}

.pipeline-column {
    flex: 1;
    min-width: 280px;
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
}

.pipeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.pipeline-title {
    font-size: 14px;
    font-weight: 600;
    color: #1a202c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pipeline-count {
    background: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #667eea;
}

.pipeline-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 200px;
}

.delivery-card {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s;
    cursor: pointer;
}

.delivery-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.delivery-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.delivery-title {
    font-weight: 600;
    color: #1a202c;
    font-size: 14px;
    margin-bottom: 4px;
    line-height: 1.4;
}

.delivery-meta {
    font-size: 12px;
    color: #718096;
    margin-bottom: 8px;
}

.delivery-client {
    font-size: 13px;
    color: #4a5568;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.delivery-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    margin-top: 12px;
}

.channel-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.channel-email { background: #dbeafe; color: #1e40af; }
.channel-portal { background: #e0e7ff; color: #4338ca; }
.channel-whatsapp { background: #dcfce7; color: #166534; }
.channel-physical { background: #fed7aa; color: #9a3412; }
.channel-courier { background: #fce7f3; color: #9f1239; }
.channel-cloud-link { background: #ddd6fe; color: #5b21b6; }
.channel-other { background: #f1f5f9; color: #475569; }

.delivery-stats {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #718096;
}

.empty-pipeline {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.empty-pipeline-icon {
    font-size: 48px;
    margin-bottom: 8px;
}

/* Filters */
.filters-section {
    background: white;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.filter-group label {
    display: block;
    font-size: 13px;
    color: #4a5568;
    margin-bottom: 6px;
    font-weight: 500;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-top">
                <div>
                    <h1>🚚 Delivery Management</h1>
                    <p>Track and manage deliveries from approval to client confirmation</p>
                </div>
                <div class="header-actions">
                    <div class="view-toggle">
                        <a href="?view=pipeline" class="view-toggle-btn <?php echo $view_mode === 'pipeline' ? 'active' : ''; ?>">
                            📊 Pipeline
                        </a>
                        <a href="?view=list" class="view-toggle-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>">
                            📋 List
                        </a>
                    </div>
                    <a href="create.php" class="btn btn-primary">
                        <span>➕</span> New Delivery
                    </a>
                    <a href="export.php" class="btn btn-secondary">
                        <span>📊</span> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon">📦</div>
                <div class="stat-card-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-card-label">Total Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">⏳</div>
                <div class="stat-card-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-card-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">🔨</div>
                <div class="stat-card-value"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <div class="stat-card-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">✅</div>
                <div class="stat-card-value"><?php echo $stats['ready'] ?? 0; ?></div>
                <div class="stat-card-label">Ready</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">📤</div>
                <div class="stat-card-value"><?php echo $stats['delivered'] ?? 0; ?></div>
                <div class="stat-card-label">Delivered</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">✨</div>
                <div class="stat-card-value"><?php echo $stats['confirmed'] ?? 0; ?></div>
                <div class="stat-card-label">Confirmed</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Channel</label>
                        <select name="channel">
                            <option value="">All Channels</option>
                            <option value="Email" <?php echo $channel_filter === 'Email' ? 'selected' : ''; ?>>Email</option>
                            <option value="Portal" <?php echo $channel_filter === 'Portal' ? 'selected' : ''; ?>>Portal</option>
                            <option value="WhatsApp" <?php echo $channel_filter === 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
                            <option value="Physical" <?php echo $channel_filter === 'Physical' ? 'selected' : ''; ?>>Physical</option>
                            <option value="Courier" <?php echo $channel_filter === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                            <option value="Cloud Link" <?php echo $channel_filter === 'Cloud Link' ? 'selected' : ''; ?>>Cloud Link</option>
                            <option value="Other" <?php echo $channel_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search deliveries..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Apply</button>
                        <a href="index.php?view=<?php echo $view_mode; ?>" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pipeline View -->
        <?php if ($view_mode === 'pipeline'): ?>
        <div class="pipeline-container">
            <?php foreach ($pipeline as $status => $items): ?>
            <div class="pipeline-column">
                <div class="pipeline-header">
                    <span class="pipeline-title"><?php echo $status; ?></span>
                    <span class="pipeline-count"><?php echo count($items); ?></span>
                </div>
                <div class="pipeline-items">
                    <?php if (empty($items)): ?>
                        <div class="empty-pipeline">
                            <div class="empty-pipeline-icon">📭</div>
                            <p>No items</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <div class="delivery-card" onclick="window.location.href='view.php?id=<?php echo $item['id']; ?>'">
                            <div class="delivery-card-header">
                                <div style="flex: 1;">
                                    <div class="delivery-title"><?php echo htmlspecialchars($item['deliverable_name']); ?></div>
                                    <div class="delivery-meta">
                                        WO: <?php echo htmlspecialchars($item['work_order_code']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="delivery-client">
                                👤 <?php echo htmlspecialchars($item['client_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="delivery-footer">
                                <span class="channel-badge channel-<?php echo strtolower(str_replace(' ', '-', $item['channel'])); ?>">
                                    <?php echo htmlspecialchars($item['channel']); ?>
                                </span>
                                <div class="delivery-stats">
                                    <span>📎 <?php echo $item['file_count']; ?></span>
                                    <?php if ($item['pod_count'] > 0): ?>
                                        <span>✓ POD</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- List View (can be implemented similar to other modules) -->
        <div style="background: white; padding: 40px; border-radius: 12px; text-align: center;">
            <p style="color: #718096;">List view coming soon. Use Pipeline view for now.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>

<?php
/**
 * Deliverables & Approvals Module - Dashboard
 * Main landing page with statistics and deliverables list
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();

// Check if deliverables tables exist
$tables_to_check = ['deliverables', 'deliverable_versions', 'deliverable_files', 'deliverable_activity_log'];
$missing_tables = [];

foreach ($tables_to_check as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$check || mysqli_num_rows($check) === 0) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    die("
        <h1>Deliverables Module Not Installed</h1>
        <p>The following tables are missing: <strong>" . implode(', ', $missing_tables) . "</strong></p>
        <p>Please install the Deliverables module first:</p>
        <p><a href='" . APP_URL . "/scripts/setup_deliverables_tables.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Install Deliverables Module</a></p>
        <p><a href='" . APP_URL . "/public/index.php'>Back to Dashboard</a></p>
    ");
}

$page_title = "Deliverables Dashboard - " . APP_NAME;

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$work_order_filter = $_GET['work_order'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where = ["1=1"];
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where[] = "d.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($work_order_filter)) {
    $where[] = "d.work_order_id = ?";
    $params[] = intval($work_order_filter);
    $types .= "i";
}

if (!empty($assigned_filter)) {
    $where[] = "d.assigned_to = ?";
    $params[] = intval($assigned_filter);
    $types .= "i";
}

if (!empty($search)) {
    $where[] = "(d.deliverable_name LIKE ? OR d.description LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where);

// Fetch statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'Internal Approved' THEN 1 ELSE 0 END) as internal_approved,
    SUM(CASE WHEN status = 'Client Review' THEN 1 ELSE 0 END) as client_review,
    SUM(CASE WHEN status = 'Revision Requested' THEN 1 ELSE 0 END) as revision_requested,
    SUM(CASE WHEN status = 'Client Approved' THEN 1 ELSE 0 END) as client_approved,
    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered
    FROM deliverables d";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch deliverables list
$query = "SELECT d.*, 
    CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
    e.employee_code,
    CONCAT(u.username) as creator_name,
    wo.work_order_code,
    (SELECT COUNT(*) FROM deliverable_versions dv WHERE dv.deliverable_id = d.id) as total_versions,
    (SELECT COUNT(*) FROM deliverable_files df WHERE df.deliverable_id = d.id) as total_files
    FROM deliverables d
    LEFT JOIN employees e ON d.assigned_to = e.id
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN work_orders wo ON d.work_order_id = wo.id
    WHERE {$where_clause}
    ORDER BY d.created_at DESC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deliverables = [];

while ($row = mysqli_fetch_assoc($result)) {
    $deliverables[] = $row;
}

// Fetch work orders for filter
$wo_query = "SELECT id, work_order_code FROM work_orders ORDER BY work_order_code DESC LIMIT 50";
$wo_result = mysqli_query($conn, $wo_query);
$work_orders = [];
while ($wo = mysqli_fetch_assoc($wo_result)) {
    $work_orders[] = $wo;
}

// Fetch employees for filter
$emp_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, employee_code 
              FROM employees WHERE status = 'Active' ORDER BY first_name";
$emp_result = mysqli_query($conn, $emp_query);
$employees = [];
while ($emp = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $emp;
}

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.main-wrapper { background: #f8fafc; min-height: 100vh; }
.main-content { padding: 24px; max-width: 1400px; margin: 0 auto; }

.page-header {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
    margin-top: 16px;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    font-size: 14px;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filters */
.filters-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
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
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Deliverables Table */
.deliverables-table-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.deliverables-table {
    width: 100%;
    border-collapse: collapse;
}

.deliverables-table thead {
    background: #f7fafc;
}

.deliverables-table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.deliverables-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #2d3748;
}

.deliverables-table tbody tr {
    transition: all 0.2s;
}

.deliverables-table tbody tr:hover {
    background: #f8fafc;
}

.deliverable-name {
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 4px;
}

.deliverable-desc {
    font-size: 13px;
    color: #718096;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.status-draft { background: #e2e8f0; color: #2d3748; }
.status-submitted { background: #bee3f8; color: #2c5282; }
.status-internal-approved { background: #c6f6d5; color: #22543d; }
.status-client-review { background: #feebc8; color: #7c2d12; }
.status-revision-requested { background: #fed7d7; color: #742a2a; }
.status-client-approved { background: #9ae6b4; color: #22543d; }
.status-delivered { background: #d6bcfa; color: #44337a; }

.action-links {
    display: flex;
    gap: 12px;
}

.action-links a {
    color: #667eea;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

.action-links a:hover {
    color: #5568d3;
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: #2d3748;
    margin-bottom: 8px;
}

.version-badge {
    background: #edf2f7;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    color: #4a5568;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📦 Deliverables & Approvals</h1>
            <p>Manage deliverable submissions, reviews, and client approvals</p>
            <div class="header-actions">
                <a href="create.php" class="btn btn-primary">
                    <span>➕</span> Create Deliverable
                </a>
                <a href="export.php" class="btn btn-secondary">
                    <span>📊</span> Export Report
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon">📋</div>
                <div class="stat-card-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-card-label">Total Deliverables</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">⏳</div>
                <div class="stat-card-value"><?php echo $stats['submitted'] ?? 0; ?></div>
                <div class="stat-card-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">✅</div>
                <div class="stat-card-value"><?php echo $stats['internal_approved'] ?? 0; ?></div>
                <div class="stat-card-label">Internal Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">👤</div>
                <div class="stat-card-value"><?php echo $stats['client_review'] ?? 0; ?></div>
                <div class="stat-card-label">Client Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">🔄</div>
                <div class="stat-card-value"><?php echo $stats['revision_requested'] ?? 0; ?></div>
                <div class="stat-card-label">Revisions Needed</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">✨</div>
                <div class="stat-card-value"><?php echo $stats['client_approved'] ?? 0; ?></div>
                <div class="stat-card-label">Client Approved</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Draft" <?php echo $status_filter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="Internal Approved" <?php echo $status_filter === 'Internal Approved' ? 'selected' : ''; ?>>Internal Approved</option>
                            <option value="Client Review" <?php echo $status_filter === 'Client Review' ? 'selected' : ''; ?>>Client Review</option>
                            <option value="Revision Requested" <?php echo $status_filter === 'Revision Requested' ? 'selected' : ''; ?>>Revision Requested</option>
                            <option value="Client Approved" <?php echo $status_filter === 'Client Approved' ? 'selected' : ''; ?>>Client Approved</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Work Order</label>
                        <select name="work_order">
                            <option value="">All Work Orders</option>
                            <?php foreach ($work_orders as $wo): ?>
                                <option value="<?php echo $wo['id']; ?>" <?php echo $work_order_filter == $wo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wo['work_order_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Assigned To</label>
                        <select name="assigned">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo $assigned_filter == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']) . ' (' . $emp['employee_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search deliverables..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Deliverables Table -->
        <div class="deliverables-table-wrapper">
            <?php if (empty($deliverables)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>No deliverables found</h3>
                    <p>Create your first deliverable to get started</p>
                </div>
            <?php else: ?>
                <table class="deliverables-table">
                    <thead>
                        <tr>
                            <th>Deliverable</th>
                            <th>Work Order</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th>Files</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliverables as $deliverable): ?>
                            <tr>
                                <td>
                                    <div class="deliverable-name"><?php echo htmlspecialchars($deliverable['deliverable_name']); ?></div>
                                    <?php if ($deliverable['description']): ?>
                                        <div class="deliverable-desc"><?php echo htmlspecialchars($deliverable['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($deliverable['work_order_code'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($deliverable['assigned_name']); ?>
                                    <br><small style="color: #718096;"><?php echo htmlspecialchars($deliverable['employee_code']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $deliverable['status'])); ?>">
                                        <?php echo htmlspecialchars($deliverable['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="version-badge">v<?php echo $deliverable['current_version']; ?></span>
                                    <?php if ($deliverable['total_versions'] > 1): ?>
                                        <small style="color: #718096;">(<?php echo $deliverable['total_versions']; ?> total)</small>
                                    <?php endif; ?>
                                </td>
                                <td>📎 <?php echo $deliverable['total_files']; ?></td>
                                <td><?php echo date('d M Y', strtotime($deliverable['created_at'])); ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="view.php?id=<?php echo $deliverable['id']; ?>">View</a>
                                        <a href="edit.php?id=<?php echo $deliverable['id']; ?>">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>

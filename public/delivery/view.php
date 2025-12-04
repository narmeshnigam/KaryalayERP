<?php
/**
 * Delivery Module - View Delivery Item
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "View Delivery - " . APP_NAME;

// Get delivery ID
$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$delivery_id) {
    header('Location: index.php');
    exit;
}

// Fetch delivery details
$query = "SELECT di.*, 
          d.deliverable_name, d.description as deliverable_desc,
          wo.work_order_code, wo.title as work_order_title,
          c.client_name, c.email as client_email,
          l.company_name as lead_company,
          CONCAT(e.first_name, ' ', e.last_name) as delivered_by_name,
          CONCAT(u.first_name, ' ', u.last_name) as created_by_name
          FROM delivery_items di
          INNER JOIN deliverables d ON di.deliverable_id = d.id
          LEFT JOIN work_orders wo ON di.work_order_id = wo.id
          LEFT JOIN clients c ON di.client_id = c.id
          LEFT JOIN crm_leads l ON di.lead_id = l.id
          LEFT JOIN employees e ON di.delivered_by = e.id
          LEFT JOIN users u ON di.created_by = u.id
          WHERE di.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header('Location: index.php');
    exit;
}

// Fetch delivery files
$files_query = "SELECT df.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
                FROM delivery_files df
                LEFT JOIN users u ON df.uploaded_by = u.id
                WHERE df.delivery_id = ?
                ORDER BY df.uploaded_at DESC";
$files_stmt = mysqli_prepare($conn, $files_query);
mysqli_stmt_bind_param($files_stmt, 'i', $delivery_id);
mysqli_stmt_execute($files_stmt);
$files_result = mysqli_stmt_get_result($files_stmt);
$files = [];
while ($file = mysqli_fetch_assoc($files_result)) {
    $files[] = $file;
}

// Fetch POD files
$pod_query = "SELECT dp.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
              FROM delivery_pod dp
              LEFT JOIN users u ON dp.uploaded_by = u.id
              WHERE dp.delivery_id = ?
              ORDER BY dp.uploaded_at DESC";
$pod_stmt = mysqli_prepare($conn, $pod_query);
mysqli_stmt_bind_param($pod_stmt, 'i', $delivery_id);
mysqli_stmt_execute($pod_stmt);
$pod_result = mysqli_stmt_get_result($pod_stmt);
$pod_files = [];
while ($pod = mysqli_fetch_assoc($pod_result)) {
    $pod_files[] = $pod;
}

// Fetch activity log
$activity_query = "SELECT da.*, CONCAT(u.first_name, ' ', u.last_name) as performed_by_name 
                   FROM delivery_activity_log da
                   LEFT JOIN users u ON da.performed_by = u.id
                   WHERE da.delivery_id = ?
                   ORDER BY da.created_at DESC";
$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($activity_stmt, 'i', $delivery_id);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);
$activities = [];
while ($activity = mysqli_fetch_assoc($activity_result)) {
    $activities[] = $activity;
}

// Status configuration
$status_config = [
    'Pending' => ['color' => '#f59e0b', 'icon' => '⏳'],
    'In Progress' => ['color' => '#3b82f6', 'icon' => '🔄'],
    'Ready to Deliver' => ['color' => '#8b5cf6', 'icon' => '📦'],
    'Delivered' => ['color' => '#10b981', 'icon' => '✅'],
    'Confirmed' => ['color' => '#059669', 'icon' => '✓✓'],
    'Failed' => ['color' => '#ef4444', 'icon' => '❌']
];

$channel_config = [
    'Email' => '#3b82f6',
    'Portal' => '#8b5cf6',
    'WhatsApp' => '#10b981',
    'Physical' => '#f59e0b',
    'Courier' => '#ef4444',
    'Cloud Link' => '#06b6d4',
    'Other' => '#6b7280'
];

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.main-wrapper { background: #f8fafc; min-height: 100vh; }
.main-content { padding: 24px; max-width: 1200px; margin: 0 auto; }

/* Header Section */
.page-header {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.header-left h1 {
    font-size: 28px;
    color: #1a202c;
    margin-bottom: 8px;
}

.header-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #718096;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    color: white;
}

.channel-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    font-size: 18px;
    color: #1a202c;
    font-weight: 600;
}

.card-body {
    padding: 24px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-item label {
    display: block;
    font-size: 13px;
    color: #718096;
    margin-bottom: 6px;
    font-weight: 500;
}

.info-item value {
    display: block;
    font-size: 15px;
    color: #1a202c;
    font-weight: 500;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

/* File List */
.file-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.file-icon {
    font-size: 24px;
}

.file-info {
    flex: 1;
}

.file-name {
    font-size: 14px;
    color: #1a202c;
    font-weight: 500;
    margin-bottom: 4px;
}

.file-meta {
    font-size: 12px;
    color: #718096;
}

.file-actions {
    display: flex;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #a0aec0;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

/* Activity Timeline */
.activity-timeline {
    position: relative;
    padding-left: 32px;
}

.activity-item {
    position: relative;
    padding-bottom: 24px;
}

.activity-item:last-child {
    padding-bottom: 0;
}

.activity-item:before {
    content: '';
    position: absolute;
    left: -26px;
    top: 8px;
    width: 2px;
    height: calc(100% + 8px);
    background: #e2e8f0;
}

.activity-item:last-child:before {
    display: none;
}

.activity-icon {
    position: absolute;
    left: -32px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    border: 3px solid white;
    box-shadow: 0 0 0 1px #e2e8f0;
}

.activity-content {
    background: #f7fafc;
    padding: 12px 16px;
    border-radius: 8px;
}

.activity-desc {
    font-size: 14px;
    color: #1a202c;
    margin-bottom: 6px;
}

.activity-meta {
    font-size: 12px;
    color: #718096;
}

/* Buttons */
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
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-icon {
    padding: 8px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1>📦 Delivery #<?php echo $delivery_id; ?></h1>
                <div class="header-meta">
                    <span>
                        <span class="status-badge" style="background: <?php echo $status_config[$delivery['status']]['color']; ?>">
                            <?php echo $status_config[$delivery['status']]['icon']; ?>
                            <?php echo $delivery['status']; ?>
                        </span>
                    </span>
                    <span>
                        <span class="channel-badge" style="background: <?php echo $channel_config[$delivery['channel']]; ?>">
                            <?php echo $delivery['channel']; ?>
                        </span>
                    </span>
                    <span>Created: <?php echo date('M d, Y', strtotime($delivery['created_at'])); ?></span>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($delivery['status'] === 'Pending'): ?>
                    <form action="api/start_delivery.php" method="POST" style="display:inline;">
                        <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            🔄 Start Delivery
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($delivery['status'] === 'In Progress'): ?>
                    <form action="api/mark_ready.php" method="POST" style="display:inline;">
                        <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            📦 Mark Ready
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($delivery['status'] === 'Ready to Deliver'): ?>
                    <form action="api/mark_delivered.php" method="POST" style="display:inline;">
                        <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                        <button type="submit" class="btn btn-success btn-sm">
                            ✅ Mark Delivered
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($delivery['status'] === 'Delivered' && empty($pod_files)): ?>
                    <a href="pod.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary btn-sm">
                        📸 Upload POD
                    </a>
                <?php endif; ?>
                
                <a href="edit.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary btn-sm">
                    ✏️ Edit
                </a>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    ← Back
                </a>
            </div>
        </div>

        <div class="content-grid">
            <!-- Main Content -->
            <div>
                <!-- Deliverable Info -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">📋 Deliverable Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item full-width">
                                <label>Deliverable Name</label>
                                <value><?php echo htmlspecialchars($delivery['deliverable_name']); ?></value>
                            </div>
                            <div class="info-item">
                                <label>Work Order</label>
                                <value>
                                    <?php echo htmlspecialchars($delivery['work_order_code']); ?>
                                    <br><small style="color: #718096; font-size: 13px;"><?php echo htmlspecialchars($delivery['work_order_title']); ?></small>
                                </value>
                            </div>
                            <div class="info-item">
                                <label>Client</label>
                                <value><?php echo htmlspecialchars($delivery['client_name'] ?: $delivery['lead_company'] ?: 'N/A'); ?></value>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Details -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">🚚 Delivery Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Delivered By</label>
                                <value><?php echo htmlspecialchars($delivery['delivered_by_name'] ?: 'Not assigned'); ?></value>
                            </div>
                            <div class="info-item">
                                <label>Delivered To</label>
                                <value><?php echo htmlspecialchars($delivery['delivered_to_name'] ?: 'N/A'); ?></value>
                            </div>
                            <div class="info-item">
                                <label>Contact</label>
                                <value><?php echo htmlspecialchars($delivery['delivered_to_contact'] ?: 'N/A'); ?></value>
                            </div>
                            <div class="info-item">
                                <label>Delivered At</label>
                                <value><?php echo $delivery['delivered_at'] ? date('M d, Y H:i', strtotime($delivery['delivered_at'])) : 'N/A'; ?></value>
                            </div>
                            <?php if ($delivery['main_link']): ?>
                            <div class="info-item full-width">
                                <label>Main Link</label>
                                <value>
                                    <a href="<?php echo htmlspecialchars($delivery['main_link']); ?>" target="_blank" style="color: #667eea;">
                                        <?php echo htmlspecialchars($delivery['main_link']); ?> 🔗
                                    </a>
                                </value>
                            </div>
                            <?php endif; ?>
                            <?php if ($delivery['notes']): ?>
                            <div class="info-item full-width">
                                <label>Notes</label>
                                <value><?php echo nl2br(htmlspecialchars($delivery['notes'])); ?></value>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Delivery Files -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">📎 Delivery Files (<?php echo count($files); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($files)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📁</div>
                                <p>No files attached</p>
                            </div>
                        <?php else: ?>
                            <div class="file-list">
                                <?php foreach ($files as $file): ?>
                                    <div class="file-item">
                                        <div class="file-icon">📄</div>
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                            <div class="file-meta">
                                                <?php echo number_format($file['file_size'] / 1024, 2); ?> KB • 
                                                <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?> • 
                                                <?php echo htmlspecialchars($file['uploaded_by_name']); ?>
                                            </div>
                                        </div>
                                        <div class="file-actions">
                                            <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-secondary btn-icon" title="View">
                                                👁️
                                            </a>
                                            <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" download class="btn btn-secondary btn-icon" title="Download">
                                                ⬇️
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- POD Files -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📸 Proof of Delivery (<?php echo count($pod_files); ?>)</h3>
                        <?php if ($delivery['status'] === 'Delivered'): ?>
                            <a href="pod.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary btn-sm">
                                + Upload POD
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pod_files)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📷</div>
                                <p>No proof of delivery uploaded</p>
                            </div>
                        <?php else: ?>
                            <div class="file-list">
                                <?php foreach ($pod_files as $pod): ?>
                                    <div class="file-item">
                                        <div class="file-icon">📸</div>
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($pod['file_name']); ?></div>
                                            <div class="file-meta">
                                                <?php echo number_format($pod['file_size'] / 1024, 2); ?> KB • 
                                                <?php echo date('M d, Y H:i', strtotime($pod['uploaded_at'])); ?> • 
                                                <?php echo htmlspecialchars($pod['uploaded_by_name']); ?>
                                            </div>
                                            <?php if ($pod['notes']): ?>
                                                <div style="margin-top: 8px; font-size: 13px; color: #4a5568;">
                                                    <?php echo nl2br(htmlspecialchars($pod['notes'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="file-actions">
                                            <a href="../../<?php echo htmlspecialchars($pod['file_path']); ?>" target="_blank" class="btn btn-secondary btn-icon" title="View">
                                                👁️
                                            </a>
                                            <a href="../../<?php echo htmlspecialchars($pod['file_path']); ?>" download class="btn btn-secondary btn-icon" title="Download">
                                                ⬇️
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Activity Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📜 Activity Log</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📋</div>
                                <p>No activity yet</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-timeline">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon"></div>
                                        <div class="activity-content">
                                            <div class="activity-desc"><?php echo htmlspecialchars($activity['description']); ?></div>
                                            <div class="activity-meta">
                                                <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?> • 
                                                <?php echo htmlspecialchars($activity['performed_by_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>

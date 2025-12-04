<?php
/**
 * Deliverables Module - View Deliverable Details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Deliverable Details - " . APP_NAME;

$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$deliverable_id) {
    header('Location: index.php');
    exit;
}

// Fetch deliverable details
$query = "SELECT d.*, 
    CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
    e.employee_code,
    CONCAT(u.username) as creator_name,
    wo.work_order_code,
    wo.linked_type,
    wo.linked_id
    FROM deliverables d
    LEFT JOIN employees e ON d.assigned_to = e.id
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN work_orders wo ON d.work_order_id = wo.id
    WHERE d.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $deliverable_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deliverable = mysqli_fetch_assoc($result);

if (!$deliverable) {
    header('Location: index.php');
    exit;
}

// Fetch all versions
$versions_query = "SELECT dv.*, 
    CONCAT(u.username) as submitted_by_name,
    CONCAT(ui.username) as internal_approver_name
    FROM deliverable_versions dv
    LEFT JOIN users u ON dv.submitted_by = u.id
    LEFT JOIN users ui ON dv.approved_by_internal = ui.id
    WHERE dv.deliverable_id = ?
    ORDER BY dv.version_no DESC";

$versions_stmt = mysqli_prepare($conn, $versions_query);
mysqli_stmt_bind_param($versions_stmt, 'i', $deliverable_id);
mysqli_stmt_execute($versions_stmt);
$versions_result = mysqli_stmt_get_result($versions_stmt);
$versions = [];
while ($row = mysqli_fetch_assoc($versions_result)) {
    $versions[] = $row;
}

// Fetch all files
$files_query = "SELECT df.*, 
    CONCAT(u.username) as uploaded_by_name
    FROM deliverable_files df
    LEFT JOIN users u ON df.uploaded_by = u.id
    WHERE df.deliverable_id = ?
    ORDER BY df.version_no DESC, df.uploaded_at DESC";

$files_stmt = mysqli_prepare($conn, $files_query);
mysqli_stmt_bind_param($files_stmt, 'i', $deliverable_id);
mysqli_stmt_execute($files_stmt);
$files_result = mysqli_stmt_get_result($files_stmt);
$files = [];
while ($row = mysqli_fetch_assoc($files_result)) {
    $files[] = $row;
}

// Fetch activity log
$activity_query = "SELECT dal.*, 
    CONCAT(u.username) as action_by_name
    FROM deliverable_activity_log dal
    LEFT JOIN users u ON dal.action_by = u.id
    WHERE dal.deliverable_id = ?
    ORDER BY dal.created_at DESC";

$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($activity_stmt, 'i', $deliverable_id);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);
$activity_log = [];
while ($row = mysqli_fetch_assoc($activity_result)) {
    $activity_log[] = $row;
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
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.btn-success {
    background: #48bb78;
    color: white;
}

.btn-success:hover {
    background: #38a169;
}

.btn-warning {
    background: #ed8936;
    color: white;
}

.btn-warning:hover {
    background: #dd6b20;
}

.status-banner {
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.status-draft { background: #e2e8f0; color: #2d3748; }
.status-submitted { background: #bee3f8; color: #2c5282; }
.status-internal-approved { background: #c6f6d5; color: #22543d; }
.status-client-review { background: #feebc8; color: #7c2d12; }
.status-revision-requested { background: #fed7d7; color: #742a2a; }
.status-client-approved { background: #9ae6b4; color: #22543d; }
.status-delivered { background: #d6bcfa; color: #44337a; }

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

.card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.card h3 {
    font-size: 18px;
    color: #1a202c;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.info-grid {
    display: grid;
    gap: 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 13px;
    color: #718096;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 15px;
    color: #2d3748;
}

.version-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.version-item {
    padding: 16px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.version-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.version-number {
    font-weight: 700;
    color: #1a202c;
    font-size: 16px;
}

.version-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-approved { background: #c6f6d5; color: #22543d; }
.badge-pending { background: #feebc8; color: #7c2d12; }
.badge-revision { background: #fed7d7; color: #742a2a; }

.version-meta {
    font-size: 13px;
    color: #718096;
    margin-bottom: 8px;
}

.version-notes {
    font-size: 14px;
    color: #4a5568;
    background: white;
    padding: 12px;
    border-radius: 6px;
    margin-top: 8px;
}

.file-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    transition: all 0.2s;
}

.file-item:hover {
    background: #edf2f7;
}

.file-icon {
    font-size: 32px;
}

.file-info {
    flex: 1;
}

.file-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 4px;
}

.file-meta {
    font-size: 13px;
    color: #718096;
}

.file-actions a {
    color: #667eea;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

.file-actions a:hover {
    color: #5568d3;
    text-decoration: underline;
}

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
    content: "";
    position: absolute;
    left: -26px;
    top: 8px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #667eea;
}

.activity-item:after {
    content: "";
    position: absolute;
    left: -21px;
    top: 20px;
    width: 2px;
    height: calc(100% - 12px);
    background: #e2e8f0;
}

.activity-item:last-child:after {
    display: none;
}

.activity-action {
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 4px;
}

.activity-meta {
    font-size: 13px;
    color: #718096;
    margin-bottom: 6px;
}

.activity-notes {
    font-size: 14px;
    color: #4a5568;
    background: #f8fafc;
    padding: 10px;
    border-radius: 6px;
    margin-top: 6px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 12px;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-top">
                <div>
                    <h1><?php echo htmlspecialchars($deliverable['deliverable_name']); ?></h1>
                    <p>Work Order: <strong><?php echo htmlspecialchars($deliverable['work_order_code'] ?? 'N/A'); ?></strong></p>
                </div>
                <div>
                    <span class="status-banner status-<?php echo strtolower(str_replace(' ', '-', $deliverable['status'])); ?>">
                        <?php echo htmlspecialchars($deliverable['status']); ?>
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="edit.php?id=<?php echo $deliverable_id; ?>" class="btn btn-secondary">
                    <span>✏️</span> Edit Details
                </a>
                <?php if ($deliverable['status'] === 'Draft'): ?>
                    <a href="api/submit.php?id=<?php echo $deliverable_id; ?>" class="btn btn-primary" onclick="return confirm('Submit this deliverable for internal review?')">
                        <span>📤</span> Submit for Review
                    </a>
                <?php endif; ?>
                <?php if ($deliverable['status'] === 'Submitted'): ?>
                    <a href="api/approve_internal.php?id=<?php echo $deliverable_id; ?>" class="btn btn-success" onclick="return confirm('Approve this deliverable internally?')">
                        <span>✅</span> Approve (Internal)
                    </a>
                <?php endif; ?>
                <?php if ($deliverable['status'] === 'Internal Approved'): ?>
                    <a href="api/send_to_client.php?id=<?php echo $deliverable_id; ?>" class="btn btn-primary" onclick="return confirm('Send this deliverable to client for review?')">
                        <span>📧</span> Send to Client
                    </a>
                <?php endif; ?>
                <?php if ($deliverable['status'] === 'Client Review'): ?>
                    <a href="api/approve_client.php?id=<?php echo $deliverable_id; ?>" class="btn btn-success" onclick="return confirm('Mark as client approved?')">
                        <span>✨</span> Client Approved
                    </a>
                    <a href="revise.php?id=<?php echo $deliverable_id; ?>" class="btn btn-warning">
                        <span>🔄</span> Request Revision
                    </a>
                <?php endif; ?>
                <?php if ($deliverable['status'] === 'Revision Requested'): ?>
                    <a href="revise.php?id=<?php echo $deliverable_id; ?>" class="btn btn-primary">
                        <span>📝</span> Submit Revision
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">
                    <span>←</span> Back to List
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Details -->
                <div class="card">
                    <h3>📋 Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Description</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($deliverable['description'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Assigned To</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($deliverable['assigned_name']); ?> 
                                (<?php echo htmlspecialchars($deliverable['employee_code']); ?>)
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Version</span>
                            <span class="info-value">Version <?php echo $deliverable['current_version']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created By</span>
                            <span class="info-value"><?php echo htmlspecialchars($deliverable['creator_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created At</span>
                            <span class="info-value"><?php echo date('d M Y H:i', strtotime($deliverable['created_at'])); ?></span>
                        </div>
                        <?php if ($deliverable['updated_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value"><?php echo date('d M Y H:i', strtotime($deliverable['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Versions -->
                <div class="card">
                    <h3>📦 Version History (<?php echo count($versions); ?>)</h3>
                    <?php if (empty($versions)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📦</div>
                            <p>No versions yet</p>
                        </div>
                    <?php else: ?>
                        <div class="version-list">
                            <?php foreach ($versions as $version): ?>
                                <div class="version-item">
                                    <div class="version-header">
                                        <span class="version-number">Version <?php echo $version['version_no']; ?></span>
                                        <div>
                                            <?php if ($version['approval_internal']): ?>
                                                <span class="version-badge badge-approved">Internal ✓</span>
                                            <?php endif; ?>
                                            <?php if ($version['approval_client']): ?>
                                                <span class="version-badge badge-approved">Client ✓</span>
                                            <?php endif; ?>
                                            <?php if ($version['revision_requested']): ?>
                                                <span class="version-badge badge-revision">Revision</span>
                                            <?php endif; ?>
                                            <?php if (!$version['approval_internal'] && !$version['approval_client'] && !$version['revision_requested']): ?>
                                                <span class="version-badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="version-meta">
                                        Submitted by <?php echo htmlspecialchars($version['submitted_by_name']); ?> 
                                        on <?php echo date('d M Y H:i', strtotime($version['created_at'])); ?>
                                    </div>
                                    <?php if ($version['submission_notes']): ?>
                                        <div class="version-notes">
                                            <?php echo nl2br(htmlspecialchars($version['submission_notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($version['remarks']): ?>
                                        <div class="version-notes" style="border-left: 3px solid #ed8936;">
                                            <strong>Remarks:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($version['remarks'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Files -->
                <div class="card">
                    <h3>📎 Files & Attachments (<?php echo count($files); ?>)</h3>
                    <?php if (empty($files)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📎</div>
                            <p>No files uploaded</p>
                        </div>
                    <?php else: ?>
                        <div class="file-list">
                            <?php foreach ($files as $file): ?>
                                <div class="file-item">
                                    <div class="file-icon">📄</div>
                                    <div class="file-info">
                                        <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                        <div class="file-meta">
                                            Version <?php echo $file['version_no']; ?> • 
                                            <?php echo number_format($file['file_size'] / 1024, 2); ?> KB • 
                                            Uploaded by <?php echo htmlspecialchars($file['uploaded_by_name']); ?> 
                                            on <?php echo date('d M Y', strtotime($file['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">Download</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Activity Log -->
                <div class="card">
                    <h3>📜 Activity Log</h3>
                    <?php if (empty($activity_log)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📜</div>
                            <p>No activity yet</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activity_log as $log): ?>
                                <div class="activity-item">
                                    <div class="activity-action"><?php echo htmlspecialchars($log['action_type']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($log['action_by_name']); ?> • 
                                        <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                                    </div>
                                    <?php if ($log['notes']): ?>
                                        <div class="activity-notes">
                                            <?php echo htmlspecialchars($log['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>

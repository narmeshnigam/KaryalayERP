<?php
require_once __DIR__ . '/../../config/config.php';
/**
 * Work Orders Module - View Work Order Details
 */

// Removed auth_check.php include

// Permission checks removed

$work_order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$work_order_id) {
    header('Location: index.php');
    exit;
}

$page_title = "Work Order Details - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch work order details
$query = "SELECT wo.*, 
          CASE 
              WHEN wo.linked_type = 'Lead' THEN COALESCE(l.company_name, l.name)
              WHEN wo.linked_type = 'Client' THEN COALESCE(c.legal_name, c.name)
          END as linked_name,
          CASE 
              WHEN wo.linked_type = 'Lead' THEN l.name
              WHEN wo.linked_type = 'Client' THEN c.name
          END as contact_person,
          u_creator.username as creator_name,
          u_approver.username as approver_name,
          DATEDIFF(wo.due_date, CURDATE()) as days_remaining
          FROM work_orders wo
          LEFT JOIN crm_leads l ON wo.linked_type = 'Lead' AND wo.linked_id = l.id
          LEFT JOIN clients c ON wo.linked_type = 'Client' AND wo.linked_id = c.id
          LEFT JOIN users u_creator ON wo.created_by = u_creator.id
          LEFT JOIN users u_approver ON wo.internal_approver = u_approver.id
          WHERE wo.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $work_order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$work_order = mysqli_fetch_assoc($result);

if (!$work_order) {
    header('Location: index.php');
    exit;
}

// Fetch team members
$team_query = "SELECT wot.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code
               FROM work_order_team wot
               JOIN employees e ON wot.employee_id = e.id
               WHERE wot.work_order_id = ?
               ORDER BY wot.id";
$team_stmt = mysqli_prepare($conn, $team_query);
mysqli_stmt_bind_param($team_stmt, 'i', $work_order_id);
mysqli_stmt_execute($team_stmt);
$team_result = mysqli_stmt_get_result($team_stmt);
$team_members = [];
while ($row = mysqli_fetch_assoc($team_result)) {
    $team_members[] = $row;
}

// Fetch deliverables
$deliv_query = "SELECT wod.*, CONCAT(e.first_name, ' ', e.last_name) as assigned_name
                FROM work_order_deliverables wod
                LEFT JOIN employees e ON wod.assigned_to = e.id
                WHERE wod.work_order_id = ?
                ORDER BY wod.due_date";
$deliv_stmt = mysqli_prepare($conn, $deliv_query);
mysqli_stmt_bind_param($deliv_stmt, 'i', $work_order_id);
mysqli_stmt_execute($deliv_stmt);
$deliv_result = mysqli_stmt_get_result($deliv_stmt);
$deliverables = [];
while ($row = mysqli_fetch_assoc($deliv_result)) {
    $deliverables[] = $row;
}

// Fetch files
$files_query = "SELECT wof.*, u.username as uploaded_by_name
                FROM work_order_files wof
                LEFT JOIN users u ON wof.uploaded_by = u.id
                WHERE wof.work_order_id = ?
                ORDER BY wof.created_at DESC";
$files_stmt = mysqli_prepare($conn, $files_query);
mysqli_stmt_bind_param($files_stmt, 'i', $work_order_id);
mysqli_stmt_execute($files_stmt);
$files_result = mysqli_stmt_get_result($files_stmt);
$files = [];
while ($row = mysqli_fetch_assoc($files_result)) {
    $files[] = $row;
}

// Fetch activity log
$activity_query = "SELECT wol.*, u.username as action_by_name
                   FROM work_order_activity_log wol
                   LEFT JOIN users u ON wol.action_by = u.id
                   WHERE wol.work_order_id = ?
                   ORDER BY wol.created_at DESC";
$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($activity_stmt, 'i', $work_order_id);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);
$activity_log = [];
while ($row = mysqli_fetch_assoc($activity_result)) {
    $activity_log[] = $row;
}

$can_edit = true;
$can_delete = true;

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="wo-header-flex">
                <div>
                    <h1>📋 Work Order: <?php echo htmlspecialchars($work_order['work_order_code']); ?></h1>
                    <p><?php echo htmlspecialchars($work_order['service_type']); ?></p>
                </div>
                <div class="wo-header-btn">
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $work_order_id; ?>" class="btn">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-accent">← Back to List</a>
                </div>
            </div>
        </div>

        <!-- Status Banner -->
        <div class="wo-view-banner wo-view-banner-<?php echo strtolower(str_replace(' ', '-', $work_order['status'])); ?>">
            <div class="wo-view-banner-content">
                <div>
                    <strong>Status:</strong> <?php echo htmlspecialchars($work_order['status']); ?>
                    <span class="wo-priority-badge wo-priority-<?php echo strtolower($work_order['priority']); ?>" style="margin-left:15px;">
                        <?php echo htmlspecialchars($work_order['priority']); ?> Priority
                    </span>
                </div>
                <div>
                    <?php if ($work_order['days_remaining'] < 0): ?>
                        <span class="wo-overdue-label">⚠️ Overdue by <?php echo abs($work_order['days_remaining']); ?> days</span>
                    <?php elseif ($work_order['days_remaining'] <= 3): ?>
                        <span class="wo-due-soon-label">⏰ Due in <?php echo $work_order['days_remaining']; ?> days</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="wo-view-grid">
            <!-- Left Column -->
            <div class="wo-view-left">
                <!-- Basic Information -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">📄 Basic Information</h3>
                    <div class="wo-info-grid">
                        <div class="wo-info-item">
                            <span class="wo-info-label">Work Order Code</span>
                            <span class="wo-info-value"><?php echo htmlspecialchars($work_order['work_order_code']); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Order Date</span>
                            <span class="wo-info-value"><?php echo date('d M Y', strtotime($work_order['order_date'])); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Linked To</span>
                            <span class="wo-info-value">
                                <span class="wo-type-badge wo-type-<?php echo strtolower($work_order['linked_type']); ?>">
                                    <?php echo htmlspecialchars($work_order['linked_type']); ?>
                                </span>
                                <?php echo htmlspecialchars($work_order['linked_name']); ?>
                            </span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Contact Person</span>
                            <span class="wo-info-value"><?php echo htmlspecialchars($work_order['contact_person']); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Service Type</span>
                            <span class="wo-info-value"><?php echo htmlspecialchars($work_order['service_type']); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Start Date</span>
                            <span class="wo-info-value"><?php echo date('d M Y', strtotime($work_order['start_date'])); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">Due Date</span>
                            <span class="wo-info-value"><?php echo date('d M Y', strtotime($work_order['due_date'])); ?></span>
                        </div>
                        <div class="wo-info-item">
                            <span class="wo-info-label">TAT (Days)</span>
                            <span class="wo-info-value"><?php echo $work_order['TAT_days'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="wo-info-item wo-info-full">
                            <span class="wo-info-label">Description</span>
                            <span class="wo-info-value"><?php echo nl2br(htmlspecialchars($work_order['description'])); ?></span>
                        </div>
                        <?php if ($work_order['dependencies']): ?>
                        <div class="wo-info-item wo-info-full">
                            <span class="wo-info-label">Dependencies</span>
                            <span class="wo-info-value"><?php echo nl2br(htmlspecialchars($work_order['dependencies'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($work_order['exceptions']): ?>
                        <div class="wo-info-item wo-info-full">
                            <span class="wo-info-label">Exceptions</span>
                            <span class="wo-info-value"><?php echo nl2br(htmlspecialchars($work_order['exceptions'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($work_order['remarks']): ?>
                        <div class="wo-info-item wo-info-full">
                            <span class="wo-info-label">Remarks</span>
                            <span class="wo-info-value"><?php echo nl2br(htmlspecialchars($work_order['remarks'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Deliverables -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">📦 Deliverables (<?php echo count($deliverables); ?>)</h3>
                    <?php if (empty($deliverables)): ?>
                        <p class="wo-empty-state">No deliverables defined</p>
                    <?php else: ?>
                        <div class="wo-deliverables-list">
                            <?php foreach ($deliverables as $deliv): ?>
                                <div class="wo-deliverable-card">
                                    <div class="wo-deliverable-header">
                                        <strong><?php echo htmlspecialchars($deliv['deliverable_name']); ?></strong>
                                        <span class="wo-status-badge wo-status-<?php echo strtolower(str_replace(' ', '-', $deliv['delivery_status'])); ?>">
                                            <?php echo htmlspecialchars($deliv['delivery_status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($deliv['description']): ?>
                                        <p class="wo-deliverable-desc"><?php echo htmlspecialchars($deliv['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="wo-deliverable-meta">
                                        <span>👤 <?php echo htmlspecialchars($deliv['assigned_name']); ?></span>
                                        <span>📅 <?php echo date('d M', strtotime($deliv['start_date'])); ?> - <?php echo date('d M Y', strtotime($deliv['due_date'])); ?></span>
                                    </div>
                                    <?php if (!empty($deliv['delivered_date'])): ?>
                                        <div class="wo-deliverable-delivered">✅ Delivered: <?php echo date('d M Y H:i', strtotime($deliv['delivered_date'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Team Members -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">👥 Team Members (<?php echo count($team_members); ?>)</h3>
                    <?php if (empty($team_members)): ?>
                        <p class="wo-empty-state">No team members assigned</p>
                    <?php else: ?>
                        <div class="wo-team-list">
                            <?php foreach ($team_members as $member): ?>
                                <div class="wo-team-card">
                                    <div class="wo-team-info">
                                        <strong><?php echo htmlspecialchars($member['employee_name']); ?></strong>
                                        <span class="wo-team-code"><?php echo htmlspecialchars($member['employee_code']); ?></span>
                                    </div>
                                    <div class="wo-team-role"><?php echo htmlspecialchars($member['role']); ?></div>
                                    <?php if ($member['remarks']): ?>
                                        <div class="wo-team-remarks"><?php echo htmlspecialchars($member['remarks']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="wo-view-right">
                <!-- Approval Status -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">✅ Approval Status</h3>
                    <div class="wo-approval-section">
                        <div class="wo-approval-item">
                            <span class="wo-approval-label">Internal Approver</span>
                            <span class="wo-approval-value"><?php echo $work_order['approver_name'] ? htmlspecialchars($work_order['approver_name']) : 'Not assigned'; ?></span>
                        </div>
                        <div class="wo-approval-item">
                            <span class="wo-approval-label">Internal Status</span>
                            <span class="wo-approval-value">
                                <?php if (!empty($work_order['internal_approval_date'])): ?>
                                    <span class="wo-approved">✅ Approved</span>
                                    <br><small><?php echo date('d M Y', strtotime($work_order['internal_approval_date'])); ?></small>
                                <?php else: ?>
                                    <span class="wo-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="wo-approval-item">
                            <span class="wo-approval-label">Client Approval</span>
                            <span class="wo-approval-value">
                                <?php if (!empty($work_order['client_approval_date'])): ?>
                                    <span class="wo-approved">✅ Approved</span>
                                    <br><small><?php echo date('d M Y', strtotime($work_order['client_approval_date'])); ?></small>
                                <?php else: ?>
                                    <span class="wo-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Files -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">📎 Attachments (<?php echo count($files); ?>)</h3>
                    <?php if (empty($files)): ?>
                        <p class="wo-empty-state">No files attached</p>
                    <?php else: ?>
                        <div class="wo-files-list">
                            <?php foreach ($files as $file): ?>
                                <div class="wo-file-item">
                                    <div class="wo-file-icon">📄</div>
                                    <div class="wo-file-info">
                                        <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="wo-file-name">
                                            <?php echo htmlspecialchars($file['file_name']); ?>
                                        </a>
                                        <div class="wo-file-meta">
                                            <small>By <?php echo htmlspecialchars($file['uploaded_by_name']); ?> on <?php echo date('d M Y', strtotime($file['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Log -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">📜 Activity Log</h3>
                    <?php if (empty($activity_log)): ?>
                        <p class="wo-empty-state">No activity recorded</p>
                    <?php else: ?>
                        <div class="wo-activity-timeline">
                            <?php foreach ($activity_log as $log): ?>
                                <div class="wo-activity-item">
                                    <div class="wo-activity-dot"></div>
                                    <div class="wo-activity-content">
                                        <div class="wo-activity-action"><?php echo htmlspecialchars($log['action_type']); ?></div>
                                        <div class="wo-activity-meta">
                                            <?php echo htmlspecialchars($log['action_by_name']); ?> • 
                                            <?php echo !empty($log['created_at']) ? date('d M Y H:i', strtotime($log['created_at'])) : 'N/A'; ?>
                                        </div>
                                        <?php if ($log['description']): ?>
                                            <div class="wo-activity-remarks"><?php echo htmlspecialchars($log['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Metadata -->
                <div class="card wo-card">
                    <h3 class="wo-section-title">ℹ️ Metadata</h3>
                    <div class="wo-meta-list">
                        <div class="wo-meta-item">
                            <span class="wo-meta-label">Created By</span>
                            <span class="wo-meta-value"><?php echo htmlspecialchars($work_order['creator_name']); ?></span>
                        </div>
                        <div class="wo-meta-item">
                            <span class="wo-meta-label">Created At</span>
                            <span class="wo-meta-value"><?php echo date('d M Y H:i', strtotime($work_order['created_at'])); ?></span>
                        </div>
                        <?php if ($work_order['updated_at']): ?>
                        <div class="wo-meta-item">
                            <span class="wo-meta-label">Last Updated</span>
                            <span class="wo-meta-value"><?php echo date('d M Y H:i', strtotime($work_order['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

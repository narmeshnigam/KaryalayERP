<?php
/**
 * Projects Module - View Project Page
 * Comprehensive project overview with tabs: Overview, Tasks, Phases, Members, Documents, Activity
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'projects', 'view');

// Check if tables exist
if (!projects_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/scripts/setup_projects_tables.php');
    exit;
}

// Get project ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}

// Get project details
$project = get_project_by_id($conn, $project_id);
if (!$project) {
    $_SESSION['flash_message'] = "Project not found.";
    $_SESSION['flash_type'] = "error";
    header('Location: index.php');
    exit;
}

// Get KPIs
$kpis = get_project_kpis($conn, $project_id);

// Get project members
$members = get_project_members($conn, $project_id);

// Get recent activity
$recent_activity = get_project_activity($conn, $project_id, 10);

// Get phases
$phases_query = $conn->prepare("SELECT * FROM project_phases WHERE project_id = ? ORDER BY sequence_order ASC, id ASC");
$phases_query->bind_param("i", $project_id);
$phases_query->execute();
$phases = $phases_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent tasks
$tasks_query = $conn->prepare("
    SELECT t.*, 
           ph.title as phase_title,
           GROUP_CONCAT(u.username SEPARATOR ', ') as assignees
    FROM project_tasks t
    LEFT JOIN project_phases ph ON t.phase_id = ph.id
    LEFT JOIN project_task_assignees ta ON t.id = ta.task_id
    LEFT JOIN users u ON ta.user_id = u.id
    WHERE t.project_id = ?
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 10
");
$tasks_query->bind_param("i", $project_id);
$tasks_query->execute();
$recent_tasks = $tasks_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent documents
$docs_query = $conn->prepare("
    SELECT d.*, u.username as uploaded_by_name
    FROM project_documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.project_id = ? AND d.is_active = 1
    ORDER BY d.uploaded_at DESC
    LIMIT 5
");
$docs_query->bind_param("i", $project_id);
$docs_query->execute();
$recent_documents = $docs_query->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = $project['title'] . ' - Projects - ' . APP_NAME;
$active_tab = $_GET['tab'] ?? 'overview';
$can_edit = authz_user_can($conn, 'projects', 'update');

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.project-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #003581, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    border-radius: 12px;
    flex-shrink: 0;
}

.project-header-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #6c757d;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.kpi-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.kpi-value {
    font-size: 32px;
    font-weight: 700;
    color: #003581;
    margin-bottom: 4px;
}

.kpi-label {
    font-size: 14px;
    color: #6c757d;
}

.member-avatar {
    width: 40px;
    height: 40px;
    background: #003581;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border-radius: 50%;
    flex-shrink: 0;
}

.activity-item {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 8px;
}

.activity-icon {
    width: 32px;
    height: 32px;
    background: #003581;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    flex-shrink: 0;
    font-size: 14px;
}

.progress-section {
    margin: 16px 0;
}

.progress-bar-container {
    background: #e9ecef;
    height: 24px;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #003581, #0056b3);
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
<style>
.projects-view-header-flex{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:8px;}
.projects-view-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:768px){
.projects-view-header-flex{flex-direction:column;align-items:stretch;}
.projects-view-header-buttons{width:100%;flex-direction:column;gap:10px;}
.projects-view-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.projects-view-header-flex h1{font-size:1.5rem;}
}

/* Header Info Section Responsive */
.projects-view-header-info{display:flex;gap:20px;align-items:flex-start;margin-bottom:20px;}

@media (max-width:768px){
.projects-view-header-info{flex-direction:column;gap:16px;}
.project-avatar{width:60px;height:60px;font-size:24px;}
}

@media (max-width:480px){
.projects-view-header-info{gap:12px;}
.project-avatar{width:50px;height:50px;font-size:20px;border-radius:8px;}
}

/* Header Meta Responsive */
.project-header-meta{display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;}

@media (max-width:768px){
.project-header-meta{gap:12px;}
.meta-item{font-size:13px;}
}

@media (max-width:480px){
.project-header-meta{gap:8px;}
.meta-item{font-size:12px;}
}

/* Progress Section Responsive */
.progress-section{margin-top:16px;}

@media (max-width:480px){
.progress-section{font-size:13px;}
}

/* Tabs Responsive */
.projects-view-tabs{display:flex;gap:0;border-bottom:2px solid #e9ecef;margin-bottom:24px;overflow-x:auto;}

@media (max-width:768px){
.projects-view-tabs a{padding:12px 16px;font-size:14px;}
}

@media (max-width:480px){
.projects-view-tabs{margin-bottom:16px;}
.projects-view-tabs a{padding:10px 12px;font-size:13px;}
}

/* KPI Grid Responsive */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}

@media (max-width:768px){
.kpi-grid{grid-template-columns:repeat(3,1fr);gap:12px;}
}

@media (max-width:480px){
.kpi-grid{grid-template-columns:repeat(2,1fr);gap:10px;}
.kpi-card{padding:16px;}
.kpi-value{font-size:24px;}
.kpi-label{font-size:12px;}
}

/* Overview Grid Responsive */
.projects-view-overview-grid{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;}

@media (max-width:768px){
.projects-view-overview-grid{grid-template-columns:1fr;gap:16px;}
}

@media (max-width:480px){
.projects-view-overview-grid{gap:12px;}
}

/* Members List Responsive */
.projects-view-members-list{display:flex;flex-direction:column;gap:12px;}

@media (max-width:480px){
.projects-view-members-list{gap:10px;}
.member-avatar{width:32px;height:32px;font-size:12px;}
}

/* Recent Items Lists Responsive */
.projects-view-recent-list{display:flex;flex-direction:column;gap:12px;}

@media (max-width:480px){
.projects-view-recent-list{gap:10px;}
}

/* Table Responsive - Card Style on Mobile */
.projects-view-table-wrapper{overflow-x:auto;}

@media (max-width:600px){
.projects-view-table-wrapper table{display:block;}
.projects-view-table-wrapper thead{display:none;}
.projects-view-table-wrapper tbody{display:block;}
.projects-view-table-wrapper tr{display:block;margin-bottom:16px;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;padding:0;}
.projects-view-table-wrapper td{display:block;padding:12px;border:none;border-bottom:1px solid #e9ecef;text-align:left;}
.projects-view-table-wrapper td:last-child{border-bottom:none;}
.projects-view-table-wrapper td::before{content:attr(data-label);font-weight:600;color:#003581;display:block;font-size:12px;margin-bottom:4px;}
}

@media (max-width:480px){
.projects-view-table-wrapper tr{margin-bottom:12px;}
.projects-view-table-wrapper td{padding:10px;font-size:13px;}
.projects-view-table-wrapper td::before{font-size:11px;}
}
</style>

        <!-- Project Header -->
        <div class="page-header">
            <div class="projects-view-header-info">
                <div class="project-avatar">
                    <?= get_project_initials($project['title']) ?>
                </div>
                <div style="flex: 1;">
                    <div class="projects-view-header-flex">
                        <div>
                            <h1 style="margin: 0 0 4px 0; font-size: 28px;">
                                <?= htmlspecialchars($project['title']) ?>
                            </h1>
                            <span style="font-size: 14px; color: #6c757d; font-family: monospace;">
                                #<?= htmlspecialchars($project['project_code']) ?>
                            </span>
                        </div>
                        <div class="projects-view-header-buttons">
                            <?php if ($can_edit): ?>
                                <a href="edit.php?id=<?= $project_id ?>" class="btn" style="text-decoration: none;">✏️ Edit</a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back</a>
                        </div>
                    </div>
                    
                    <div class="project-header-meta">
                        <div class="meta-item">
                            <?= get_project_type_icon($project['type']) ?>
                            <strong><?= htmlspecialchars($project['type']) ?></strong>
                        </div>
                        
                        <?php if ($project['client_name']): ?>
                            <div class="meta-item">
                                🏢 <a href="../clients/view.php?id=<?= $project['client_id'] ?>" 
                                      style="color: #003581; text-decoration: none;">
                                    <?= htmlspecialchars($project['client_name']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            👤 <strong>Owner:</strong> <?= htmlspecialchars($project['owner_username']) ?>
                        </div>
                        
                        <div class="meta-item">
                            <?= get_priority_icon($project['priority']) ?>
                            <span class="badge" style="background: 
                                <?= $project['priority'] === 'Critical' ? '#dc3545' : 
                                    ($project['priority'] === 'High' ? '#faa718' : 
                                    ($project['priority'] === 'Medium' ? '#ffc107' : '#17a2b8')) ?>;">
                                <?= htmlspecialchars($project['priority']) ?>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <?= get_project_status_icon($project['status']) ?>
                            <span class="badge" style="background: 
                                <?= $project['status'] === 'Completed' ? '#28a745' : 
                                    ($project['status'] === 'Active' ? '#003581' : '#6c757d') ?>;">
                                <?= htmlspecialchars($project['status']) ?>
                            </span>
                        </div>
                        
                        <?php if ($project['start_date'] || $project['end_date']): ?>
                            <div class="meta-item">
                                📅 <?= $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : 'Not set' ?>
                                → <?= $project['end_date'] ? date('M j, Y', strtotime($project['end_date'])) : 'Not set' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($project['tags']): ?>
                        <div style="margin-top: 12px; display: flex; gap: 6px; flex-wrap: wrap;">
                            <?php foreach (explode(',', $project['tags']) as $tag): ?>
                                <span class="badge" style="background: #e9ecef; color: #495057;">
                                    🏷️ <?= htmlspecialchars(trim($tag)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-section">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-weight: 600; color: #003581;">Overall Progress</span>
                    <span style="font-weight: 600; color: #003581;"><?= number_format($project['progress'], 1) ?>%</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $project['progress'] ?>%">
                        <?php if ($project['progress'] > 15): ?>
                            <?= number_format($project['progress'], 0) ?>%
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Tabs -->
        <div class="projects-view-tabs">
            <a href="?id=<?= $project_id ?>&tab=overview" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'overview' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'overview' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                📊 Overview
            </a>
            <a href="?id=<?= $project_id ?>&tab=tasks" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'tasks' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'tasks' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                ✅ Tasks <?php if (!empty($kpis['total_tasks'])): ?>(<?= $kpis['total_tasks'] ?>)<?php endif; ?>
            </a>
            <a href="?id=<?= $project_id ?>&tab=phases" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'phases' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'phases' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                📋 Phases <?php if (!empty($kpis['total_phases'])): ?>(<?= $kpis['total_phases'] ?>)<?php endif; ?>
            </a>
            <a href="?id=<?= $project_id ?>&tab=members" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'members' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'members' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                👥 Team <?php if (!empty($kpis['total_members'])): ?>(<?= $kpis['total_members'] ?>)<?php endif; ?>
            </a>
            <a href="?id=<?= $project_id ?>&tab=documents" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'documents' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'documents' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                📎 Documents <?php if (!empty($kpis['total_documents'])): ?>(<?= $kpis['total_documents'] ?>)<?php endif; ?>
            </a>
            <a href="?id=<?= $project_id ?>&tab=activity" 
               style="padding: 16px 24px; text-decoration: none; white-space: nowrap; border-bottom: 3px solid <?= $active_tab === 'activity' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'activity' ? '#003581' : '#6c757d' ?>; font-weight: 600; transition: all 0.3s;">
                📝 Activity
            </a>
        </div>

        <!-- Tab Content -->
        <?php if ($active_tab === 'overview'): ?>
            
            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?= $kpis['total_tasks'] ?? 0 ?></div>
                    <div class="kpi-label">Total Tasks</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #28a745;"><?= $kpis['completed_tasks'] ?? 0 ?></div>
                    <div class="kpi-label">Completed</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #dc3545;"><?= $kpis['overdue_tasks'] ?? 0 ?></div>
                    <div class="kpi-label">Overdue</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value"><?= $kpis['total_phases'] ?? 0 ?></div>
                    <div class="kpi-label">Phases</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value"><?= $kpis['total_members'] ?? 0 ?></div>
                    <div class="kpi-label">Team Members</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value"><?= $kpis['total_documents'] ?? 0 ?></div>
                    <div class="kpi-label">Documents</div>
                </div>
            </div>

            <div class="projects-view-overview-grid">
                <!-- Description -->
                <div class="card">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px;">
                        📝 Description
                    </h3>
                    <?php if ($project['description']): ?>
                        <p style="white-space: pre-wrap; color: #495057;">
                            <?= nl2br(htmlspecialchars($project['description'])) ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #6c757d; font-style: italic;">No description provided</p>
                    <?php endif; ?>
                </div>

                <!-- Team Members -->
                <div class="card">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px;">
                        👥 Team Members
                    </h3>
                    <?php if (!empty($members)): ?>
                        <div class="projects-view-members-list">
                            <?php foreach (array_slice($members, 0, 5) as $member): ?>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="member-avatar">
                                        <?= strtoupper(substr($member['username'], 0, 2)) ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #1b2a57;">
                                            <?= htmlspecialchars($member['username']) ?>
                                        </div>
                                        <div style="font-size: 13px; color: #6c757d;">
                                            <?= htmlspecialchars($member['role']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($members) > 5): ?>
                            <a href="?id=<?= $project_id ?>&tab=members" style="display: block; margin-top: 12px; text-align: center; color: #003581;">
                                View all <?= count($members) ?> members →
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #6c757d; font-style: italic;">No team members</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phases -->
            <?php if (!empty($phases)): ?>
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px;">
                        📋 Phases
                    </h3>
                    <div class="projects-view-recent-list">
                        <?php foreach (array_slice($phases, 0, 5) as $phase): ?>
                            <div style="padding: 16px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid 
                                <?= $phase['status'] === 'Completed' ? '#28a745' : ($phase['status'] === 'In Progress' ? '#003581' : '#6c757d') ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                    <h4 style="margin: 0; color: #1b2a57;"><?= htmlspecialchars($phase['title']) ?></h4>
                                    <span class="badge" style="background: 
                                        <?= $phase['status'] === 'Completed' ? '#28a745' : ($phase['status'] === 'In Progress' ? '#003581' : '#6c757d') ?>;">
                                        <?= htmlspecialchars($phase['status']) ?>
                                    </span>
                                </div>
                                <?php if ($phase['description']): ?>
                                    <p style="margin: 0 0 8px 0; color: #6c757d; font-size: 14px;">
                                        <?= htmlspecialchars($phase['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="progress-bar-container" style="height: 8px;">
                                    <div class="progress-bar-fill" style="width: <?= $phase['progress'] ?>%; font-size: 0;"></div>
                                </div>
                                <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                                    <?= number_format($phase['progress'], 0) ?>% complete
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($phases) > 5): ?>
                        <a href="?id=<?= $project_id ?>&tab=phases" style="display: block; margin-top: 12px; text-align: center; color: #003581;">
                            View all <?= count($phases) ?> phases →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="card">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px;">
                    📝 Recent Activity
                </h3>
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $icon = match($activity['activity_type']) {
                                    'Task' => '✅',
                                    'Phase' => '�',
                                    'Document' => '�',
                                    'Status' => '🔄',
                                    'Member' => '�',
                                    default => '📝'
                                };
                                echo $icon;
                                ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: #1b2a57; margin-bottom: 4px;">
                                    <strong><?= htmlspecialchars($activity['username']) ?></strong>
                                    <?= htmlspecialchars($activity['description']) ?>
                                </div>
                                <div style="font-size: 13px; color: #6c757d;">
                                    <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="activity.php?project_id=<?= $project_id ?>" style="display: block; margin-top: 12px; text-align: center; color: #003581;">
                        View full activity →
                    </a>
                <?php else: ?>
                    <p style="color: #6c757d; font-style: italic;">No activity yet</p>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'tasks'): ?>
            
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin:0;">
                        ✅ Project Tasks
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <a href="tasks.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Tasks</a>
                    </div>
                </div>
                
                <?php if (!empty($recent_tasks)): ?>
                    <div class="projects-view-table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Phase</th>
                                <th>Assignees</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tasks as $task): ?>
                                <tr>
                                    <td data-label="Task"><?= htmlspecialchars($task['title']) ?></td>
                                    <td data-label="Phase"><?= $task['phase_title'] ? htmlspecialchars($task['phase_title']) : '-' ?></td>
                                    <td data-label="Assignees"><?= $task['assignees'] ? htmlspecialchars($task['assignees']) : 'Unassigned' ?></td>
                                    <td data-label="Priority">
                                        <span class="badge" style="background: 
                                            <?= $task['priority'] === 'Critical' ? '#dc3545' : 
                                                ($task['priority'] === 'High' ? '#faa718' : 
                                                ($task['priority'] === 'Medium' ? '#ffc107' : '#17a2b8')) ?>;">
                                            <?= get_priority_icon($task['priority']) ?> <?= htmlspecialchars($task['priority']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Due Date"><?= $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : '-' ?></td>
                                    <td data-label="Progress"><?= number_format($task['progress'], 0) ?>%</td>
                                    <td data-label="Status">
                                        <span class="badge" style="background: 
                                            <?= $task['status'] === 'Completed' ? '#28a745' : 
                                                ($task['status'] === 'In Progress' ? '#003581' : '#6c757d') ?>;">
                                            <?= htmlspecialchars($task['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                        <p style="font-size: 18px; margin-bottom: 8px;">No tasks yet</p>
                        <p>Use the Manage Tasks button to add your first task.</p>
                        <div style="margin-top:12px;">
                            <a href="tasks.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Tasks</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'phases'): ?>
            
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin:0;">
                        📋 Project Phases
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <a href="phases.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Phases</a>
                    </div>
                </div>
                
                <?php if (!empty($phases)): ?>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($phases as $phase): ?>
                            <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid 
                                <?= $phase['status'] === 'Completed' ? '#28a745' : ($phase['status'] === 'In Progress' ? '#003581' : '#6c757d') ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <h4 style="margin: 0 0 4px 0; color: #1b2a57;"><?= htmlspecialchars($phase['title']) ?></h4>
                                        <div style="font-size: 14px; color: #6c757d;">
                                            Sequence: <?= $phase['sequence_order'] ?>
                                        </div>
                                    </div>
                                    <span class="badge" style="background: 
                                        <?= $phase['status'] === 'Completed' ? '#28a745' : ($phase['status'] === 'In Progress' ? '#003581' : '#6c757d') ?>;">
                                        <?= htmlspecialchars($phase['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if ($phase['description']): ?>
                                    <p style="margin: 0 0 12px 0; color: #6c757d;">
                                        <?= nl2br(htmlspecialchars($phase['description'])) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="progress-bar-container" style="margin-bottom: 8px;">
                                    <div class="progress-bar-fill" style="width: <?= $phase['progress'] ?>%">
                                        <?php if ($phase['progress'] > 15): ?>
                                            <?= number_format($phase['progress'], 0) ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #6c757d;">
                                    <span><?= number_format($phase['progress'], 0) ?>% complete</span>
                                    <?php if ($phase['start_date'] || $phase['end_date']): ?>
                                        <span>
                                            📅 <?= $phase['start_date'] ? date('M j', strtotime($phase['start_date'])) : '?' ?>
                                            → <?= $phase['end_date'] ? date('M j, Y', strtotime($phase['end_date'])) : '?' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                        <p style="font-size: 18px; margin-bottom: 8px;">No phases defined</p>
                        <p>Phases help organize project milestones and will be managed in future updates</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'members'): ?>
            
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin:0;">
                        👥 Project Team
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <a href="members.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Members</a>
                    </div>
                </div>
                
                <?php if (!empty($members)): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                        <?php foreach ($members as $member): ?>
                            <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: #f8f9fa; border-radius: 8px;">
                                <div class="member-avatar" style="width: 60px; height: 60px; font-size: 24px;">
                                    <?= strtoupper(substr($member['username'], 0, 2)) ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; color: #1b2a57; margin-bottom: 4px;">
                                        <?= htmlspecialchars($member['username']) ?>
                                    </div>
                                    <div style="font-size: 14px; color: #6c757d; margin-bottom: 4px;">
                                        <?= htmlspecialchars($member['role']) ?>
                                    </div>
                                    <div style="font-size: 13px; color: #6c757d;">
                                        Joined: <?= date('M j, Y', strtotime($member['joined_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                        <p style="font-size: 18px; margin-bottom: 8px;">No team members</p>
                        <p>Invite members to collaborate on this project</p>
                        <div style="margin-top:12px;">
                            <a href="members.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Members</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'documents'): ?>
            
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin:0;">
                        📎 Project Documents
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <a href="documents.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Documents</a>
                    </div>
                </div>
                
                <?php if (!empty($recent_documents)): ?>
                    <div class="projects-view-table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th>Version</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_documents as $doc): ?>
                                <tr>
                                    <td data-label="Document">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 24px;">📄</span>
                                            <?= htmlspecialchars($doc['file_name']) ?>
                                        </div>
                                    </td>
                                    <td data-label="Type"><?= htmlspecialchars($doc['doc_type']) ?></td>
                                    <td data-label="Version">v<?= $doc['version'] ?></td>
                                    <td data-label="Uploaded By"><?= htmlspecialchars($doc['uploaded_by_name']) ?></td>
                                    <td data-label="Date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></td>
                                    <td data-label="Actions">
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-sm btn-primary" download>
                                            ⬇️ Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📎</div>
                        <p style="font-size: 18px; margin-bottom: 8px;">No documents</p>
                        <p>Use the Manage Documents button to upload the first document.</p>
                        <div style="margin-top:12px;">
                            <a href="documents.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Manage Documents</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'activity'): ?>
            
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin:0;">
                        📝 Activity Log
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <a href="activity.php?project_id=<?= $project_id ?>" class="btn" style="text-decoration: none;">Open Activity</a>
                    </div>
                </div>
                
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $icon = match($activity['activity_type']) {
                                    'Task' => '✅',
                                    'Phase' => '�',
                                    'Document' => '�',
                                    'Status' => '🔄',
                                    'Member' => '�',
                                    default => '📝'
                                };
                                echo $icon;
                                ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: #1b2a57; margin-bottom: 4px;">
                                    <strong><?= htmlspecialchars($activity['username']) ?></strong>
                                    <?= htmlspecialchars($activity['description']) ?>
                                </div>
                                <div style="font-size: 13px; color: #6c757d;">
                                    <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                        <p style="font-size: 18px; margin-bottom: 8px;">No activity yet</p>
                        <p>Activity will be logged as changes are made</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

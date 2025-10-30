<?php
/**
 * Projects Dashboard - Comprehensive Project Management & Analytics
 * Combines project listing with advanced analytics (admin/manager only)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'projects', 'view');

// Check if tables exist
if (!projects_tables_exist($conn)) {
    header('Location: /KaryalayERP/scripts/setup_projects_tables.php');
    exit;
}

// Get filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['type'])) {
    $filters['type'] = $_GET['type'];
}
if (!empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (!empty($_GET['owner_id'])) {
    $filters['owner_id'] = (int)$_GET['owner_id'];
}
if (!empty($_GET['client_id'])) {
    $filters['client_id'] = (int)$_GET['client_id'];
}
if (isset($_GET['my_projects']) && $_GET['my_projects'] === '1') {
    $filters['my_projects'] = true;
}

// Get all projects with filters
$projects = get_all_projects($conn, $_SESSION['user_id'], $filters);

// Get statistics
$stats = get_project_statistics($conn, $_SESSION['user_id']);

// Get all owners for filter
$owners = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Get all clients for filter (if exists)
$clients = [];
if ($conn->query("SHOW TABLES LIKE 'clients'")->num_rows > 0) {
    $clients = $conn->query("SELECT id, name, code FROM clients ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
}

// Check permissions
$can_create = authz_user_can($conn, 'projects', 'create');
$can_update = authz_user_can($conn, 'projects', 'update');

// Check if user is admin or manager for analytics view
$is_admin_or_manager = in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true);

$page_title = 'Projects Dashboard - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    /* Dashboard Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .dashboard-header h2 {
        margin: 0;
    }
    
    .dashboard-header p {
        margin: 0.5rem 0 0 0;
        color: #6c757d;
    }
    
    /* View Toggle */
    .view-toggle {
        display: inline-flex;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .view-toggle-btn {
        padding: 0.5rem 1rem;
        border: none;
        background: white;
        color: #666;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .view-toggle-btn:hover {
        background: #f8f9fa;
    }
    
    .view-toggle-btn.active {
        background: #007bff;
        color: white;
    }
    
    /* View Containers */
    .projects-list,
    .analytics-view {
        display: none;
    }
    
    .projects-list.active,
    .analytics-view.active {
        display: block;
    }
    
    /* Statistics Cards */
    .stat-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .stat-content {
        flex: 1;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1b2a57;
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }
    
    /* Analytics KPI Cards */
    .kpi-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .kpi-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #007bff;
    }
    
    .kpi-card.warning { border-left-color: #ffc107; }
    .kpi-card.success { border-left-color: #28a745; }
    .kpi-card.danger { border-left-color: #dc3545; }
    
    .kpi-value {
        font-size: 2rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.5rem;
    }
    
    .kpi-label {
        font-size: 0.875rem;
        color: #666;
        text-transform: uppercase;
    }
    
    /* Analytics Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 968px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .dashboard-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
    }
    
    .workload-table,
    .project-item {
        width: 100%;
    }
    
    .workload-table th,
    .workload-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .workload-table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.875rem;
        color: #666;
        text-transform: uppercase;
    }
    
    .efficiency-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .efficiency-progress {
        flex: 1;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .efficiency-fill {
        height: 100%;
        background: #28a745;
        transition: width 0.3s ease;
    }
    
    .efficiency-fill.low { background: #dc3545; }
    .efficiency-fill.medium { background: #ffc107; }
    
    .alert-item {
        padding: 1rem;
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
        margin-bottom: 0.75rem;
    }
    
    .alert-item.deadline { background: #f8d7da; border-left-color: #dc3545; }
    
    .alert-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.25rem;
    }
    
    .alert-desc {
        font-size: 0.875rem;
        color: #666;
    }
    
    .project-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .project-item:last-child {
        border-bottom: none;
    }
    
    .project-name {
        font-weight: 500;
        color: #007bff;
        text-decoration: none;
    }
    
    .project-name:hover {
        text-decoration: underline;
    }
    
    .project-meta {
        font-size: 0.875rem;
        color: #666;
    }
    
    .loading-state {
        text-align: center;
        padding: 2rem;
        color: #666;
    }
    
    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #007bff;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="dashboard-header">
            <div style="flex: 1;">
                <h2>🚀 Projects Dashboard</h2>
                <p>Comprehensive project management and analytics</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <?php if ($is_admin_or_manager): ?>
                <div class="view-toggle">
                    <button class="view-toggle-btn active" onclick="switchView('list')" id="listViewBtn">
                        📋 Projects List
                    </button>
                    <button class="view-toggle-btn" onclick="switchView('analytics')" id="analyticsViewBtn">
                        📊 Analytics
                    </button>
                </div>
                <?php endif; ?>
                <?php if ($can_create): ?>
                    <a href="add.php" class="btn btn-primary">➕ New Project</a>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- PROJECTS LIST VIEW -->
        <div class="projects-list active" id="projectsList">
            
            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd;">📊</div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total Projects</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9;">🚀</div>
                    <div class="stat-content">
                        <div class="stat-value" style="color: #28a745;"><?= $stats['active'] ?></div>
                        <div class="stat-label">Active Projects</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0;">⏱️</div>
                    <div class="stat-content">
                        <div class="stat-value" style="color: #ff9800;"><?= $stats['overdue'] ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f3e5f5;">👤</div>
                    <div class="stat-content">
                        <div class="stat-value" style="color: #6f42c1;"><?= $stats['my_projects'] ?></div>
                        <div class="stat-label">My Projects</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0f2f1;">✅</div>
                    <div class="stat-content">
                        <div class="stat-value" style="color: #00796b;"><?= $stats['completed'] ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card" style="margin-bottom: 24px;">
                <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🔍 Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Title, code, description..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">📊 Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Pending" <?= ($_GET['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="In Progress" <?= ($_GET['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>🚀 In Progress</option>
                            <option value="On Hold" <?= ($_GET['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>⏸️ On Hold</option>
                            <option value="Completed" <?= ($_GET['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>✅ Completed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🏷️ Type</label>
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Internal" <?= ($_GET['type'] ?? '') === 'Internal' ? 'selected' : '' ?>>🏠 Internal</option>
                            <option value="Client" <?= ($_GET['type'] ?? '') === 'Client' ? 'selected' : '' ?>>🏢 Client</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">⚡ Priority</label>
                        <select name="priority" class="form-control">
                            <option value="">All Priorities</option>
                            <option value="Low" <?= ($_GET['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>🔵 Low</option>
                            <option value="Medium" <?= ($_GET['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>🟡 Medium</option>
                            <option value="High" <?= ($_GET['priority'] ?? '') === 'High' ? 'selected' : '' ?>>🟠 High</option>
                            <option value="Critical" <?= ($_GET['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>🔴 Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">👤 Owner</label>
                        <select name="owner_id" class="form-control">
                            <option value="">All Owners</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?= $owner['id'] ?>" 
                                    <?= ($_GET['owner_id'] ?? '') == $owner['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($owner['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($clients)): ?>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🏢 Client</label>
                            <select name="client_id" class="form-control">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>" 
                                        <?= ($_GET['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Apply Filters</button>
                        <a href="dashboard.php" class="btn btn-secondary" style="flex: 1;">Clear</a>
                    </div>
                </form>
                
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e9ecef;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" onclick="window.location.href='?my_projects=1'" 
                               <?= isset($_GET['my_projects']) ? 'checked' : '' ?>>
                        <span style="font-weight: 600; color: #1b2a57;">Show only projects I'm a member of</span>
                    </label>
                </div>
            </div>

            <!-- Projects Table -->
            <?php if (count($projects) > 0): ?>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Type</th>
                                <th>Client</th>
                                <th>Owner</th>
                                <th>Priority</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; gap: 12px; align-items: center;">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0059b3 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; flex-shrink: 0;">
                                                <?= get_project_initials($project['title']) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #1b2a57; margin-bottom: 2px;">
                                                    <a href="view.php?id=<?= $project['id'] ?>" style="color: #003581; text-decoration: none;">
                                                        <?= htmlspecialchars($project['title']) ?>
                                                    </a>
                                                </div>
                                                <div style="font-size: 11px; color: #6c757d; font-family: monospace;">
                                                    <?= htmlspecialchars($project['project_code']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?= get_project_type_icon($project['type']) ?> <?= htmlspecialchars($project['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($project['client_name']): ?>
                                            <a href="../clients/view.php?id=<?= $project['client_id'] ?>" 
                                               style="color: #003581; text-decoration: none;">
                                                <?= htmlspecialchars($project['client_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($project['owner_username']) ?></td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?= get_priority_icon($project['priority']) ?> <?= htmlspecialchars($project['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                                <div style="height: 100%; background: #003581; width: <?= $project['progress'] ?>%;"></div>
                                            </div>
                                            <span style="font-size: 12px; color: #6c757d; min-width: 40px;">
                                                <?= number_format($project['progress'], 0) ?>%
                                            </span>
                                        </div>
                                        <div style="font-size: 11px; color: #6c757d; margin-top: 4px;">
                                            <?= $project['completed_tasks'] ?>/<?= $project['task_count'] ?> tasks
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($project['status'] === 'In Progress'): ?>
                                            <span class="badge badge-success">
                                                <?= get_project_status_icon($project['status']) ?> <?= htmlspecialchars($project['status']) ?>
                                            </span>
                                        <?php elseif ($project['status'] === 'Completed'): ?>
                                            <span class="badge badge-info">
                                                <?= get_project_status_icon($project['status']) ?> <?= htmlspecialchars($project['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <?= get_project_status_icon($project['status']) ?> <?= htmlspecialchars($project['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['start_date']): ?>
                                            <div style="font-size: 13px;">
                                                📅 <?= date('M d, Y', strtotime($project['start_date'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($project['end_date']): ?>
                                            <div style="font-size: 13px; color: #6c757d;">
                                                → <?= date('M d, Y', strtotime($project['end_date'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="view.php?id=<?= $project['id'] ?>" 
                                               class="btn btn-secondary" 
                                               style="padding: 6px 12px; font-size: 12px;">
                                                👁️ View
                                            </a>
                                            <?php if ($can_update): ?>
                                                <a href="edit.php?id=<?= $project['id'] ?>" 
                                                   class="btn btn-secondary" 
                                                   style="padding: 6px 12px; font-size: 12px;">
                                                    ✏️ Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
                    <h3 style="color: #1b2a57; margin-bottom: 8px;">No projects found</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Start by creating your first project or adjust your filters.</p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Create First Project</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        </div>

        <!-- ANALYTICS VIEW (Admin/Manager Only) -->
        <?php if ($is_admin_or_manager): ?>
        <div class="analytics-view" id="analyticsView">
            
            <!-- Analytics KPIs -->
            <div class="kpi-strip" id="kpiStrip">
                <div class="loading-state">
                    <div class="spinner"></div>
                    Loading analytics...
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Projects by Status</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Internal vs Client</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Trend Chart (Full Width) -->
            <div class="dashboard-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3 class="card-title">Project Creation Trends (Last 12 Months)</h3>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <!-- Workload & Alerts -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Team Workload</h3>
                    </div>
                    <div id="workloadTable">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            Loading workload...
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">⚠️ Alerts</h3>
                    </div>
                    <div id="alertsPanel">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            Loading alerts...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Project Insights -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Recently Updated Projects</h3>
                    </div>
                    <div id="recentProjects">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            Loading projects...
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Top Performing Projects</h3>
                    </div>
                    <div id="topProjects">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            Loading projects...
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($is_admin_or_manager): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let statusChartInstance, typeChartInstance, trendChartInstance;
let analyticsLoaded = false;

// View Switcher
function switchView(view) {
    const listView = document.getElementById('projectsList');
    const analyticsView = document.getElementById('analyticsView');
    const listBtn = document.getElementById('listViewBtn');
    const analyticsBtn = document.getElementById('analyticsViewBtn');
    
    if (view === 'list') {
        listView.classList.add('active');
        analyticsView.classList.remove('active');
        listBtn.classList.add('active');
        analyticsBtn.classList.remove('active');
    } else {
        listView.classList.remove('active');
        analyticsView.classList.add('active');
        listBtn.classList.remove('active');
        analyticsBtn.classList.add('active');
        
        // Load analytics data on first switch
        if (!analyticsLoaded) {
            loadAnalytics();
            analyticsLoaded = true;
        }
    }
}

async function loadAnalytics() {
    await Promise.all([
        loadKPIs(),
        loadCharts(),
        loadWorkload(),
        loadAlerts(),
        loadProjects()
    ]);
}

async function loadKPIs() {
    try {
        const response = await fetch('/public/api/projects/dashboard/summary.php');
        const result = await response.json();
        
        if (result.success) {
            renderKPIs(result.data);
        } else {
            document.getElementById('kpiStrip').innerHTML = '<div class="alert alert-danger">Failed to load KPIs</div>';
        }
    } catch (error) {
        console.error('KPI fetch error:', error);
        document.getElementById('kpiStrip').innerHTML = '<div class="alert alert-danger">Error loading KPIs</div>';
    }
}

function renderKPIs(data) {
    const kpiHTML = `
        <div class="kpi-card">
            <div class="kpi-value">${data.total_projects || 0}</div>
            <div class="kpi-label">Total Projects</div>
        </div>
        <div class="kpi-card success">
            <div class="kpi-value">${data.active_projects || 0}</div>
            <div class="kpi-label">Active Projects</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">${data.completed_projects || 0}</div>
            <div class="kpi-label">Completed</div>
        </div>
        <div class="kpi-card warning">
            <div class="kpi-value">${data.on_hold_projects || 0}</div>
            <div class="kpi-label">On Hold</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">${data.internal_projects || 0}</div>
            <div class="kpi-label">Internal</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">${data.client_projects || 0}</div>
            <div class="kpi-label">Client</div>
        </div>
        <div class="kpi-card success">
            <div class="kpi-value">${parseFloat(data.avg_progress || 0).toFixed(1)}%</div>
            <div class="kpi-label">Avg Progress</div>
        </div>
        <div class="kpi-card danger">
            <div class="kpi-value">${data.overdue_tasks || 0}</div>
            <div class="kpi-label">Overdue Tasks</div>
        </div>
    `;
    
    document.getElementById('kpiStrip').innerHTML = kpiHTML;
}

async function loadCharts() {
    try {
        const response = await fetch('/public/api/projects/dashboard/charts.php');
        const result = await response.json();
        
        if (result.success) {
            renderCharts(result.data);
        }
    } catch (error) {
        console.error('Charts fetch error:', error);
    }
}

function renderCharts(data) {
    // Status Bar Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    if (statusChartInstance) statusChartInstance.destroy();
    
    statusChartInstance = new Chart(statusCtx, {
        type: 'bar',
        data: {
            labels: data.status.labels,
            datasets: [{
                label: 'Projects',
                data: data.status.values,
                backgroundColor: ['#ffc107', '#007bff', '#6c757d', '#28a745']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Type Pie Chart
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    if (typeChartInstance) typeChartInstance.destroy();
    
    typeChartInstance = new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: data.type.labels,
            datasets: [{
                data: data.type.values,
                backgroundColor: ['#17a2b8', '#6f42c1']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    if (trendChartInstance) trendChartInstance.destroy();
    
    trendChartInstance = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: data.trends.months,
            datasets: [{
                label: 'Projects Created',
                data: data.trends.counts,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

async function loadWorkload() {
    try {
        const response = await fetch('/public/api/projects/dashboard/workload.php');
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            renderWorkload(result.data);
        } else {
            document.getElementById('workloadTable').innerHTML = '<p class="text-muted text-center py-3">No workload data available</p>';
        }
    } catch (error) {
        console.error('Workload fetch error:', error);
        document.getElementById('workloadTable').innerHTML = '<p class="text-danger text-center py-3">Error loading workload</p>';
    }
}

function renderWorkload(data) {
    let html = '<table class="workload-table"><thead><tr><th>Member</th><th>Assigned</th><th>Completed</th><th>Efficiency</th></tr></thead><tbody>';
    
    data.forEach(member => {
        const efficiency = parseFloat(member.efficiency);
        let effClass = 'low';
        if (efficiency >= 75) effClass = '';
        else if (efficiency >= 50) effClass = 'medium';
        
        html += `
            <tr>
                <td>${escapeHtml(member.name)}</td>
                <td>${member.assigned_tasks}</td>
                <td>${member.completed_tasks}</td>
                <td>
                    <div class="efficiency-bar">
                        <div class="efficiency-progress">
                            <div class="efficiency-fill ${effClass}" style="width: ${efficiency}%"></div>
                        </div>
                        <span style="font-size: 0.875rem; color: #666;">${efficiency.toFixed(0)}%</span>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    document.getElementById('workloadTable').innerHTML = html;
}

async function loadAlerts() {
    try {
        const response = await fetch('/public/api/projects/dashboard/alerts.php');
        const result = await response.json();
        
        if (result.success) {
            renderAlerts(result.data);
        }
    } catch (error) {
        console.error('Alerts fetch error:', error);
        document.getElementById('alertsPanel').innerHTML = '<p class="text-danger text-center py-3">Error loading alerts</p>';
    }
}

function renderAlerts(data) {
    const allAlerts = [...data.deadline, ...data.stagnant];
    
    if (allAlerts.length === 0) {
        document.getElementById('alertsPanel').innerHTML = '<p class="text-success text-center py-3">✅ No alerts. All projects on track!</p>';
        return;
    }
    
    let html = '';
    
    data.deadline.forEach(alert => {
        html += `
            <div class="alert-item deadline">
                <div class="alert-title">🔴 ${escapeHtml(alert.name)} - Deadline Approaching</div>
                <div class="alert-desc">Due: ${alert.end_date} (${alert.days_until} days remaining)</div>
            </div>
        `;
    });
    
    data.stagnant.forEach(alert => {
        html += `
            <div class="alert-item">
                <div class="alert-title">⚠️ ${escapeHtml(alert.name)} - Stagnant Project</div>
                <div class="alert-desc">Last activity: ${alert.days_stagnant} days ago</div>
            </div>
        `;
    });
    
    document.getElementById('alertsPanel').innerHTML = html;
}

async function loadProjects() {
    try {
        const response = await fetch('/public/api/projects/dashboard/recent.php');
        const result = await response.json();
        
        if (result.success) {
            renderProjectInsights(result.data);
        }
    } catch (error) {
        console.error('Projects fetch error:', error);
    }
}

function renderProjectInsights(data) {
    // Recent Projects
    let recentHTML = '';
    if (data.recent.length === 0) {
        recentHTML = '<p class="text-muted text-center py-3">No recent projects</p>';
    } else {
        data.recent.forEach(proj => {
            recentHTML += `
                <div class="project-item">
                    <div>
                        <a href="view.php?id=${proj.id}" class="project-name">${escapeHtml(proj.name)}</a>
                        <div class="project-meta">Updated: ${proj.updated_at}</div>
                    </div>
                    <span class="badge badge-${getStatusClass(proj.status)}">${proj.status}</span>
                </div>
            `;
        });
    }
    document.getElementById('recentProjects').innerHTML = recentHTML;
    
    // Top Performing Projects
    let topHTML = '';
    if (data.top_performers.length === 0) {
        topHTML = '<p class="text-muted text-center py-3">No completed projects yet</p>';
    } else {
        data.top_performers.forEach(proj => {
            topHTML += `
                <div class="project-item">
                    <div>
                        <a href="view.php?id=${proj.id}" class="project-name">${escapeHtml(proj.name)}</a>
                        <div class="project-meta">${proj.total_tasks} tasks completed</div>
                    </div>
                    <span class="badge badge-completed">${parseFloat(proj.progress).toFixed(0)}%</span>
                </div>
            `;
        });
    }
    document.getElementById('topProjects').innerHTML = topHTML;
}

function getStatusClass(status) {
    const map = {
        'Pending': 'pending',
        'In Progress': 'progress',
        'On Hold': 'hold',
        'Completed': 'completed'
    };
    return map[status] || 'pending';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<?php endif; ?>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

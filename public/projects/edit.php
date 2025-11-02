<?php
/**
 * Projects Module - Edit Project Page
 * Update existing project details with status transition validation
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'projects', 'update');

// Check if tables exist
if (!projects_tables_exist($conn)) {
    header('Location: /KaryalayERP/scripts/setup_projects_tables.php');
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

$errors = [];

// Get all users for owner dropdown
$users = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Get all clients (if exists)
$clients = [];
if ($conn->query("SHOW TABLES LIKE 'clients'")->num_rows > 0) {
    $clients = $conn->query("SELECT id, name, code FROM clients WHERE status = 'Active' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'type' => $_POST['type'] ?? 'Internal',
        'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
        'owner_id' => (int)($_POST['owner_id'] ?? $project['owner_id']),
        'description' => trim($_POST['description'] ?? ''),
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'priority' => $_POST['priority'] ?? 'Medium',
        'status' => $_POST['status'] ?? 'Draft',
        'tags' => trim($_POST['tags'] ?? ''),
    ];
    
    // Validate
    $errors = validate_project_data($data);
    
    // Status transition validation
    $old_status = $project['status'];
    $new_status = $data['status'];
    
    if ($old_status !== $new_status) {
        // Draft can go to Active or On Hold
        if ($old_status === 'Draft' && !in_array($new_status, ['Active', 'On Hold'])) {
            $errors[] = "Draft projects can only transition to Active or On Hold status.";
        }
        // Active can go to On Hold, Completed, or back to Draft
        elseif ($old_status === 'Active' && !in_array($new_status, ['On Hold', 'Completed', 'Draft'])) {
            $errors[] = "Active projects can transition to On Hold, Completed, or Draft status.";
        }
        // On Hold can go to Active or Archived
        elseif ($old_status === 'On Hold' && !in_array($new_status, ['Active', 'Archived'])) {
            $errors[] = "On Hold projects can transition to Active or Archived status.";
        }
        // Completed can go to Archived
        elseif ($old_status === 'Completed' && $new_status !== 'Archived') {
            $errors[] = "Completed projects can only transition to Archived status.";
        }
        // Archived cannot transition
        elseif ($old_status === 'Archived') {
            $errors[] = "Archived projects cannot be modified. Please restore the project first.";
        }
    }
    
    // Update if no errors
    if (empty($errors)) {
        if (update_project($conn, $project_id, $data, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Project updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $project_id);
            exit;
        } else {
            $errors[] = "Failed to update project. Please try again.";
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = [
        'title' => $project['title'],
        'type' => $project['type'],
        'client_id' => $project['client_id'],
        'owner_id' => $project['owner_id'],
        'description' => $project['description'],
        'start_date' => $project['start_date'],
        'end_date' => $project['end_date'],
        'priority' => $project['priority'],
        'status' => $project['status'],
        'tags' => $project['tags'],
    ];
}

$page_title = 'Edit Project - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">✏️ Edit Project</h1>
                    <p style="color: #6c757d; margin: 0;">Update project details and settings</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="view.php?id=<?= $project_id ?>" class="btn btn-accent" style="text-decoration: none;">← Back</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" style="margin-bottom: 24px;">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 24px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Project Code (Read-only) -->
            <div class="card" style="margin-bottom: 24px; background: #f8f9fa;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 700; color: #003581;">📋 Project Code:</span>
                    <span style="font-family: monospace; font-size: 18px; color: #003581;">
                        #<?= htmlspecialchars($project['project_code']) ?>
                    </span>
                    <span style="margin-left: auto; font-size: 13px; color: #6c757d;">
                        Auto-generated and cannot be changed
                    </span>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📋 Basic Information
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            Project Title <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" required
                               placeholder="Enter project title..."
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                            Changing title will not update the project code
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            🏷️ Project Type <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="type" id="projectType" class="form-control" required 
                                onchange="toggleClientField()">
                            <option value="Internal" <?= ($_POST['type'] ?? '') === 'Internal' ? 'selected' : '' ?>>Internal Project</option>
                            <option value="Client" <?= ($_POST['type'] ?? '') === 'Client' ? 'selected' : '' ?>>Client Project</option>
                        </select>
                    </div>
                    
                    <div id="clientField" style="display: <?= ($_POST['type'] ?? '') === 'Client' ? 'block' : 'none' ?>;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            🏢 Client <span style="color: #dc3545;" id="clientRequired">*</span>
                        </label>
                        <select name="client_id" class="form-control">
                            <option value="">Select Client...</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" 
                                    <?= ($_POST['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($clients)): ?>
                            <div style="font-size: 13px; color: #dc3545; margin-top: 4px;">
                                No active clients found. Please add clients first.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Project Details -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📝 Project Details
                </h3>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                        Description
                    </label>
                    <textarea name="description" class="form-control" rows="4" 
                              placeholder="Enter project description, scope, and objectives..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            👤 Project Owner <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="owner_id" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    <?= ($_POST['owner_id'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            ⚡ Priority
                        </label>
                        <select name="priority" class="form-control">
                            <option value="Low" <?= ($_POST['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= ($_POST['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($_POST['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Critical" <?= ($_POST['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            📊 Status <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="status" class="form-control" required>
                            <option value="Draft" <?= ($_POST['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Active" <?= ($_POST['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="On Hold" <?= ($_POST['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                            <option value="Completed" <?= ($_POST['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Archived" <?= ($_POST['status'] ?? '') === 'Archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                            Current: <?= get_project_status_icon($project['status']) ?> <?= htmlspecialchars($project['status']) ?>
                        </div>
                    </div>
                </div>

                <!-- Status Transition Rules Info -->
                <div style="margin-top: 16px; padding: 12px; background: #e7f3ff; border-left: 4px solid #0066cc; border-radius: 4px;">
                    <strong style="color: #003581;">📌 Status Transition Rules:</strong>
                    <ul style="margin: 8px 0 0 24px; color: #495057; font-size: 14px;">
                        <li><strong>Draft</strong> → Active, On Hold</li>
                        <li><strong>Active</strong> → On Hold, Completed, Draft</li>
                        <li><strong>On Hold</strong> → Active, Archived</li>
                        <li><strong>Completed</strong> → Archived only</li>
                        <li><strong>Archived</strong> → Cannot be modified</li>
                    </ul>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📅 Timeline
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            Start Date
                        </label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            End Date
                        </label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                    </div>
                </div>
                
                <div style="font-size: 13px; color: #6c757d; margin-top: 8px;">
                    📌 End date must be after start date if both are provided
                </div>
            </div>

            <!-- Additional Information -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    🏷️ Additional Information
                </h3>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                        Tags
                    </label>
                    <input type="text" name="tags" class="form-control" 
                           placeholder="Development, Marketing, Priority, Q1-2025..."
                           value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                    <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                        Comma-separated tags for better organization
                    </div>
                </div>
            </div>

            <!-- Project Metadata -->
            <div class="card" style="margin-bottom: 24px; background: #f8f9fa;">
                <h3 style="font-size: 16px; font-weight: 700; color: #003581; margin-bottom: 12px;">
                    📊 Project Metadata
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; font-size: 14px; color: #6c757d;">
                    <div>
                        <strong>Created By:</strong> <?= htmlspecialchars($project['created_by_username']) ?><br>
                        <span style="font-size: 13px;"><?= date('M j, Y g:i A', strtotime($project['created_at'])) ?></span>
                    </div>
                    <?php if ($project['updated_at']): ?>
                        <div>
                            <strong>Last Updated:</strong><br>
                            <span style="font-size: 13px;"><?= date('M j, Y g:i A', strtotime($project['updated_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong>Progress:</strong> <?= number_format($project['progress'], 1) ?>%<br>
                        <span style="font-size: 13px;">Auto-calculated from tasks</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
                <a href="view.php?id=<?= $project_id ?>" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
                <button type="submit" class="btn" style="padding: 12px 32px;">
                    💾 Update Project
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function toggleClientField() {
    const projectType = document.getElementById('projectType').value;
    const clientField = document.getElementById('clientField');
    const clientRequired = document.getElementById('clientRequired');
    
    if (projectType === 'Client') {
        clientField.style.display = 'block';
        clientRequired.style.display = 'inline';
    } else {
        clientField.style.display = 'none';
        clientRequired.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleClientField);
</script>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

<?php
/**
 * Projects Module - Add Project Page
 * Create new internal or client-linked projects
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'projects', 'create');

// Check if tables exist
if (!projects_tables_exist($conn)) {
    header('Location: /KaryalayERP/scripts/setup_projects_tables.php');
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
        'owner_id' => (int)($_POST['owner_id'] ?? $_SESSION['user_id']),
        'description' => trim($_POST['description'] ?? ''),
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'priority' => $_POST['priority'] ?? 'Medium',
        'status' => $_POST['status'] ?? 'Draft',
        'tags' => trim($_POST['tags'] ?? ''),
        'created_by' => $_SESSION['user_id']
    ];
    
    // Validate
    $errors = validate_project_data($data);
    
    // Create if no errors
    if (empty($errors)) {
        $project_id = create_project($conn, $data);
        if ($project_id) {
            $_SESSION['flash_message'] = "Project created successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $project_id);
            exit;
        } else {
            $errors[] = "Failed to create project. Please try again.";
        }
    }
}

$page_title = 'New Project - Projects - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">➕ New Project</h1>
                    <p style="color: #6c757d; margin: 0;">Create a new internal or client project</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back</a>
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
                            Project code will be auto-generated from the title
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
                            <option value="Internal" <?= ($_POST['type'] ?? '') === 'Internal' ? 'selected' : '' ?>>
                                Internal Project
                            </option>
                            <option value="Client" <?= ($_POST['type'] ?? '') === 'Client' ? 'selected' : '' ?>>
                                Client Project
                            </option>
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
                                    <?= ($_POST['owner_id'] ?? $_SESSION['user_id']) == $user['id'] ? 'selected' : '' ?>>
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
                            <option value="Medium" <?= ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($_POST['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Critical" <?= ($_POST['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #003581;">
                            📊 Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="Draft" <?= ($_POST['status'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Active" <?= ($_POST['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        </select>
                    </div>
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
                    📌 Timeline is optional and can be updated later
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

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
                <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
                <button type="submit" class="btn" style="padding: 12px 32px;">
                    🚀 Create Project
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

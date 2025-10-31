<?php
/**
 * Projects Module - Database Setup
 * Creates all necessary tables for comprehensive project management
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/module_dependencies.php';
require_once __DIR__ . '/../config/setup_helper.php';

// Check prerequisites
$conn_check = createConnection(true);
$prerequisite_check = $conn_check ? get_prerequisite_check_result($conn_check, 'projects') : ['allowed' => false, 'missing_modules' => []];
if ($conn_check) closeConnection($conn_check);

// Only allow super-admin or admin (RBAC)
require_once __DIR__ . '/../includes/authz.php';
$isSuperAdmin = false;
if (function_exists('authz_is_super_admin')) {
    $isSuperAdmin = authz_is_super_admin($conn);
} else if (isset($_SESSION['role_names']) && is_array($_SESSION['role_names'])) {
    $roleNames = array_map('strtolower', $_SESSION['role_names']);
    $isSuperAdmin = in_array('super admin', $roleNames) || in_array('admin', $roleNames);
}
if (!$isSuperAdmin) {
    die('Access denied. Admin or Super Admin only.');
}

$conn = createConnection();
$errors = [];
$success_messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    
    // 1. Create projects table
    $sql = "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_code VARCHAR(30) UNIQUE NOT NULL,
        title VARCHAR(200) NOT NULL,
        type ENUM('Internal','Client') DEFAULT 'Internal',
        client_id INT UNSIGNED NULL,
        owner_id INT UNSIGNED NOT NULL,
        description TEXT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
        progress DECIMAL(5,2) DEFAULT 0.00,
        status ENUM('Draft','Active','On Hold','Completed','Archived') DEFAULT 'Draft',
        tags TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project_code (project_code),
        INDEX idx_type (type),
        INDEX idx_client_id (client_id),
        INDEX idx_owner_id (owner_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_created_by (created_by),
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'projects' created successfully";
    } else {
        $errors[] = "Error creating projects table: " . $conn->error;
    }

    // 2. Create project_phases table
    $sql = "CREATE TABLE IF NOT EXISTS project_phases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        status ENUM('Pending','In Progress','Completed','On Hold') DEFAULT 'Pending',
        progress DECIMAL(5,2) DEFAULT 0.00,
        sequence_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project_id (project_id),
        INDEX idx_status (status),
        INDEX idx_sequence (sequence_order),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_phases' created successfully";
    } else {
        $errors[] = "Error creating project_phases table: " . $conn->error;
    }

    // 3. Create project_tasks table
    $sql = "CREATE TABLE IF NOT EXISTS project_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        phase_id INT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        due_date DATE NULL,
        priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
        status ENUM('Pending','In Progress','Review','Completed') DEFAULT 'Pending',
        progress DECIMAL(5,2) DEFAULT 0.00,
        marked_done_by INT UNSIGNED NULL,
        closing_notes TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project_id (project_id),
        INDEX idx_phase_id (phase_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_due_date (due_date),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL,
        FOREIGN KEY (marked_done_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_tasks' created successfully";
    } else {
        $errors[] = "Error creating project_tasks table: " . $conn->error;
    }

    // 4. Create project_task_assignees table
    $sql = "CREATE TABLE IF NOT EXISTS project_task_assignees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id),
        INDEX idx_user_id (user_id),
        UNIQUE KEY unique_assignment (task_id, user_id),
        FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_task_assignees' created successfully";
    } else {
        $errors[] = "Error creating project_task_assignees table: " . $conn->error;
    }

    // 5. Create project_members table
    $sql = "CREATE TABLE IF NOT EXISTS project_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        role ENUM('Owner','Contributor','Viewer') DEFAULT 'Contributor',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        removed_at TIMESTAMP NULL,
        INDEX idx_project_id (project_id),
        INDEX idx_user_id (user_id),
        INDEX idx_role (role),
        UNIQUE KEY unique_member (project_id, user_id),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_members' created successfully";
    } else {
        $errors[] = "Error creating project_members table: " . $conn->error;
    }

    // 6. Create project_documents table
    $sql = "CREATE TABLE IF NOT EXISTS project_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path TEXT NOT NULL,
        doc_type VARCHAR(100) NULL,
        uploaded_by INT UNSIGNED NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        version INT DEFAULT 1,
        is_active BOOLEAN DEFAULT 1,
        INDEX idx_project_id (project_id),
        INDEX idx_doc_type (doc_type),
        INDEX idx_uploaded_by (uploaded_by),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_documents' created successfully";
    } else {
        $errors[] = "Error creating project_documents table: " . $conn->error;
    }

    // 7. Create project_templates table
    $sql = "CREATE TABLE IF NOT EXISTS project_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_by (created_by),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_templates' created successfully";
    } else {
        $errors[] = "Error creating project_templates table: " . $conn->error;
    }

    // 8. Create project_template_tasks table
    $sql = "CREATE TABLE IF NOT EXISTS project_template_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
        sequence_order INT DEFAULT 0,
        INDEX idx_template_id (template_id),
        INDEX idx_sequence (sequence_order),
        FOREIGN KEY (template_id) REFERENCES project_templates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_template_tasks' created successfully";
    } else {
        $errors[] = "Error creating project_template_tasks table: " . $conn->error;
    }

    // 9. Create project_activity_log table
    $sql = "CREATE TABLE IF NOT EXISTS project_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        activity_type ENUM('Task','Phase','Document','Status','Member','General') DEFAULT 'General',
        reference_id INT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_id (project_id),
        INDEX idx_user_id (user_id),
        INDEX idx_activity_type (activity_type),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✅ Table 'project_activity_log' created successfully";
    } else {
        $errors[] = "Error creating project_activity_log table: " . $conn->error;
    }

    // Create uploads directory
    $upload_dir = __DIR__ . '/../uploads/projects';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
            $success_messages[] = "✅ Uploads directory created successfully";
        } else {
            $errors[] = "Error creating uploads directory";
        }
    }
}

closeConnection($conn);

// Get setup status
$conn = createConnection(true);
$setup_status = [
    'projects' => table_exists($conn, 'projects'),
    'project_phases' => table_exists($conn, 'project_phases'),
    'project_tasks' => table_exists($conn, 'project_tasks'),
    'project_task_assignees' => table_exists($conn, 'project_task_assignees'),
    'project_members' => table_exists($conn, 'project_members'),
    'project_documents' => table_exists($conn, 'project_documents'),
    'project_templates' => table_exists($conn, 'project_templates'),
    'project_template_tasks' => table_exists($conn, 'project_template_tasks'),
    'project_activity_log' => table_exists($conn, 'project_activity_log')
];
$all_setup = !in_array(false, $setup_status);
closeConnection($conn);

$page_title = "Projects Module Setup - " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem;
}
.setup-container {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
}
.setup-header {
    background: linear-gradient(135deg, #003581 0%, #0059b3 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}
.setup-header h1 {
    margin: 0;
    font-size: 2rem;
}
.setup-content {
    padding: 2rem;
}
.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}
.status-item {
    padding: 1rem;
    border-radius: 8px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.status-item.success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}
.status-item.pending {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.alert-danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
.btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}
.btn-primary {
    background: #003581;
    color: white;
}
.btn-primary:hover:not(:disabled) {
    background: #002a66;
    transform: translateY(-2px);
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-success:hover {
    background: #218838;
}
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}
.feature-list {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
}
.feature-list h3 {
    color: #003581;
    margin-top: 0;
}
.feature-list ul {
    margin: 0;
    padding-left: 1.5rem;
}
.feature-list li {
    margin: 0.5rem 0;
}
</style>

<div class="setup-container">
    <div class="setup-header">
        <h1>🚀 Projects Module Setup</h1>
        <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Comprehensive project management for your organization</p>
    </div>

    <div class="setup-content">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Setup Errors:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_messages)): ?>
            <div class="alert alert-success">
                <?php foreach ($success_messages as $message): ?>
                    <div><?= htmlspecialchars($message) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$prerequisite_check['allowed']): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Missing Prerequisites:</strong>
                <p>The following modules must be set up before Projects:</p>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($prerequisite_check['missing_modules'] as $module): ?>
                        <li><strong><?= htmlspecialchars($module) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top: 1rem;">Please set up these modules first, then return here.</p>
            </div>
        <?php endif; ?>

        <div class="feature-list">
            <h3>📋 Module Features</h3>
            <ul>
                <li><strong>Project Management:</strong> Create & manage internal and client-linked projects</li>
                <li><strong>Phases & Milestones:</strong> Break projects into structured phases</li>
                <li><strong>Task Management:</strong> Multi-assignee tasks with progress tracking</li>
                <li><strong>Team Collaboration:</strong> Project members with role-based access</li>
                <li><strong>Document Management:</strong> Upload and version control for project files</li>
                <li><strong>Activity Tracking:</strong> Complete audit trail of all project activities</li>
                <li><strong>Client Integration:</strong> Link projects to clients seamlessly</li>
                <li><strong>Templates:</strong> Reusable project templates for consistency</li>
                <li><strong>Reporting:</strong> KPIs, progress tracking, and analytics</li>
            </ul>
        </div>

        <h3>Database Tables Status</h3>
        <div class="status-grid">
            <?php foreach ($setup_status as $table => $exists): ?>
                <div class="status-item <?= $exists ? 'success' : 'pending' ?>">
                    <span><?= $exists ? '✅' : '⏳' ?></span>
                    <span><?= htmlspecialchars($table) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <?php if ($all_setup): ?>
                <a href="../public/projects/index.php" class="btn btn-success">
                    ✅ Go to Projects Module
                </a>
            <?php else: ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="setup" class="btn btn-primary" 
                            <?= !$prerequisite_check['allowed'] ? 'disabled' : '' ?>>
                        🔧 Run Setup
                    </button>
                </form>
            <?php endif; ?>
            <a href="../setup/index.php" class="btn" style="background: #6c757d; color: white;">
                ← Back to Setup
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

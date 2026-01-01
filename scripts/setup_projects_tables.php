<?php
/**
 * Projects Module - Database Setup
 * Creates all necessary tables for comprehensive project management
 */

require_once __DIR__ . '/../config/db_connect.php';

if (!function_exists('table_exists')) {
    function table_exists($conn, $table) {
        $table = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $exists = ($res && mysqli_num_rows($res) > 0);
        if ($res) mysqli_free_result($res);
        return $exists;
    }
}

function setup_projects_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $already_exists = table_exists($conn, 'projects');
    
    if (!table_exists($conn, 'clients')) {
        return ['success' => false, 'message' => 'Clients table not found. Please install the Clients module first.'];
    }
    if (!table_exists($conn, 'users')) {
        return ['success' => false, 'message' => 'Users table not found.'];
    }
    
    $tables = [];
    
    $tables['projects'] = "CREATE TABLE IF NOT EXISTS projects (
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

    $tables['project_phases'] = "CREATE TABLE IF NOT EXISTS project_phases (
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
    
    $tables['project_tasks'] = "CREATE TABLE IF NOT EXISTS project_tasks (
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
    
    $tables['project_task_assignees'] = "CREATE TABLE IF NOT EXISTS project_task_assignees (
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
    
    $tables['project_members'] = "CREATE TABLE IF NOT EXISTS project_members (
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

    $tables['project_documents'] = "CREATE TABLE IF NOT EXISTS project_documents (
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
    
    $tables['project_templates'] = "CREATE TABLE IF NOT EXISTS project_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_by (created_by),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $tables['project_template_tasks'] = "CREATE TABLE IF NOT EXISTS project_template_tasks (
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
    
    $tables['project_activity_log'] = "CREATE TABLE IF NOT EXISTS project_activity_log (
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
    
    foreach ($tables as $name => $sql) {
        if ($conn->query($sql)) {
            $tables_created[] = $name;
        } else {
            $errors[] = "Error creating '$name': " . $conn->error;
        }
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/projects';
    if (!file_exists($upload_dir)) {
        $old_umask = umask(0);
        $created = @mkdir($upload_dir, 0777, true);
        umask($old_umask);
        
        if (!$created && !file_exists($upload_dir)) {
            // Directory creation failed, but don't fail the entire setup
            // Just log it as a warning that can be fixed later
            error_log("Warning: Could not create projects upload directory: $upload_dir");
        }
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Projects tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Projects module tables created: ' . implode(', ', $tables_created)];
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    require_once __DIR__ . '/../config/config.php';
    
    $conn = createConnection(true);
    $result = setup_projects_module($conn);
    closeConnection($conn);
    
    echo "<h1>Projects Module Setup</h1>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='../public/projects/index.php'>Go to Projects Module</a></p>";
    }
    echo "<p><a href='../setup/index.php'>Back to Setup</a></p>";
}

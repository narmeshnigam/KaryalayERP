<?php
/**
 * Setup Functions Helper
 * Provides standardized setup functions for modules that don't have them
 * These functions wrap the existing setup logic to work with the AJAX installer
 */

require_once __DIR__ . '/../config/db_connect.php';

/**
 * Helper function to check if a table exists
 */
if (!function_exists('setup_table_exists')) {
    function setup_table_exists(mysqli $conn, string $table): bool {
        $table = mysqli_real_escape_string($conn, $table);
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) mysqli_free_result($result);
        return $exists;
    }
}

/**
 * Setup Projects Module
 */
function setup_projects_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // Check if clients table exists (prerequisite)
    if (!setup_table_exists($conn, 'clients')) {
        if ($should_close) closeConnection($conn);
        return ['success' => false, 'message' => 'Clients module must be installed first'];
    }
    
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
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'projects';
    } else {
        $errors[] = 'projects: ' . mysqli_error($conn);
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
        INDEX idx_sequence (sequence_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'project_phases';
    } else {
        $errors[] = 'project_phases: ' . mysqli_error($conn);
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
        INDEX idx_due_date (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'project_tasks';
    } else {
        $errors[] = 'project_tasks: ' . mysqli_error($conn);
    }
    
    // 4. Create project_members table
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
        UNIQUE KEY unique_member (project_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'project_members';
    } else {
        $errors[] = 'project_members: ' . mysqli_error($conn);
    }
    
    // Create uploads directory
    $upload_dir = __DIR__ . '/../uploads/projects';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Projects module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Work Orders Module
 */
function setup_workorders_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create work_orders table
    $sql = "CREATE TABLE IF NOT EXISTS `work_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_code` VARCHAR(20) NOT NULL UNIQUE,
        `order_date` DATE NOT NULL,
        `linked_type` ENUM('Lead', 'Client') NOT NULL,
        `linked_id` INT NOT NULL,
        `service_type` VARCHAR(255) NOT NULL,
        `priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
        `start_date` DATE NOT NULL,
        `due_date` DATE NOT NULL,
        `tat_days` INT GENERATED ALWAYS AS (DATEDIFF(`due_date`, `start_date`)) STORED,
        `status` ENUM('Draft', 'In Progress', 'Internal Review', 'Client Review', 'Delivered', 'Closed') NOT NULL DEFAULT 'Draft',
        `description` TEXT,
        `remarks` TEXT,
        `dependencies` TEXT,
        `exceptions` TEXT,
        `internal_approver` INT,
        `internal_approval_date` DATETIME,
        `client_approver` VARCHAR(255),
        `client_approval_date` DATETIME,
        `quotation_id` INT,
        `invoice_id` INT,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_work_order_code` (`work_order_code`),
        INDEX `idx_linked` (`linked_type`, `linked_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_priority` (`priority`),
        INDEX `idx_dates` (`start_date`, `due_date`),
        INDEX `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'work_orders';
    } else {
        $errors[] = 'work_orders: ' . mysqli_error($conn);
    }
    
    // 2. Create work_order_files table
    $sql = "CREATE TABLE IF NOT EXISTS `work_order_files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` TEXT NOT NULL,
        `file_size` INT,
        `file_type` VARCHAR(50),
        `uploaded_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_uploaded_by` (`uploaded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'work_order_files';
    } else {
        $errors[] = 'work_order_files: ' . mysqli_error($conn);
    }
    
    // 3. Create work_order_activity_log table
    $sql = "CREATE TABLE IF NOT EXISTS `work_order_activity_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_order_id` INT NOT NULL,
        `action_by` INT NOT NULL,
        `action_type` ENUM('Create', 'Update', 'Approve Internal', 'Approve Client', 'Deliver', 'Close', 'Comment') NOT NULL,
        `description` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_work_order` (`work_order_id`),
        INDEX `idx_action_by` (`action_by`),
        INDEX `idx_action_type` (`action_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'work_order_activity_log';
    } else {
        $errors[] = 'work_order_activity_log: ' . mysqli_error($conn);
    }
    
    // Create uploads directory
    $upload_dir = __DIR__ . '/../uploads/workorders';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Work Orders module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Payroll Module
 */
function setup_payroll_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create payroll_runs table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        run_code VARCHAR(30) UNIQUE NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        status ENUM('Draft','Processing','Completed','Cancelled') DEFAULT 'Draft',
        total_employees INT DEFAULT 0,
        total_amount DECIMAL(15,2) DEFAULT 0.00,
        processed_by INT UNSIGNED NULL,
        processed_at DATETIME NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_month_year (month, year),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'payroll_runs';
    } else {
        $errors[] = 'payroll_runs: ' . mysqli_error($conn);
    }
    
    // 2. Create payroll_items table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT NOT NULL,
        employee_id INT NOT NULL,
        basic_salary DECIMAL(12,2) DEFAULT 0.00,
        allowances DECIMAL(12,2) DEFAULT 0.00,
        deductions DECIMAL(12,2) DEFAULT 0.00,
        net_salary DECIMAL(12,2) DEFAULT 0.00,
        payment_status ENUM('Pending','Paid','Failed') DEFAULT 'Pending',
        payment_date DATE NULL,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payroll_run (payroll_run_id),
        INDEX idx_employee (employee_id),
        INDEX idx_payment_status (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'payroll_items';
    } else {
        $errors[] = 'payroll_items: ' . mysqli_error($conn);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Payroll module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Contacts Module
 */
function setup_contacts_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create contacts table
    $sql = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NULL,
        email VARCHAR(150) NULL,
        phone VARCHAR(30) NULL,
        mobile VARCHAR(30) NULL,
        company VARCHAR(200) NULL,
        designation VARCHAR(100) NULL,
        address TEXT NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(100) NULL,
        country VARCHAR(100) NULL,
        postal_code VARCHAR(20) NULL,
        notes TEXT NULL,
        tags TEXT NULL,
        source VARCHAR(100) NULL,
        status ENUM('Active','Inactive') DEFAULT 'Active',
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_phone (phone),
        INDEX idx_company (company),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'contacts';
    } else {
        $errors[] = 'contacts: ' . mysqli_error($conn);
    }
    
    // 2. Create contact_groups table
    $sql = "CREATE TABLE IF NOT EXISTS contact_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        color VARCHAR(20) DEFAULT '#003581',
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'contact_groups';
    } else {
        $errors[] = 'contact_groups: ' . mysqli_error($conn);
    }
    
    // 3. Create contact_group_members table
    $sql = "CREATE TABLE IF NOT EXISTS contact_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT NOT NULL,
        group_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_membership (contact_id, group_id),
        INDEX idx_contact (contact_id),
        INDEX idx_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'contact_group_members';
    } else {
        $errors[] = 'contact_group_members: ' . mysqli_error($conn);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Contacts module installed successfully', 'tables_created' => $tables_created];
}


/**
 * Setup Deliverables Module
 */
function setup_deliverables_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create deliverables table
    $sql = "CREATE TABLE IF NOT EXISTS deliverables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NULL,
        work_order_id INT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        file_path TEXT NULL,
        file_name VARCHAR(255) NULL,
        version INT DEFAULT 1,
        status ENUM('Draft','In Review','Approved','Rejected','Delivered') DEFAULT 'Draft',
        due_date DATE NULL,
        delivered_date DATE NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project (project_id),
        INDEX idx_work_order (work_order_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'deliverables';
    } else {
        $errors[] = 'deliverables: ' . mysqli_error($conn);
    }
    
    // 2. Create deliverable_revisions table
    $sql = "CREATE TABLE IF NOT EXISTS deliverable_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        deliverable_id INT NOT NULL,
        version INT NOT NULL,
        file_path TEXT NULL,
        file_name VARCHAR(255) NULL,
        notes TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_deliverable (deliverable_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'deliverable_revisions';
    } else {
        $errors[] = 'deliverable_revisions: ' . mysqli_error($conn);
    }
    
    // Create uploads directory
    $upload_dir = __DIR__ . '/../uploads/deliverables';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Deliverables module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Delivery Module
 */
function setup_delivery_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create deliveries table
    $sql = "CREATE TABLE IF NOT EXISTS deliveries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_code VARCHAR(30) UNIQUE NOT NULL,
        client_id INT UNSIGNED NULL,
        invoice_id INT NULL,
        delivery_date DATE NOT NULL,
        delivery_address TEXT NULL,
        status ENUM('Pending','In Transit','Delivered','Failed','Returned') DEFAULT 'Pending',
        tracking_number VARCHAR(100) NULL,
        carrier VARCHAR(100) NULL,
        notes TEXT NULL,
        proof_of_delivery TEXT NULL,
        delivered_at DATETIME NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_delivery_code (delivery_code),
        INDEX idx_client (client_id),
        INDEX idx_status (status),
        INDEX idx_delivery_date (delivery_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'deliveries';
    } else {
        $errors[] = 'deliveries: ' . mysqli_error($conn);
    }
    
    // 2. Create delivery_items table
    $sql = "CREATE TABLE IF NOT EXISTS delivery_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id INT NOT NULL,
        item_name VARCHAR(200) NOT NULL,
        quantity INT DEFAULT 1,
        unit VARCHAR(50) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_delivery (delivery_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'delivery_items';
    } else {
        $errors[] = 'delivery_items: ' . mysqli_error($conn);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Delivery module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Notebook Module
 */
function setup_notebook_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create notes table
    $sql = "CREATE TABLE IF NOT EXISTS notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        content LONGTEXT NULL,
        category VARCHAR(100) NULL,
        tags TEXT NULL,
        is_pinned TINYINT(1) DEFAULT 0,
        is_archived TINYINT(1) DEFAULT 0,
        color VARCHAR(20) DEFAULT '#ffffff',
        owner_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_owner (owner_id),
        INDEX idx_category (category),
        INDEX idx_pinned (is_pinned),
        INDEX idx_archived (is_archived)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'notes';
    } else {
        $errors[] = 'notes: ' . mysqli_error($conn);
    }
    
    // 2. Create note_versions table
    $sql = "CREATE TABLE IF NOT EXISTS note_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_id INT NOT NULL,
        content LONGTEXT NULL,
        version INT NOT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_note (note_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'note_versions';
    } else {
        $errors[] = 'note_versions: ' . mysqli_error($conn);
    }
    
    // 3. Create note_shares table
    $sql = "CREATE TABLE IF NOT EXISTS note_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_id INT NOT NULL,
        shared_with INT UNSIGNED NOT NULL,
        permission ENUM('View','Edit') DEFAULT 'View',
        shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_share (note_id, shared_with),
        INDEX idx_note (note_id),
        INDEX idx_shared_with (shared_with)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'note_shares';
    } else {
        $errors[] = 'note_shares: ' . mysqli_error($conn);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Notebook module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Assets Module
 */
function setup_assets_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create assets table
    $sql = "CREATE TABLE IF NOT EXISTS assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(100) NULL,
        description TEXT NULL,
        serial_number VARCHAR(100) NULL,
        purchase_date DATE NULL,
        purchase_price DECIMAL(12,2) NULL,
        warranty_expiry DATE NULL,
        status ENUM('Available','Assigned','Under Maintenance','Retired') DEFAULT 'Available',
        location VARCHAR(200) NULL,
        notes TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_asset_code (asset_code),
        INDEX idx_category (category),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'assets';
    } else {
        $errors[] = 'assets: ' . mysqli_error($conn);
    }
    
    // 2. Create asset_assignments table
    $sql = "CREATE TABLE IF NOT EXISTS asset_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        employee_id INT NOT NULL,
        assigned_date DATE NOT NULL,
        return_date DATE NULL,
        status ENUM('Active','Returned') DEFAULT 'Active',
        notes TEXT NULL,
        assigned_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset (asset_id),
        INDEX idx_employee (employee_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'asset_assignments';
    } else {
        $errors[] = 'asset_assignments: ' . mysqli_error($conn);
    }
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Assets module installed successfully', 'tables_created' => $tables_created];
}

/**
 * Setup Data Transfer Module
 */
function setup_data_transfer_module(?mysqli $conn = null): array {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $errors = [];
    $tables_created = [];
    
    // 1. Create import_logs table
    $sql = "CREATE TABLE IF NOT EXISTS import_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        import_type VARCHAR(100) NOT NULL,
        file_name VARCHAR(255) NULL,
        total_records INT DEFAULT 0,
        successful_records INT DEFAULT 0,
        failed_records INT DEFAULT 0,
        status ENUM('Pending','Processing','Completed','Failed') DEFAULT 'Pending',
        error_log TEXT NULL,
        imported_by INT UNSIGNED NOT NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_import_type (import_type),
        INDEX idx_status (status),
        INDEX idx_imported_by (imported_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'import_logs';
    } else {
        $errors[] = 'import_logs: ' . mysqli_error($conn);
    }
    
    // 2. Create export_logs table
    $sql = "CREATE TABLE IF NOT EXISTS export_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        export_type VARCHAR(100) NOT NULL,
        file_name VARCHAR(255) NULL,
        file_path TEXT NULL,
        total_records INT DEFAULT 0,
        format VARCHAR(20) DEFAULT 'csv',
        status ENUM('Pending','Processing','Completed','Failed') DEFAULT 'Pending',
        exported_by INT UNSIGNED NOT NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_export_type (export_type),
        INDEX idx_status (status),
        INDEX idx_exported_by (exported_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        $tables_created[] = 'export_logs';
    } else {
        $errors[] = 'export_logs: ' . mysqli_error($conn);
    }
    
    // Create directories
    $import_dir = __DIR__ . '/../uploads/imports';
    $export_dir = __DIR__ . '/../uploads/exports';
    if (!is_dir($import_dir)) @mkdir($import_dir, 0755, true);
    if (!is_dir($export_dir)) @mkdir($export_dir, 0755, true);
    
    if ($should_close) closeConnection($conn);
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Errors: ' . implode(', ', $errors), 'tables_created' => $tables_created];
    }
    
    return ['success' => true, 'message' => 'Data Transfer module installed successfully', 'tables_created' => $tables_created];
}


/**
 * Ensure system_settings table exists
 */
function ensure_system_settings_table(?mysqli $conn = null): bool {
    $should_close = false;
    if ($conn === null) {
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return false;
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT NULL,
        setting_type VARCHAR(50) DEFAULT 'string',
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_setting_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $result = mysqli_query($conn, $sql);
    
    if ($should_close) closeConnection($conn);
    
    return $result !== false;
}

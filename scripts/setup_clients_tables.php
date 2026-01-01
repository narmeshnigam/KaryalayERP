<?php
/**
 * Setup script for Clients Module
 * Creates all required tables for the Clients module
 * 
 * This script can be:
 * 1. Called directly via browser for interactive setup
 * 2. Invoked programmatically via setup_clients_module() function
 */

/**
 * Setup the clients module tables
 * 
 * @param mysqli|null $conn Optional database connection. If null, creates one.
 * @return array Result with 'success' (bool) and 'message' (string)
 */
function setup_clients_module(?mysqli $conn = null): array {
    $should_close = false;
    
    if ($conn === null) {
        require_once __DIR__ . '/../config/db_connect.php';
        $conn = createConnection(true);
        $should_close = true;
    }
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $tables_created = [];
    $errors = [];
    
    try {
        // Check if ALL clients tables already exist
        $required_tables = ['clients', 'client_addresses', 'client_contacts_map', 'client_documents', 'client_custom_fields'];
        $all_exist = true;
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($check) == 0) {
                $all_exist = false;
                $missing_tables[] = $table;
            }
        }
        
        if ($all_exist) {
            if ($should_close) closeConnection($conn);
            return ['success' => true, 'message' => 'Clients tables already exist.', 'already_exists' => true];
        }
        
        // Check prerequisites - users table must exist
        $check_users = "SHOW TABLES LIKE 'users'";
        $users_result = mysqli_query($conn, $check_users);
        if (mysqli_num_rows($users_result) == 0) {
            if ($should_close) closeConnection($conn);
            return ['success' => false, 'message' => 'Users table does not exist! Please setup User Management module first.'];
        }

        // Table 1: clients
        $sql_clients = "
        CREATE TABLE IF NOT EXISTS `clients` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(30) UNIQUE NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `legal_name` VARCHAR(200) NULL,
            `industry` VARCHAR(100) NULL,
            `website` VARCHAR(150) NULL,
            `email` VARCHAR(150) NULL,
            `phone` VARCHAR(20) NULL,
            `gstin` VARCHAR(50) NULL,
            `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            `owner_id` INT UNSIGNED NOT NULL,
            `lead_id` INT NULL,
            `tags` TEXT NULL,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_code` (`code`),
            INDEX `idx_name` (`name`),
            INDEX `idx_status` (`status`),
            INDEX `idx_owner_id` (`owner_id`),
            INDEX `idx_lead_id` (`lead_id`),
            INDEX `idx_email` (`email`),
            INDEX `idx_phone` (`phone`),
            FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql_clients) === TRUE) {
            $tables_created[] = 'clients';
        } else {
            $errors[] = "Error creating 'clients' table: " . $conn->error;
        }

        // Table 2: client_addresses
        $sql_addresses = "
        CREATE TABLE IF NOT EXISTS `client_addresses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `label` VARCHAR(50) NOT NULL,
            `line1` VARCHAR(255) NOT NULL,
            `line2` VARCHAR(255) NULL,
            `city` VARCHAR(100) NOT NULL,
            `state` VARCHAR(100) NOT NULL,
            `zip` VARCHAR(20) NOT NULL,
            `country` VARCHAR(100) NOT NULL DEFAULT 'India',
            `is_default` BOOLEAN DEFAULT 0,
            INDEX `idx_client_id` (`client_id`),
            INDEX `idx_label` (`label`),
            INDEX `idx_is_default` (`is_default`),
            FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql_addresses) === TRUE) {
            $tables_created[] = 'client_addresses';
        } else {
            $errors[] = "Error creating 'client_addresses' table: " . $conn->error;
        }

        // Table 3: client_contacts_map (create without FK to contacts if contacts table doesn't exist)
        $check_contacts = "SHOW TABLES LIKE 'contacts'";
        $contacts_result = mysqli_query($conn, $check_contacts);
        $contacts_exist = mysqli_num_rows($contacts_result) > 0;
        
        if ($contacts_exist) {
            $sql_contacts_map = "
            CREATE TABLE IF NOT EXISTS `client_contacts_map` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `contact_id` INT NOT NULL,
                `is_primary` BOOLEAN DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_contact_id` (`contact_id`),
                UNIQUE KEY `unique_client_contact` (`client_id`, `contact_id`),
                FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
        } else {
            // Create without FK to contacts - will be added later when contacts module is installed
            $sql_contacts_map = "
            CREATE TABLE IF NOT EXISTS `client_contacts_map` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `contact_id` INT NOT NULL,
                `is_primary` BOOLEAN DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_contact_id` (`contact_id`),
                UNIQUE KEY `unique_client_contact` (`client_id`, `contact_id`),
                FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
        }

        if ($conn->query($sql_contacts_map) === TRUE) {
            $tables_created[] = 'client_contacts_map';
        } else {
            $errors[] = "Error creating 'client_contacts_map' table: " . $conn->error;
        }

        // Table 4: client_documents
        $sql_documents = "
        CREATE TABLE IF NOT EXISTS `client_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` TEXT NOT NULL,
            `file_type` VARCHAR(100) NULL,
            `file_size` INT NULL,
            `description` VARCHAR(255) NULL,
            `uploaded_by` INT UNSIGNED NOT NULL,
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_client_id` (`client_id`),
            INDEX `idx_uploaded_by` (`uploaded_by`),
            FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql_documents) === TRUE) {
            $tables_created[] = 'client_documents';
        } else {
            $errors[] = "Error creating 'client_documents' table: " . $conn->error;
        }

        // Table 5: client_custom_fields
        $sql_custom_fields = "
        CREATE TABLE IF NOT EXISTS `client_custom_fields` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `field_key` VARCHAR(50) NOT NULL,
            `field_value` VARCHAR(200) NULL,
            INDEX `idx_client_id` (`client_id`),
            INDEX `idx_field_key` (`field_key`),
            UNIQUE KEY `unique_client_field` (`client_id`, `field_key`),
            FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql_custom_fields) === TRUE) {
            $tables_created[] = 'client_custom_fields';
        } else {
            $errors[] = "Error creating 'client_custom_fields' table: " . $conn->error;
        }

        // Create uploads directory (suppress errors if permission denied)
        $upload_dir = __DIR__ . '/../uploads/clients';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        if ($should_close) closeConnection($conn);

        if (!empty($errors)) {
            return ['success' => false, 'message' => implode('; ', $errors)];
        }

        return [
            'success' => true,
            'message' => 'Clients module tables created successfully!',
            'tables_created' => $tables_created
        ];

    } catch (Exception $e) {
        if ($should_close) closeConnection($conn);
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Only run interactive UI when accessed directly via browser
if (!defined('AJAX_MODULE_INSTALL') && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    session_start();
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/db_connect.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }

    // Page title for header
    $page_title = "Clients Module - Database Setup";

    // Include header with sidebar
    require_once __DIR__ . '/../includes/header_sidebar.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    // Run setup if form submitted
    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = setup_clients_module();
    }
    ?>

    <div class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>👥 Clients Module Setup</h1>
                        <p>Create database tables for Client Management</p>
                    </div>
                    <div>
                        <a href="../public/index.php" class="btn btn-accent">
                            ← Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <div class="card" style="max-width: 800px; margin: 0 auto;">
                <?php if ($result): ?>
                    <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
                        <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                    
                    <?php if ($result['success']): ?>
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="../public/clients/index.php" class="btn" style="padding: 15px 40px; font-size: 16px;">
                                Go to Clients Module
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="../public/index.php" class="btn btn-accent">Back to Dashboard</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <strong>ℹ️ Setup Information</strong><br>
                        This will create the following database tables:<br><br>
                        <strong>1. clients</strong> - Main client master table<br>
                        <strong>2. client_addresses</strong> - Client address records<br>
                        <strong>3. client_contacts_map</strong> - Links clients to contacts (if contacts module exists)<br>
                        <strong>4. client_custom_fields</strong> - Custom field storage<br><br>
                        <strong>⚠️ Prerequisite:</strong> Users module must be setup first.<br><br>
                        Click below to create these tables.
                    </div>

                    <form method="POST" style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;">
                            🚀 Create Clients Module Tables
                        </button>
                    </form>
                <?php endif; ?>
                
                <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #6c757d;">
                    <strong>📋 Tables to be created:</strong><br>
                    • <strong>clients:</strong> Company info, contact details, status, owner assignment<br>
                    • <strong>client_addresses:</strong> Multiple addresses per client with labels<br>
                    • <strong>client_contacts_map:</strong> Link contacts to clients<br>
                    • <strong>client_custom_fields:</strong> Flexible custom field storage
                </div>
            </div>
        </div>
    </div>

    <?php 
    require_once __DIR__ . '/../includes/footer_sidebar.php';
}

<?php
/**
 * Setup Script: Notebook Module Tables
 * Creates all required tables for the Notebook Module
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_notebook_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'notebook_notes'");
    $already_exists = $check && mysqli_num_rows($check) > 0;

    mysqli_begin_transaction($conn);

    try {
        // Table 1: notebook_notes
        $sql_notes = "CREATE TABLE IF NOT EXISTS notebook_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content LONGTEXT,
            created_by INT NOT NULL,
            linked_entity_id INT NULL,
            linked_entity_type ENUM('Client','Project','Lead','Other') NULL,
            share_scope ENUM('Private','Team','Organization') DEFAULT 'Private',
            tags TEXT NULL,
            version INT DEFAULT 1,
            is_pinned BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_created_by (created_by),
            INDEX idx_share_scope (share_scope),
            INDEX idx_linked_entity (linked_entity_id, linked_entity_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $sql_notes)) {
            throw new Exception("Failed to create notebook_notes: " . mysqli_error($conn));
        }
        $tables_created[] = 'notebook_notes';
        
        // Table 2: notebook_attachments
        $sql_attachments = "CREATE TABLE IF NOT EXISTS notebook_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path TEXT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_note_id (note_id),
            FOREIGN KEY (note_id) REFERENCES notebook_notes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $sql_attachments)) {
            throw new Exception("Failed to create notebook_attachments: " . mysqli_error($conn));
        }
        $tables_created[] = 'notebook_attachments';
        
        // Table 3: notebook_shares
        $sql_shares = "CREATE TABLE IF NOT EXISTS notebook_shares (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            shared_with_id INT NULL,
            shared_with_role VARCHAR(100) NULL,
            permission ENUM('View','Edit') DEFAULT 'View',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_note_id (note_id),
            INDEX idx_shared_with_id (shared_with_id),
            FOREIGN KEY (note_id) REFERENCES notebook_notes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $sql_shares)) {
            throw new Exception("Failed to create notebook_shares: " . mysqli_error($conn));
        }
        $tables_created[] = 'notebook_shares';
        
        // Table 4: notebook_versions
        $sql_versions = "CREATE TABLE IF NOT EXISTS notebook_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            version_number INT NOT NULL,
            content_snapshot LONGTEXT,
            updated_by INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_note_id (note_id),
            INDEX idx_version (note_id, version_number),
            FOREIGN KEY (note_id) REFERENCES notebook_notes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $sql_versions)) {
            throw new Exception("Failed to create notebook_versions: " . mysqli_error($conn));
        }
        $tables_created[] = 'notebook_versions';
        
        // Create uploads directory
        $upload_dir = __DIR__ . '/../uploads/notebook';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        
        mysqli_commit($conn);
        
        if ($already_exists) {
            return ['success' => true, 'message' => 'Notebook tables already exist or were verified successfully.'];
        }
        
        return ['success' => true, 'message' => 'Notebook module tables created: ' . implode(', ', $tables_created)];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Only run output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../config/config.php';
    $conn = createConnection(true);
    $result = setup_notebook_module($conn);
    closeConnection($conn);
    
    echo "Notebook Module Setup\n";
    echo ($result['success'] ? "✅ " : "❌ ") . $result['message'] . "\n";
}

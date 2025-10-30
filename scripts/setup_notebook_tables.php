<?php
/**
 * Setup Script: Notebook Module Tables
 * Creates all required tables for the Notebook Module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error() . "\n");
}

echo "🔄 Starting Notebook Module Setup...\n\n";

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Table 1: notebook_notes
    echo "📝 Creating table: notebook_notes...\n";
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
    
    if (mysqli_query($conn, $sql_notes)) {
        echo "  ✅ Table notebook_notes created successfully\n";
    } else {
        throw new Exception("Failed to create notebook_notes: " . mysqli_error($conn));
    }
    
    // Table 2: notebook_attachments
    echo "\n📎 Creating table: notebook_attachments...\n";
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
    
    if (mysqli_query($conn, $sql_attachments)) {
        echo "  ✅ Table notebook_attachments created successfully\n";
    } else {
        throw new Exception("Failed to create notebook_attachments: " . mysqli_error($conn));
    }
    
    // Table 3: notebook_shares
    echo "\n🔗 Creating table: notebook_shares...\n";
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
    
    if (mysqli_query($conn, $sql_shares)) {
        echo "  ✅ Table notebook_shares created successfully\n";
    } else {
        throw new Exception("Failed to create notebook_shares: " . mysqli_error($conn));
    }
    
    // Table 4: notebook_versions
    echo "\n📚 Creating table: notebook_versions...\n";
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
    
    if (mysqli_query($conn, $sql_versions)) {
        echo "  ✅ Table notebook_versions created successfully\n";
    } else {
        throw new Exception("Failed to create notebook_versions: " . mysqli_error($conn));
    }
    
    // Create uploads directory
    echo "\n📁 Creating uploads directory...\n";
    $upload_dir = __DIR__ . '/../uploads/notebook';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
            echo "  ✅ Directory created: /uploads/notebook\n";
        } else {
            echo "  ⚠️  Warning: Could not create uploads directory\n";
        }
    } else {
        echo "  ⏭️  Directory already exists\n";
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "\n✅ Notebook Module setup completed successfully!\n";
    echo "\n📊 Summary:\n";
    echo "  - notebook_notes: Notes with rich content\n";
    echo "  - notebook_attachments: File attachments\n";
    echo "  - notebook_shares: Sharing permissions\n";
    echo "  - notebook_versions: Version history\n";
    echo "  - Upload directory: /uploads/notebook\n";
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo "\n❌ Setup failed: " . $e->getMessage() . "\n";
    echo "Database rolled back to previous state.\n";
}

closeConnection($conn);
echo "\n🏁 Setup script finished.\n";
?>

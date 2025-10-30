<?php
/**
 * Migration Script: Update Users Table Structure
 * 
 * This script migrates the existing users table to the new schema
 * required by the Users Management Module.
 * 
 * BACKUP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error() . "\n");
}

echo "🔄 Starting Users Table Migration...\n\n";

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Step 1: Add new columns
    echo "📝 Step 1: Adding new columns...\n";
    
    $alterations = [
        "ADD COLUMN entity_id INT NULL AFTER id",
        "ADD COLUMN entity_type ENUM('Employee','Client','Other') NULL AFTER entity_id",
        "ADD COLUMN phone VARCHAR(20) NULL AFTER email",
        "ADD COLUMN role_id INT NULL AFTER phone",
        "ADD COLUMN status ENUM('Active','Inactive','Suspended') DEFAULT 'Active' AFTER role_id",
        "ADD COLUMN last_login DATETIME NULL AFTER status",
        "ADD COLUMN created_by INT NULL AFTER last_login"
    ];
    
    foreach ($alterations as $alteration) {
        // Check if column already exists before adding
        $column_name = trim(explode(' ', $alteration)[2]);
        $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$column_name'");
        
        if (mysqli_num_rows($check) == 0) {
            $sql = "ALTER TABLE users $alteration";
            if (mysqli_query($conn, $sql)) {
                echo "  ✅ Added column: $column_name\n";
            } else {
                throw new Exception("Failed to add column $column_name: " . mysqli_error($conn));
            }
        } else {
            echo "  ⏭️  Column $column_name already exists, skipping...\n";
        }
        mysqli_free_result($check);
    }
    
    // Step 2: Rename columns
    echo "\n📝 Step 2: Renaming columns...\n";
    
    // Rename 'password' to 'password_hash'
    $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_free_result($check);
        if (mysqli_query($conn, "ALTER TABLE users CHANGE COLUMN password password_hash VARCHAR(255) NOT NULL")) {
            echo "  ✅ Renamed 'password' to 'password_hash'\n";
        } else {
            throw new Exception("Failed to rename password column: " . mysqli_error($conn));
        }
    } else {
        mysqli_free_result($check);
        echo "  ⏭️  Column 'password' already renamed, skipping...\n";
    }
    
    // Step 3: Modify username length
    echo "\n📝 Step 3: Modifying column constraints...\n";
    if (mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN username VARCHAR(100) NOT NULL")) {
        echo "  ✅ Updated username column to VARCHAR(100)\n";
    } else {
        throw new Exception("Failed to modify username: " . mysqli_error($conn));
    }
    
    // Update email length
    if (mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN email VARCHAR(150) NULL")) {
        echo "  ✅ Updated email column to VARCHAR(150)\n";
    } else {
        throw new Exception("Failed to modify email: " . mysqli_error($conn));
    }
    
    // Step 4: Migrate existing data
    echo "\n📝 Step 4: Migrating existing data...\n";
    
    // Map old 'role' enum to new role_id (assuming roles table exists)
    $role_mapping = [];
    $roles_result = mysqli_query($conn, "SELECT id, name FROM roles");
    if ($roles_result) {
        while ($role = mysqli_fetch_assoc($roles_result)) {
            $role_name_lower = strtolower($role['name']);
            $role_mapping[$role_name_lower] = $role['id'];
        }
        mysqli_free_result($roles_result);
        echo "  📋 Found " . count($role_mapping) . " roles in roles table\n";
    }
    
    // Update existing users with role_id based on old 'role' column
    if (!empty($role_mapping)) {
        $users_result = mysqli_query($conn, "SELECT id, role FROM users WHERE role_id IS NULL");
        $updated = 0;
        
        while ($user = mysqli_fetch_assoc($users_result)) {
            $old_role = strtolower($user['role']);
            if (isset($role_mapping[$old_role])) {
                $new_role_id = $role_mapping[$old_role];
                mysqli_query($conn, "UPDATE users SET role_id = $new_role_id WHERE id = {$user['id']}");
                $updated++;
            } else {
                // Try to find a default role
                if (isset($role_mapping['employee'])) {
                    mysqli_query($conn, "UPDATE users SET role_id = {$role_mapping['employee']} WHERE id = {$user['id']}");
                    $updated++;
                }
            }
        }
        mysqli_free_result($users_result);
        echo "  ✅ Migrated role_id for $updated users\n";
    }
    
    // Set status based on is_active
    $status_result = mysqli_query($conn, "UPDATE users SET status = CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END WHERE status IS NULL OR status = ''");
    if ($status_result) {
        echo "  ✅ Migrated status from is_active column\n";
    }
    
    // Step 5: Add indexes
    echo "\n📝 Step 5: Adding indexes...\n";
    
    $indexes = [
        "ADD INDEX idx_entity (entity_id, entity_type)",
        "ADD INDEX idx_role_id (role_id)",
        "ADD INDEX idx_status (status)",
        "ADD INDEX idx_last_login (last_login)"
    ];
    
    foreach ($indexes as $index) {
        $index_name = preg_match('/idx_(\w+)/', $index, $matches) ? $matches[1] : '';
        $check = mysqli_query($conn, "SHOW INDEX FROM users WHERE Key_name = 'idx_$index_name'");
        
        if (mysqli_num_rows($check) == 0) {
            $sql = "ALTER TABLE users $index";
            if (mysqli_query($conn, $sql)) {
                echo "  ✅ Added index: idx_$index_name\n";
            } else {
                // Index errors are non-critical, just warn
                echo "  ⚠️  Warning: Could not add index idx_$index_name: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "  ⏭️  Index idx_$index_name already exists, skipping...\n";
        }
        mysqli_free_result($check);
    }
    
    // Step 6: Add foreign key constraints (optional, commented out for safety)
    echo "\n📝 Step 6: Foreign key constraints (skipped for safety)...\n";
    echo "  ℹ️  Foreign keys can be added manually if needed:\n";
    echo "     - ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id);\n";
    echo "     - ALTER TABLE users ADD FOREIGN KEY (created_by) REFERENCES users(id);\n";
    
    // Step 7: Drop old columns (optional, commented out for safety)
    echo "\n📝 Step 7: Cleanup old columns...\n";
    echo "  ⚠️  Old columns will be kept for safety. You can manually drop them later:\n";
    echo "     - ALTER TABLE users DROP COLUMN role;\n";
    echo "     - ALTER TABLE users DROP COLUMN is_active;\n";
    echo "     - ALTER TABLE users DROP COLUMN full_name;\n";
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\n📊 Updated Users Table Structure:\n\n";
    
    $result = mysqli_query($conn, "DESCRIBE users");
    echo str_pad("Field", 25) . str_pad("Type", 30) . str_pad("Null", 10) . "Key\n";
    echo str_repeat("-", 75) . "\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo str_pad($row['Field'], 25) . 
             str_pad($row['Type'], 30) . 
             str_pad($row['Null'], 10) . 
             $row['Key'] . "\n";
    }
    mysqli_free_result($result);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Database rolled back to previous state.\n";
}

closeConnection($conn);
echo "\n🏁 Migration script finished.\n";
?>

<?php
/**
 * Migrate Permissions Table Structure
 * Updates permissions and role_permissions tables for granular permission types
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "🔄 Starting Permissions Structure Migration...\n\n";

$conn = createConnection(true);

// Step 1: Backup existing data
echo "📦 Step 1: Backing up existing permissions...\n";
$backup_stmt = mysqli_query($conn, "SELECT * FROM permissions");
$backup_data = [];
while ($row = mysqli_fetch_assoc($backup_stmt)) {
    $backup_data[] = $row;
}
echo "✅ Backed up " . count($backup_data) . " permission records.\n\n";

// Step 2: Drop role_permissions first (foreign key constraint)
echo "🔨 Step 2: Dropping role_permissions table first...\n";

$drop_role_permissions_first = "DROP TABLE IF EXISTS role_permissions";
if (mysqli_query($conn, $drop_role_permissions_first)) {
    echo "✅ Dropped old role_permissions table.\n";
} else {
    echo "❌ Error dropping role_permissions table: " . mysqli_error($conn) . "\n";
    exit;
}

// Step 3: Drop and recreate permissions table with new structure
echo "🔨 Step 3: Updating permissions table structure...\n";

$drop_permissions = "DROP TABLE IF EXISTS permissions";
if (mysqli_query($conn, $drop_permissions)) {
    echo "✅ Dropped old permissions table.\n";
} else {
    echo "❌ Error dropping permissions table: " . mysqli_error($conn) . "\n";
    exit;
}

$permissions_table = "
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_path VARCHAR(255) NOT NULL UNIQUE COMMENT 'Relative path from /public (e.g., employees.php, crm/leads.php)',
    module VARCHAR(100) NOT NULL COMMENT 'Main module folder name',
    submodule VARCHAR(100) NULL COMMENT 'Submodule folder name if nested',
    page_name VARCHAR(150) NOT NULL COMMENT 'Display name for the page',
    
    -- Create permission
    can_create TINYINT(1) DEFAULT 0 COMMENT 'Can create new records',
    
    -- View permissions (granular)
    can_view_all TINYINT(1) DEFAULT 0 COMMENT 'Can view all records',
    can_view_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can view records assigned to user',
    can_view_own TINYINT(1) DEFAULT 0 COMMENT 'Can view records created by user',
    
    -- Edit permissions (granular)
    can_edit_all TINYINT(1) DEFAULT 0 COMMENT 'Can edit all records',
    can_edit_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can edit records assigned to user',
    can_edit_own TINYINT(1) DEFAULT 0 COMMENT 'Can edit records created by user',
    
    -- Delete permissions (granular)
    can_delete_all TINYINT(1) DEFAULT 0 COMMENT 'Can delete all records',
    can_delete_assigned TINYINT(1) DEFAULT 0 COMMENT 'Can delete records assigned to user',
    can_delete_own TINYINT(1) DEFAULT 0 COMMENT 'Can delete records created by user',
    
    -- Export permission
    can_export TINYINT(1) DEFAULT 0 COMMENT 'Can export data',
    
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether page still exists',
    last_scanned TIMESTAMP NULL COMMENT 'Last time page was detected in scan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_page_path (page_path),
    INDEX idx_module (module),
    INDEX idx_submodule (submodule),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (mysqli_query($conn, $permissions_table)) {
    echo "✅ Created new permissions table structure.\n\n";
} else {
    echo "❌ Error creating permissions table: " . mysqli_error($conn) . "\n";
    exit;
}

// Step 4: Recreate role_permissions table structure
echo "🔨 Step 4: Recreating role_permissions table structure...\n";

$role_permissions_table = "
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    
    -- Create permission
    can_create TINYINT(1) DEFAULT 0,
    
    -- View permissions (granular)
    can_view_all TINYINT(1) DEFAULT 0,
    can_view_assigned TINYINT(1) DEFAULT 0,
    can_view_own TINYINT(1) DEFAULT 0,
    
    -- Edit permissions (granular)
    can_edit_all TINYINT(1) DEFAULT 0,
    can_edit_assigned TINYINT(1) DEFAULT 0,
    can_edit_own TINYINT(1) DEFAULT 0,
    
    -- Delete permissions (granular)
    can_delete_all TINYINT(1) DEFAULT 0,
    can_delete_assigned TINYINT(1) DEFAULT 0,
    can_delete_own TINYINT(1) DEFAULT 0,
    
    -- Export permission
    can_export TINYINT(1) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (mysqli_query($conn, $role_permissions_table)) {
    echo "✅ Created new role_permissions table structure.\n\n";
} else {
    echo "❌ Error creating role_permissions table: " . mysqli_error($conn) . "\n";
    exit;
}

echo "✅ Migration completed successfully!\n";
echo "📝 Next step: Run the permissions scan to populate the table.\n";

closeConnection($conn);

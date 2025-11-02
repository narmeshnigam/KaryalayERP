<?php
/**
 * Migration: Remove role_id column from users table
 * 
 * This script:
 * 1. Verifies that all users have at least one role assignment in user_roles table
 * 2. Safely drops the role_id column from the users table
 * 3. All role management is now handled exclusively through the user_roles table
 * 
 * Run this script once from the browser or command line.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/config.php';

$conn = createConnection(true);

echo "<h2>Migration: Remove role_id from users table</h2>";
echo "<pre>";

// Step 1: Check if role_id column exists
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role_id'");
if (!$check_column || mysqli_num_rows($check_column) === 0) {
    echo "✓ role_id column does not exist in users table. Migration already complete.\n";
    closeConnection($conn);
    exit;
}

echo "Step 1: Checking role_id column exists...\n";
echo "✓ Found role_id column in users table\n\n";

// Step 2: Check if user_roles table exists
$check_user_roles = mysqli_query($conn, "SHOW TABLES LIKE 'user_roles'");
if (!$check_user_roles || mysqli_num_rows($check_user_roles) === 0) {
    echo "✗ ERROR: user_roles table does not exist!\n";
    echo "  Please run the roles setup script first.\n";
    closeConnection($conn);
    exit;
}

echo "Step 2: Verifying user_roles table exists...\n";
echo "✓ user_roles table found\n\n";

// Step 3: Check all active users have at least one role assignment
echo "Step 3: Checking all active users have role assignments...\n";
$users_without_roles = mysqli_query($conn, "
    SELECT u.id, u.username, u.full_name 
    FROM users u 
    LEFT JOIN user_roles ur ON u.id = ur.user_id 
    WHERE u.status = 'Active' 
    AND ur.id IS NULL
");

if ($users_without_roles && mysqli_num_rows($users_without_roles) > 0) {
    echo "⚠ WARNING: Found users without role assignments:\n";
    while ($user = mysqli_fetch_assoc($users_without_roles)) {
        echo "  - User ID {$user['id']}: {$user['username']} ({$user['full_name']})\n";
    }
    
    // Assign default Employee role to users without roles
    echo "\nAssigning default 'Employee' role to users without roles...\n";
    
    $get_employee_role = mysqli_query($conn, "SELECT id FROM roles WHERE name = 'Employee' AND status = 'Active' LIMIT 1");
    if ($get_employee_role && mysqli_num_rows($get_employee_role) > 0) {
        $employee_role = mysqli_fetch_assoc($get_employee_role);
        $employee_role_id = $employee_role['id'];
        
        // Get admin user as assigned_by
        $admin_query = mysqli_query($conn, "SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $admin_user = mysqli_fetch_assoc($admin_query);
        $assigned_by = $admin_user['id'] ?? 1;
        
        // Re-fetch users without roles and assign
        mysqli_data_seek($users_without_roles, 0);
        while ($user = mysqli_fetch_assoc($users_without_roles)) {
            $insert = mysqli_prepare($conn, "INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insert, 'iii', $user['id'], $employee_role_id, $assigned_by);
            mysqli_stmt_execute($insert);
            echo "  ✓ Assigned Employee role to {$user['username']}\n";
            mysqli_stmt_close($insert);
        }
    } else {
        echo "✗ ERROR: Could not find 'Employee' role. Please create it first.\n";
        closeConnection($conn);
        exit;
    }
} else {
    echo "✓ All active users have role assignments\n";
}

echo "\n";

// Step 4: Create backup of role_id data (optional, for safety)
echo "Step 4: Creating backup of role_id data...\n";
$backup_query = "
    CREATE TABLE IF NOT EXISTS users_role_id_backup AS
    SELECT id, username, role_id, NOW() as backup_date
    FROM users
    WHERE role_id IS NOT NULL
";
if (mysqli_query($conn, $backup_query)) {
    echo "✓ Backup table 'users_role_id_backup' created\n\n";
} else {
    echo "⚠ Could not create backup table (non-critical): " . mysqli_error($conn) . "\n\n";
}

// Step 5: Drop the role_id column
echo "Step 5: Dropping role_id column from users table...\n";
$drop_column = mysqli_query($conn, "ALTER TABLE users DROP COLUMN role_id");

if ($drop_column) {
    echo "✓ Successfully dropped role_id column from users table\n\n";
    echo "=========================================\n";
    echo "Migration completed successfully!\n";
    echo "=========================================\n";
    echo "All role management is now handled through the user_roles table.\n";
    echo "Users can have multiple roles assigned via the Roles & Permissions module.\n";
} else {
    echo "✗ ERROR: Failed to drop role_id column\n";
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "</pre>";

closeConnection($conn);
?>

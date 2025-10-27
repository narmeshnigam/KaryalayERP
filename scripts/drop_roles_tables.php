<?php
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);

echo "Dropping roles & permissions tables...\n\n";

$tables = ['user_roles', 'role_permissions', 'permission_audit_log', 'permissions', 'roles'];

foreach ($tables as $table) {
    $result = @mysqli_query($conn, "DROP TABLE IF EXISTS $table");
    if ($result) {
        echo "✓ Dropped table: $table\n";
    } else {
        echo "✗ Failed to drop table: $table - " . mysqli_error($conn) . "\n";
    }
}

echo "\n✅ All tables dropped. Ready for onboarding test.\n";

mysqli_close($conn);

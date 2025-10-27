<?php
/**
 * Test the roles module flow
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../public/settings/roles/helpers.php';

$conn = createConnection(true);

echo "=== Testing Roles & Permissions Module ===\n\n";

// Test 1: Check if tables exist
echo "Test 1: Table existence check\n";
$tables_exist = roles_tables_exist($conn);
echo "  Tables exist: " . ($tables_exist ? "YES" : "NO") . "\n";
echo "  Expected: NO (before setup)\n";
echo "  Status: " . (!$tables_exist ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 2: Check permission function with missing tables
echo "Test 2: Permission check with missing tables\n";
$has_perm = has_permission($conn, 1, 'test/page', 'view');
echo "  Has permission: " . ($has_perm ? "YES" : "NO") . "\n";
echo "  Expected: YES (allows access when tables missing)\n";
echo "  Status: " . ($has_perm ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 3: Get user roles with missing tables
echo "Test 3: Get user roles with missing tables\n";
$roles = get_user_roles($conn, 1);
echo "  Roles count: " . count($roles) . "\n";
echo "  Expected: 0 (empty array)\n";
echo "  Status: " . (count($roles) === 0 ? "✓ PASS" : "✗ FAIL") . "\n\n";

mysqli_close($conn);

echo "=== Test Complete ===\n";
echo "\nNext Steps:\n";
echo "1. Access: http://localhost/KaryalayERP/public/settings/roles/index.php\n";
echo "2. Should redirect to onboarding.php\n";
echo "3. Click 'Run Setup Script'\n";
echo "4. Should create all tables and redirect to index.php\n";

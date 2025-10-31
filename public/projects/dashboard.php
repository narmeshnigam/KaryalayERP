<?php
/**
 * Projects Dashboard - Comprehensive Project Management & Analytics
 * Combines project listing with advanced analytics (admin/manager only)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'projects', 'view');

// Check if tables exist
if (!projects_tables_exist($conn)) {
    header('Location: /KaryalayERP/scripts/setup_projects_tables.php');
    exit;
}

// Get filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
<?php
// Legacy route – redirect to the new Projects index
header('Location: /KaryalayERP/public/projects/index.php', true, 302);
exit;
if (!empty($_GET['priority'])) {

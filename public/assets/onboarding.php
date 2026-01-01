<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

$page_title = 'Asset Management Setup - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
closeConnection($conn);

echo '<div class="main-wrapper"><div class="main-content">';
echo '<div class="card" style="max-width:800px;margin:0 auto;">';
echo '<h2 style="margin-top:0;color:#003581;">🧰 Asset & Resource Management Module Not Set Up</h2>'; 
echo '<p style="font-size:15px;line-height:1.6;">The Asset Management Module helps you track and manage all organizational assets including IT devices, vehicles, tools, machinery, furniture, and shared spaces.</p>';
echo '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0;">';
echo '<h4 style="margin-top:0;color:#003581;">Module Features:</h4>';
echo '<ul style="line-height:1.8;">';
echo '<li>✅ <strong>Asset Registry</strong> - Centralized database of all organizational assets</li>';
echo '<li>✅ <strong>Context-Based Allocation</strong> - Assign assets to Employees, Projects, Clients, or Leads</li>';
echo '<li>✅ <strong>Status Tracking</strong> - Monitor availability, usage, maintenance, and condition</li>';
echo '<li>✅ <strong>Maintenance Management</strong> - Track repairs, service history, and schedules</li>';
echo '<li>✅ <strong>File Attachments</strong> - Store bills, warranties, manuals, and service records</li>';
echo '<li>✅ <strong>Complete Audit Trail</strong> - Full activity logging for compliance</li>';
echo '<li>✅ <strong>Alerts & Dashboards</strong> - Monitor expiring warranties, overdue returns, and utilization</li>';
echo '</ul>';
echo '</div>';
echo '<p style="color:#666;"><strong>Note:</strong> Running the setup will create 6 database tables: assets_master, asset_allocation_log, asset_status_log, asset_maintenance_log, asset_files, and asset_activity_log.</p>';
echo '<a href="' . APP_URL . '/scripts/setup_assets_tables.php" class="btn" style="margin-top:20px;">🚀 Setup Asset Management Module</a>';
echo '<a href="' . APP_URL . '/public/index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">← Back to Dashboard</a>';
echo '<div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">';
echo '<p style="margin:0;"><strong>Tip:</strong> You can also install multiple modules at once using the <a href="' . APP_URL . '/setup/module_installer.php?from=settings" style="color:#003581;text-decoration:underline;">Unified Module Installer</a></p>';
echo '</div>';
echo '</div></div>';

require_once __DIR__ . '/../../includes/footer_sidebar.php';
exit;
?>

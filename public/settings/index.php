<?php
/**
 * Settings Index Page
 * Central hub for system settings and administration
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/setup_helper.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/../../includes/module_discovery.php';

// Create database connection
$conn = createConnection(true);

// Refresh authorization context to populate session
authz_refresh_context($conn);

// Check if user has admin access
$has_admin_access = authz_has_role('Super Admin') || authz_has_role('Admin');

if (!$has_admin_access) {
    closeConnection($conn);
    header('Location: ' . APP_URL . '/public/unauthorized.php');
    exit;
}

// Check for uninstalled modules
$all_modules = discover_modules($conn);
$uninstalled_count = 0;
foreach ($all_modules as $module) {
    if (!$module['installed']) {
        $uninstalled_count++;
    }
}

$page_title = 'Settings - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <h1>⚙️ Settings</h1>
                    <p class="muted">System configuration and administration</p>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 1200px; margin: 20px auto;">
            <div class="card-header">
                <h2 style="margin: 0; color: #003581;">System Administration</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    
                    <!-- Module Installer -->
                    <a href="<?php echo APP_URL; ?>/setup/module_installer.php?from=settings" class="settings-card" style="text-decoration: none; color: inherit;">
                        <div style="background: #f8f9fa; border: 2px solid #e1e8ed; border-radius: 12px; padding: 24px; transition: all 0.3s; position: relative;">
                            <div style="font-size: 48px; margin-bottom: 12px;">📦</div>
                            <h3 style="margin: 0 0 8px 0; color: #003581; font-size: 18px;">Install Modules</h3>
                            <p style="margin: 0; color: #6c757d; font-size: 14px; line-height: 1.5;">
                                Install additional modules to extend system functionality
                            </p>
                            <?php if ($uninstalled_count > 0): ?>
                                <div style="position: absolute; top: 20px; right: 20px; background: #faa718; color: white; border-radius: 12px; padding: 4px 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo $uninstalled_count; ?> available
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>

                    <!-- Roles & Permissions -->
                    <?php if (@authz_user_can($conn, 'roles', 'view_all') || @authz_user_can($conn, 'permissions', 'view_all')): ?>
                    <a href="<?php echo APP_URL; ?>/public/settings/roles/index.php" class="settings-card" style="text-decoration: none; color: inherit;">
                        <div style="background: #f8f9fa; border: 2px solid #e1e8ed; border-radius: 12px; padding: 24px; transition: all 0.3s;">
                            <div style="font-size: 48px; margin-bottom: 12px;">🔐</div>
                            <h3 style="margin: 0 0 8px 0; color: #003581; font-size: 18px;">Roles & Permissions</h3>
                            <p style="margin: 0; color: #6c757d; font-size: 14px; line-height: 1.5;">
                                Manage user roles and access permissions
                            </p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <!-- User Management -->
                    <?php if (@authz_user_can($conn, 'users', 'view_all')): ?>
                    <a href="<?php echo APP_URL; ?>/public/users/index.php" class="settings-card" style="text-decoration: none; color: inherit;">
                        <div style="background: #f8f9fa; border: 2px solid #e1e8ed; border-radius: 12px; padding: 24px; transition: all 0.3s;">
                            <div style="font-size: 48px; margin-bottom: 12px;">👥</div>
                            <h3 style="margin: 0 0 8px 0; color: #003581; font-size: 18px;">User Management</h3>
                            <p style="margin: 0; color: #6c757d; font-size: 14px; line-height: 1.5;">
                                Manage system users and accounts
                            </p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <!-- Branding -->
                    <?php if (@authz_user_can($conn, 'branding_settings', 'view_all')): ?>
                    <a href="<?php echo APP_URL; ?>/public/branding/view.php" class="settings-card" style="text-decoration: none; color: inherit;">
                        <div style="background: #f8f9fa; border: 2px solid #e1e8ed; border-radius: 12px; padding: 24px; transition: all 0.3s;">
                            <div style="font-size: 48px; margin-bottom: 12px;">🎨</div>
                            <h3 style="margin: 0 0 8px 0; color: #003581; font-size: 18px;">Branding</h3>
                            <p style="margin: 0; color: #6c757d; font-size: 14px; line-height: 1.5;">
                                Configure organization branding and logos
                            </p>
                        </div>
                    </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-card:hover > div {
    border-color: #003581;
    box-shadow: 0 4px 12px rgba(0, 53, 129, 0.1);
    transform: translateY(-2px);
}
</style>

<?php 
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

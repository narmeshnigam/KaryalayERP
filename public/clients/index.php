<?php
/**
 * Clients Module - Main Listing Page
 * View all clients with filters, search, and statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!authz_user_can_any($conn, [
    ['table' => 'clients', 'permission' => 'view_all'],
    ['table' => 'clients', 'permission' => 'view_assigned'],
    ['table' => 'clients', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'clients', 'view_all');
}

// Check if tables exist

if (!clients_tables_exist($conn)) {
    $page_title = 'Clients Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-warning" style="margin:40px auto;max-width:600px;padding:32px 28px;font-size:1.1em;">';
    echo '<h2 style="margin-bottom:16px;color:#b85c00;">Clients Module Not Set Up</h2>';
    echo '<p>The clients module database tables have not been created yet. To use this module, please run the setup for clients.</p>';
    echo '<a href="/KaryalayERP/scripts/setup_clients_tables.php" class="btn btn-primary" style="margin-top:18px;">Set Up Clients Module</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Get filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['owner_id'])) {
    $filters['owner_id'] = (int)$_GET['owner_id'];
}
if (!empty($_GET['industry'])) {
    $filters['industry'] = $_GET['industry'];
}
if (!empty($_GET['tag'])) {
    $filters['tag'] = trim($_GET['tag']);
}

// Get all clients with filters
$clients = get_all_clients($conn, $_SESSION['user_id'], $filters);

// Get statistics
$stats = get_clients_statistics($conn, $_SESSION['user_id']);

// Get all owners for filter
$owners = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Get all industries for filter
$industries = get_all_industries($conn);

// Get all tags for filter
$tags = get_all_client_tags($conn);

// Check permissions
$can_create = authz_user_can($conn, 'clients', 'create');
$can_export = authz_user_can($conn, 'clients', 'update'); // Can export if can update

$page_title = 'All Clients - Clients - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">🏢 Clients Management</h1>
                    <p style="color: #6c757d; margin: 0;">Manage your client relationships and information</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="my.php" class="btn btn-secondary">👤 My Clients</a>
                    <a href="import_export.php" class="btn btn-secondary">📤 Import/Export</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Add Client</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>


        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e3f2fd;">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Clients</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9;">✅</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #28a745;"><?= $stats['active'] ?></div>
                    <div class="stat-label">Active Clients</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e5f5;">👤</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #007bff;"><?= $stats['my_clients'] ?></div>
                    <div class="stat-label">My Clients</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0;">🚀</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #6f42c1;"><?= $stats['with_projects'] ?></div>
                    <div class="stat-label">With Projects</div>
                </div>
            </div>
        </div>


        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🔍 Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, phone, code..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">📊 Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?= ($_GET['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">👤 Owner</label>
                    <select name="owner_id" class="form-control">
                        <option value="">All Owners</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['id'] ?>" 
                                <?= ($_GET['owner_id'] ?? '') == $owner['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($owner['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🏭 Industry</label>
                    <select name="industry" class="form-control">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?= htmlspecialchars($industry) ?>" 
                                <?= ($_GET['industry'] ?? '') === $industry ? 'selected' : '' ?>>
                                <?= htmlspecialchars($industry) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary" style="flex: 1;">Clear</a>
                </div>
            </form>
        </div>


        <!-- Clients Table -->
        <?php if (count($clients) > 0): ?>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact Details</th>
                            <th>Industry</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Stats</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0059b3 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; flex-shrink: 0;">
                                            <?= get_client_initials($client['name']) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1b2a57; margin-bottom: 2px;">
                                                <a href="view.php?id=<?= $client['id'] ?>" style="color: #003581; text-decoration: none;">
                                                    <?= htmlspecialchars($client['name']) ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 11px; color: #6c757d; font-family: monospace;">
                                                <?= htmlspecialchars($client['code']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($client['email']): ?>
                                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 13px;">
                                            <span>📧</span>
                                            <a href="mailto:<?= htmlspecialchars($client['email']) ?>" style="color: #003581; text-decoration: none;">
                                                <?= htmlspecialchars($client['email']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($client['phone']): ?>
                                        <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                            <span>📞</span>
                                            <a href="tel:<?= htmlspecialchars($client['phone']) ?>" style="color: #003581; text-decoration: none;">
                                                <?= htmlspecialchars($client['phone']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($client['industry'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($client['owner_username'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($client['status'] === 'Active'): ?>
                                        <span class="badge badge-success"><?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 12px; font-size: 12px; color: #6c757d;">
                                        <div title="Contacts">👥 <?= $client['contact_count'] ?></div>
                                        <div title="Addresses">📍 <?= $client['address_count'] ?></div>
                                        <div title="Documents">📄 <?= $client['document_count'] ?></div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="view.php?id=<?= $client['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" title="View">
                                            👁️ View
                                        </a>
                                        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" title="Edit">
                                            ✏️ Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 48px; margin-bottom: 16px;">🏢</div>
                <h3 style="color: #1b2a57; margin-bottom: 8px;">No clients found</h3>
                <p style="color: #6c757d; margin-bottom: 24px;">Start by adding your first client or adjust your filters.</p>
                <?php if ($can_create): ?>
                    <a href="add.php" class="btn btn-primary">➕ Add First Client</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
/* Stat Card Styles */
.stat-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1b2a57;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
    font-weight: 500;
}
</style>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>

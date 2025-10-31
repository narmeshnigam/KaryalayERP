<?php
/**
 * Clients Module - My Clients Page
 * View only clients owned by the current user
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
    header('Location: /KaryalayERP/setup/index.php?module=clients');
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
if (!empty($_GET['industry'])) {
    $filters['industry'] = $_GET['industry'];
}

// Add owner filter for current user
$filters['owner_id'] = $_SESSION['user_id'];

// Get my clients
$clients = get_all_clients($conn, $_SESSION['user_id'], $filters);

// Get statistics for my clients only
$stats = get_clients_statistics($conn, $_SESSION['user_id']);

// Get all industries for filter
$industries = get_all_industries($conn);

// Check permissions
$can_create = authz_user_can($conn, 'clients', 'create');
$can_update = authz_user_can($conn, 'clients', 'update');

$page_title = 'My Clients - Clients - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">👤 My Clients</h1>
                    <p style="color: #6c757d; margin: 0;">Clients you own and manage</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-secondary">🏢 All Clients</a>
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
                    <div class="stat-value"><?= count($clients) ?></div>
                    <div class="stat-label">My Total Clients</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9;">✅</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #28a745;">
                        <?= count(array_filter($clients, fn($c) => $c['status'] === 'Active')) ?>
                    </div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e5f5;">👥</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #6f42c1;">
                        <?= count(array_filter($clients, fn($c) => $c['contact_count'] > 0)) ?>
                    </div>
                    <div class="stat-label">With Contacts</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0;">📄</div>
                <div class="stat-content">
                    <div class="stat-value" style="color: #ff9800;">
                        <?= count(array_filter($clients, fn($c) => $c['document_count'] > 0)) ?>
                    </div>
                    <div class="stat-label">With Documents</div>
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
                    <a href="my.php" class="btn btn-secondary" style="flex: 1;">Clear</a>
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
                <div style="font-size: 48px; margin-bottom: 16px;">👤</div>
                <h3 style="color: #1b2a57; margin-bottom: 8px;">No clients assigned to you yet</h3>
                <p style="color: #6c757d; margin-bottom: 24px;">You don't own any clients at the moment.</p>
                <?php if ($can_create): ?>
                    <a href="add.php" class="btn btn-primary">➕ Add Your First Client</a>
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

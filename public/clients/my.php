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
<style>
.clients-my-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.clients-my-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:768px){
.clients-my-header-flex{flex-direction:column;align-items:stretch;}
.clients-my-header-buttons{width:100%;flex-direction:column;gap:10px;}
.clients-my-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.clients-my-header-flex h1{font-size:1.5rem;}
}

/* Statistics Grid Responsive */
.clients-my-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}

@media (max-width:768px){
.clients-my-stats-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
}

@media (max-width:480px){
.clients-my-stats-grid{grid-template-columns:1fr;gap:12px;}
.stat-card{padding:16px;gap:12px;}
.stat-value{font-size:24px;}
.stat-label{font-size:12px;}
}

/* Filter Form Responsive */
.clients-my-filter-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}

@media (max-width:768px){
.clients-my-filter-form{grid-template-columns:repeat(2,1fr);gap:12px;}
}

@media (max-width:480px){
.clients-my-filter-form{grid-template-columns:1fr;gap:12px;}
.clients-my-filter-form .form-group{margin-bottom:0;}
.form-control{font-size:16px;}
}

/* Filter Buttons */
.clients-my-filter-buttons{display:flex;gap:8px;align-items:flex-end;}

@media (max-width:768px){
.clients-my-filter-buttons{grid-column:1/-1;flex-direction:column;}
.clients-my-filter-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.clients-my-filter-buttons{gap:8px;}
.clients-my-filter-buttons .btn{font-size:13px;padding:10px 16px;}
}

/* Table Responsive - Card Style on Mobile */
.clients-my-table-wrapper{overflow-x:auto;}

@media (max-width:600px){
.clients-my-table-wrapper .table{display:block;}
.clients-my-table-wrapper .table thead{display:none;}
.clients-my-table-wrapper .table tbody{display:block;}
.clients-my-table-wrapper .table tr{display:block;margin-bottom:16px;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;}
.clients-my-table-wrapper .table td{display:block;padding:12px;border:none;border-bottom:1px solid #e9ecef;text-align:left;}
.clients-my-table-wrapper .table td:last-child{border-bottom:none;}
.clients-my-table-wrapper .table td::before{content:attr(data-label);font-weight:600;color:#003581;display:block;font-size:12px;margin-bottom:4px;}
.clients-my-table-wrapper .table td:nth-child(1)::before{content:'Client';}
.clients-my-table-wrapper .table td:nth-child(2)::before{content:'Contact Details';}
.clients-my-table-wrapper .table td:nth-child(3)::before{content:'Industry';}
.clients-my-table-wrapper .table td:nth-child(4)::before{content:'Status';}
.clients-my-table-wrapper .table td:nth-child(5)::before{content:'Stats';}
.clients-my-table-wrapper .table td:nth-child(6)::before{content:'Actions';}
.clients-my-table-wrapper .table td[style*="text-align: center"]{text-align:left;}
}

@media (max-width:480px){
.clients-my-table-wrapper .table tr{margin-bottom:12px;}
.clients-my-table-wrapper .table td{padding:10px;font-size:13px;}
.clients-my-table-wrapper .table td::before{font-size:11px;margin-bottom:2px;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="clients-my-header-flex">
                <div style="flex: 1;">
                    <h1>👤 My Clients</h1>
                    <p>Clients you own and manage</p>
                </div>
                <div class="clients-my-header-buttons">
                    <a href="index.php" class="btn btn-accent">🏢 All Clients</a>
                    <a href="import_export.php" class="btn btn-accent">📤 Import/Export</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn">➕ Add Client</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>


        <!-- Statistics Cards -->
        <div class="clients-my-stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div class="stat-content">
                    <div class="stat-value"><?= count($clients) ?></div>
                    <div class="stat-label">My Total Clients</div>
                </div>
                <div class="stat-icon">📊</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
                <div class="stat-content">
                    <div class="stat-value">
                        <?= count(array_filter($clients, fn($c) => $c['status'] === 'Active')) ?>
                    </div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
                <div class="stat-content">
                    <div class="stat-value">
                        <?= count(array_filter($clients, fn($c) => $c['contact_count'] > 0)) ?>
                    </div>
                    <div class="stat-label">With Contacts</div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
                <div class="stat-content">
                    <div class="stat-value">
                        <?= count(array_filter($clients, fn($c) => $c['document_count'] > 0)) ?>
                    </div>
                    <div class="stat-label">With Documents</div>
                </div>
                <div class="stat-icon">📄</div>
            </div>
        </div>


        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" class="clients-my-filter-form">
                <div class="form-group">
                    <label>🔍 Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, phone, code..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>📊 Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?= ($_GET['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>🏭 Industry</label>
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
                
                <div class="clients-my-filter-buttons">
                    <button type="submit" class="btn" style="flex: 1;">Apply Filters</button>
                    <a href="my.php" class="btn btn-accent" style="flex: 1;">Clear</a>
                </div>
            </form>
        </div>


        <!-- Clients Table -->
        <?php if (count($clients) > 0): ?>
            <div class="card">
                <div class="clients-my-table-wrapper">
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
                                <td data-label="Client">
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
                                <td data-label="Contact Details">
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
                                <td data-label="Industry"><?= htmlspecialchars($client['industry'] ?? '-') ?></td>
                                <td data-label="Status">
                                    <?php if ($client['status'] === 'Active'): ?>
                                        <span class="badge badge-success"><?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Stats">
                                    <div style="display: flex; gap: 12px; font-size: 12px; color: #6c757d;">
                                        <div title="Contacts">👥 <?= $client['contact_count'] ?></div>
                                        <div title="Addresses">📍 <?= $client['address_count'] ?></div>
                                        <div title="Documents">📄 <?= $client['document_count'] ?></div>
                                    </div>
                                </td>
                                <td data-label="Actions" style="text-align: left;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="view.php?id=<?= $client['id'] ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px;" title="View">
                                            👁️ View
                                        </a>
                                        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px;" title="Edit">
                                            ✏️ Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 48px; margin-bottom: 16px;">👤</div>
                <h3 style="color: #1b2a57; margin-bottom: 8px;">No clients assigned to you yet</h3>
                <p style="color: #6c757d; margin-bottom: 24px;">You don't own any clients at the moment.</p>
                <?php if ($can_create): ?>
                    <a href="add.php" class="btn">➕ Add Your First Client</a>
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
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 13px;
    font-weight: 500;
    opacity: 0.95;
}
</style>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
